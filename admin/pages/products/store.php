<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: index.php');
    exit();
}

try {
    // Validate and sanitize inputs
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $buying_price = !empty($_POST['buying_price']) ? (float)$_POST['buying_price'] : 0;
    $selling_price = !empty($_POST['selling_price']) ? (float)$_POST['selling_price'] : 0;
    $stock_quantity = !empty($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
    $icon = trim($_POST['icon'] ?? '🥃');

    // Validation
    if (empty($product_name)) {
        throw new Exception('Product name is required.');
    }

    if (strlen($product_name) > 160) {
        throw new Exception('Product name must not exceed 160 characters.');
    }

    if ($buying_price < 0) {
        throw new Exception('Buying price cannot be negative.');
    }

    if ($selling_price < 0) {
        throw new Exception('Selling price cannot be negative.');
    }

    if ($stock_quantity < 0) {
        throw new Exception('Stock quantity cannot be negative.');
    }

    if (!isset($conn)) {
        throw new Exception('Database connection not initialized.');
    }

    // Handle PDO connections
    if ($conn instanceof PDO) {
        $stmt = $conn->prepare("
            INSERT INTO products (product_name, category, brand_id, supplier_id, buying_price, selling_price, stock_quantity, icon)
            VALUES (:product_name, :category, :brand_id, :supplier_id, :buying_price, :selling_price, :stock_quantity, :icon)
        ");

        if (!$stmt) {
            throw new Exception('Failed to prepare database statement.');
        }

        $result = $stmt->execute([
            ':product_name' => $product_name,
            ':category' => $category,
            ':brand_id' => $brand_id,
            ':supplier_id' => $supplier_id,
            ':buying_price' => $buying_price,
            ':selling_price' => $selling_price,
            ':stock_quantity' => $stock_quantity,
            ':icon' => $icon
        ]);

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Database error: ' . ($errorInfo[2] ?? 'Could not insert product.'));
        }

    // Handle MySQLi connections
    } elseif (class_exists('mysqli') && $conn instanceof mysqli) {
        $stmt = $conn->prepare("
            INSERT INTO products (product_name, category, brand_id, supplier_id, buying_price, selling_price, stock_quantity, icon)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        // Bind parameters: s=string, i=integer, d=double/float
        $stmt->bind_param(
            'ssiiidis',
            $product_name,
            $category,
            $brand_id,
            $supplier_id,
            $buying_price,
            $selling_price,
            $stock_quantity,
            $icon
        );

        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }

        $stmt->close();

    } else {
        throw new Exception('Unsupported database connection type.');
    }

    $_SESSION['success_message'] = 'Product created successfully!';
    header('Location: index.php');
    exit();

} catch (Exception $e) {
    error_log('Product creation error: ' . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: index.php');
    exit();
}
?>
