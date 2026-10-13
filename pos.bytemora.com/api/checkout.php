<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$items = $input['items'] ?? [];
$customerName  = trim($input['customer_name'] ?? '');
$customerPhone = trim($input['customer_phone'] ?? '');
$amountPaidInput = (float) ($input['amount_paid'] ?? 0);

if (!$items || !is_array($items)) {
    echo json_encode(['ok' => false, 'error' => 'Cart is empty.']);
    exit;
}
if ($customerName === '' || $customerPhone === '') {
    echo json_encode(['ok' => false, 'error' => 'Customer name and phone are required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Find or create customer by phone.
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE phone = ?');
    $stmt->execute([$customerPhone]);
    $customer = $stmt->fetch();
    if (!$customer) {
        $pdo->prepare('INSERT INTO customers (name, phone) VALUES (?, ?)')->execute([$customerName, $customerPhone]);
        $customerId = (int) $pdo->lastInsertId();
    } else {
        $customerId = (int) $customer['id'];
        if ($customer['name'] !== $customerName) {
            $pdo->prepare('UPDATE customers SET name = ? WHERE id = ?')->execute([$customerName, $customerId]);
        }
    }

    // Create a placeholder sale row so we have a sale id for stock_out / sale_items references.
    $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, subtotal, total, amount_paid, balance_due, payment_status, created_by) VALUES ('PENDING', ?, 0, 0, 0, 0, 'unpaid', ?)")
        ->execute([$customerId, $_SESSION['user_id']]);
    $saleId = (int) $pdo->lastInsertId();

    $subtotal = 0.0;
    $insertItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, tracking_type, serial_number_id, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?,?)");
    $insertStockOut = $pdo->prepare("INSERT INTO stock_out (product_id, tracking_type, serial_number_id, quantity, reason, reference_sale_id, created_by) VALUES (?,?,?,?, 'sale', ?, ?)");

    foreach ($items as $item) {
        $type = $item['type'] ?? '';
        $productId = (int) ($item['product_id'] ?? 0);

        $pStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch();
        if (!$product) {
            throw new RuntimeException('A product in the cart is no longer available.');
        }

        if ($type === 'bulk') {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            if ($product['tracking_type'] !== 'bulk') {
                throw new RuntimeException($product['name'] . ' is not a bulk product.');
            }
            if ((int) $product['stock_qty'] < $qty) {
                throw new RuntimeException('Not enough stock for ' . $product['name'] . ' (only ' . $product['stock_qty'] . ' left).');
            }
            $unitPrice = (float) $product['unit_price'];
            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $insertItem->execute([$saleId, $productId, 'bulk', null, $qty, $unitPrice, $lineTotal]);
            $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?')->execute([$qty, $productId]);
            $insertStockOut->execute([$productId, 'bulk', null, $qty, $saleId, $_SESSION['user_id']]);
        } elseif ($type === 'serialized') {
            $serialId = (int) ($item['serial_number_id'] ?? 0);
            if ($product['tracking_type'] !== 'serialized') {
                throw new RuntimeException($product['name'] . ' is not a serialized product.');
            }
            $sStmt = $pdo->prepare("SELECT * FROM serial_numbers WHERE id = ? AND product_id = ? FOR UPDATE");
            $sStmt->execute([$serialId, $productId]);
            $serial = $sStmt->fetch();
            if (!$serial || $serial['status'] !== 'in_stock') {
                throw new RuntimeException('Unit for ' . $product['name'] . ' is no longer available.');
            }
            $unitPrice = (float) $product['unit_price'];
            $lineTotal = $unitPrice;
            $subtotal += $lineTotal;

            $insertItem->execute([$saleId, $productId, 'serialized', $serialId, 1, $unitPrice, $lineTotal]);
            $pdo->prepare("UPDATE serial_numbers SET status = 'sold' WHERE id = ?")->execute([$serialId]);
            $insertStockOut->execute([$productId, 'serialized', $serialId, 1, $saleId, $_SESSION['user_id']]);
        } else {
            throw new RuntimeException('Invalid item type in cart.');
        }
    }

    $total = round($subtotal, 2);
    $amountPaid = max(0, min($amountPaidInput, $total));
    $balanceDue = round($total - $amountPaid, 2);
    $paymentStatus = $balanceDue <= 0 ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid');
    $invoiceNo = invoice_number($saleId);

    $pdo->prepare("UPDATE sales SET invoice_no=?, subtotal=?, total=?, amount_paid=?, balance_due=?, payment_status=? WHERE id=?")
        ->execute([$invoiceNo, $total, $total, $amountPaid, $balanceDue, $paymentStatus, $saleId]);

    if ($amountPaid > 0) {
        $pdo->prepare("INSERT INTO customer_payments (customer_id, sale_id, amount, payment_date, notes) VALUES (?,?,?,CURDATE(),'Payment at checkout')")
            ->execute([$customerId, $saleId, $amountPaid]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'sale_id' => $saleId, 'invoice_no' => $invoiceNo]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
