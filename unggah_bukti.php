<?php
include 'koneksi.php';

$id_booking = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_booking == 0) {
    header("Location: index.php");
    exit();
}

// Cek apakah booking valid dan status masih PENDING
// Kita juga perlu mengambil detail nama pemesan dan nomor telepon untuk ditampilkan
$query = "
    SELECT id_booking, status_pembayaran, nama_lengkap, nomor_telepon
    FROM booking 
    WHERE id_booking = ?
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_booking);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking || $booking['status_pembayaran'] != 'PENDING') {
    die("Booking tidak valid atau sudah diverifikasi/gagal. Status saat ini: " . ($booking ? $booking['status_pembayaran'] : 'Tidak Ditemukan'));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Unggah Bukti Pembayaran BoBall</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h4>Unggah Bukti Pembayaran #<?php echo $id_booking; ?></h4>
            </div>
            <div class="card-body">
                <!-- Tambahkan detail pemesan agar user yakin -->
                <p class="mb-3">
                    <strong>Pemesan:</strong> <?php echo htmlspecialchars($booking['nama_lengkap']); ?><br>
                    <strong>Nomor Telp:</strong> <?php echo htmlspecialchars($booking['nomor_telepon']); ?>
                </p>
                <p>Harap unggah bukti transfer/QRIS Anda dalam format JPG, JPEG, atau PNG.</p>

                <!-- KOREKSI KRITIS DI SINI: enctype="multipart/form-data" HARUS ADA -->
                <form action="proses_unggah.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_booking" value="<?php echo $id_booking; ?>">
                    
                    <div class="mb-3">
                        <label for="bukti" class="form-label">Pilih File Bukti Pembayaran</label>
                        <input class="form-control" type="file" id="bukti" name="bukti" accept=".jpg, .jpeg, .png" required>
                    </div>

                    <button type="submit" name="submit_upload" class="btn btn-success w-100">
                        Kirim Bukti & Selesaikan Proses
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>