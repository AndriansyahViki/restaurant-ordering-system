<?php
include "../../config.php";
$conn = getConnection(); 

// Cek role user
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../../login/login.php');
    exit;
}

$order_id = $_GET['id'];
$status = $_GET['status'];

mysqli_query($conn, "
    UPDATE orders SET status='$status' WHERE order_id=$order_id
");

header("Location: order_detail.php?id=$order_id");
exit;