<?php
session_start();
include 'koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header('Location: login.php');
    exit();
}

$id_booking = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id_booking == 0 || !in_array($action, ['validasi', 'gagal'])) {
    header('Location: dashboard.php');
    exit();
}

$new_status = ($action == 'validasi') ? 'SUKSES' : 'GAGAL';

// Query update status
$sql = "UPDATE booking SET status_pembayaran = ? WHERE id_booking = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("si", $new_status, $id_booking);

if ($stmt->execute()) {
    // Redirect kembali ke dashboard atau ke ringkasan
    header("Location: dashboard.php?message=Booking%20%23{$id_booking}%20berhasil%20diupdate%20menjadi%20{$new_status}.");
    exit();
} else {
    die("Gagal mengupdate status booking: " . $stmt->error);
}
?>