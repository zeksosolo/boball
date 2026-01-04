<?php
session_start();
include 'koneksi.php';

// Proteksi User
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'user') {
    header('Location: login.php');
    exit();
}

$default_date = date('Y-m-d');

// Ambil data user dummy (perlu diisi secara manual karena tidak ada tabel user)
$nama_user_default = $_SESSION['user'];
$nomor_telepon_user_default = ""; // Dummy phone number for user123

// =========================================================================
// 1. FITUR: Ambil booking PENDING yang BELUM expired milik user ini
// =========================================================================
$query_pending = $koneksi->prepare("
    SELECT b.id_booking, b.tanggal_booking, b.jam_mulai, b.durasi_jam, b.total_biaya, b.waktu_pembayaran_expired, l.nama_lapangan
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    WHERE b.nama_lengkap = ? 
    AND b.status_pembayaran = 'PENDING' 
    AND b.waktu_pembayaran_expired > NOW()
    ORDER BY b.waktu_booking_dibuat DESC
");
$query_pending->bind_param("s", $nama_user_default);
$query_pending->execute();
$result_pending = $query_pending->get_result();
$pending_bookings = $result_pending->fetch_all(MYSQLI_ASSOC);
$query_pending->close();


// =========================================================================
// 2. FITUR BARU: Ambil SEMUA riwayat booking (sukses, gagal, dll.)
// =========================================================================
$query_history = $koneksi->prepare("
    SELECT b.id_booking, b.tanggal_booking, b.jam_mulai, b.durasi_jam, b.total_biaya, b.status_pembayaran, l.nama_lapangan
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    WHERE b.nama_lengkap = ? 
    ORDER BY b.waktu_booking_dibuat DESC
");
$query_history->bind_param("s", $nama_user_default);
$query_history->execute();
$result_history = $query_history->get_result();
$history_bookings = $result_history->fetch_all(MYSQLI_ASSOC);
$query_history->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengguna BoBall</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css"> 
    <style>
        .slot-kosong { background-color: #d1e7dd; cursor: pointer; }
        .slot-booked { background-color: #f8d7da; cursor: default; }
        .slot-berlalu { background-color: #e2e3e5; cursor: default; }
        .slot-kosong:hover { background-color: #a3cfb4; }
        .slot-card { margin-bottom: 10px; border: 1px solid #ccc; padding: 10px; border-radius: 5px; }
        .slot-card.selected { border: 3px solid #007bff; background-color: #e9f0ff; }
        .slot-list { height: 400px; overflow-y: auto; }
        .field-card { height: 100%; }
        .badge-status { white-space: normal; }
    </style>
</head>
<body>
    <header class="header-goball shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="h3">👤 Dashboard Pengguna BoBall</h1>
            <div>
                <p class="mb-0 small text-light">Halo, **<?php echo $nama_user_default; ?>**</p>
                <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
            </div>
        </div>
    </header>

    <div class="container mt-4">

        <?php if (!empty($pending_bookings)): ?>
            <div class="card p-3 mb-4 shadow-sm border-warning">
                <h4 class="text-dark">⚠️ Pesanan Menunggu Pembayaran (<?php echo count($pending_bookings); ?>)</h4>
                <p>Anda memiliki pesanan yang belum diselesaikan. Klik tombol "Lanjutkan Bayar" untuk kembali ke halaman konfirmasi.</p>
                <ul class="list-group">
                    <?php foreach ($pending_bookings as $pb): 
                        // Hitung sisa waktu (untuk tampilan informasi)
                        $expired_ts = strtotime($pb['waktu_pembayaran_expired']);
                        $current_ts = time();
                        $time_left_sec = $expired_ts - $current_ts;
                        $minutes = floor($time_left_sec / 60);
                        $seconds = $time_left_sec % 60;
                        $time_left_display = ($minutes >= 0 && $seconds >= 0) ? "Sisa: {$minutes}m {$seconds}d" : "Waktu Habis";
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>#<?php echo $pb['id_booking']; ?></strong> - Lapangan: <?php echo htmlspecialchars($pb['nama_lapangan']); ?>, Tgl: <?php echo date('d M Y', strtotime($pb['tanggal_booking'])); ?> Jam: <?php echo substr($pb['jam_mulai'], 0, 5); ?> (<?php echo $pb['durasi_jam']; ?> Jam)
                                <br><small class="text-muted">Total: Rp <?php echo number_format($pb['total_biaya'], 0, ',', '.'); ?></small>
                            </div>
                            <a href="konfirmasi_booking.php?id=<?php echo $pb['id_booking']; ?>" class="btn btn-sm btn-warning">
                                Lanjutkan Bayar (<?php echo $time_left_display; ?>)
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="card p-3 mb-4 shadow-sm">
            <h4>Pilih Tanggal Reservasi</h4>
            <label for="tanggal_filter" class="form-label fw-bold">Filter Ketersediaan</label>
            <input type="date" class="form-control" id="tanggal_filter" value="<?php echo $default_date; ?>" 
                   min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
        </div>

        <div id="loading" class="text-center my-5 d-none">
            <div class="spinner-border text-primary" role="status"></div>
            <p>Memuat jadwal ketersediaan...</p>
        </div>
        
        <h4 class="mt-4 mb-3" id="fieldGridTitle">Jadwal Lapangan pada Tanggal <?php echo date('d-m-Y'); ?></h4>
        <div id="jadwalContainer" class="row">
            </div>

        <hr class="my-5">
        <div class="card shadow-lg p-4">
            <h4>Form Pemesanan Cepat</h4>
            <div id="bookingInfo" class="alert alert-info d-none">
                Anda akan memesan **<span id="displayLapName"></span>** pada tanggal **<span id="displayDate"></span>**, pukul **<span id="displayTime"></span>**.
            </div>
            
            <form id="quickBookingForm" action="proses_booking.php" method="POST">
                <input type="hidden" id="form_id_lapangan" name="id_lapangan">
                <input type="hidden" id="form_tanggal" name="tanggal">
                <input type="hidden" id="form_jam_mulai" name="jam_mulai">
                <input type="hidden" id="form_harga_per_jam" value="0">
                
                <h5 class="mt-4">Data Diri Pemesan</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                               value="<?php echo htmlspecialchars($nama_user_default); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nomor_telepon" class="form-label">Nomor Telepon</label>
                       <input type="tel" class="form-control" id="nomor_telepon" name="nomor_telepon"
       value="<?php echo htmlspecialchars($nomor_telepon_user_default); ?>" 
       placeholder="Contoh: 08123456789" required>
                    </div>
                </div>

                <h5 class="mt-2">Detail Pesanan & Pembayaran</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="form_durasi" class="form-label">Durasi (Jam Min. 1, Max. <span id="maxDurasiDisplay">5</span>)</label>
                        <input type="number" class="form-control" id="form_durasi" name="durasi" min="1" max="5" value="1" required disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="form_metode_pembayaran" class="form-label">Metode Pembayaran</label>
                        <select class="form-select" id="form_metode_pembayaran" name="metode_pembayaran" required disabled>
                            <option value="QRIS">QRIS</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Biaya</label>
                        <p class="form-control-plaintext h5 text-success" id="displayTotalBiayaForm">Rp 0</p>
                        <input type="hidden" name="total_biaya_hidden" id="form_total_biaya_hidden">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" id="submitBooking" disabled>
                    ✅ Proses Booking Sekarang
                </button>
                <div id="formWarning" class="alert alert-warning mt-2 mb-0">
                    Pilih slot waktu yang kosong di atas untuk mengaktifkan form pemesanan.
                </div>
            </form>
        </div>
        
        <hr class="my-5">
        <div class="card p-4 shadow-sm">
            <h4 class="mb-3">📜 Riwayat Semua Transaksi Anda</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Lapangan</th>
                            <th>Tanggal/Jam</th>
                            <th>Durasi</th>
                            <th>Biaya</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($history_bookings)): ?>
                            <?php foreach ($history_bookings as $hb): 
                                $status_badge = '';
                                if ($hb['status_pembayaran'] == 'SUKSES') {
                                    $status_badge = 'success';
                                } elseif ($hb['status_pembayaran'] == 'PENDING' || $hb['status_pembayaran'] == 'MENUNGGU_VERIFIKASI') {
                                    $status_badge = 'warning';
                                } else {
                                    $status_badge = 'danger';
                                }
                            ?>
                                <tr>
                                    <td>#<?php echo $hb['id_booking']; ?></td>
                                    <td><?php echo htmlspecialchars($hb['nama_lapangan']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($hb['tanggal_booking'])) . " / " . substr($hb['jam_mulai'], 0, 5); ?></td>
                                    <td><?php echo $hb['durasi_jam']; ?> Jam</td>
                                    <td>Rp <?php echo number_format($hb['total_biaya'], 0, ',', '.'); ?></td>
                                    <td><span class="badge bg-<?php echo $status_badge; ?>"><?php echo $hb['status_pembayaran']; ?></span></td>
                                    <td>
                                        <?php if ($hb['status_pembayaran'] == 'SUKSES'): ?>
                                            <a href="cetak_bukti.php?id=<?php echo $hb['id_booking']; ?>" target="_blank" class="btn btn-sm btn-info text-white">Cetak</a>
                                        <?php elseif ($hb['status_pembayaran'] == 'PENDING'): ?>
                                            <a href="konfirmasi_booking.php?id=<?php echo $hb['id_booking']; ?>" class="btn btn-sm btn-warning">Bayar Ulang/Cek</a>
                                        <?php elseif ($hb['status_pembayaran'] == 'MENUNGGU_VERIFIKASI'): ?>
                                            <a href="ringkasan_verifikasi.php?id=<?php echo $hb['id_booking']; ?>" class="btn btn-sm btn-secondary">Lihat Ringkasan</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center">Anda belum memiliki riwayat transaksi di sistem GoBall.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tanggalFilter = document.getElementById('tanggal_filter');
        const jadwalContainer = document.getElementById('jadwalContainer');
        const loadingDiv = document.getElementById('loading');
        const bookingInfo = document.getElementById('bookingInfo');
        const fieldGridTitle = document.getElementById('fieldGridTitle');
        const submitBookingButton = document.getElementById('submitBooking');
        const formWarning = document.getElementById('formWarning');
        
        // Form Inputs
        const formNamaLengkap = document.getElementById('nama_lengkap');
        const formNomorTelepon = document.getElementById('nomor_telepon');
        const formDurasi = document.getElementById('form_durasi');
        const formMetodeBayar = document.getElementById('form_metode_pembayaran');
        const displayTotalBiayaForm = document.getElementById('displayTotalBiayaForm');
        const maxDurasiDisplay = document.getElementById('maxDurasiDisplay'); 

        let allJadwalData = []; 

        document.addEventListener('DOMContentLoaded', () => {
            fetchJadwal(tanggalFilter.value);
        });

        tanggalFilter.addEventListener('change', (e) => {
            fetchJadwal(e.target.value);
        });
        
        formDurasi.addEventListener('input', calculateTotal);

        function fetchJadwal(tanggal) {
            loadingDiv.classList.remove('d-none');
            jadwalContainer.innerHTML = '';
            fieldGridTitle.textContent = `Jadwal Lapangan pada Tanggal ${new Date(tanggal).toLocaleDateString('id-ID', {day: '2-digit', month: 'long', year: 'numeric'})}`;
            
            resetForm();
            
            fetch(`cek_jadwal.php?tanggal=${tanggal}`)
                .then(response => response.json())
                .then(data => {
                    loadingDiv.classList.add('d-none');
                    allJadwalData = data;
                    renderJadwal(data);
                })
                .catch(error => {
                    loadingDiv.classList.add('d-none');
                    jadwalContainer.innerHTML = `<div class="alert alert-danger">Gagal memuat jadwal: ${error.message}</div>`;
                    console.error('Error fetching jadwal:', error);
                });
        }

        function renderJadwal(data) {
            let html = '';
            
            const sortedData = data.sort((a, b) => {
                if (a.jenis < b.jenis) return -1;
                if (a.jenis > b.jenis) return 1;
                return 0;
            });
            
            sortedData.forEach(lapangan => {
                html += `
                    <div class="col-md-4 mb-4">
                        <div class="card field-card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">${lapangan.nama} (${lapangan.jenis})</h6>
                                <small>Rp ${parseFloat(lapangan.harga).toLocaleString('id-ID')}/jam</small>
                            </div>
                            <div class="card-body slot-list" data-lapangan-id="${lapangan.id}">
                                ${lapangan.slots.map(slot => {
                                    const slotClass = slot.is_booked ? 'slot-booked' : (slot.status_display === 'Slot Berlalu' ? 'slot-berlalu' : 'slot-kosong');
                                    const cursor = slot.is_clickable ? 'pointer' : 'default';
                                    
                                    return `
                                        <div 
                                            class="slot-card d-flex justify-content-between align-items-center ${slotClass}"
                                            data-jam="${slot.jam_raw}"
                                            data-id="${lapangan.id}"
                                            data-nama="${lapangan.nama}"
                                            data-harga="${lapangan.harga}"
                                            data-tanggal="${tanggalFilter.value}"
                                            style="cursor: ${cursor};"
                                            onclick="handleSlotClick(this)"
                                        >
                                            <span class="fw-bold">${slot.jam}</span>
                                            <span class="badge ${slot.is_booked ? 'bg-danger' : (slot.status_display === 'Slot Berlalu' ? 'bg-secondary' : 'bg-success')} badge-status">
                                                ${slot.status_display}
                                            </span>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    </div>
                `;
            });

            jadwalContainer.innerHTML = html;
        }

        function calculateMaxDuration(startTime) {
            const closeHour = 24; 
            const startHour = parseInt(startTime.split(':')[0]);
            let maxDuration = closeHour - startHour;
            return Math.min(15, Math.max(1, maxDuration));
        }

        function handleSlotClick(element) {
            if (!element.classList.contains('slot-kosong')) {
                return;
            }
            
            document.querySelectorAll('.slot-card').forEach(card => card.classList.remove('selected'));
            element.classList.add('selected');

            const id = element.dataset.id;
            const nama = element.dataset.nama;
            const jam_raw = element.dataset.jam;
            const jam = element.querySelector('span:first-child').textContent;
            const harga = parseFloat(element.dataset.harga);
            const tanggal = element.dataset.tanggal;

            const maxDuration = calculateMaxDuration(jam_raw);
            formDurasi.setAttribute('max', maxDuration);
            maxDurasiDisplay.textContent = maxDuration;
            formDurasi.value = Math.min(parseInt(formDurasi.value) || 1, maxDuration);

            document.getElementById('form_id_lapangan').value = id;
            document.getElementById('form_tanggal').value = tanggal;
            document.getElementById('form_jam_mulai').value = jam_raw;
            document.getElementById('form_harga_per_jam').value = harga;
            
            document.getElementById('displayLapName').textContent = nama;
            document.getElementById('displayDate').textContent = new Date(tanggal).toLocaleDateString('id-ID');
            document.getElementById('displayTime').textContent = jam;
            bookingInfo.classList.remove('d-none');
            formWarning.classList.add('d-none');

            formDurasi.disabled = false;
            formMetodeBayar.disabled = false;
            submitBookingButton.disabled = false;
            
            calculateTotal();
        }

        function calculateTotal() {
            const harga = parseFloat(document.getElementById('form_harga_per_jam').value);
            let durasi = parseInt(formDurasi.value);
            
            const maxAllowed = parseInt(formDurasi.getAttribute('max'));
            
            // Validasi nilai durasi
            if (durasi > maxAllowed) {
                durasi = maxAllowed;
                formDurasi.value = durasi;
            } else if (durasi < 1 || isNaN(durasi)) {
                durasi = 1;
                formDurasi.value = durasi;
            }

            if (isNaN(harga)) {
                displayTotalBiayaForm.textContent = 'Rp 0';
                document.getElementById('form_total_biaya_hidden').value = 0;
                return;
            }
            
            const total = harga * durasi;
            
            displayTotalBiayaForm.textContent = `Rp ${total.toLocaleString('id-ID')}`;
            document.getElementById('form_total_biaya_hidden').value = total;
        }

        function resetForm() {
            bookingInfo.classList.add('d-none');
            formWarning.classList.remove('d-none');
            
            formDurasi.disabled = true;
            formMetodeBayar.disabled = true;
            submitBookingButton.disabled = true;
            
            maxDurasiDisplay.textContent = 5;
            formDurasi.setAttribute('max', 5);

            document.getElementById('form_id_lapangan').value = '';
            document.getElementById('form_tanggal').value = '';
            document.getElementById('form_jam_mulai').value = '';
            document.getElementById('form_harga_per_jam').value = 0;
            displayTotalBiayaForm.textContent = 'Rp 0';
            document.getElementById('form_total_biaya_hidden').value = 0;

            document.querySelectorAll('.slot-card').forEach(card => card.classList.remove('selected'));
        }
    </script>
</body>
</html>