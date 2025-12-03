<?php
session_start();
require_once '../../config.php';
$conn = getConnection();

// Cek role user
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login/login.php');
    exit;
}

if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $sales_query = $conn->prepare("
        SELECT 
            o.order_code,
            DATE(o.order_date) as tanggal,
            m.name as produk,
            m.category,
            oi.quantity,
            oi.price,
            oi.subtotal,
            o.status,
            o.payment_method
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN menu m ON oi.menu_id = m.menu_id
        WHERE o.order_date BETWEEN ? AND ? + INTERVAL 1 DAY
        AND o.status = 'completed'
        ORDER BY o.order_date DESC
    ");
    
    if (!$sales_query) {
        die("Error preparing query: " . $conn->error);
    }
    
    $sales_query->bind_param("ss", $start_date, $end_date);
    
    if (!$sales_query->execute()) {
        die("Error executing query: " . $sales_query->error);
    }
    
    $sales_data = $sales_query->get_result();

    if (!$sales_data) {
        die("Error getting result: " . $conn->error);
    }

    // Header untuk download Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_penjualan_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "Laporan Penjualan Resto K6\n";
    echo "Periode: $start_date s/d $end_date\n";
    echo "Tanggal Export: " . date('d/m/Y H:i:s') . "\n\n";
    
    // Header tabel
    echo "Kode Order\tTanggal\tProduk\tKategori\tQty\tHarga\tSubtotal\tStatus\tMetode Bayar\n";
    
    $total_revenue = 0;
    $total_quantity = 0;
    
    // Data rows
    while ($row = $sales_data->fetch_assoc()) {
        echo $row['order_code'] . "\t";
        echo $row['tanggal'] . "\t";
        echo $row['produk'] . "\t";
        echo $row['category'] . "\t";
        echo $row['quantity'] . "\t";
        echo number_format($row['price'], 0, ',', '.') . "\t";
        echo number_format($row['subtotal'], 0, ',', '.') . "\t";
        echo $row['status'] . "\t";
        echo $row['payment_method'] . "\n";
        
        $total_revenue += $row['subtotal'];
        $total_quantity += $row['quantity'];
    }
    
    // Footer dengan total
    echo "\n";
    echo "TOTAL:\t\t\t\t" . $total_quantity . "\t\t" . number_format($total_revenue, 0, ',', '.') . "\n";
    
    // Summary
    echo "\nSUMMARY:\n";
    echo "Total Pesanan: " . $sales_data->num_rows . "\n";
    echo "Total Item Terjual: " . $total_quantity . "\n";
    echo "Total Pendapatan: Rp " . number_format($total_revenue, 0, ',', '.') . "\n";
    
    exit;
} else {
    // Jika bukan request export, redirect ke halaman laporan
    header('Location: laporan_penjualan.php');
    exit;
}
?>