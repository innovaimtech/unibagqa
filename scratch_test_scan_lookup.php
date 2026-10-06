<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';

Env::load(__DIR__ . '/.env');

$trzPdo = Db::trzPdo();
print_r($trzPdo->query("SHOW COLUMNS FROM rolls")->fetchAll(PDO::FETCH_COLUMN));
