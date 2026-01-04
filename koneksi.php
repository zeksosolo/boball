<?php
$host = "localhost";
$user = "apli5300_boball"; // Isi sesuai u: di foto
$pass = "xKE~~EdMUy0xQ_^0"; // Isi sesuai p: di foto DB
$db   = "apli5300_boball"; // Isi sesuai db: di foto

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi BoBall Gagal: " . mysqli_connect_error());
}
// Set timezone (penting untuk perhitungan waktu kedaluwarsa)
date_default_timezone_set('Asia/Jakarta'); 
?>