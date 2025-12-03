<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'];
    $status = $input['status'];
    
    $conn = getConnection();
    
    // SESUAI DATABASE - status di tabel orders adalah enum("pending","cancelled","completed")
    $allowed_status = ['pending', 'cancelled', 'completed'];
    
    if (!in_array($status, $allowed_status)) {
        // Jika status tidak sesuai, gunakan 'completed' untuk pembayaran berhasil
        $status = 'completed';
    }
    
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $status, $order_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    
    $conn->close();
}
?>