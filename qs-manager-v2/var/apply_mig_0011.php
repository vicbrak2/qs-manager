<?php
$pdo = new PDO('pgsql:host=db;port=5432;dbname=qs_manager_v2', 'qs_user', 'qs_password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = file_get_contents(__DIR__ . '/../database/migrations/0011_workshop_finance_views.sql');
$pdo->exec($sql);
echo "Migration 0011 applied successfully.\n";
