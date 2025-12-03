<?php
session_start();
require_once '../config.php';

if (!isset($_GET['order_id'])) {
    header("Location: ../index.php");
    exit();
}

$order_id = $_GET['order_id'];
$conn = getConnection();

// Get order details dengan GROUP BY
$query = "SELECT o.*, SUM(oi.subtotal) as total_amount 
          FROM orders o 
          LEFT JOIN order_items oi ON o.order_id = oi.order_id 
          WHERE o.order_id = ?
          GROUP BY o.order_id";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: ../index.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran QRIS - K-6 Resto</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/payment.css">
   
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍔 K-6 Resto</h1>
            <a href="index.php" class="back-btn">← Kembali</a>
        </div>

        <div class="payment-container">
            <h1>Pembayaran QRIS</h1>
            
            <div class="order-info">
                <h3>Detail Pesanan</h3>
                <p><strong>Kode Order:</strong> <?php echo htmlspecialchars($order['order_code']); ?></p>
                <p><strong>Nomor Meja:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                <p><strong>Total Pembayaran:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
            </div>
            
            <div class="qris-code">
                <h3>Scan QR Code Berikut</h3>
                <img src="qris_sample.jpg" alt="QRIS Code" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjUwIiBoZWlnaHQ9IjI1MCIgdmlld0JveD0iMCAwIDI1MCAyNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjI1MCIgaGVpZ2h0PSIyNTAiIGZpbGw9IiNmMWYxZjEiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzk5OSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0Ij5RUklTIFBMQUNFSE9MREVSPC90ZXh0Pjwvc3ZnPg=='">
                <p>Scan QR code di atas menggunakan aplikasi e-wallet atau mobile banking Anda</p>
            </div>
            
            <div class="payment-instructions">
                <h3>Instruksi Pembayaran:</h3>
                <ol>
                    <li>Buka aplikasi e-wallet atau mobile banking Anda</li>
                    <li>Pilih fitur scan QRIS</li>
                    <li>Arahkan kamera ke QR code di atas</li>
                    <li>Konfirmasi pembayaran</li>
                    <li>Tunggu notifikasi berhasil</li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <!-- <button class="btn btn-primary" onclick="simulatePayment()">Simulasikan Pembayaran Berhasil</button> -->
                <a href="../index.php" class="btn btn-secondary">Kembali ke Menu</a>
            </div>
        </div>
    </div>

    <script>
//     function simulatePayment() {
//         if (confirm('Apakah Anda ingin mensimulasikan pembayaran berhasil?')) {
//             // Show loading state
//             const button = event.target;
//             const originalText = button.textContent;
//             button.textContent = 'Memproses...';
//             button.disabled = true;
            
//             // Update status order di database - gunakan 'completed' sesuai enum
//             fetch('update_payment_status.php', {
//                 method: 'POST',
//                 headers: {
//                     'Content-Type': 'application/json',
//                 },
//                 body: JSON.stringify({
//                     order_id: <?php echo $order_id; ?>,
//                     status: 'completed'
//                 })
//             })
//             .then(response => {
//                 if (!response.ok) {
//                     throw new Error('Network response was not ok');
//                 }
//                 return response.json();
//             })
//             .then(data => {
//                 if (data.success) {
//                     alert('✅ Pembayaran berhasil! Pesanan Anda akan segera diproses.');
//                     window.location.href = '../my_orders.php';
//                 } else {
//                     throw new Error(data.error || 'Unknown error occurred');
//                 }
//             })
//             .catch(error => {
//                 console.error('Error:', error);
//                 alert('❌ Terjadi kesalahan: ' + error.message + '\n\nSilakan coba lagi atau hubungi staff.');
//                 // Reset button
//                 button.textContent = originalText;
//                 button.disabled = false;
//             });
//         }
//     }
// </script>
</body>
</html>