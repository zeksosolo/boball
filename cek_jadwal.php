<?php
include 'koneksi.php';

// Atur header agar response berupa JSON
header('Content-Type: application/json');

$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Validasi tanggal
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $tanggal)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tanggal tidak valid.']);
    exit();
}

// Query untuk mengambil semua lapangan
$query_lapangan = "
    SELECT l.id_lapangan, l.nama_lapangan, h.jenis_lapangan, h.harga_per_jam
    FROM lapangan l
    JOIN harga h ON l.id_jenis_lapangan = h.id_jenis_lapangan
    ORDER BY h.id_jenis_lapangan, l.id_lapangan
";
$result_lapangan = $koneksi->query($query_lapangan);
$lapangan_list = [];
while ($row = $result_lapangan->fetch_assoc()) {
    $lapangan_list[] = $row;
}

// Jam operasional: 08:00 sampai 23:00 (hanya jam mulai)
$jam_operasional = [];
for ($i = 8; $i < 24; $i++) {
    $jam_operasional[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ":00:00";
}

$jadwal_lapangan = [];

foreach ($lapangan_list as $lapangan) {
    $id_lapangan = $lapangan['id_lapangan'];
    $jadwal_lapangan[$id_lapangan] = [
        'id' => $id_lapangan,
        'nama' => $lapangan['nama_lapangan'],
        'jenis' => $lapangan['jenis_lapangan'],
        'harga' => $lapangan['harga_per_jam'],
        'slots' => []
    ];

    // Query untuk mengambil semua booking AKTIF untuk lapangan dan tanggal ini.
    // Booking aktif: SUKSES, atau PENDING/MENUNGGU_VERIFIKASI yang belum expired (Sistem BoBall).
    $query_booking = $koneksi->prepare("
        SELECT jam_mulai, durasi_jam, status_pembayaran, waktu_pembayaran_expired
        FROM booking
        WHERE id_lapangan = ? AND tanggal_booking = ? 
        AND (status_pembayaran = 'SUKSES' OR (status_pembayaran IN ('PENDING', 'MENUNGGU_VERIFIKASI') AND waktu_pembayaran_expired > NOW()))
    ");
    $query_booking->bind_param("is", $id_lapangan, $tanggal);
    $query_booking->execute();
    $result_booking = $query_booking->get_result();
    $bookings = $result_booking->fetch_all(MYSQLI_ASSOC);

    $booked_slots = [];
    foreach ($bookings as $booking) {
        $start_time_ts = strtotime($tanggal . ' ' . $booking['jam_mulai']);
        $end_time_ts = strtotime("+" . $booking['durasi_jam'] . " hours", $start_time_ts);
        
        for ($i = 0; $i < $booking['durasi_jam']; $i++) {
            $slot_hour = date('H:i:s', strtotime("+" . $i . " hours", $start_time_ts));
            
            // Hitung waktu selesai slot ini
            $current_slot_end_ts = strtotime("+1 hour", strtotime($slot_hour));
            
            // Tentukan status berdasarkan waktu saat ini
            $status = $booking['status_pembayaran'];
            $waktu_sekarang_ts = time();
            
            $keterangan_status = '';
            
            if ($waktu_sekarang_ts >= $current_slot_end_ts && $tanggal == date('Y-m-d')) {
                // Slot sudah lewat
                $keterangan_status = 'Selesai';
            } elseif ($waktu_sekarang_ts >= $start_time_ts && $waktu_sekarang_ts < $end_time_ts && $tanggal == date('Y-m-d')) {
                 // Slot sedang berjalan
                 $keterangan_status = "Sedang Berlangsung (Selesai " . date('H:i', $end_time_ts) . ")";
            } else {
                // Slot di masa depan
                $keterangan_status = "Dibooking ({$status}) - Selesai " . date('H:i', $end_time_ts);
            }
            
            $booked_slots[$slot_hour] = [
                'status_display' => $keterangan_status,
                'status_code' => $status,
                'is_active_booking' => true,
            ];
        }
    }

    // Gabungkan slot operasional dengan data booking BoBall
    foreach ($jam_operasional as $jam) {
        $slot_data = [
            'jam' => date('H:i', strtotime($jam)),
            'jam_raw' => $jam,
            'is_booked' => false,
            'status_display' => 'Kosong',
            'is_clickable' => true
        ];

        // Cek apakah slot sudah dibooking
        if (array_key_exists($jam, $booked_slots)) {
            $slot_data['is_booked'] = true;
            $slot_data['status_display'] = $booked_slots[$jam]['status_display'];
            $slot_data['is_clickable'] = false;
        }

        // Cek apakah slot sudah lewat jika hari ini
        if ($tanggal == date('Y-m-d') && strtotime($jam) < time()) {
            $slot_data['status_display'] = 'Slot Berlalu';
            $slot_data['is_clickable'] = false;
        }

        $jadwal_lapangan[$id_lapangan]['slots'][] = $slot_data;
    }
}

// Konversi array asosiatif menjadi array terindeks untuk output sistem BoBall
echo json_encode(array_values($jadwal_lapangan));
?>