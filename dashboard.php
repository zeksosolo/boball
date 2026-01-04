<?php
session_start();
include 'koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header('Location: login.php');
    exit();
}

// ----------------------------------------------------
// A. DATA UNTUK RANGKUMAN 
// ----------------------------------------------------

// 1. Total Booking Hari Ini (Semua Status)
$date_today = date('Y-m-d');
$query_today = "SELECT COUNT(*) AS total FROM booking WHERE DATE(waktu_booking_dibuat) = '$date_today'";
$result_today = $koneksi->query($query_today)->fetch_assoc();
$total_booking_today = $result_today['total'];

// 2. Total Pendapatan Bulan Ini (Hanya Status SUKSES)
$date_month = date('Y-m');
$query_monthly = "SELECT SUM(total_biaya) AS total FROM booking WHERE DATE_FORMAT(waktu_booking_dibuat, '%Y-%m') = '$date_month' AND status_pembayaran = 'SUKSES'";
$result_monthly = $koneksi->query($query_monthly)->fetch_assoc();
$total_pendapatan_bulan = number_format($result_monthly['total'] ?? 0, 0, ',', '.');

// ----------------------------------------------------
// B. DATA BOOKING AKTIF (REAL-TIME DASHBOARD)
// ----------------------------------------------------

// Menampilkan booking yang statusnya PENDING, MENUNGGU_VERIFIKASI, atau SUKSES dan waktunya belum terlewat
$query_aktif = "
    SELECT b.*, l.nama_lapangan, h.jenis_lapangan, 
            TIMESTAMPDIFF(SECOND, NOW(), b.waktu_pembayaran_expired) AS time_left 
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    JOIN harga h ON l.id_jenis_lapangan = h.id_jenis_lapangan
    WHERE (b.status_pembayaran IN ('PENDING', 'MENUNGGU_VERIFIKASI', 'SUKSES') AND b.tanggal_booking >= CURDATE())
    OR (b.status_pembayaran IN ('PENDING', 'MENUNGGU_VERIFIKASI') AND b.waktu_pembayaran_expired >= NOW())
    ORDER BY b.tanggal_booking ASC, b.jam_mulai ASC
";
$result_aktif = $koneksi->query($query_aktif);

// ----------------------------------------------------
// C. DATA BOOKING MENUNGGU VALIDASI (Hanya status MENUNGGU_VERIFIKASI)
// ----------------------------------------------------
$query_validasi = "
    SELECT b.id_booking, b.nama_lengkap, b.nomor_telepon, b.total_biaya, l.nama_lapangan, b.metode_pembayaran
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    WHERE b.status_pembayaran = 'MENUNGGU_VERIFIKASI' 
    ORDER BY b.waktu_booking_dibuat ASC
";
$result_validasi = $koneksi->query($query_validasi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard BoBall</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <header class="header-goball shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="h3">📊 Admin Dashboard BoBall</h1>
            <div>
                <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <h2 class="mt-4 mb-4">Ringkasan Operasional</h2>
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="card bg-info text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">Booking Hari Ini</h5>
                        <p class="card-text h1"><?php echo $total_booking_today; ?></p>
                        <p class="card-text small">Total booking dibuat hari ini (Semua status)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white shadow">
                    <div class="card-body">
                        <h5 class="card-title">Pendapatan Bersih Bulan Ini</h5>
                        <p class="card-text h1">Rp <?php echo $total_pendapatan_bulan; ?></p>
                        <p class="card-text small">Dari booking berstatus SUKSES</p>
                    </div>
                </div>
            </div>
        </div>
        
        <h2 class="mt-4 mb-3">Booking Butuh Verifikasi Manual (Bukti Terkirim)</h2>
        <div class="alert alert-danger">Verifikasi transaksi di bawah ini untuk memastikan lapangan tersedia/terisi.</div>
        
        <div class="table-responsive mb-5">
            <table class="table table-bordered table-striped">
                <thead class="table-danger">
                    <tr>
                        <th>ID</th>
                        <th>Nama Pemesan</th>
                        <th>Metode</th>
                        <th>Total Biaya</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_validasi->num_rows > 0): ?>
                        <?php while($pending = $result_validasi->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $pending['id_booking']; ?></td>
                                <td><?php echo htmlspecialchars($pending['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($pending['metode_pembayaran']); ?></td>
                                <td>Rp <?php echo number_format($pending['total_biaya'], 0, ',', '.'); ?></td>
                                <td>
                                    <a href="validasi_pembayaran.php?id=<?php echo $pending['id_booking']; ?>" class="btn btn-sm btn-primary">Lihat & Validasi Bukti</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">Tidak ada booking yang menunggu verifikasi bukti pembayaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 class="mt-4 mb-3">Aktivitas Booking & Pembayaran Aktif</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Lap.</th>
                        <th>Tanggal/Waktu</th>
                        <th>Pemesan</th>
                        <th>Status</th>
                        <th>Biaya</th>
                        <th>Batas Waktu</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($booking = $result_aktif->fetch_assoc()): 
                        $badge_color = '';
                        $status = $booking['status_pembayaran'];
                        $action_button = '';

                        if ($status == 'SUKSES') {
                            $badge_color = 'success';
                        } elseif ($status == 'MENUNGGU_VERIFIKASI') {
                            $badge_color = 'primary';
                            $action_button = '<a href="validasi_pembayaran.php?id=' . $booking['id_booking'] . '" class="btn btn-sm btn-warning">Verifikasi</a>';
                        } elseif ($status == 'PENDING') {
                            $badge_color = 'warning';
                        } else {
                            $badge_color = 'secondary';
                        }
                        
                        $is_expired = $booking['time_left'] < 0 && $status == 'PENDING';
                    ?>
                    <tr>
                        <td>#<?php echo $booking['id_booking']; ?></td>
                        <td><?php echo $booking['nama_lapangan']; ?></td>
                        <td><?php echo date('d M', strtotime($booking['tanggal_booking'])) . " " . date('H:i', strtotime($booking['jam_mulai'])) . " (" . $booking['durasi_jam'] . " Jam)"; ?></td>
                        <td><?php echo $booking['nama_lengkap']; ?></td>
                        <td><span class="badge bg-<?php echo $badge_color; ?>"><?php echo $status; ?></span></td>
                        <td>Rp <?php echo number_format($booking['total_biaya'], 0, ',', '.'); ?></td>
                        <td>
                            <?php if ($is_expired): ?>
                                <span class="badge bg-danger">EXPIRED</span>
                            <?php elseif ($status == 'PENDING' || $status == 'MENUNGGU_VERIFIKASI'): ?>
                                <span class="countdown-timer badge bg-warning text-dark" data-time-left="<?php echo $booking['time_left']; ?>"></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo $action_button; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateAllCountdowns() {
            document.querySelectorAll('.countdown-timer').forEach(function(span) {
                let timeLeft = parseInt(span.getAttribute('data-time-left'));
                
                if (timeLeft > 0) {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    span.textContent = minutes + "m " + seconds + "s";
                    span.setAttribute('data-time-left', timeLeft - 1);
                    span.className = 'badge bg-warning text-dark';
                } else {
                    span.textContent = "EXPIRED (DB Pending)";
                    span.className = 'badge bg-danger';
                }
            });
        }

        setInterval(updateAllCountdowns, 1000);
        updateAllCountdowns();
    </script>
</body>
</html>