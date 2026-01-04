<?php
// Mulai output buffering untuk mencegah masalah header()
ob_start(); 
include 'koneksi.php';

if (isset($_POST['submit_upload'])) {
    $id_booking = (int)$_POST['id_booking'];
    
    // --- Pengaturan Folder Unggahan ---
    $target_dir = "bukti_transfer/"; // Pastikan folder ini ADA dan WRITEABLE
    
    // Cek dan buat folder jika belum ada
    if (!is_dir($target_dir)) {
        // Cek jika mkdir gagal
        if (!mkdir($target_dir, 0777, true)) {
            // Hentikan proses dan tampilkan error
            die("Error: Gagal membuat folder unggahan di server.");
        }
    }
    
    // PENTING: Cek apakah file benar-benar di-upload dan tidak ada error
    if (!isset($_FILES["bukti"]) || $_FILES["bukti"]["error"] != 0) {
        $php_error_code = $_FILES["bukti"]["error"] ?? 'Tidak Ada File';
        // Hentikan proses dan tampilkan kode error PHP internal
        die("Error saat menerima file. Kode Error PHP: " . $php_error_code . ". Cek konfigurasi php.ini Anda (upload_max_filesize). Silakan <a href='unggah_bukti.php?id=$id_booking'>coba lagi</a>.");
    }

    $file_name = basename($_FILES["bukti"]["name"]);
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Nama file unik (ID Booking + Timestamp)
    $unique_filename = $id_booking . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $unique_filename;
    $uploadOk = 1;
    $error_message = "";

    // Cek ekstensi file
    if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
        $error_message = "Maaf, hanya file JPG, JPEG, & PNG yang diperbolehkan.";
        $uploadOk = 0;
    }

    if ($uploadOk == 0) {
        // Jika validasi gagal, tampilkan pesan error
        die("Gagal Unggah: " . $error_message . " Silakan <a href='unggah_bukti.php?id=$id_booking'>coba lagi</a>.");
    } else {
        if (move_uploaded_file($_FILES["bukti"]["tmp_name"], $target_file)) {
            
            // PERBAIKAN KRITIS DI SINI:
            // Status diubah menjadi 'MENUNGGU_VERIFIKASI' agar Admin bisa meninjau.
            $sql = "UPDATE booking SET bukti_pembayaran = ?, status_pembayaran = 'MENUNGGU_VERIFIKASI' WHERE id_booking = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("si", $unique_filename, $id_booking);
            
            if ($stmt->execute()) {
                // Berhasil update, arahkan ke ringkasan verifikasi
                ob_end_clean(); 
                header("Location: ringkasan_verifikasi.php?id=" . $id_booking);
                exit();
            } else {
                // Jika DB gagal update, hapus file yang baru diupload
                unlink($target_file); 
                die("Error saat update database: " . $stmt->error . " Silakan <a href='unggah_bukti.php?id=$id_booking'>coba lagi</a>.");
            }
            $stmt->close();
            
        } else {
            // Kegagalan saat memindahkan file (Izin folder atau masalah server)
            // Tambahkan logging untuk mengetahui path yang dicoba
            error_log("Gagal memindahkan file dari " . $_FILES["bukti"]["tmp_name"] . " ke " . $target_file);
            die("Maaf, terjadi error saat mengunggah file. KEMUNGKINAN BESAR MASALAH IZIN FOLDER 777. Cek log server untuk detailnya.");
        }
    }
    
} else {
    // Jika diakses tanpa POST, redirect ke index
    header("Location: index.php");
    ob_end_flush();
    exit();
}
?>