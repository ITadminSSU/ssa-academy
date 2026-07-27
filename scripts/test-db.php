<?php

$start = microtime(true);

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=ssu_academy',
        'root',
        '',
        [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    $pdo->query('SELECT 1')->fetchColumn();
    $elapsed = round(microtime(true) - $start, 2);
    echo "DB OK in {$elapsed}s\n";
} catch (Throwable $e) {
    $elapsed = round(microtime(true) - $start, 2);
    echo "DB FAIL after {$elapsed}s: {$e->getMessage()}\n";
    exit(1);
}
