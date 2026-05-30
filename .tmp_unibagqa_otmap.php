<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = ['prod_agenda','prod_header','prod_worker_init'];
foreach ($tables as $table) {
    echo "===== DESCRIBE {$table} =====\n";
    foreach ($pdo->query("DESCRIBE `{$table}`") as $row) {
        echo $row['Field'], "\t", $row['Type'], "\t", $row['Null'], "\t", $row['Key'], "\n";
    }
    echo "\n";
}
$queries = [
  "SELECT * FROM prod_agenda ORDER BY id DESC LIMIT 5",
  "SELECT * FROM prod_header ORDER BY id DESC LIMIT 5",
  "SELECT * FROM prod_worker_init ORDER BY id DESC LIMIT 5"
];
foreach ($queries as $sql) {
    echo "===== QUERY =====\n{$sql}\n";
    foreach ($pdo->query($sql) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
    }
    echo "\n";
}
