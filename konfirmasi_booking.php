<?php
include 'koneksi.php';

$id_booking = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_booking == 0) {
    header("Location: index.php");
    exit();
}

// Ambil detail booking BoBall
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
    echo "Data booking BoBall tidak ditemukan.";
    exit();
}

// Konversi waktu expired ke timestamp JavaScript (milidetik)
$expired_time_js = strtotime($booking['waktu_pembayaran_expired']) * 1000;
$waktu_expired_tampil = date('H:i:s, d M Y', strtotime($booking['waktu_pembayaran_expired']));

// Ambil Total Biaya dari Database dan format
$total_biaya_format = number_format($booking['total_biaya'], 0, ',', '.');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Booking BoBall</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-lg">
            <div class="card-header bg-warning text-dark text-center">
                <h3>⏳ TUNGGU PEMBAYARAN - Batas Waktu: <?php echo $waktu_expired_tampil; ?></h3>
            </div>
            <div class="card-body">
                <div class="alert alert-danger text-center" id="countdownTimer">
                    Waktu tersisa: <span class="h2" id="timerDisplay">Menghitung...</span>
                </div>
                
                <hr>
                
                <h4 class="text-center">Instruksi Pembayaran</h4>

                <?php if ($booking['metode_pembayaran'] == 'QRIS'): ?>
                    <div class="alert alert-info text-center">
                        <p class="fw-bold">Metode Pembayaran: QRIS</p>
                        <p>Total yang harus dibayar: <strong class="text-success h5">Rp <?php echo $total_biaya_format; ?></strong></p>
                        
                        <div class="my-3 border p-3 d-inline-block bg-white shadow-sm">
                            <img src="img/qris_placeholder.png" alt="QR Code Pembayaran" style="width: 250px; height: 250px; display: block;">
                        </div>
                    </div>

                <?php elseif ($booking['metode_pembayaran'] == 'Transfer Bank'): ?>
                    <div class="alert alert-info">
                        <p class="fw-bold">Metode Pembayaran: Transfer Bank</p>
                        <p>Total yang harus dibayar: <strong class="text-success h5">Rp <?php echo $total_biaya_format; ?></strong></p>
                        
                        <ul class="list-group">
                            <li class="list-group-item">Nomor Rekening Tujuan: 1560021073301</li>
                            <li class="list-group-item">Bank Tujuan: Mandiri</li>
                            <li class="list-group-item">Nama Pemilik Rekening: BoBall</li>
                            <li class="list-group-item">Pastikan jumlah transfer sama persis: Rp <?php echo $total_biaya_format; ?>**</li>
                        </ul>
                    </div>

                <?php endif; ?>

                <hr>
                
                <h4>Detail Pesanan BoBall Anda (ID: #<?php echo $booking['id_booking']; ?>)</h4>
                <table class="table table-bordered">
                    <tr><td><strong>Nama Pemesan</strong></td><td><?php echo $booking['nama_lengkap']; ?></td></tr>
                    <tr><td><strong>Lapangan</strong></td><td><?php echo $booking['nama_lapangan'] . " (" . $booking['jenis_lapangan'] . ")"; ?></td></tr>
                    <tr><td><strong>Tanggal/Jam Main</strong></td><td><?php echo date('d M Y', strtotime($booking['tanggal_booking'])) . " / " . $booking['jam_mulai'] . " (Durasi: " . $booking['durasi_jam'] . " Jam)"; ?></td></tr>
                    <tr><td class="bg-light"><strong>TOTAL BAYAR</strong></td><td class="bg-light h4 text-success">Rp <?php echo $total_biaya_format; ?></td></tr>
                </table>

                <p class="text-center">
                    <a href="unggah_bukti.php?id=<?php echo $booking['id_booking']; ?>" class="btn btn-primary btn-lg mt-3 w-75">
                        Unggah Bukti Pembayaran
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        const expiredTime = <?php echo $expired_time_js; ?>;
        const timerDisplay = document.getElementById('timerDisplay');
        const countdownDiv = document.getElementById('countdownTimer');
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = expiredTime - now;
            
            // Hitungan Menit dan Detik
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            if (distance < 0) {
                clearInterval(interval);
                timerDisplay.textContent = "GAGAL! Waktu Habis";
                countdownDiv.className = 'alert alert-danger text-center';
                alert("Waktu pembayaran telah habis. Booking GAGAL. Harap buat booking baru.");
            } else {
                timerDisplay.textContent = `${minutes} menit ${seconds} detik`;
            }
        }

        const interval = setInterval(updateCountdown, 1000);
        updateCountdown(); 
    </script>
</body>
</html>