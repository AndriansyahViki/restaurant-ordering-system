<?php
include "../../config.php"; 
$conn = getConnection();

// Cek role user
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login/login.php');
    exit;
}
// ================== AMBIL DATA DINE-IN ==================
$dineInQuery = "
    SELECT 
        o.order_id,
        o.order_code,
        o.table_number,
        o.order_date,
        o.status,
        o.payment_method,
        SUM(oi.subtotal) AS total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.order_id
    WHERE (o.delivery_name IS NULL OR o.delivery_name = '') 
    AND o.table_number IS NOT NULL
    GROUP BY o.order_id
    ORDER BY o.order_id DESC
";
$dineInResult = mysqli_query($conn, $dineInQuery);

if (!$dineInResult) {
    echo "Error Dine-In Query: " . mysqli_error($conn);
    $dineInResult = false;
}

// ================== AMBIL DATA DELIVERY ==================
$deliveryQuery = "
    SELECT 
        o.order_id,
        o.order_code,
        o.order_date,
        o.status,
        o.payment_method,
        d.customer_name,
        d.customer_phone,
        d.customer_address,
        SUM(oi.subtotal) AS total
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.order_id
    LEFT JOIN delivery_info d ON d.order_id = o.order_id
    WHERE (o.delivery_name IS NOT NULL AND o.delivery_name != '')
    OR d.order_id IS NOT NULL
    GROUP BY o.order_id
    ORDER BY o.order_id DESC
";
$deliveryResult = mysqli_query($conn, $deliveryQuery);

if (!$deliveryResult) {
    echo "Error Delivery Query: " . mysqli_error($conn);
    $deliveryResult = false;
}

// Hitung statistik
$totalDineIn = $dineInResult ? mysqli_num_rows($dineInResult) : 0;
$totalDelivery = $deliveryResult ? mysqli_num_rows($deliveryResult) : 0;
$pendingQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$pendingCount = $pendingQuery ? mysqli_fetch_assoc($pendingQuery)['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Pelanggan - K-6 Resto</title>
    <link rel="stylesheet" href="../../CSS/style_admin.css">
</head>
<body>
 <a href="../../index.php" class="btn btn-secondary">Kembali Ke Halaman Utama</a>
<div class="container">
    <h1>📦 Order Pelanggan - K-6 Resto</h1>

     
    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total Dine-In</div>
            <div class="stat-number"><?= $totalDineIn ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Delivery</div>
            <div class="stat-number"><?= $totalDelivery ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-number"><?= $pendingCount ?></div>
        </div>
    </div>

    <div class="tabs">
        <button id="btnDinein" class="tab-btn active" onclick="switchTab('dinein')">
            🍽️ Dine-In (<?= $totalDineIn ?>)
        </button>
        <button id="btnDelivery" class="tab-btn" onclick="switchTab('delivery')">
            🚚 Delivery (<?= $totalDelivery ?>)
        </button>
    </div>

    <!-- ========== DINE-IN ========== -->
    <div id="dinein" class="table-box">
        <h2>🍽️ Order Dine-In</h2>

        <?php if (!$dineInResult): ?>
            <div class="error-message">
                ❌ Error saat mengambil data dine-in: <?php echo mysqli_error($conn); ?>
            </div>
        <?php elseif (mysqli_num_rows($dineInResult) == 0): ?>
            <div class="empty-message">
                🍽️ Tidak ada data order dine-in saat ini.
            </div>
        <?php else: ?>
            <table>
                <tr>
                    <th>Kode Order</th>
                    <th>Meja</th>
                    <th>Tanggal</th>
                    <th>Pembayaran</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>

                <?php while($row = mysqli_fetch_assoc($dineInResult)) { ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['order_code']) ?></strong></td>
                    <td>Meja <?= htmlspecialchars($row['table_number']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></td>
                    <td><span class="payment-badge"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                    <td><strong>Rp <?= number_format($row['total'], 0, ',', '.') ?></strong></td>
                    <td><span class="status <?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td><a href="order_detail.php?id=<?= $row['order_id'] ?>"><button class="detail-btn">📋 Detail</button></a></td>
                </tr>
                <?php } ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- ========== DELIVERY ========== -->
    <div id="delivery" class="table-box hidden">
        <h2>🚚 Order Delivery</h2>

        <?php if (!$deliveryResult): ?>
            <div class="error-message">
                ❌ Error saat mengambil data delivery: <?php echo mysqli_error($conn); ?>
            </div>
        <?php elseif (mysqli_num_rows($deliveryResult) == 0): ?>
            <div class="empty-message">
                🚚 Tidak ada data order delivery saat ini.
            </div>
        <?php else: ?>
            <table>
                <tr>
                    <th>Kode Order</th>
                    <th>Nama Customer</th>
                    <th>No HP</th>
                    <th>Alamat</th>
                    <th>Pembayaran</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>

                <?php while($row = mysqli_fetch_assoc($deliveryResult)) { ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['order_code']) ?></strong></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><?= htmlspecialchars($row['customer_phone']) ?></td>
                    <td><?= htmlspecialchars(substr($row['customer_address'], 0, 30)) ?>...</td>
                    <td><span class="payment-badge"><?= htmlspecialchars($row['payment_method']) ?></span></td>
                    <td><strong>Rp <?= number_format($row['total'], 0, ',', '.') ?></strong></td>
                    <td><span class="status <?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td><a href="order_detail.php?id=<?= $row['order_id'] ?>"><button class="detail-btn">📋 Detail</button></a></td>
                </tr>
                <?php } ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function switchTab(type) {
    document.getElementById("dinein").classList.add("hidden");
    document.getElementById("delivery").classList.add("hidden");
    document.getElementById(type).classList.remove("hidden");

    document.getElementById("btnDinein").classList.remove("active");
    document.getElementById("btnDelivery").classList.remove("active");

    if(type === "dinein") {
        document.getElementById("btnDinein").classList.add("active");
    } else {
        document.getElementById("btnDelivery").classList.add("active");
    }
}

// Auto refresh setiap 30 detik
setTimeout(function() {
    location.reload();
}, 30000);
</script>

</body>
</html>

<?php 
if ($dineInResult) mysqli_free_result($dineInResult);
if ($deliveryResult) mysqli_free_result($deliveryResult);
if ($conn) mysqli_close($conn);
?>