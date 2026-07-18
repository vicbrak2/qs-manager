<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use QSManager\Infrastructure\Database\ConnectionFactory;

$pdo = ConnectionFactory::fromEnvironment();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS qs_schema_migrations (
        version VARCHAR(100) PRIMARY KEY,
        checksum VARCHAR(64) NOT NULL,
        applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
    );
");

$migrationsDir = __DIR__ . '/../database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$applied = $pdo->query("SELECT version, checksum FROM qs_schema_migrations")->fetchAll(\PDO::FETCH_KEY_PAIR);

foreach ($files as $file) {
    $version = basename($file);
    $content = file_get_contents($file);
    
    // Remove BOM if exists
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }
    
    $checksum = hash('sha256', $content);

    if (isset($applied[$version])) {
        if ($applied[$version] !== $checksum) {
            throw new RuntimeException("Checksum mismatch for migration {$version}. Expected {$applied[$version]}, got {$checksum}");
        }
        continue;
    }

    echo "Applying {$version}...\n";
    $pdo->beginTransaction();
    try {
        $pdo->exec($content);
        $stmt = $pdo->prepare("INSERT INTO qs_schema_migrations (version, checksum) VALUES (:version, :checksum)");
        $stmt->execute(['version' => $version, 'checksum' => $checksum]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw new RuntimeException("Failed to apply migration {$version}: " . $e->getMessage(), 0, $e);
    }
}