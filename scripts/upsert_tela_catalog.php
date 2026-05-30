<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';

Env::load(__DIR__ . '/../.env');

$pdo = Db::trzPdo();

$catalog = [
    ['code' => 'TEL0006', 'description' => 'PP/NEGRO/C90/100X80X1100'],
    ['code' => 'TEL0007', 'description' => 'PP/MORADO/P62/100X80X1100'],
    ['code' => 'TEL0008', 'description' => 'PP/ROJO/RO1/100X80X1100'],
    ['code' => 'TEL0009', 'description' => 'PP/NARANJO/Y23/100X80X1100'],
    ['code' => 'TEL0011', 'description' => 'PP/VERDE PISTACHO/G41/100X80X1100'],
    ['code' => 'TEL0034', 'description' => 'PP/BEIGE/Y26/130X80X1100'],
    ['code' => 'TEL0042', 'description' => 'PP/FUCSIA/R08/100X80X1100'],
    ['code' => 'TEL0044', 'description' => 'PP/BLANCO/W80/100X80X1100'],
    ['code' => 'TEL0046', 'description' => 'PP/AZUL REY/B53/100X80X1100'],
    ['code' => 'TEL0070', 'description' => 'PP/CELESTE/B50/100X80X1100'],
    ['code' => 'TEL0084', 'description' => 'PP/BURDEO/R06/100X80X1100'],
    ['code' => 'TEL0122', 'description' => 'PP/GRIS OSCURO/E72/100X80X1100'],
    ['code' => 'TEL0173', 'description' => 'PP/AMARILLO/Y20/130X80X1100'],
    ['code' => 'TEL0174', 'description' => 'PP/VERDE HOJA/G42/130X80X1100'],
    ['code' => 'TEL0197', 'description' => 'PP/NEGRO/C90/100X80X1000'],
];

$stmt = $pdo->prepare(
    'INSERT INTO skus (code, description, is_active) VALUES (:code, :description, 1)
     ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = 1'
);

$pdo->beginTransaction();
try {
    foreach ($catalog as $item) {
        $stmt->execute([
            ':code' => $item['code'],
            ':description' => $item['description'],
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo json_encode([
    'updated' => count($catalog),
    'codes' => array_column($catalog, 'code'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
