<?php

declare(strict_types=1);

if (! extension_loaded('ftp')) {
    fwrite(STDERR, "The PHP FTP extension is required.\n");
    exit(1);
}

$server = getenv('FTP_SERVER') ?: '';
$username = getenv('FTP_USERNAME') ?: '';
$password = getenv('FTP_PASSWORD') ?: '';
$localDir = getenv('FTP_LOCAL_DIR') ?: 'dist/qs-core';
$remoteDir = getenv('FTP_REMOTE_DIR') ?: '/';
$stateName = getenv('FTP_STATE_NAME') ?: '.ftp-deploy-sync-state.json';
$port = (int) (getenv('FTP_PORT') ?: '21');
$timeout = (int) (getenv('FTP_TIMEOUT') ?: '30');
$useSsl = filter_var(getenv('FTP_USE_SSL') ?: 'false', FILTER_VALIDATE_BOOL);

if ($server === '' || $username === '' || $password === '') {
    fwrite(STDERR, "FTP_SERVER, FTP_USERNAME, and FTP_PASSWORD are required.\n");
    exit(1);
}

$localRoot = realpath($localDir);
if ($localRoot === false || ! is_dir($localRoot)) {
    fwrite(STDERR, "Local directory not found: {$localDir}\n");
    exit(1);
}

$remoteRoot = normalize_remote_dir($remoteDir);
$remoteStatePath = join_remote_path($remoteRoot, $stateName);

$connection = $useSsl ? @ftp_ssl_connect($server, $port, $timeout) : @ftp_connect($server, $port, $timeout);
if ($connection === false) {
    fwrite(STDERR, "Unable to connect to FTP server {$server}:{$port}.\n");
    exit(1);
}

if (! @ftp_login($connection, $username, $password)) {
    fwrite(STDERR, "FTP login failed.\n");
    ftp_close($connection);
    exit(1);
}

ftp_pasv($connection, true);
ftp_set_option($connection, FTP_TIMEOUT_SEC, $timeout);

echo "Building local deployment manifest...\n";
$localState = build_local_state($localRoot);
$remoteState = load_remote_state($connection, $remoteStatePath);

$uploads = [];
foreach ($localState['files'] as $path => $metadata) {
    if (! isset($remoteState['files'][$path]) || $remoteState['files'][$path]['sha1'] !== $metadata['sha1']) {
        $uploads[$path] = $metadata;
    }
}

$deletes = [];
foreach ($remoteState['files'] as $path => $_metadata) {
    if (! isset($localState['files'][$path])) {
        $deletes[] = $path;
    }
}

echo sprintf("Files to upload: %d\n", count($uploads));
echo sprintf("Files to delete: %d\n", count($deletes));

$ensuredDirectories = [];
foreach ($uploads as $path => $metadata) {
    $remotePath = join_remote_path($remoteRoot, $path);
    ensure_remote_directory($connection, dirname($remotePath), $ensuredDirectories);

    $localPath = $metadata['local_path'];
    if (! @ftp_put($connection, $remotePath, $localPath, FTP_BINARY)) {
        fwrite(STDERR, "Failed to upload {$path}.\n");
        ftp_close($connection);
        exit(1);
    }
}

foreach ($deletes as $path) {
    $remotePath = join_remote_path($remoteRoot, $path);
    if (! @ftp_delete($connection, $remotePath)) {
        fwrite(STDERR, "Failed to delete {$path}.\n");
        ftp_close($connection);
        exit(1);
    }
}

cleanup_remote_directories($connection, $remoteRoot, $localState, $remoteState);

$statePayload = [
    'version' => 1,
    'generated_at' => gmdate(DATE_ATOM),
    'files' => array_map(
        static fn (array $metadata): array => [
            'sha1' => $metadata['sha1'],
            'size' => $metadata['size'],
        ],
        $localState['files']
    ),
];

$tempStateFile = tempnam(sys_get_temp_dir(), 'ftp-state-');
if ($tempStateFile === false) {
    fwrite(STDERR, "Unable to allocate a temporary state file.\n");
    ftp_close($connection);
    exit(1);
}

