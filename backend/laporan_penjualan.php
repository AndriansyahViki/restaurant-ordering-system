<?php
session_start();
require_once '../../config.php';
$conn = getConnection();

// Cek role user
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login/login.php');
    exit;
}

// Filter tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Data untuk grafik - Penjualan Harian
$chart_data = [];
$chart_labels = [];

// PERBAIKAN: Gunakan order_date atau tambahkan created_at jika tidak ada
$chart_query = $conn->prepare("
    SELECT DATE(o.order_date) as tanggal, SUM(oi.subtotal) as total
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY
    AND o.status = 'completed'
    GROUP BY DATE(o.order_date)
    ORDER BY tanggal
");

if (!$chart_query) {
    die("Error preparing chart query: " . $conn->error);
}

$chart_query->bind_param("ss", $start_date, $end_date);
$chart_query->execute();
$chart_result = $chart_query->get_result();

if (!$chart_result) {
    die("Error executing chart query: " . $chart->error);
}

while ($row = $chart_result->fetch_assoc()) {
    $chart_labels[] = date('d M', strtotime($row['tanggal']));
    $chart_data[] = $row['total'];
}

// Data untuk grafik - Kategori Menu
$category_data = [];
$category_labels = [];
$category_query = $conn->prepare("
    SELECT m.category, SUM(oi.subtotal) as total
    FROM order_items oi
    JOIN menu m ON oi.menu_id = m.menu_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY
    AND o.status = 'completed'
    GROUP BY m.category
");

if (!$category_query) {
    die("Error preparing category query: " . $conn->error);
}

$category_query->bind_param("ss", $start_date, $end_date);
$category_query->execute();
$category_result = $category_query->get_result();

while ($row = $category_result->fetch_assoc()) {
    $category_labels[] = $row['category'];
    $category_data[] = $row['total'];
}

// Statistik Utama
$stats_query = $conn->prepare("
    SELECT 
        COUNT(DISTINCT o.order_id) as total_orders,
        SUM(oi.subtotal) as total_revenue,
        AVG(oi.subtotal) as avg_order_value,
        COUNT(DISTINCT oi.menu_id) as unique_products_sold
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY
    AND o.status = 'completed'
");

if (!$stats_query) {
    die("Error preparing stats query: " . $conn->error);
}

$stats_query->bind_param("ss", $start_date, $end_date);
$stats_query->execute();
$stats_result = $stats_query->get_result();
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Produk Terlaris
$bestseller_query = $conn->prepare("
    SELECT m.name, m.category, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN menu m ON oi.menu_id = m.menu_id
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY
    AND o.status = 'completed'
    GROUP BY m.menu_id, m.name, m.category
    ORDER BY total_sold DESC
    LIMIT 10
");

if (!$bestseller_query) {
    die("Error preparing bestseller query: " . $conn->error);
}

$bestseller_query->bind_param("ss", $start_date, $end_date);
$bestseller_query->execute();
$bestsellers = $bestseller_query->get_result();

// DEBUG: Cek data
echo "<!-- Chart Labels: " . count($chart_labels) . " -->";
echo "<!-- Chart Data: " . count($chart_data) . " -->";
echo "<!-- Stats: " . print_r($stats, true) . " -->";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan - Resto K6</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../../CSS/laporan_penjualan.css">
</head>
<body>
    <div class="main-content">
        <div class="report-container">
            <h1>📊 Laporan Penjualan</h1>
            
            <!-- Filter Form -->
            <form method="GET" class="filter-form">
                <div>
                    <div>
                        <label>Dari Tanggal:</label>
                        <input type="date" name="start_date" value="<?= $start_date ?>" class="form-control">
                    </div>
                    <div>
                        <label>Sampai Tanggal:</label>
                        <input type="date" name="end_date" value="<?= $end_date ?>" class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn-primary">Filter</button>
                        <button type="button" onclick="exportToExcel()" class="export-btn">Export Excel</button>
                    </div>
                </div>
            </form>

            <?php if (empty($chart_data) && empty($category_data)): ?>
                <div class="error-message">
                    ❌ Tidak ada data penjualan untuk periode yang dipilih.
                    Pastikan:
                    <ul>
                        <li>Ada order dengan status 'completed'</li>
                        <li>Tabel order_items terhubung dengan orders</li>
                        <li>Data berada dalam rentang tanggal yang dipilih</li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Statistik Utama -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Pesanan</h3>
                    <div class="number"><?= $stats['total_orders'] ?? 0 ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Pendapatan</h3>
                    <div class="number">Rp <?= number_format($stats['total_revenue'] ?? 0, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <h3>Rata-rata per Pesanan</h3>
                    <div class="number">Rp <?= number_format($stats['avg_order_value'] ?? 0, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <h3>Produk Terjual</h3>
                    <div class="number"><?= $stats['unique_products_sold'] ?? 0 ?></div>
                </div>
            </div>

            <!-- Grafik -->
            <?php if (!empty($chart_data) || !empty($category_data)): ?>
            <div class="charts-grid">
                <?php if (!empty($chart_data)): ?>
                <div class="chart-container">
                    <h3>Trend Penjualan Harian</h3>
                    <canvas id="salesChart"></canvas>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($category_data)): ?>
                <div class="chart-container">
                    <h3>Penjualan per Kategori</h3>
                    <canvas id="categoryChart"></canvas>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Produk Terlaris -->
            <div class="table-container">
                <h3>🔥 10 Produk Terlaris</h3>
                <?php if ($bestsellers && $bestsellers->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th>Terjual</th>
                            <th>Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $bestsellers->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['category']) ?></td>
                            <td><?= $product['total_sold'] ?></td>
                            <td>Rp <?= number_format($product['revenue'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>Tidak ada data produk terlaris untuk periode ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        <?php if (!empty($chart_data)): ?>
        // Grafik Trend Penjualan
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Pendapatan Harian',
                    data: <?= json_encode($chart_data) ?>,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if (!empty($category_data)): ?>
        // Grafik Kategori
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($category_labels) ?>,
                datasets: [{
                    data: <?= json_encode($category_data) ?>,
                    backgroundColor: [
                        '#4CAF50',
                        '#2196F3', 
                        '#FF9800',
                        '#E91E63',
                        '#9C27B0'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                return context.label + ': Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Export to Excel
        function exportToExcel() {
            const params = new URLSearchParams({
                start_date: '<?= $start_date ?>',
                end_date: '<?= $end_date ?>',
                export: 'excel'
            });
            window.open('export_sales.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>