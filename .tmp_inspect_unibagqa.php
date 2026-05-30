<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "CONNECTED\n";
    foreach ($pdo->query('SHOW TABLES') as $row) {
        echo $row[0], PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
