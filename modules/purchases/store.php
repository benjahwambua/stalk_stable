<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf();

try {
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $purchaseDate = trim((string)($_POST['purchase_date'] ?? ''));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? 'Credit'));
    $paidAmount = (float)($_POST['paid_amount'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $tax = (float)($_POST['tax'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $items = $_POST['items'] ?? [];

    if ($supplierId <= 0) throw new RuntimeException('Please select a supplier.');
    if ($purchaseDate === '') $purchaseDate = date('Y-m-d H:i:s');
    else $purchaseDate = date('Y-m-d H:i:s', strtotime($purchaseDate));
    if ($paidAmount < 0 || $discount < 0 || $tax < 0) throw new RuntimeException('Payment, discount and tax cannot be negative.');
    if (!in_array($paymentMethod, ['Credit','Cash','M-Pesa','Bank','Cheque','Other'], true)) throw new RuntimeException('Invalid payment method.');
    if (!is_array($items) || !$items) throw new RuntimeException('Add at least one purchase item.');

    $cleanItems = [];
    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        $price = (float)($item['buying_price'] ?? 0);
        if ($productId <= 0 || $qty <= 0) throw new RuntimeException('Each purchase item must have a valid product and quantity.');
        if ($price < 0) throw new RuntimeException('Buying price cannot be negative.');
        $cleanItems[] = ['product_id'=>$productId, 'quantity'=>$qty, 'buying_price'=>$price];
    }

    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT id, supplier_name FROM suppliers WHERE id=? AND status='Active' FOR UPDATE");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    if (!$supplier) throw new RuntimeException('Supplier not found or inactive.');

    $subtotal = 0.0;
    foreach ($cleanItems as &$item) {
        $stmt = $conn->prepare("SELECT id, product_name, stock_quantity FROM products WHERE id=? AND status='Active' FOR UPDATE");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch();
        if (!$product) throw new RuntimeException('One of the selected products is missing or inactive.');
        $item['product_name'] = $product['product_name'];
        $item['stock_before'] = (int)$product['stock_quantity'];
        $item['line_total'] = round($item['quantity'] * $item['buying_price'], 2);
        $subtotal += $item['line_total'];
    }
    unset($item);

    $total = round(max(0, $subtotal - $discount + $tax), 2);
    if ($paidAmount > $total) throw new RuntimeException('Paid amount cannot exceed the purchase total.');
    $balance = round($total - $paidAmount, 2);

    $prefix = 'SS-PUR';
    $setting = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key='purchase_prefix' LIMIT 1");
    $setting->execute();
    $configuredPrefix = $setting->fetchColumn();
    if ($configuredPrefix) $prefix = (string)$configuredPrefix;

    $purchaseNumber = $prefix . '-' . date('YmdHis') . '-' . random_int(100, 999);

    $stmt = $conn->prepare("INSERT INTO purchases (purchase_number,supplier_id,user_id,purchase_date,subtotal,discount,tax,total_amount,paid_amount,balance,payment_method,status,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,'Completed',?)");
    $stmt->execute([
        $purchaseNumber,$supplierId,(int)$_SESSION['user_id'],$purchaseDate,
        $subtotal,$discount,$tax,$total,$paidAmount,$balance,$paymentMethod,$notes ?: null
    ]);
    $purchaseId = (int)$conn->lastInsertId();

    $itemStmt = $conn->prepare("INSERT INTO purchase_items (purchase_id,product_id,quantity,buying_price,subtotal) VALUES (?,?,?,?,?)");
    $stockStmt = $conn->prepare("UPDATE products SET stock_quantity=stock_quantity+?, buying_price=? WHERE id=?");
    $movementStmt = $conn->prepare("INSERT INTO stock_movements (product_id,user_id,movement_type,quantity,stock_before,stock_after,reference_type,reference_id,notes,movement_date) VALUES (?,?,?,?,?,?,?,?,?,?)");

    foreach ($cleanItems as $item) {
        $stockAfter = $item['stock_before'] + $item['quantity'];
        $itemStmt->execute([$purchaseId,$item['product_id'],$item['quantity'],$item['buying_price'],$item['line_total']]);
        $stockStmt->execute([$item['quantity'],$item['buying_price'],$item['product_id']]);
        $movementStmt->execute([
            $item['product_id'],(int)$_SESSION['user_id'],'Purchase',$item['quantity'],
            $item['stock_before'],$stockAfter,'purchase',$purchaseId,
            'Purchase '.$purchaseNumber.' - '.$item['product_name'],$purchaseDate
        ]);
    }

    if ($balance > 0) {
        $stmt = $conn->prepare("UPDATE suppliers SET balance=balance+? WHERE id=?");
        $stmt->execute([$balance,$supplierId]);
    }

    $conn->commit();

    $_SESSION['purchase_flash'] = 'Purchase '.$purchaseNumber.' completed successfully. Stock has been updated.';
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Purchase error: '.$e->getMessage());
    $_SESSION['purchase_flash'] = 'Purchase could not be completed: '.$e->getMessage();
}

header('Location: index.php');
exit;
