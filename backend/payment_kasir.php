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
    <title>Bayar di Kasir - K-6 Resto</title>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/payment.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍔 K-6 Resto</h1>
            <a href="../index.php" class="back-btn">← Kembali ke Menu</a>
        </div>

        <div class="payment-container">
            <h1>💰 Bayar di Kasir</h1>
            
            <div class="order-info">
                <h3>📋 Detail Pesanan</h3>
                <p><strong>Kode Order:</strong> <?php echo htmlspecialchars($order['order_code']); ?></p>
                <p><strong>Nomor Meja:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                <p><strong>Total Pembayaran:</strong> <span style="color: #d63384; font-weight: bold;">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span></p>
            </div>
            
            <div class="kasir-info">
                <div class="icon-large">💳</div>
                <h3>Tunjukkan Kode Berikut ke Kasir</h3>
                <div class="payment-code" id="paymentCode">
                    <?php echo generatePaymentCode($order_id); ?>
                </div>
                <button class="copy-btn" onclick="copyPaymentCode()">📋 Salin Kode</button>
                <p style="margin-top: 15px; color: #666;">Tunjukkan kode di atas kepada kasir untuk melakukan pembayaran</p>
            </div>
            
            <div class="payment-instructions">
                <h3>📝 Instruksi Pembayaran:</h3>
                <ol>
                    <li><strong>Tunggu pesanan Anda selesai dimasak</strong></li>
                    <li><strong>Datang ke kasir dengan membawa kode pembayaran</strong></li>
                    <li><strong>Tunjukkan kode di atas kepada kasir</strong></li>
                    <li><strong>Lakukan pembayaran dengan tunai atau kartu</strong></li>
                    <li><strong>Tunggu konfirmasi dari kasir</strong></li>
                    <li><strong>Ambil pesanan Anda setelah pembayaran selesai</strong></li>
                </ol>
            </div>
            
            <div class="action-buttons">
                <!-- <button class="btn btn-primary" onclick="simulatePayment()">✅ Simulasikan Pembayaran Berhasil</button> -->
                <a href="../index.php" class="btn btn-secondary">Kembali Ke menu</a>
                <!-- <a href="../my_orders.php" class="btn btn-secondary">📋 Lihat Pesanan Saya</a> -->
            </div>
        </div>
    </div>

    <script>
        function copyPaymentCode() {
            const paymentCode = document.getElementById('paymentCode').textContent;
            navigator.clipboard.writeText(paymentCode).then(function() {
                alert('✅ Kode pembayaran berhasil disalin: ' + paymentCode);
            }, function(err) {
                // Fallback untuk browser lama
                const textArea = document.createElement('textarea');
                textArea.value = paymentCode;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✅ Kode pembayaran berhasil disalin: ' + paymentCode);
            });
        }

        function simulatePayment() {
            if (confirm('Apakah Anda ingin mensimulasikan pembayaran berhasil di kasir?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '🔄 Memproses...';
                button.disabled = true;
                
                // Update status order di database - gunakan 'completed' sesuai enum
                fetch('../update_payment_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: <?php echo $order_id; ?>,
                        status: 'completed'
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('✅ Pembayaran berhasil! Pesanan Anda telah selesai.');
                        // window.location.href = '../my_orders.php';
                    } else {
                        throw new Error(data.error || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Terjadi kesalahan: ' + error.message + '\n\nSilakan coba lagi atau hubungi staff.');
                    // Reset button
                    button.textContent = originalText;
                    button.disabled = false;
                });
            }
        }

        // Auto focus on page load untuk memudahkan copy
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('paymentCode').style.backgroundColor = '#fff3cd';
        });
    </script>
</body>
</html>

<?php
function generatePaymentCode($order_id) {
    // Format: KASIR- + order_code (dari database)
    // Kita akan ambil order_code dari database atau generate sederhana
    return 'KASIR-' . str_pad($order_id, 6, '0', STR_PAD_LEFT) . '-' . date('His');
}
?>