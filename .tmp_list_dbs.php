<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($pdo->query('SHOW DATABASES') as $row) {
    echo $row[0], PHP_EOL;
}
