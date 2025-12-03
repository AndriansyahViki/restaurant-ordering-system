<?php
include "../../config.php";
$conn = getConnection(); 

// Cek role user
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login/login.php');
    exit;
}
// Error handling untuk parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Order ID tidak ditemukan");
}

$order_id = intval($_GET['id']);

// AMBIL ORDER dengan error handling
$orderQuery = mysqli_query($conn, "
    SELECT * FROM orders WHERE order_id = $order_id
");

if (!$orderQuery) {
    die("Error mengambil data order: " . mysqli_error($conn));
}

$order = mysqli_fetch_assoc($orderQuery);

if (!$order) {
    die("Error: Order tidak ditemukan");
}

// AMBIL ITEM dengan error handling
$itemQuery = mysqli_query($conn, "
    SELECT oi.*, m.name 
    FROM order_items oi
    JOIN menu m ON m.menu_id = oi.menu_id
    WHERE oi.order_id = $order_id
");

if (!$itemQuery) {
    echo "<div class='error-message'>Error mengambil item order: " . mysqli_error($conn) . "</div>";
    $items = [];
} else {
    $items = [];
    while ($item = mysqli_fetch_assoc($itemQuery)) {
        $items[] = $item;
    }
}

// CEK DELIVERY INFO dengan error handling
$deliveryQuery = mysqli_query($conn, "
    SELECT * FROM delivery_info WHERE order_id = $order_id
");

$delivery = null;
if ($deliveryQuery && mysqli_num_rows($deliveryQuery) > 0) {
    $delivery = mysqli_fetch_assoc($deliveryQuery);
}

// Tentukan tipe order
$isDineIn = !empty($order['table_number']) && $order['table_number'] > 0;
$isDelivery = ($delivery !== null) || (!empty($order['delivery_name']));

// Hitung grand total
$grandTotal = 0;
foreach ($items as $item) {
    $grandTotal += $item['subtotal'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Order - K-6 Resto</title>
    <link rel="stylesheet" href="../../CSS/style_admin.css">
</head>
<body>

<div class="detail-box">
    <h2>📋 Detail Order #<?= htmlspecialchars($order['order_code']) ?></h2>

    <div class="order-header">
        <p class="status-box">Status: <span class="status <?= $order['status'] ?>"><?= $order['status'] ?></span></p>
        <p><b>Metode Pembayaran:</b> <?= htmlspecialchars($order['payment_method']) ?></p>
        <p><b>Tanggal Order:</b> <?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></p>
    </div>

    <?php if ($isDineIn): ?>
        <div class="order-type dinein">
            <h3>🍽️ Dine-In Order</h3>
            <p><b>Meja:</b> <?= htmlspecialchars($order['table_number']) ?></p>
        </div>
    <?php elseif ($isDelivery): ?>
        <div class="order-type delivery">
            <h3>🚚 Delivery Order</h3>
            <?php if ($delivery): ?>
                <p><b>Nama:</b> <?= htmlspecialchars($delivery['customer_name']) ?></p>
                <p><b>No HP:</b> <?= htmlspecialchars($delivery['customer_phone']) ?></p>
                <p><b>Alamat:</b> <?= htmlspecialchars($delivery['customer_address']) ?></p>
                <p><b>Catatan:</b> <?= !empty($delivery['notes']) ? htmlspecialchars($delivery['notes']) : '-' ?></p>
            <?php else: ?>
                <p><b>Nama:</b> <?= htmlspecialchars($order['delivery_name']) ?></p>
                <p><b>No HP:</b> <?= htmlspecialchars($order['delivery_phone']) ?></p>
                <p><b>Alamat:</b> <?= htmlspecialchars($order['delivery_address']) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h3>🍽️ Item Pesanan</h3>

    <?php if (empty($items)): ?>
        <div class="empty-message">
            Tidak ada item pesanan ditemukan.
        </div>
    <?php else: ?>
        <table>
            <tr>
                <th>Menu</th>
                <th>Qty</th>
                <th>Harga</th>
                <th>Subtotal</th>
            </tr>

            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= htmlspecialchars($item['quantity']) ?></td>
                <td>Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                <td>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="total-section">
            <h2>Total: Rp <?= number_format($grandTotal, 0, ',', '.') ?></h2>
        </div>
    <?php endif; ?>

    <!-- TOMBOL UPDATE STATUS -->
    <?php if ($order['status'] == 'pending'): ?>
        <div class="action-buttons">
            <a href="update_status.php?id=<?= $order_id ?>&status=completed">
                <button class="btn-action btn-done">✅ Selesaikan Pesanan</button>
            </a>

            <a href="update_status.php?id=<?= $order_id ?>&status=cancelled">
                <button class="btn-action btn-cancel">❌ Batalkan Pesanan</button>
            </a>
        </div>
    <?php endif; ?>

    <div class="back-button">
        <a href="admin_orders.php">
            <button class="btn-action">← Kembali</button>
        </a>
    </div>
</div>

</body>
</html>