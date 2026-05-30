<?php
$pdo = new PDO('mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=railway;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo $pdo->query('SELECT DATABASE()')->fetchColumn();
