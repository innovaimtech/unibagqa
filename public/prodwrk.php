<?php
declare(strict_types=1);

$mid = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;
$agid = isset($_GET['agid']) ? (int)$_GET['agid'] : 0;

if ($mid === 2 && $agid > 0) {
    header('Location: /production/work-orders/' . $agid . '/operate');
    exit;
}
if ($mid === 2) {
    header('Location: /production/work-orders/active');
    exit;
}
if ($mid === 1 || $mid === 5) {
    header('Location: /production/work-orders/new');
    exit;
}
if ($mid === 3) {
    header('Location: /production/work-orders/history');
    exit;
}

header('Location: /production/machines');
exit;