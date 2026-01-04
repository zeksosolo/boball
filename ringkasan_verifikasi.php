<?php
include 'koneksi.php';

$id_booking = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_booking == 0) {
    header("Location: index.php");
    exit();
}

// Ambil detail booking GoBall
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
    die("Data booking tidak ditemukan.");
}

// Catatan: Status di sini bisa SUKSES atau MENUNGGU_VERIFIKASI
$status_pembayaran_text = $booking['status_pembayaran'];
$alert_class = ($status_pembayaran_text == 'SUKSES') ? 'alert-success' : 'alert-warning';

// Tambahkan logika untuk menentukan kemana tombol 'Kembali' akan diarahkan
// Jika user login, arahkan ke user_dashboard.php, jika tidak, ke index.php
session_start();
$redirect_url = (isset($_SESSION['level']) && $_SESSION['level'] == 'user') ? 'user_dashboard.php' : 'index.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ringkasan Pesanan BoBall #<?php echo $booking['id_booking']; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white text-center">
                <h2>Ringkasan Pesanan BoBall</h2>
            </div>
            <div class="card-body">
                <div class="alert <?php echo $alert_class; ?> text-center">
                    <?php if ($status_pembayaran_text == 'SUKSES'): ?>
                        <h4 class="mb-0">✅ PEMBAYARAN BERHASIL DIVERIFIKASI!</h4>
                        <p class="mb-0">Pesanan Anda telah dikonfirmasi dan siap digunakan.</p>
                    <?php else: ?>
                        <h4 class="mb-0">⏳ BUKTI TERKIRIM, MENUNGGU VERIFIKASI ADMIN.</h4>
                        <p class="mb-0">Pesanan Anda akan segera diproses. Silakan cetak bukti ini.</p>
                    <?php endif; ?>
                </div>

                <h4>Detail Pesanan #<?php echo $booking['id_booking']; ?></h4>
                <table class="table table-bordered">
                    <tr><th>Nama Pemesan</th><td><?php echo $booking['nama_lengkap']; ?></td></tr>
                    <tr><th>Nomor Telepon</th><td><?php echo $booking['nomor_telepon']; ?></td></tr>
                    <tr><th>Lapangan</th><td><?php echo $booking['nama_lapangan'] . " (" . $booking['jenis_lapangan'] . ")"; ?></td></tr>
                    <tr><th>Tanggal/Jam Main</th><td><?php echo date('d M Y', strtotime($booking['tanggal_booking'])) . " / " . $booking['jam_mulai'] . " (Durasi: " . $booking['durasi_jam'] . " Jam)"; ?></td></tr>
                    <tr><th>Metode Bayar</th><td><?php echo $booking['metode_pembayaran']; ?></td></tr>
                    <tr><td class="bg-light"><strong>TOTAL BAYAR</strong></td><td class="bg-light h4 text-success">Rp <?php echo number_format($booking['total_biaya'], 0, ',', '.'); ?></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-<?php echo ($status_pembayaran_text == 'SUKSES' ? 'success' : 'warning'); ?>"><?php echo $status_pembayaran_text; ?></span></td></tr>
                </table>

                <div class="text-center mt-4">
                    <a href="cetak_bukti.php?id=<?php echo $booking['id_booking']; ?>" target="_blank" class="btn btn-secondary btn-lg mb-2 w-75">
                        Cetak Bukti Pesanan
                    </a>
                    
                    <a href="<?php echo $redirect_url; ?>" class="btn btn-outline-primary btn-lg w-75">
                        Kembali ke Dashboard
                    </a>
                </div>
                
                <?php if ($status_pembayaran_text != 'SUKSES'): ?>
                    <p class="text-center text-muted small mt-2">Bukti ini akan berlaku penuh setelah status diubah oleh Admin menjadi **SUKSES**.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>