<?php
// session_start();
require_once '../config.php';
$conn = getConnection();

// <========== VALIDASI ORDER ID ==========>
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    // <========== DEBUG: CEK ORDER YANG ADA DI DATABASE ==========>
    $debug_stmt = $conn->prepare("SELECT order_id, order_code FROM orders ORDER BY order_id DESC LIMIT 5");
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    
    $available_orders = [];
    while ($row = $debug_result->fetch_assoc()) {
        $available_orders[] = $row;
    }
    
    echo "<h3>Order ID tidak valid. Order yang tersedia:</h3>";
    echo "<pre>";
    print_r($available_orders);
    echo "</pre>";
    echo "<p>Gunakan URL: payment_briva.php?order_id=ORDER_ID_YANG_VALID</p>";
    die();
}

// <========== QUERY DENGAN TABLE YANG BENAR ==========>
// PERHATIAN: Table di database adalah 'order_tems' bukan 'order_items'
$stmt = $conn->prepare("
    SELECT o.*, SUM(oi.subtotal) as total_amount 
    FROM orders o 
    LEFT JOIN order_items oi ON o.order_id = oi.order_id  -- <=== GUNAKAN 'order_tems'
    WHERE o.order_id = ? 
    GROUP BY o.order_id
");

if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    die("Order dengan ID $order_id tidak ditemukan dalam database");
}

$order = $result->fetch_assoc();

// <========== GENERATE NOMOR VA BRIVA ==========>
$va_number = "88051" . str_pad($order_id, 10, "0", STR_PAD_LEFT);

// <========== UPDATE PAYMENT METHOD ==========>
if ($order['payment_method'] != 'briva') {
    $update_stmt = $conn->prepare("UPDATE orders SET payment_method = 'briva' WHERE order_id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param("i", $order_id);
        $update_stmt->execute();
    }
}

// <========== FORMAT TAMPILAN ==========>
$va_display = chunk_split($va_number, 4, ' ');
$total_amount = $order['total_amount'] ?? 0;

