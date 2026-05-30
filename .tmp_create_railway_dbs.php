<?php
$pdo = new PDO('mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=railway;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS unibagqa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('CREATE DATABASE IF NOT EXISTS unibag_trazabilidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "ok";
