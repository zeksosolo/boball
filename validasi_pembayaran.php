<?php
session_start();
include 'koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header('Location: login.php');
    exit();
}

$id_booking = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_booking == 0) {
    die("ID Booking tidak valid.");
}

$query = "
    SELECT b.*, l.nama_lapangan, h.jenis_lapangan 
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    JOIN harga h ON l.id_jenis_lapangan = h.id_jenis_lapangan
    WHERE b.id_booking = ? AND b.status_pembayaran IN ('MENUNGGU_VERIFIKASI')
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_booking);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Data booking tidak ditemukan, sudah diverifikasi, atau statusnya bukan 'MENUNGGU_VERIFIKASI'.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Validasi Pembayaran Admin #<?php echo $id_booking; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-lg">
            <div class="card-header bg-danger text-white text-center">
                <h3>ADMIN: Validasi Pembayaran #<?php echo $id_booking; ?></h3>
            </div>
            <div class="card-body">
                <div class="alert alert-warning text-center">
                    <h5>Bukti telah diunggah oleh pelanggan, harap cek dan validasi.</h5>
                </div>
                
                <h4>Detail Pesanan</h4>
                <table class="table table-bordered">
                    <tr><th>Nama Pemesan</th><td><?php echo $booking['nama_lengkap']; ?></td></tr>
                    <tr><th>Lapangan</th><td><?php echo $booking['nama_lapangan'] . " (" . $booking['jenis_lapangan'] . ")"; ?></td></tr>
                    <tr><th>Waktu Main</th><td><?php echo date('d M Y', strtotime($booking['tanggal_booking'])) . " / " . $booking['jam_mulai'] . " (" . $booking['durasi_jam'] . " Jam)"; ?></td></tr>
                    <tr><td class="bg-light"><strong>TOTAL BAYAR</strong></td><td class="bg-light h4 text-success">Rp <?php echo number_format($booking['total_biaya'], 0, ',', '.'); ?></td></tr>
                </table>

                <hr>
                
                <h4>Bukti Transfer (Screenshot Pelanggan)</h4>
                <div class="text-center my-4 border p-3">
                    <?php if ($booking['bukti_pembayaran']): ?>
                        <img src="bukti_transfer/<?php echo htmlspecialchars($booking['bukti_pembayaran']); ?>" 
                             alt="Bukti Pembayaran" style="max-width: 100%; height: auto; border: 1px solid #ccc;">
                        <p class="mt-2 small">Nama file: <?php echo htmlspecialchars($booking['bukti_pembayaran']); ?></p>
                    <?php else: ?>
                        <p class="text-danger">Bukti pembayaran tidak ditemukan.</p>
                    <?php endif; ?>
                </div>

                <hr>
                
                <div class="text-center">
                    <a href="proses_validasi.php?id=<?php echo $id_booking; ?>&action=validasi" class="btn btn-success btn-lg mx-2">
                        ✅ VALIDASI & KONFIRMASI (SUKSES)
                    </a>
                    <a href="proses_validasi.php?id=<?php echo $id_booking; ?>&action=gagal" class="btn btn-danger btn-lg mx-2">
                        ❌ BATALKAN BOOKING (GAGAL)
                    </a>
                </div>
                <p class="text-center mt-3 small text-muted">Aksi ini akan mengubah status di database.</p>

            </div>
        </div>
    </div>
</body>
</html>