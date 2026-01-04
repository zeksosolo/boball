<?php
include 'koneksi.php';

$id_booking = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_booking == 0) {
    die("ID Booking BoBall tidak valid.");
}

// Ambil detail booking
$query = "
    SELECT b.*, l.nama_lapangan, h.jenis_lapangan, h.harga_per_jam 
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    JOIN harga h ON l.id_jenis_lapangan = h.id_jenis_lapangan
    WHERE b.id_booking = ?
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_booking);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Data booking BoBall tidak ditemukan.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Booking BoBall #<?php echo $booking['id_booking']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .bukti { border: 2px solid #333; padding: 20px; width: 600px; margin: 0 auto; }
        h1 { text-align: center; color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table td, table th { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .total { background-color: #f2f2f2; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        @media print {
            .btn-cetak { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="bukti">
        <h1>✅ BUKTI RESERVASI LAPANGAN BOBALL</h1>
        <p style="text-align: right;">Tanggal Cetak: <?php echo date('d/m/Y H:i:s'); ?></p>
        <hr>
        
        <table>
            <tr><th>Nomor Booking</th><td>#<?php echo $booking['id_booking']; ?></td></tr>
            <tr><th>Nama Pemesan</th><td><?php echo $booking['nama_lengkap']; ?></td></tr>
            <tr><th>Nomor Telepon</th><td><?php echo $booking['nomor_telepon']; ?></td></tr>
            <tr><th>Lapangan</th><td><?php echo $booking['nama_lapangan']; ?> (<?php echo $booking['jenis_lapangan']; ?>)</td></tr>
            <tr><th>Tanggal Main</th><td><?php echo date('d M Y', strtotime($booking['tanggal_booking'])); ?></td></tr>
            <tr><th>Waktu Main</th><td><?php echo $booking['jam_mulai'] . " - " . date('H:i', strtotime($booking['jam_mulai'] . ' + ' . $booking['durasi_jam'] . ' hours')); ?></td></tr>
            <tr><th>Durasi</th><td><?php echo $booking['durasi_jam']; ?> Jam</td></tr>
            <tr><th class="total">TOTAL BAYAR</th><td class="total">Rp <?php echo number_format($booking['total_biaya'], 0, ',', '.'); ?></td></tr>
            <tr><th>Status Pembayaran</th><td class="status-pending"><?php echo $booking['status_pembayaran']; ?></td></tr>
        </table>
        
        <p style="text-align: center; margin-top: 30px; font-size: 12px;">
            **CATATAN: Bukti ini belum final hingga status pembayaran SUKSES. Booking akan dibatalkan otomatis jika pembayaran melewati batas waktu.**
        </p>
    </div>
    <div style="text-align: center; margin-top: 10px;" class="btn-cetak">
        <button onclick="window.print()">Cetak Ulang</button>
    </div>
</body>
</html>