// <========== UPDATE STATUS JIKA PERLU ==========>
// Di database status enum: 'pending','cancelled','completed'
if ($order['status'] == 'pending') {
    $status_stmt = $conn->prepare("UPDATE orders SET status = 'pending' WHERE order_id = ?");
    if ($status_stmt) {
        $status_stmt->bind_param("i", $order_id);
        $status_stmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembayaran BRIVA - Resto K6</title>
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <!-- <========== CONTAINER UTAMA PEMBAYARAN ==========> -->
    <div class="payment-container">
        <div class="payment-header">
            <h1>💳 Pembayaran BRIVA</h1>
            <div class="order-code">Kode Pesanan: <strong><?= htmlspecialchars($order['order_code']) ?></strong></div>
        </div>
        
        <div class="payment-content">
            <!-- <========== STATUS PEMBAYARAN ==========> -->
            <div class="status-info">
                ✅ Menunggu Pembayaran - Silakan transfer sebelum <strong id="expiryTime"></strong>
            </div>
            
            <!-- <========== SECTION TOTAL PEMBAYARAN ==========> -->
            <div class="amount-section">
                <div class="amount-label">Total yang harus dibayar:</div>
                <div class="amount-value">Rp <?= number_format($total_amount, 0, ',', '.') ?></div>
            </div>
            
            <!-- <========== SECTION NOMOR VIRTUAL ACCOUNT ==========> -->
            <div class="va-section">
                <div class="va-label">Nomor Virtual Account BRIVA:</div>
                <div class="va-number" id="vaNumber" onclick="copyVANumber()" title="Klik untuk copy">
                    <?= htmlspecialchars($va_display) ?>
                </div>
                <div class="copy-success" id="copySuccess">✅ Berhasil disalin ke clipboard!</div>
            </div>
            
            <!-- <========== INFORMASI BANK ==========> -->
            <div class="bank-info">
                <div class="bank-logo">🏦</div>
                <div class="bank-details">
                    <div class="bank-name">BANK BRI (BRIVA)</div>
                    <div>Atas Nama: <strong><?= htmlspecialchars($order['delivery_name']) ?></strong></div>
                </div>
            </div>
            
            <!-- <========== INSTRUKSI CARA PEMBAYARAN ==========> -->
            <div class="instructions">
                <h3>📋 Cara Pembayaran:</h3>
                <ol>
                    <li><strong>BRI Mobile/Internet Banking:</strong>
                        <br>Pilih "Pembayaran" → "BRIVA" → Masukkan nomor VA di atas
                    </li>
                    <li><strong>ATM BRI:</strong>
                        <br>Pilih "Pembayaran" → "BRIVA" → Masukkan nomor VA
                    </li>
                    <li><strong>Mobile Banking Bank Lain:</strong>
                        <br>Pilih "Transfer" → "BRI Virtual Account" → Masukkan nomor VA
                    </li>
                    <li><strong>Teller BRI:</strong>
                        <br>Beri tahu teller: "Bayar BRIVA" dan sebutkan nomor VA
                    </li>
                </ol>
                <p style="margin-top: 10px; font-style: italic;">
                    ⚠️ <strong>Penting:</strong> Transfer tepat sesuai jumlah di atas. Sistem akan otomatis memverifikasi pembayaran Anda.
                </p>
            </div>
            
            <!-- <========== TOMBOL AKSI ==========> -->
            <div class="action-buttons">
                <a href="../index.php" class="btn btn-secondary">
                     Kembali ke Menu 
                </a>
            </div>
        </div>
    </div>

    <script>
        // <========== FUNGSI SET WAKTU KEDALUWARSA ==========>
        function setExpiryTime() {
            const now = new Date();
            now.setHours(now.getHours() + 24); // Tambah 24 jam
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('expiryTime').textContent = now.toLocaleDateString('id-ID', options);
        }
        
        // <========== FUNGSI COPY NOMOR VA KE CLIPBOARD ==========>
        function copyVANumber() {
            const vaNumber = '<?= $va_number ?>';
            navigator.clipboard.writeText(vaNumber).then(function() {
                // Tampilkan pesan sukses
                const copySuccess = document.getElementById('copySuccess');
                copySuccess.style.display = 'block';
                setTimeout(() => {
                    copySuccess.style.display = 'none';
                }, 3000);
            }).catch(function(err) {
                alert('Gagal menyalin: ' + err);
            });
        }
        
        // <========== INISIALISASI SAAT DOKUMEN LOAD ==========>
        document.addEventListener('DOMContentLoaded', function() {
            setExpiryTime();
        });
    </script>
</body>
</html>

<!-- <========== STYLING UNTUK TAMPILAN PEMBAYARAN ==========> -->
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    /* <========== STYLE UTAMA BODY ==========> */
    body {
        font-family: 'Arial', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    
    /* <========== CONTAINER KARTU PEMBAYARAN ==========> */
    .payment-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        max-width: 500px;
        width: 100%;
        overflow: hidden;
    }
    
    /* <========== HEADER PEMBAYARAN ==========> */
    .payment-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        padding: 30px;
        text-align: center;
    }
    
    .payment-header h1 {
        font-size: 1.8rem;
        margin-bottom: 10px;
    }
    
    .payment-header .order-code {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    /* <========== KONTEN UTAMA ==========> */
    .payment-content {
        padding: 30px;
    }
    
    /* <========== SECTION NOMOR VA ==========> */
    .va-section {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .va-label {
        font-size: 1rem;
        color: #666;
        margin-bottom: 10px;
    }
    
    .va-number {
        font-size: 2rem;
        font-weight: bold;
        color: #1e3c72;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        border: 2px dashed #1e3c72;
        margin: 15px 0;
        letter-spacing: 2px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .va-number:hover {
        background: #e3f2fd;
        transform: scale(1.02);
    }
    
    /* <========== SECTION JUMLAH PEMBAYARAN ==========> */
    .amount-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        text-align: center;
    }
    
    .amount-label {
        font-size: 1rem;
        color: #666;
        margin-bottom: 5px;
    }
    
    .amount-value {
        font-size: 2rem;
        font-weight: bold;
        color: #28a745;
    }
    
    /* <========== SECTION INSTRUKSI ==========> */
    .instructions {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .instructions h3 {
        color: #856404;
        margin-bottom: 15px;
        font-size: 1.1rem;
    }
    
    .instructions ol {
        padding-left: 20px;
        color: #856404;
    }
    
    .instructions li {
        margin-bottom: 8px;
        line-height: 1.5;
    }
    
    /* <========== INFORMASI BANK ==========> */
    .bank-info {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        margin-bottom: 25px;
        padding: 15px;
        background: #e3f2fd;
        border-radius: 10px;
    }
    
    .bank-logo {
        font-size: 2rem;
    }
    
    .bank-details {
        text-align: left;
    }
    
    .bank-name {
        font-weight: bold;
        color: #1e3c72;
    }
    
    /* <========== TOMBOL AKSI ==========> */
    .action-buttons {
        display: flex;
        gap: 10px;
        flex-direction: column;
    }
    
    .btn {
        padding: 15px 20px;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        text-decoration: none;
        display: block;
    }
    
    .btn-copy {
        background: #17a2b8;
        color: white;
    }
    
    .btn-copy:hover {
        background: #138496;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
    }
    
    /* <========== STATUS INFO ==========> */
    .status-info {
        text-align: center;
        padding: 15px;
        background: #d4edda;
        color: #155724;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid #c3e6cb;
    }
    
    /* <========== PESAN COPY SUKSES ==========> */
    .copy-success {
        text-align: center;
        color: #28a745;
        font-weight: bold;
        margin-top: 10px;
        display: none;
    }
    
    /* <========== RESPONSIVE DESIGN ==========> */
    @media (max-width: 480px) {
        .payment-container {
            margin: 10px;
        }
        
        .payment-content {
            padding: 20px;
        }
        
        .va-number {
            font-size: 1.5rem;
            padding: 15px;
        }
        
        .amount-value {
            font-size: 1.5rem;
        }
    }
</style>