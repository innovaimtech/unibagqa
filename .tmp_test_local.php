<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='unibagqa'")->fetchColumn();
