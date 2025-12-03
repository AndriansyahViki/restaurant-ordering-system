<?php
session_start();
require_once '../config.php';
$conn = getConnection();

// Ambil menu
$menus = $conn->query("SELECT * FROM menu WHERE stock > 0 ORDER BY category, name");

// Buat array keranjang 
if (!isset($_SESSION['delivery_cart'])) {
    $_SESSION['delivery_cart'] = [];
}

// Tambah ke cart
if (isset($_POST['add_to_cart'])) {
    $menu_id = $_POST['menu_id'];
    $name = $_POST['name'];
    $price = $_POST['price'];

    if (!isset($_SESSION['delivery_cart'][$menu_id])) {
        $_SESSION['delivery_cart'][$menu_id] = [
            "menu_id" => $menu_id,
            "name" => $name,
            "price" => $price,
            "qty" => 1
        ];
    } else {
        $_SESSION['delivery_cart'][$menu_id]['qty']++;
    }
}

// Hapus item dari cart
if (isset($_GET['remove'])) {
    $id = $_GET['remove'];
    unset($_SESSION['delivery_cart'][$id]);
    header("Location: order_delivery.php");
    exit;
}



// Proses checkout delivery
$success = false;
$error = "";

if (isset($_POST['place_delivery'])) {

    if (count($_SESSION['delivery_cart']) == 0) {
        $error = "Keranjang masih kosong!";
    } else {
        $name = $_POST['customer_name'];
        $address = $_POST['customer_address'];
        $phone = $_POST['customer_phone'];
        $payment_method = $_POST['payment_method']; // Ambil metode pembayaran

        $conn->begin_transaction();

        try {
            // Buat kode order unik
            $order_code = "DLV-" . date("Ymd") . "-" . rand(1000, 9999);
            
            // Tentukan table_number. Karena ini Delivery, kita gunakan 0 
            // atau angka lain yang valid dan bukan NULL (sesuai error)
            $table_number = 0; 

            // Insert ke orders - PERBAIKAN: Ganti NULL dengan $table_number (nilai 0)
            $stmt = $conn->prepare("
                INSERT INTO orders 
                (order_code, table_number, user_id, status, delivery_name, delivery_address, delivery_phone, payment_method)
                VALUES (?, ?, 1, 'pending', ?, ?, ?, ?)
            ") or die("QUERY ERROR: " . $conn->error);

            // Perhatikan bind_param: s i s s s s
            // order_code (s), table_number (i), name (s), address (s), phone (s), payment_method (s)
            $stmt->bind_param("sissss",
                $order_code,
                $table_number, // <--- PERBAIKAN: Menggunakan $table_number (int)
                $name,
                $address,
                $phone,
                $payment_method
            );

            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert order items
            foreach ($_SESSION['delivery_cart'] as $item) {
                $menu_id = $item['menu_id'];
                $qty = $item['qty'];
                $price = $item['price'];
                $subtotal = $qty * $price;

                // Cek stok
                $s = $conn->query("SELECT stock FROM menu WHERE menu_id = $menu_id");
                $stok = $s->fetch_assoc()['stock'];

                if ($stok < $qty) {
                    throw new Exception("Stok habis untuk " . $item['name']);
                }

                // Insert items
                $stmt = $conn->prepare("
                    INSERT INTO order_items (order_id, menu_id, quantity, price, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiidd", $order_id, $menu_id, $qty, $price, $subtotal);
                $stmt->execute();

                // Update stok
                $new_stock = $stok - $qty;
                $conn->query("UPDATE menu SET stock = $new_stock WHERE menu_id = $menu_id");
            }

            $conn->commit();
            $success = true;
            $_SESSION['delivery_cart'] = []; // kosongkan cart
            
            // PERBAIKAN REDIRECT: Menggunakan order_id sebagai parameter
            if ($payment_method === 'briva') {
                header("Location: ../assets/payment_briva.php?order_id=" . $order_id);
            } else {
                header("Location: ../assets/payment_qris.php?order_id=" . $order_id);
            }
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            // Tampilkan error dari exception
            $error = "Gagal membuat pesanan: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Delivery Order</title>
<link rel="stylesheet" href="../CSS/Order_delivery.css">
<link rel="stylesheet" href="../CSS/style.css">
</head>
<body>

    <form action="../index.php" method="post">
          <button id="close-index" class="close-index-btn" >
        &larr;
    </button>
    </form>

<div class="delivery-container">

    <h1 class="title">🛵 Pemesanan Delivery</h1>
    <p class="subtitle">Pesan makanan & minuman, kami antar sampai rumah!</p>

    <?php if ($success): ?>
        <div class="success-box">
            ✅ Pesanan berhasil dibuat! Kurir segera menuju lokasi Anda.
        </div>
    <?php elseif ($error): ?>
        <div class="error-box">
            ❌ <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="delivery-grid">

        <div class="delivery-menu">
            <h2>📋 Menu</h2>

            <?php while ($m = $menus->fetch_assoc()): ?>
                <form method="POST" class="menu-item">
                    <div>
                        <strong><?= $m['name'] ?></strong>
                        <p>Rp <?= number_format($m['price'],0,',','.') ?></p>
                    </div>
                    <input type="hidden" name="menu_id" value="<?= $m['menu_id'] ?>">
                    <input type="hidden" name="name" value="<?= $m['name'] ?>">
                    <input type="hidden" name="price" value="<?= $m['price'] ?>">
                    <button name="add_to_cart" class="add-btn">+</button>
                </form>
            <?php endwhile; ?>
        </div>


        <div class="delivery-cart">
            <h2>🛒 Keranjang</h2>

            <?php if (count($_SESSION['delivery_cart']) == 0): ?>
                <p class="empty">Keranjang kosong</p>
            <?php else: ?>

                <?php 
                    $total = 0; 
                    foreach ($_SESSION['delivery_cart'] as $item): 
                        $total += $item['qty'] * $item['price'];
                ?>
                    <div class="cart-item">
                        <span><?= $item['name'] ?> (x<?= $item['qty'] ?>)</span>
                        <a href="?remove=<?= $item['menu_id'] ?>" class="remove">Hapus</a>
                    </div>
                <?php endforeach; ?>

                <div class="total-price">
                    Total: <strong>Rp <?= number_format($total,0,',','.') ?></strong>
                </div>

            <?php endif; ?>


            <form method="POST" class="delivery-form">
                <h3>🧍 Data Penerima</h3>

                <input type="text" name="customer_name" placeholder="Nama lengkap" required>
                <input type="text" name="customer_address" placeholder="Alamat lengkap" required>
                <input type="text" name="customer_phone" placeholder="Nomor telepon" required>
                <h3>💳 Metode Pembayaran</h3>

                <label class="pay-option">
                    <input type="radio" name="payment_method" value="briva" required>
                    <span>BRIVA (Virtual Account BRI)</span>
                </label>

                <label class="pay-option">
                    <input type="radio" name="payment_method" value="qris" required>
                    <span>QRIS (Scan QR)</span>
                </label>

                <button name="place_delivery" class="checkout-btn">Buat Pesanan Delivery</button>
            </form>

        </div>

    </div>

</div>

</body>
</html>