file_put_contents($tempStateFile, json_encode($statePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
if (! @ftp_put($connection, $remoteStatePath, $tempStateFile, FTP_BINARY)) {
    @unlink($tempStateFile);
    fwrite(STDERR, "Failed to upload deployment state file.\n");
    ftp_close($connection);
    exit(1);
}

@unlink($tempStateFile);
ftp_close($connection);

echo "FTP deployment completed successfully.\n";

/**
 * @return array{files: array<string, array{sha1: string, size: int, local_path: string}>}
 */
function build_local_state(string $localRoot): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($localRoot, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $localPath = $file->getPathname();
        $relativePath = normalize_relative_path(substr($localPath, strlen($localRoot) + 1));
        $files[$relativePath] = [
            'sha1' => sha1_file($localPath),
            'size' => (int) $file->getSize(),
            'local_path' => $localPath,
        ];
    }

    ksort($files);

    return ['files' => $files];
}

/**
 * @return array{files: array<string, array{sha1: string, size: int}>}
 */
function load_remote_state(FTP\Connection $connection, string $remoteStatePath): array
{
    $tempFile = tempnam(sys_get_temp_dir(), 'ftp-state-');
    if ($tempFile === false) {
        return ['files' => []];
    }

    $downloaded = @ftp_get($connection, $tempFile, $remoteStatePath, FTP_BINARY);
    if (! $downloaded) {
        @unlink($tempFile);
        return ['files' => []];
    }

    $rawState = file_get_contents($tempFile);
    @unlink($tempFile);

    if (! is_string($rawState) || $rawState === '') {
        return ['files' => []];
    }

    $decoded = json_decode($rawState, true);
    if (! is_array($decoded) || ! isset($decoded['files']) || ! is_array($decoded['files'])) {
        return ['files' => []];
    }

    $files = [];
    foreach ($decoded['files'] as $path => $metadata) {
        if (
            ! is_string($path)
            || ! is_array($metadata)
            || ! isset($metadata['sha1'], $metadata['size'])
            || ! is_string($metadata['sha1'])
            || ! is_numeric($metadata['size'])
        ) {
            continue;
        }

        $files[normalize_relative_path($path)] = [
            'sha1' => $metadata['sha1'],
            'size' => (int) $metadata['size'],
        ];
    }

    return ['files' => $files];
}

/**
 * @param array<string, true> $ensuredDirectories
 */
function ensure_remote_directory(FTP\Connection $connection, string $directory, array &$ensuredDirectories): void
{
    $directory = rtrim(str_replace('\\', '/', $directory), '/');
    if ($directory === '') {
        return;
    }

    $workingDirectory = @ftp_pwd($connection);
    if (! is_string($workingDirectory) || $workingDirectory === '') {
        $workingDirectory = '/';
    }

    $parts = array_values(array_filter(explode('/', ltrim($directory, '/')), static fn (string $part): bool => $part !== ''));
    $current = '';

    foreach ($parts as $part) {
        $current .= '/' . $part;
        if (isset($ensuredDirectories[$current])) {
            continue;
        }

        if (@ftp_chdir($connection, $current)) {
            @ftp_chdir($connection, $workingDirectory);
            $ensuredDirectories[$current] = true;
            continue;
        }

        if (! @ftp_mkdir($connection, $current) && ! @ftp_chdir($connection, $current)) {
            throw new RuntimeException("Unable to create remote directory {$current}.");
        }

        @ftp_chdir($connection, $workingDirectory);
        $ensuredDirectories[$current] = true;
    }
}

/**
 * @param array{files: array<string, array{sha1: string, size: int, local_path?: string}>} $localState
 * @param array{files: array<string, array{sha1: string, size: int}>} $remoteState
 */
function cleanup_remote_directories(FTP\Connection $connection, string $remoteRoot, array $localState, array $remoteState): void
{
    $localDirectories = directory_set(array_keys($localState['files']));
    $remoteDirectories = directory_set(array_keys($remoteState['files']));
    $directoriesToDelete = array_diff(array_keys($remoteDirectories), array_keys($localDirectories));

    usort(
        $directoriesToDelete,
        static fn (string $a, string $b): int => substr_count($b, '/') <=> substr_count($a, '/')
    );

    foreach ($directoriesToDelete as $directory) {
        if ($directory === '') {
            continue;
        }

        @ftp_rmdir($connection, join_remote_path($remoteRoot, $directory));
    }
}

/**
 * @param list<string> $paths
 * @return array<string, true>
 */
function directory_set(array $paths): array
{
    $directories = [];
    foreach ($paths as $path) {
        $current = dirname($path);
        while ($current !== '.' && $current !== '') {
            $directories[normalize_relative_path($current)] = true;
            $current = dirname($current);
        }
    }

    return $directories;
}

function normalize_relative_path(string $path): string
{
    return ltrim(str_replace('\\', '/', $path), '/');
}

function normalize_remote_dir(string $path): string
{
    $normalized = '/' . trim(str_replace('\\', '/', $path), '/');

    return $normalized === '//' ? '/' : $normalized;
}

function join_remote_path(string $base, string $path): string
{
    $base = rtrim(str_replace('\\', '/', $base), '/');
    $path = ltrim(str_replace('\\', '/', $path), '/');

    if ($base === '') {
        return '/' . $path;
    }

    return $path === '' ? $base : $base . '/' . $path;
}
