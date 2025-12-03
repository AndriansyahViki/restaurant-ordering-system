<?php
session_start();
require_once 'config.php';
$conn = getConnection();

// Ambil menu
$query = "SELECT * FROM menu WHERE stock > 0 ORDER BY category, name";
$result = $conn->query($query);
$menu_items = [];
while ($row = $result->fetch_assoc()) {
    $menu_items[] = $row;
}

// Handle order
$order_success = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $table_number = $_POST['table_number'];
    $payment_method = $_POST['payment_method'];
    $cart = json_decode($_POST['cart_data'], true);

    if (!empty($cart)) {

        $conn->begin_transaction();

        try {
            // Generate order code
            $order_code = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);

            // Insert order
            $stmt = $conn->prepare("INSERT INTO orders (order_code, table_number, user_id, payment_method, status) VALUES (?, ?, 1, ?, 'pending')");
            $stmt->bind_param("sis", $order_code, $table_number, $payment_method);
            $stmt->execute();
            $order_id = $conn->insert_id;

            // Insert item pesanan
            foreach ($cart as $item) {
                $menu_id = $item['menu_id'];
                $quantity = $item['quantity'];
                $price = $item['price'];
                $subtotal = $price * $quantity;

                // Cek stock
                $check = $conn->query("SELECT stock FROM menu WHERE menu_id = $menu_id");
                $current = $check->fetch_assoc();

                if ($current['stock'] < $quantity) {
                    throw new Exception("Stock tidak cukup untuk " . $item['name']);
                }

                // Insert order item
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price, subtotal) 
                                        VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiidd", $order_id, $menu_id, $quantity, $price, $subtotal);
                $stmt->execute();

                // Kurangi stock
                $new_stock = $current['stock'] - $quantity;
                $conn->query("UPDATE menu SET stock = $new_stock WHERE menu_id = $menu_id");

                // History
                $stmt = $conn->prepare("INSERT INTO stock_history (menu_id, previous_stock, stock_out, updated_stock, note)
                                        VALUES (?, ?, ?, ?, ?)");
                $note = "Order #$order_code";
                $stmt->bind_param("iiiis", $menu_id, $current['stock'], $quantity, $new_stock, $note);
                $stmt->execute();
            }

            $conn->commit();
            $order_success = true;

            // Redirect pembayaran
            if ($payment_method === 'qris') {
                header("Location: assets/payment_qris.php?order_id=$order_id");
                exit();
            } elseif ($payment_method === 'kasir') {
                header("Location: assets/payment_kasir.php?order_id=$order_id");
                exit();
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Restaurant - Order System</title>
    <link rel="stylesheet" href="CSS/style.css">
    <!-- <link rel="stylesheet" href="CSS/style2.css"> -->
    <link rel="stylesheet" href="CSS/ToSide.css">
    <link rel="stylesheet" href="CSS/ToSide2.css">
</head>
<body>
    

    <div class="container">

    <div class="header">

        <div class="logo">
            <button id="burgerBtn" class="burger-btn">
                <span class="burger-text">🍔 K-6 Resto</span>
                <div class="burger-line"></div>
                <div class="burger-line"></div>
                <div class="burger-line"></div>
            </button>
        </div>

        

                <div class="header-right">
                <div class="order-badge" onclick="window.location.href='pesan.php'">
                    Untuk Pelangan 😊
                </div>
                    
                <div class="header-notification" onclick="window.location.href='about.php'">
                    🔔
                    <span class="notification-badge">😶‍🌫️</span>
                </div>
            

                <div class="cart-badge" onclick="scrollToCart()">
                    🛒 Cart (<span id="cartCount">0</span>)
                </div>
            </div>

            

    </div> 
</div> 


        <div class="main-content">
            <!-- Menu Section -->
            <div class="menu-section">
                <h2 class="section-title">Menu Tersedia</h2>
                
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterMenu('all')">Semua</button>
                    <button class="filter-btn" onclick="filterMenu('Food')">Makanan</button>
                    <button class="filter-btn" onclick="filterMenu('Drink')">Minuman</button>
                    <button class="filter-btn" onclick="filterMenu('Snack')">Snack</button>
                </div>
                
                <div class="menu-grid">
                    <?php foreach ($menu_items as $item): ?>
                        <div class="menu-card" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                            <span class="category-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                            <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div class="menu-info">
                                <span class="price">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></span>
                                <span class="stock-badge <?php echo $item['stock'] < 10 ? 'stock-low' : ''; ?>">
                                    Stock: <?php echo $item['stock']; ?>
                                </span>
                            </div>
                            <button class="add-btn" onclick='addToCart(<?php echo json_encode([
                                "menu_id" => $item["menu_id"],
                                "name" => $item["name"],
                                "price" => $item["price"],
                                "stock" => $item["stock"]
                            ]); ?>)'>
                                Tambah ke Keranjang
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart Section -->
            <div class="cart-section" id="cartSection">
                <h2 class="section-title">Keranjang Pesanan</h2>
                
                <div class="cart-items" id="cartItems">
                    <div class="empty-cart">
                        <p>Keranjang masih kosong</p>
                        <p>Silakan pilih menu</p>
                    </div>
                </div>

               <div class="cart-total">
                    <div class="total-row">
                        <span>Total:</span>
                        <span id="totalPrice">Rp 0</span>
                    </div>
                    
                    <form method="POST" id="orderForm">
                        <input type="number" name="table_number" class="table-input" 
                            placeholder="Nomor Meja" required min="1">
                        <input type="hidden" name="cart_data" id="cartData">

                        <!-- Opsi Pembayaran -->
                        <div class="payment-options">
                            <label class="pay-option">
                                <input type="radio" name="payment_method" value="kasir" required>
                                <span>Bayar Di Kasir</span>
                            </label>

                            <label class="pay-option">
                                <input type="radio" name="payment_method" value="qris" required>
                                <span>QRIS (Scan QR)</span>
                            </label>
                        </div>
                        
                        <button type="submit" name="place_order" class="checkout-btn" id="checkoutBtn" disabled>
                            Pesan Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!--============= SideBar ================ -->

