<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';

Env::load(__DIR__ . '/../.env');

$pdo = Db::trzPdo();
$activeBefore = (int)$pdo->query("SELECT COUNT(*) FROM production_shift_sessions WHERE status = 'ACTIVE'")->fetchColumn();

$stmt = $pdo->prepare(
    "UPDATE production_shift_sessions
     SET status = 'CLOSED',
         ended_at = CURRENT_TIMESTAMP,
         comments = CASE
             WHEN comments IS NULL OR comments = '' THEN :note_empty
             ELSE CONCAT(comments, '\n', :note_append)
         END
     WHERE status = 'ACTIVE'"
);
$stmt->execute([
    ':note_empty' => 'Cierre masivo manual desde Trae',
    ':note_append' => 'Cierre masivo manual desde Trae',
]);

echo json_encode([
    'ok' => true,
    'active_before' => $activeBefore,
    'closed' => $stmt->rowCount(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
