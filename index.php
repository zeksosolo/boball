<?php
session_start(); 
include 'koneksi.php'; 

// =========================================================================
// PERBAIKAN KRITIS DI SINI: Jika user atau admin sudah login, alihkan ke dashboard mereka.
// =========================================================================
if (isset($_SESSION['level'])) {
    if ($_SESSION['level'] == 'admin') {
        header('Location: dashboard.php');
        exit();
    } elseif ($_SESSION['level'] == 'user') {
        header('Location: user_dashboard.php');
        exit();
    }
}
// =========================================================================

if ($koneksi->connect_error) {
    die("Koneksi database Gagal: " . $koneksi->connect_error);
}

// Mendapatkan data lapangan dan harganya untuk ditampilkan
$query_lapangan = "
    SELECT l.id_lapangan, l.nama_lapangan, h.jenis_lapangan, h.harga_per_jam 
    FROM lapangan l
    JOIN harga h ON l.id_jenis_lapangan = h.id_jenis_lapangan
    ORDER BY h.id_jenis_lapangan, l.id_lapangan
";
$result_lapangan = $koneksi->query($query_lapangan);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BoBall - Reservasi Lapangan Olahraga</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <header class="header-goball shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3">⚽ BoBall Reservation System</h1>
                <p>Sistem Reservasi Lapangan Sepak Bola, Badminton, dan Voli</p>
            </div>
            
            <div class="text-end">
                <?php if (isset($_SESSION['level'])): ?>
                    <p class="mb-0 small text-light">Logged as <?php echo $_SESSION['level']; ?></p>
                    <?php if ($_SESSION['level'] == 'admin'): ?>
                        <a href="dashboard.php" class="btn btn-sm btn-warning">Dashboard Admin</a>
                    <?php else: // level user ?>
                        <a href="user_dashboard.php" class="btn btn-sm btn-info text-white">Dashboard Saya</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-light">Login Pengguna/Admin</a>
                <?php endif; ?>
            </div>
            </div>
    </header>

    <div class="container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card shadow-lg">
                    <div class="card-header bg-success text-white">
                        <h4>Pilih Lapangan dan Jadwal</h4>
                    </div>
                    <div class="card-body">
                        <form id="bookingForm" action="proses_booking.php" method="POST">
                            
                            <div class="mb-3">
                                <label for="id_lapangan" class="form-label fw-bold">1. Pilih Lapangan</label>
                                <select class="form-select" id="id_lapangan" name="id_lapangan" required>
                                    <option value="">-- Pilih Lapangan --</option>
                                    <?php while($row = $result_lapangan->fetch_assoc()): ?>
                                        <option 
                                            value="<?php echo $row['id_lapangan']; ?>" 
                                            data-harga="<?php echo $row['harga_per_jam']; ?>"
                                        >
                                            <?php echo "{$row['nama_lapangan']} ({$row['jenis_lapangan']} - Rp " . number_format($row['harga_per_jam'], 0, ',', '.') . "/jam)"; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="tanggal" class="form-label fw-bold">2. Pilih Tanggal</label>
                                <?php $min_date = date('Y-m-d'); $max_date = date('Y-m-d', strtotime('+30 days')); ?>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" 
                                       min="<?php echo $min_date; ?>" max="<?php echo $max_date; ?>" required>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="jam_mulai" class="form-label fw-bold">3. Jam Mulai (08:00 - 23:00)</label>
                                    <select class="form-select" id="jam_mulai" name="jam_mulai" required>
                                        <option value="">-- Jam Mulai --</option>
                                        <?php 
                                        for ($i = 8; $i < 24; $i++) {
                                            $jam_format = str_pad($i, 2, '0', STR_PAD_LEFT) . ":00";
                                            echo "<option value='{$jam_format}'>{$jam_format}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="durasi" class="form-label fw-bold">4. Durasi (Jam Min. 1)</label>
                                    <input type="number" class="form-control" id="durasi" name="durasi" min="1" max="5" value="1" required>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-info w-100 mb-3" id="cekKetersediaan">
                                Cek Ketersediaan Lapangan & Hitung Biaya
                            </button>
                            
                            <div id="statusKetersediaan" class="alert alert-warning d-none text-center fw-bold"></div>

                            <div id="formBiodata" style="display: none;">
                                <hr>
                                <h5 class="mt-4">5. Data Diri & Pembayaran (Tanpa Login)</h5>
                                <div class="mb-3">
                                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nomor_telepon" class="form-label">Nomor Telepon</label>
                                    <input type="tel" class="form-control" id="nomor_telepon" name="nomor_telepon" 
                                           placeholder="Contoh: 08123456789" required>
                                </div>
                                <div class="mb-3">
                                    <label for="metode_pembayaran" class="form-label">6. Metode Pembayaran</label>
                                    <select class="form-select" id="metode_pembayaran" name="metode_pembayaran" required>
                                        <option value="QRIS">QRIS</option>
                                        <option value="Transfer Bank">Transfer Bank</option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-success text-center">
                                    <strong>Total Biaya: </strong> <span id="displayTotalBiaya" class="h4">Rp 0</span>
                                    <input type="hidden" name="total_biaya_hidden" id="total_biaya_hidden">
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    Proses Booking
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('cekKetersediaan').addEventListener('click', function() {
            const id_lapangan = document.getElementById('id_lapangan').value;
            const tanggal = document.getElementById('tanggal').value;
            const jam_mulai = document.getElementById('jam_mulai').value;
            const durasi = parseInt(document.getElementById('durasi').value);
            const statusDiv = document.getElementById('statusKetersediaan');
            const formBiodata = document.getElementById('formBiodata');
            
            const selectLap = document.getElementById('id_lapangan');
            const selectedOption = selectLap.options[selectLap.selectedIndex];
            const hargaPerJam = parseFloat(selectedOption.getAttribute('data-harga'));
            const totalBiaya = hargaPerJam * durasi;
            
            if (!id_lapangan || !tanggal || !jam_mulai || !durasi || isNaN(totalBiaya)) {
                statusDiv.className = 'alert alert-danger text-center fw-bold';
                statusDiv.textContent = '❌ Harap lengkapi semua pilihan di atas.';
                statusDiv.classList.remove('d-none');
                formBiodata.style.display = 'none';
                return;
            }

            // SIMULASI KETERSEDIAAN (NOTE: Cek server yang sebenarnya ada di proses_booking.php)
            statusDiv.className = 'alert alert-success text-center fw-bold';
            statusDiv.textContent = '✅ Lapangan tersedia! Total biaya sudah dihitung.';
            statusDiv.classList.remove('d-none');
            
            document.getElementById('displayTotalBiaya').textContent = `Rp ${totalBiaya.toLocaleString('id-ID')}`;
            document.getElementById('total_biaya_hidden').value = totalBiaya;
            
            formBiodata.style.display = 'block';
        });
    </script>
</body>
</html>