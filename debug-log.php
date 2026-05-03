<?php
/**
 * Temporary debug script to read plugin logs.
 * WILL BE DELETED AFTER USE.
 */

$logFile = __DIR__ . '/var/logs/plugin.log';

if (!file_exists($logFile)) {
    die("Log file not found at: $logFile");
}

header('Content-Type: text/plain');
// Read last 100 lines
$lines = file($logFile);
$lastLines = array_slice($lines, -100);
echo implode("", $lastLines);
