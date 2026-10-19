<?php
require_once __DIR__ . '/../src/Env.php';
Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../src/Db.php';

echo "--- TRZ ROLLS TABLE ---\n";
$cols = Db::trzPdo()->query('DESCRIBE rolls')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ")\n";
}

echo "\n--- TRZ EVENTS TABLE ---\n";
$cols2 = Db::trzPdo()->query('DESCRIBE events')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) {
    echo $c['Field'] . ' (' . $c['Type'] . ")\n";
}