<?php if(true): ?>
<div id="sidebar" class="sidebar">
    <button id="closeSidebarBtn" class="close-sidebar-btn">
        &larr;
    </button>

    <div class="sidebar-header">
        <h2>Resto K-6</h2>
    </div>

    <ul class="sidebar-menu">
        <li><a href="index.php">🏠 Dashboard</a></li>
        <li><a href="about.php">📋 About US </a></li>
        
        <!-- Untuk semua role yang login -->
        <li><a href="Sidebar/order_delivery.php">📦 Orders</a></li>
      

        <!-- Hanya untuk admin -->
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <!-- <li><a href="stock.php">📦 Stock</a></li>
            <li><a href="admin/admin_orders.php">📈 Laporan Admin</a></li> -->
              <li><a href="Sidebar/admin/admin_orders.php">📊 Laporan Pesanan</a></li>
            <li><a href="Sidebar/laporan/laporan_penjualan.php">💰 Laporan Penjualan</a></li>
        <?php endif; ?>
        
        
        <!-- Hanya untuk cashier -->
        <!-- <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'cashier'): ?>
            <li><a href="cashier/transaksi.php">💵 Transaksi Kasir</a></li>
        <?php endif; ?> -->
    </ul>

  <?php 
$isLoggedIn = (isset($_SESSION['user_id']) || isset($_SESSION['username']));
?>

<?php if(!$isLoggedIn): ?>
    <!-- Tampilan untuk user belum login -->
    <a href="login/login.php" class="sidebar-footer-link">
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">👤</div>
                <div class="user-details">
                    <div class="user-name">Guest User</div>
                    <div class="user-role">
                        <span class="role-badge user">Not Logged In</span>
                    </div>
                    <div class="logout-text">🚪 Klik untuk Login</div>
                </div>
            </div>
        </div>
    </a>
<?php else: ?>
    <!-- Tampilan untuk user sudah login -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo ($_SESSION['role'] == 'admin') ? '👑' : '💼'; ?>
            </div>
            <div class="user-details">
                <div class="user-name">
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                </div>
                <div class="user-role">
                    <span class="role-badge <?php echo $_SESSION['role']; ?>">
                        <?php echo ($_SESSION['role'] == 'admin') ? 'Administrator' : 'user'; ?>
                    </span>
                </div>
                <a href="login/logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php endif; ?>




    <!-- Success/Error Modal -->
    <div class="modal <?php echo $order_success || $error_message ? 'show' : ''; ?>" id="orderModal">
        <div class="modal-content">
            <?php if ($order_success): ?>
                <h2>✅ Pesanan Berhasil!</h2>
                <p>Pesanan Anda telah diterima dan sedang diproses.</p>
                <p>Stock telah diperbarui secara otomatis.</p>
            <?php elseif ($error_message): ?>
                <h2 class="error">❌ Pesanan Gagal</h2>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <button class="close-modal-btn" onclick="closeModal()">Tutup</button>
        </div>
    </div>

    <script src="script.js"></script>
    <!-- <script src="crud.js"></script> -->
    <?php if ($order_success): ?>
    <script>
        setTimeout(() => {
            closeModal();
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>