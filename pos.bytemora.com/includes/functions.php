<?php

function money($amount): string
{
    return APP_CURRENCY_SYMBOL . ' ' . number_format((float) $amount, 2);
}

function h($str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function invoice_number(int $saleId): string
{
    return 'INV-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
}

/**
 * Total stock currently available for a product, regardless of tracking type.
 */
function product_stock_qty(PDO $pdo, array $product): int
{
    if ($product['tracking_type'] === 'bulk') {
        return (int) $product['stock_qty'];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM serial_numbers WHERE product_id = ? AND status = 'in_stock'");
    $stmt->execute([$product['id']]);
    return (int) $stmt->fetchColumn();
}

function payment_status_badge(string $status): string
{
    $map = [
        'paid'    => 'badge badge-success',
        'partial' => 'badge badge-warning',
        'due'     => 'badge badge-danger',
        'unpaid'  => 'badge badge-danger',
    ];
    $class = $map[$status] ?? 'badge';
    return '<span class="' . $class . '">' . h(ucfirst($status)) . '</span>';
}
