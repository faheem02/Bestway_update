<?php
function runMigrations($dbName) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8mb4", 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "Connected to $dbName\n";

        // 1. Add created_at to products if not exists
        $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'created_at'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER status");
            echo "Added created_at to products in $dbName\n";
        }

        // Backfill products.created_at
        $pdo->exec("
            UPDATE products p SET created_at = COALESCE(
                (SELECT MIN(entry_date) FROM opening_stock_logs WHERE product_id = p.id),
                (SELECT MIN(manufacturing_date) FROM product_batches WHERE product_id = p.id),
                '2026-09-28 00:00:00'
            ) WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00' OR created_at >= CURDATE()
        ");
        echo "Backfilled products.created_at in $dbName\n";

        // 2. Add created_at to product_batches if not exists
        $bcols = $pdo->query("SHOW COLUMNS FROM product_batches LIKE 'created_at'")->fetchAll();
        if (empty($bcols)) {
            $pdo->exec("ALTER TABLE product_batches ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER status");
            echo "Added created_at to product_batches in $dbName\n";
        }

        // Backfill product_batches.created_at
        $pdo->exec("
            UPDATE product_batches pb SET created_at = COALESCE(
                (SELECT entry_date FROM opening_stock_logs WHERE product_id = pb.product_id AND batch_no = pb.batch_no LIMIT 1),
                pb.manufacturing_date,
                '2026-09-28 00:00:00'
            ) WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00' OR created_at >= CURDATE()
        ");
        echo "Backfilled product_batches.created_at in $dbName\n";

    } catch (Exception $e) {
        echo "Error in $dbName: " . $e->getMessage() . "\n";
    }
}

runMigrations('bestway2');
runMigrations('alhamd_pharma');
