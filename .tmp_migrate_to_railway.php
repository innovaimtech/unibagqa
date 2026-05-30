<?php
declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '1024M');

function out(string $message): void {
    echo '[' . date('H:i:s') . '] ' . $message . PHP_EOL;
}

function connect(string $dsn, string $user, string $pass): PDO {
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
}

function qi(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function normalizeCreateTable(string $sql): string {
    return preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $sql) ?? $sql;
}

function normalizeCreateView(string $sql): string {
    $sql = preg_replace('/CREATE\s+ALGORITHM=.*?\s+DEFINER=`[^`]+`@`[^`]+`\s+SQL\s+SECURITY\s+DEFINER\s+VIEW/i', 'CREATE VIEW', $sql) ?? $sql;
    $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/i', '', $sql) ?? $sql;
    return $sql;
}

function migrateDatabase(PDO $srcServer, PDO $dstServer, string $db): void {
    out("Migrando base {$db}");
    $srcServer->exec('USE ' . qi($db));
    $dstServer->exec('CREATE DATABASE IF NOT EXISTS ' . qi($db) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $dstServer->exec('USE ' . qi($db));
    $dstServer->exec('SET FOREIGN_KEY_CHECKS=0');
    $dstServer->exec('SET UNIQUE_CHECKS=0');

    $tablesStmt = $srcServer->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = " . $srcServer->quote($db) . " AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $table = (string)$table;
        out("Tabla {$db}.{$table}: estructura");
        $createRow = $srcServer->query('SHOW CREATE TABLE ' . qi($table))->fetch();
        $createSql = normalizeCreateTable((string)($createRow['Create Table'] ?? ''));
        if ($createSql === '') {
            throw new RuntimeException('No se pudo obtener CREATE TABLE de ' . $table);
        }

        $dstServer->exec('DROP TABLE IF EXISTS ' . qi($table));
        $dstServer->exec($createSql);

        $count = (int)$srcServer->query('SELECT COUNT(*) FROM ' . qi($table))->fetchColumn();
        out("Tabla {$db}.{$table}: {$count} filas");
        if ($count === 0) {
            continue;
        }

        $select = $srcServer->query('SELECT * FROM ' . qi($table));
        $firstRow = $select->fetch(PDO::FETCH_ASSOC);
        if ($firstRow === false) {
            continue;
        }

        $columns = array_keys($firstRow);
        $columnSql = implode(', ', array_map('qi', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $insertSql = 'INSERT INTO ' . qi($table) . ' (' . $columnSql . ') VALUES (' . $placeholders . ')';
        $insert = $dstServer->prepare($insertSql);
        $dstServer->beginTransaction();

        $inserted = 0;
        $row = $firstRow;
        while ($row !== false) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $row[$column];
            }
            $insert->execute($values);
            $inserted++;
            if ($inserted % 500 === 0) {
                $dstServer->commit();
                out("Tabla {$db}.{$table}: {$inserted}/{$count}");
                $dstServer->beginTransaction();
            }
            $row = $select->fetch(PDO::FETCH_ASSOC);
        }
        $dstServer->commit();
    }

    $viewsStmt = $srcServer->query("SELECT TABLE_NAME FROM information_schema.views WHERE table_schema = " . $srcServer->quote($db) . " ORDER BY TABLE_NAME");
    $views = $viewsStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($views as $view) {
        $view = (string)$view;
        out("Vista {$db}.{$view}");
        $row = $srcServer->query('SHOW CREATE VIEW ' . qi($view))->fetch();
        $createView = normalizeCreateView((string)($row['Create View'] ?? ''));
        if ($createView === '') {
            continue;
        }
        $dstServer->exec('DROP VIEW IF EXISTS ' . qi($view));
        $dstServer->exec($createView);
    }

    $dstServer->exec('SET UNIQUE_CHECKS=1');
    $dstServer->exec('SET FOREIGN_KEY_CHECKS=1');
    out("Base {$db} completada");
}

$src = connect('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', 'AY2309');
$dst = connect('mysql:host=ballast.proxy.rlwy.net;port=28980;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj');

migrateDatabase($src, $dst, 'unibag_trazabilidad');
migrateDatabase($src, $dst, 'unibagqa');
out('Migracion finalizada');
