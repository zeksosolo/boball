<?php
// Mulai output buffering
ob_start();
include 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Ambil dan sanitasi data POST
    $id_lapangan = (int)$_POST['id_lapangan'];
    $tanggal_booking = $koneksi->real_escape_string($_POST['tanggal']);
    $jam_mulai = $koneksi->real_escape_string($_POST['jam_mulai']);
    $durasi_jam = (int)$_POST['durasi'];
    $total_biaya = (float)$_POST['total_biaya_hidden'];
    $nama_lengkap = $koneksi->real_escape_string($_POST['nama_lengkap']);
    $nomor_telepon = $koneksi->real_escape_string($_POST['nomor_telepon']);
    $metode_pembayaran = $koneksi->real_escape_string($_POST['metode_pembayaran']);

    // --- 2. CEK TABRAKAN JADWAL (CRITICAL SERVER-SIDE CHECK) ---
    // Hitung waktu selesai booking yang diajukan
    $waktu_mulai_ts = strtotime($tanggal_booking . ' ' . $jam_mulai);
    $waktu_selesai_ts = strtotime("+" . $durasi_jam . " hours", $waktu_mulai_ts);
    
    // Konversi kembali ke format TIME
    $jam_selesai = date('H:i:s', $waktu_selesai_ts); 
    
    // Jam mulai yang diajukan
    $jam_mulai_db = date('H:i:s', $waktu_mulai_ts);

    // Kueri untuk mencari booking AKTIF yang bertabrakan dengan slot yang diajukan:
    // Booking dianggap aktif jika statusnya SUKSES, atau PENDING/MENUNGGU_VERIFIKASI yang belum expired.
    $query_collision = $koneksi->prepare("
        SELECT id_booking FROM booking
        WHERE id_lapangan = ? 
        AND tanggal_booking = ? 
        AND (status_pembayaran = 'SUKSES' OR (status_pembayaran IN ('PENDING', 'MENUNGGU_VERIFIKASI') AND waktu_pembayaran_expired > NOW()))
        AND (
            -- Kondisi Tabrakan: Booking yang sudah ada dimulai di antara waktu mulai dan selesai kita
            (jam_mulai >= ? AND jam_mulai < ?)
            -- ATAU Booking yang sudah ada berakhir di antara waktu mulai dan selesai kita (menumpang tindih)
            OR (ADDTIME(jam_mulai, SEC_TO_TIME(durasi_jam * 3600)) > ? AND jam_mulai < ?)
            -- ATAU Booking yang sudah ada MENGUNCI SELURUH interval waktu kita (melingkupi)
            OR (jam_mulai <= ? AND ADDTIME(jam_mulai, SEC_TO_TIME(durasi_jam * 3600)) >= ?)
        )
    ");
    // Parameter yang digunakan berulang kali: jam_mulai_db, jam_selesai, jam_mulai_db, jam_selesai, jam_mulai_db, jam_selesai
    $query_collision->bind_param(
        "isssssss", 
        $id_lapangan, $tanggal_booking, 
        $jam_mulai_db, $jam_selesai, 
        $jam_mulai_db, $jam_selesai,
        $jam_mulai_db, $jam_selesai
    );
    $query_collision->execute();
    $result_collision = $query_collision->get_result();

    if ($result_collision->num_rows > 0) {
        // Tabrakan ditemukan! Hentikan proses.
        ob_end_clean();
        die("<script>alert('❌ Gagal Booking! Jadwal yang Anda pilih (Lapangan $id_lapangan, $tanggal_booking $jam_mulai) bertabrakan dengan pemesanan aktif lain. Silakan pilih slot lain.'); window.location.href='index.php';</script>");
    }
    // --- AKHIR CEK TABRAKAN ---


    // 3. Proses Booking (Jika tidak ada tabrakan)
    $waktu_booking_dibuat = date('Y-m-d H:i:s');
    
    // PERBAIKAN DI SINI: Set expired 5 menit dari sekarang
    $waktu_expired = date('Y-m-d H:i:s', strtotime('+5 minutes')); 
    
    $status_awal = 'PENDING';

    $sql = "INSERT INTO booking (id_lapangan, tanggal_booking, jam_mulai, durasi_jam, total_biaya, nama_lengkap, nomor_telepon, metode_pembayaran, status_pembayaran, waktu_booking_dibuat, waktu_pembayaran_expired) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("isssissssss", 
        $id_lapangan, $tanggal_booking, $jam_mulai_db, $durasi_jam, $total_biaya, 
        $nama_lengkap, $nomor_telepon, $metode_pembayaran, $status_awal, $waktu_booking_dibuat, $waktu_expired);

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;
        $stmt->close();
        $koneksi->close();
        
        // Redirect ke halaman konfirmasi
        ob_end_clean();
        header("Location: konfirmasi_booking.php?id=" . $last_id);
        exit();
    } else {
        $stmt->close();
        $koneksi->close();
        ob_end_clean();
        die("<script>alert('Error saat menyimpan data booking: " . $koneksi->error . "'); window.history.back();</script>");
    }

} else {
    // Jika diakses tanpa POST request
    ob_end_clean();
    header("Location: index.php");
    exit();
}
?>