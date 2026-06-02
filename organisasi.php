<?php
// WAJIB: Memulai sesi
session_start(); 

require 'koneksi.php';

// Proteksi Sesi: Hanya role 'organizer' yang diizinkan mengakses halaman ini
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: index.php");
    exit;
}

$org_id = $_SESSION['user_id'];
$pesan = '';
$tipe_pesan = 'success';

// AUTO-REPAIR: Perbaiki status 'Diterima' menjadi 'Disetujui' agar konsisten
try {
    $pdo->exec("UPDATE applications SET status = 'Disetujui' WHERE status = 'Diterima'");
} catch(PDOException $e) {}

// 1. Proses Tambah Program Kegiatan Baru
if(isset($_POST['add_program'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $location = trim($_POST['location']);
    $date = $_POST['prog_date'];
    $time = $_POST['prog_time'] ?? '10:00';
    $quota = intval($_POST['quota']);
    $category = $_POST['category'] ?? 'Sosial';

    // Proses Upload Foto Sampul
    $image_url = 'https://images.unsplash.com/photo-1593113589914-075990190da4?w=500&q=80'; // Default
    if(isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['image_file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if(in_array(strtolower($ext), $allowed)) {
            $upload_dir = 'uploads/banners/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); 
            
            $new_name = 'banner_org_' . time() . '.' . $ext;
            if(move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $new_name)) {
                $image_url = $upload_dir . $new_name;
            }
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO programs (name, description, location, prog_date, prog_time, quota, organizer_id, category, image_url, rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 5.0)");
        $stmt->execute([$name, $desc, $location, $date, $time, $quota, $org_id, $category, $image_url]);
        $pesan = "Program kegiatan berhasil dipublikasikan dan dapat diakses relawan!";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal mempublikasikan program: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// 2. Proses Perbarui (Edit) Detail Kegiatan
if(isset($_POST['edit_program'])) {
    $prog_id = $_POST['prog_id'];
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $location = trim($_POST['location']);
    $date = $_POST['prog_date'];
    $time = $_POST['prog_time'] ?? '10:00';
    $quota = intval($_POST['quota']);
    $category = $_POST['category'] ?? 'Sosial';

    // Base SQL & Parameters
    $sql = "UPDATE programs SET name=?, description=?, location=?, prog_date=?, prog_time=?, quota=?, category=?";
    $params = [$name, $desc, $location, $date, $time, $quota, $category];

    // Proses Upload Foto Sampul (Jika organisasi memilih file baru)
    if(isset($_FILES['edit_image_file']) && $_FILES['edit_image_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['edit_image_file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if(in_array(strtolower($ext), $allowed)) {
            $upload_dir = 'uploads/banners/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); 
            
            $new_name = 'banner_org_' . time() . '.' . $ext;
            if(move_uploaded_file($_FILES['edit_image_file']['tmp_name'], $upload_dir . $new_name)) {
                $sql .= ", image_url=?";
                $params[] = $upload_dir . $new_name;
            }
        }
    }

    $sql .= " WHERE id=? AND organizer_id=?";
    $params[] = $prog_id;
    $params[] = $org_id;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pesan = "Detail data program berhasil diperbarui!";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal memperbarui data: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// 3. Proses Hapus Program Kegiatan
if(isset($_POST['delete_program'])) {
    $prog_id = $_POST['prog_id'];
    try {
        $delApp = $pdo->prepare("DELETE FROM applications WHERE program_id = ?");
        $delApp->execute([$prog_id]);

        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ? AND organizer_id = ?");
        $stmt->execute([$prog_id, $org_id]);
        $pesan = "Program kegiatan berhasil dihapus dari sistem.";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal menghapus program: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// 4. Proses Update Profil Organisasi Publik
if(isset($_POST['update_profile'])) {
    $desc = trim($_POST['org_desc']);
    $est_date = $_POST['org_est_date'];
    $loc = trim($_POST['org_loc']);
    $web = trim($_POST['org_web']);
    $type = trim($_POST['org_type']);

    try {
        $stmt = $pdo->prepare("UPDATE users SET description=?, established_date=?, location=?, website=?, org_type=? WHERE id=?");
        $stmt->execute([$desc, $est_date, $loc, $web, $type, $org_id]);
        $pesan = "Profil organisasi publik Anda berhasil diperbarui!";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal memperbarui profil: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// 5. Proses Seleksi Relawan (Terima/Tolak oleh Organisasi)
if(isset($_POST['update_status_relawan'])) {
    $app_id = $_POST['app_id'];
    $status = $_POST['update_status_relawan']; // 'Disetujui' atau 'Ditolak'
    
    // Verifikasi kepemilikan program agar org lain tidak bisa update
    $stmtVerify = $pdo->prepare("SELECT p.organizer_id FROM applications a JOIN programs p ON a.program_id = p.id WHERE a.id = ?");
    $stmtVerify->execute([$app_id]);
    $own_check = $stmtVerify->fetchColumn();
    
    if($own_check == $org_id) {
        $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $stmt->execute([$status, $app_id]);
        $pesan = "Status relawan berhasil diperbarui menjadi $status.";
        $tipe_pesan = 'success';
    } else {
        $pesan = "Akses ditolak. Ini bukan program Anda.";
        $tipe_pesan = 'error';
    }
}

// --- AMBIL DATA DARI DATABASE ---
try {
    $stmtStatProg = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE organizer_id = ?");
    $stmtStatProg->execute([$org_id]);
    $stat_programs = $stmtStatProg->fetchColumn();

    // Hitung Total Pelamar yang DISETUJUI saja untuk statistik
    $stmtStatVol = $pdo->prepare("
        SELECT COUNT(*) FROM applications a 
        JOIN programs p ON a.program_id = p.id 
        WHERE p.organizer_id = ? AND a.status = 'Disetujui'
    ");
    $stmtStatVol->execute([$org_id]);
    $stat_vols = $stmtStatVol->fetchColumn();

    $stmtProgs = $pdo->prepare("SELECT * FROM programs WHERE organizer_id = ? ORDER BY prog_date DESC");
    $stmtProgs->execute([$org_id]);
    $my_programs = $stmtProgs->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Daftar Relawan (Lengkap dengan Profil & CV untuk Modal Review)
    $stmtApps = $pdo->prepare("
        SELECT a.id as app_id, u.name as user_name, u.email, u.phone, u.location as user_location, u.description as user_desc, 
               p.id as prog_id, p.name as prog_name, a.status, a.apply_date, a.motivation, a.cv_path 
        FROM applications a 
        JOIN users u ON a.user_id = u.id 
        JOIN programs p ON a.program_id = p.id 
        WHERE p.organizer_id = ? 
        ORDER BY CASE WHEN a.status = 'Menunggu' THEN 1 ELSE 2 END, a.apply_date DESC
    ");
    $stmtApps->execute([$org_id]);
    $applications = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

    $stmtOrg = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtOrg->execute([$org_id]);
    $org_profile = $stmtOrg->fetch(PDO::FETCH_ASSOC);

    // Kelompokkan Relawan yang Disetujui berdasarkan Program ID untuk ditampilkan di Modal
    $approved_vols_by_prog = [];
    foreach($applications as $app) {
        if($app['status'] == 'Disetujui') {
            $approved_vols_by_prog[$app['prog_id']][] = $app;
        }
    }

} catch (PDOException $e) {
    die("Gagal memuat data sistem: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Organisasi - VolunteerOne</title>
    <!-- Murni Tailwind CSS + Custom CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f7f1eb; color: #2C2C2C; }
        .card-cream { background-color: #ebdcc9; border: 1px solid rgba(122, 28, 36, 0.15); }
        .bg-primary-vol { background-color: #7a1c24; }
        .text-primary-vol { color: #7a1c24; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #dcbfa2; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #7a1c24; }
        
        .input-form { background-color: #ffffff; border: 1px solid #dcbfa2; border-radius: 12px; padding: 12px 16px; width: 100%; outline: none; transition: all 0.3s ease; }
        .input-form:focus { border-color: #7a1c24; box-shadow: 0 0 0 3px rgba(122, 28, 36, 0.15); }
        
        .modal-overlay { background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1050; }
        
        .sticky-sidebar { position: sticky; top: 100px; z-index: 10; background-color: white; }

        /* Animasi Toast */
        @keyframes slideDown { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .toast-animate { animation: slideDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        /* FIX BUG: Pastikan modal tersembunyi dengan sempurna saat dimuat */
        .hidden-view { display: none !important; }
    </style>
</head>
<body class="pb-12">

<nav class="bg-primary-vol text-white p-4 shadow-lg sticky top-0 z-50">
    <div class="max-w-6xl mx-auto flex justify-between items-center px-4">
        <div class="flex items-center gap-3">
            <div class="bg-white/20 p-2 rounded-lg backdrop-blur-sm"><i class="fas fa-building text-xl"></i></div>
            <h1 class="font-bold text-xl tracking-wide mb-0">Panel Mitra Organisasi</h1>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden md:block">
                <p class="text-sm font-bold leading-tight m-0"><?= htmlspecialchars($_SESSION['name']) ?></p>
                <p class="text-[10px] text-white/70 uppercase tracking-wider m-0">Penyelenggara</p>
            </div>
            <a href="logout.php" class="bg-white text-primary-vol hover:bg-gray-100 px-5 py-2 rounded-full font-bold text-sm shadow-md transition">Logout <i class="fas fa-sign-out-alt ml-1"></i></a>
        </div>
    </div>
</nav>

<!-- Notifikasi Operasi -->
<?php if($pesan): ?>
<div id="toastAlert" class="fixed top-24 left-1/2 transform -translate-x-1/2 z-50 toast-animate">
    <div class="<?= $tipe_pesan == 'success' ? 'bg-green-50 border-green-500 text-green-800' : 'bg-red-50 border-red-500 text-red-800' ?> border-l-4 px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 border">
        <i class="fas <?= $tipe_pesan == 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600' ?> text-2xl"></i>
        <span class="font-semibold"><?= htmlspecialchars($pesan) ?></span>
    </div>
</div>
<script>setTimeout(() => { document.getElementById('toastAlert').remove(); }, 4000);</script>
<?php endif; ?>

<main class="max-w-6xl mx-auto p-4 py-8">
    
    <!-- Statistik Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition">
            <div class="bg-red-50 text-[#7a1c24] w-14 h-14 rounded-full flex items-center justify-center text-2xl"><i class="fas fa-bullhorn"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Aktivitas Terbit</p>
                <h3 class="text-2xl font-black text-gray-800"><?= $stat_programs ?> Kegiatan</h3>
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition">
            <div class="bg-orange-50 text-orange-600 w-14 h-14 rounded-full flex items-center justify-center text-2xl"><i class="fas fa-users"></i></div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Relawan Bergabung</p>
                <h3 class="text-2xl font-black text-gray-800"><?= $stat_vols ?> Orang</h3>
            </div>
        </div>
    </div>

    <!-- Sistem Tab Menu -->
    <div class="flex gap-6 border-b border-gray-200 mb-6 overflow-x-auto custom-scrollbar">
        <button onclick="switchTab('program')" id="btn-tab-program" class="pb-3 border-b-4 border-primary-vol font-bold text-primary-vol text-lg whitespace-nowrap">Program Saya</button>
        <button onclick="switchTab('relawan')" id="btn-tab-relawan" class="pb-3 border-b-4 border-transparent font-semibold text-gray-400 hover:text-gray-700 text-lg transition-colors whitespace-nowrap">Seleksi Relawan Masuk</button>
        <button onclick="switchTab('profil')" id="btn-tab-profil" class="pb-3 border-b-4 border-transparent font-semibold text-gray-400 hover:text-gray-700 text-lg transition-colors whitespace-nowrap"><i class="fas fa-cog"></i> Profil Publik</button>
    </div>

    <!-- TAB 1: AREA KELOLA PROGRAM -->
    <div id="tab-program" class="block">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Form Pembuatan Program -->
            <div class="lg:col-span-4">
                <div class="p-6 rounded-[2rem] shadow-sm sticky-sidebar card-cream">
                    <h3 class="font-extrabold text-xl mb-6 text-primary-vol flex items-center gap-2"><i class="fas fa-plus-circle text-2xl"></i> Rilis Kegiatan Baru</h3>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase ml-1">Nama Kegiatan</label>
                            <input type="text" name="name" required class="input-form mt-1" placeholder="Cth: Tanam Mangrove">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase ml-1">Kategori Isu</label>
                            <select name="category" class="input-form mt-1 bg-white">
                                <option value="Pendidikan">Pendidikan</option>
                                <option value="Lingkungan">Lingkungan</option>
                                <option value="Kesehatan">Kesehatan</option>
                                <option value="Sosial">Bantuan Sosial</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase ml-1">Deskripsi Singkat</label>
                            <textarea name="description" required class="input-form mt-1" rows="3" placeholder="Sebutkan tugas..."></textarea>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase ml-1">Lokasi</label>
                            <input type="text" name="location" required class="input-form mt-1" placeholder="Alamat / Titik">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Tanggal</label>
                                <input type="date" name="prog_date" required class="input-form mt-1 text-sm">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Waktu</label>
                                <input type="time" name="prog_time" required class="input-form mt-1 text-sm" value="10:00">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Kuota Relawan</label>
                            <input type="number" name="quota" required min="1" class="input-form" placeholder="Maksimal Relawan">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Foto Sampul (Banner)</label>
                            <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp" class="input-form text-xs py-2 bg-white cursor-pointer">
                        </div>
                        <button type="submit" name="add_program" class="w-full py-3.5 bg-primary-vol hover:bg-[#5a1218] text-white font-bold rounded-full shadow-lg mt-6 text-base tracking-wide transition duration-200">Publikasikan Kegiatan</button>
                    </form>
                </div>
            </div>

            <!-- List Program Kegiatan Buatan Organisasi Ini -->
            <div class="lg:col-span-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <?php foreach($my_programs as $p): ?>
                        <?php 
                            // Hitung pendaftar
                            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ? AND status = 'Disetujui'");
                            $stmtC->execute([$p['id']]);
                            $pendaftar = $stmtC->fetchColumn();
                        ?>
                        <div class="bg-white border border-gray-200 p-6 rounded-3xl shadow-sm hover:shadow-lg transition flex flex-col justify-between">
                            <div>
                                <span class="bg-red-50 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider mb-2 inline-block border border-red-200"><?= htmlspecialchars($p['category'] ?? 'Sosial') ?></span>
                                <h5 class="font-extrabold text-gray-800 text-lg mb-3 leading-snug line-clamp-2"><?= htmlspecialchars($p['name']) ?></h5>
                                
                                <div class="space-y-1.5 mb-4 text-sm text-gray-600 font-medium">
                                    <p class="flex items-start gap-2"><i class="fas fa-map-marker-alt text-primary-vol w-4 text-center mt-1"></i> <span class="line-clamp-1"><?= htmlspecialchars($p['location']) ?></span></p>
                                    <p class="flex items-start gap-2"><i class="far fa-calendar-alt text-primary-vol w-4 text-center mt-1"></i> <?= date('d F Y', strtotime($p['prog_date'])) ?> pada <?= htmlspecialchars($p['prog_time'] ?? '10:00') ?></p>
                                </div>
                            </div>
                            
                            <div>
                                <div class="bg-gray-50 p-3 rounded-xl flex justify-between items-center border border-gray-100 mb-4">
                                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wide">Relawan Diterima</div>
                                    <div class="font-black text-primary-vol text-lg"><?= $pendaftar ?> <span class="text-xs text-gray-400 font-semibold">/ <?= $p['quota'] ?></span></div>
                                </div>
                                
                                <div class="flex gap-2 flex-wrap">
                                    <!-- Tombol Daftar Relawan -->
                                    <button onclick='showApprovedVols(<?= json_encode($approved_vols_by_prog[$p['id']] ?? []) ?>, <?= json_encode($p['name']) ?>)' class="bg-primary-vol text-white w-full py-2.5 rounded-lg text-sm font-bold transition flex-1 shadow-sm"><i class="fas fa-users mr-1"></i> Daftar Relawan</button>
                                    
                                    <button onclick='openEditModal(<?= json_encode($p) ?>)' class="bg-gray-100 hover:bg-gray-200 text-gray-700 w-full py-2.5 rounded-lg text-sm font-bold transition flex-1 border border-gray-200">Edit Detail</button>
                                    
                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus program ini beserta seluruh relawan pendaftarnya?');">
                                        <input type="hidden" name="prog_id" value="<?= $p['id'] ?>">
                                        <button type="submit" name="delete_program" class="bg-red-50 hover:bg-red-500 hover:text-white text-red-500 w-11 h-11 rounded-lg flex items-center justify-center transition border border-red-100">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if(count($my_programs) == 0): ?>
                        <div class="col-span-full p-12 text-center bg-white rounded-3xl text-gray-400 border border-gray-200 shadow-sm">
                            <i class="fas fa-folder-open text-5xl mb-3 opacity-30"></i>
                            <p class="font-semibold text-gray-500">Anda belum mempublikasikan program apapun.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: DAFTAR RELAWAN MASUK (SELEKSI) -->
    <div id="tab-relawan" class="hidden">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-5 bg-blue-50/50 border-b border-blue-100 text-blue-800 font-medium text-sm">
                <i class="fas fa-user-check mr-1 text-blue-600"></i> Silakan tinjau profil pelamar dan tentukan status kelulusan (Terima/Tolak) untuk program kegiatan yang Anda miliki.
            </div>
            
            <?php if(count($applications) == 0): ?>
                <div class="p-12 text-center text-gray-400 font-medium">Belum ada pelamar untuk program Anda.</div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach($applications as $app): ?>
                        <div class="p-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:bg-gray-50 transition <?= $app['status'] == 'Menunggu' ? 'bg-yellow-50/30' : '' ?>">
                            <div class="flex items-start gap-4 flex-grow">
                                <div class="w-12 h-12 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center font-bold text-xl shrink-0 mt-1 border border-gray-300">
                                    <?= strtoupper(substr($app['user_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <h5 class="font-bold text-gray-800 text-lg leading-tight mb-1"><?= htmlspecialchars($app['user_name']) ?></h5>
                                    <p class="text-sm text-primary-vol font-bold mb-1"><i class="fas fa-bookmark mr-1 opacity-70"></i> Kegiatan: <?= htmlspecialchars($app['prog_name']) ?></p>
                                    <p class="text-xs text-gray-500 font-medium mb-2"><i class="far fa-clock mr-1"></i> Diajukan: <?= date('d M Y, H:i', strtotime($app['apply_date'])) ?></p>
                                    
                                    <!-- Tombol Tinjau Profil (Membuka Modal) -->
                                    <button type="button" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 border border-blue-200 bg-blue-50 px-3 py-1.5 rounded-full transition" onclick='showRelawanDetail(<?= json_encode($app) ?>)'>
                                        <i class="fas fa-id-card"></i> Tinjau Profil & Motivasi
                                    </button>
                                </div>
                            </div>
                            
                            <div class="w-full md:w-auto flex flex-col items-end gap-2 shrink-0">
                                <?php if($app['status'] == 'Menunggu'): ?>
                                    <!-- Tombol TERIMA / TOLAK menggunakan Murni Tailwind agar Teksnya Kelihatan -->
                                    <form method="POST" class="flex gap-2 m-0">
                                        <input type="hidden" name="app_id" value="<?= $app['app_id'] ?>">
                                        <button type="submit" name="update_status_relawan" value="Disetujui" class="bg-green-100 hover:bg-green-500 text-green-700 hover:text-white px-5 py-2.5 rounded-full font-bold text-sm transition shadow-sm border border-green-200">
                                            <i class="fas fa-check mr-1"></i> Terima
                                        </button>
                                        <button type="submit" name="update_status_relawan" value="Ditolak" class="bg-red-100 hover:bg-red-500 text-red-700 hover:text-white px-5 py-2.5 rounded-full font-bold text-sm transition shadow-sm border border-red-200">
                                            <i class="fas fa-times mr-1"></i> Tolak
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Perbaikan Logika Tampilan Badge -->
                                    <?php if($app['status'] == 'Disetujui' || $app['status'] == 'Diterima'): ?>
                                        <span class="bg-green-100 text-green-700 px-5 py-2 rounded-full font-bold text-sm flex items-center gap-2 border border-green-200">
                                            <i class="fas fa-check-circle"></i> Disetujui
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-700 px-5 py-2 rounded-full font-bold text-sm flex items-center gap-2 border border-red-200">
                                            <i class="fas fa-times-circle"></i> Ditolak
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 3: PROFIL ORGANISASI PUBLIK -->
    <div id="tab-profil" class="hidden">
        <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 md:p-8 max-w-4xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <i class="fas fa-id-card text-2xl text-primary-vol"></i>
                <h3 class="text-xl font-extrabold text-gray-800">Lengkapi Profil Organisasi Anda</h3>
            </div>
            <p class="text-sm text-gray-500 mb-8 font-medium">Data ini akan ditampilkan kepada publik/relawan di halaman "Profil Organisasi". Buat profil semenarik mungkin agar relawan percaya!</p>
            
            <form method="POST" class="space-y-5">
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Deskripsi / Tentang Kami</label>
                    <textarea name="org_desc" required class="input-form bg-gray-50" rows="5" placeholder="Ceritakan misi, visi, dan aktivitas organisasi Anda secara jelas..."><?= htmlspecialchars($org_profile['description'] ?? '') ?></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Tanggal Pendirian</label>
                        <input type="date" name="org_est_date" value="<?= htmlspecialchars($org_profile['established_date'] ?? '') ?>" class="input-form bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Tipe Organisasi</label>
                        <select name="org_type" class="input-form bg-gray-50 text-sm">
                            <option value="Komunitas" <?= ($org_profile['org_type'] ?? '') == 'Komunitas' ? 'selected' : '' ?>>Komunitas Sosial</option>
                            <option value="Yayasan" <?= ($org_profile['org_type'] ?? '') == 'Yayasan' ? 'selected' : '' ?>>Yayasan (Foundation)</option>
                            <option value="LSM / NGO" <?= ($org_profile['org_type'] ?? '') == 'LSM / NGO' ? 'selected' : '' ?>>LSM / NGO</option>
                            <option value="Instansi Pemerintah" <?= ($org_profile['org_type'] ?? '') == 'Instansi Pemerintah' ? 'selected' : '' ?>>Instansi Pemerintah</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Lokasi Markas Utama</label>
                        <input type="text" name="org_loc" value="<?= htmlspecialchars($org_profile['location'] ?? '') ?>" placeholder="Cth: Kota Tangerang Selatan, Banten" class="input-form bg-gray-50 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Website atau Link Social Media</label>
                        <input type="text" name="org_web" value="<?= htmlspecialchars($org_profile['website'] ?? '') ?>" placeholder="https://instagram.com/..." class="input-form bg-gray-50 text-sm">
                    </div>
                </div>
                <div class="pt-6 mt-6 border-t border-gray-100">
                    <button type="submit" name="update_profile" class="bg-primary-vol hover:bg-[#5a1218] text-white font-bold py-3.5 px-8 rounded-full shadow-md transition w-full md:w-auto">Simpan Profil Publik</button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- MODAL BACA PROFIL RELAWAN -->
<div id="modalRelawan" class="fixed inset-0 modal-overlay flex items-center justify-center hidden-view p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-lg overflow-hidden shadow-2xl relative">
        <div class="bg-primary-vol text-white p-5 flex justify-between items-center">
            <h3 class="font-bold text-lg"><i class="fas fa-user-circle mr-2"></i> Tinjau Profil Pelamar</h3>
            <button onclick="document.getElementById('modalRelawan').classList.add('hidden-view')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4 mb-6 border-b border-gray-100 pb-6">
                <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold text-2xl border border-gray-300" id="mr_avatar">U</div>
                <div>
                    <h4 class="font-bold text-xl text-gray-800 leading-tight" id="mr_name">Nama Relawan</h4>
                    <p class="text-sm text-gray-500" id="mr_contact">email@mail.com</p>
                </div>
            </div>
            
            <h5 class="font-bold text-xs uppercase text-gray-400 tracking-wider mb-2">Domisili</h5>
            <p class="text-sm font-medium text-gray-700 mb-4" id="mr_location"><i class="fas fa-map-marker-alt text-primary-vol w-4"></i> Lokasi</p>
            
            <h5 class="font-bold text-xs uppercase text-gray-400 tracking-wider mb-2">Pengalaman / Bio</h5>
            <div class="bg-gray-50 p-4 rounded-xl text-sm text-gray-700 italic mb-5 border border-gray-100" id="mr_desc">"Belum ada deskripsi profil"</div>

            <h5 class="font-bold text-xs uppercase text-gray-400 tracking-wider mb-2">Motivasi Mendaftar</h5>
            <div class="bg-blue-50 p-4 rounded-xl text-sm text-gray-800 mb-5 border border-blue-100" id="mr_motivation">"Belum mengisi motivasi"</div>

            <div id="mr_cv_area" class="mt-4">
                <!-- Tombol Download CV -->
            </div>
        </div>
    </div>
</div>

<!-- MODAL BACA DAFTAR RELAWAN DISETUJUI -->
<div id="modalApprovedVols" class="fixed inset-0 modal-overlay flex items-center justify-center hidden-view p-4">
    <div class="bg-white rounded-[2rem] w-full max-w-lg overflow-hidden shadow-2xl relative flex flex-col max-h-[85vh]">
        <div class="bg-green-600 text-white p-5 flex justify-between items-center shrink-0">
            <h3 class="font-bold text-lg"><i class="fas fa-users mr-2"></i> Daftar Relawan Disetujui</h3>
            <button onclick="document.getElementById('modalApprovedVols').classList.add('hidden-view')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <div class="p-4 bg-green-50 text-green-800 text-sm font-semibold border-b border-green-100 shrink-0 text-center" id="mav_progname">
            Nama Program
        </div>

        <div class="p-2 overflow-y-auto custom-scrollbar flex-grow" id="mav_list">
            <!-- Data relawan injected here -->
        </div>
        
        <div class="p-4 border-t border-gray-100 shrink-0">
            <button class="w-full bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold py-3 rounded-full transition" onclick="document.getElementById('modalApprovedVols').classList.add('hidden-view')">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL POPUP EDIT DETAIL KEGIATAN -->
<div id="modalEdit" class="fixed inset-0 modal-overlay flex items-center justify-center hidden-view p-4">
    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        
        <div class="bg-primary-vol text-white p-5 flex justify-between items-center shrink-0">
            <h3 class="font-bold text-lg"><i class="fas fa-edit mr-2"></i> Perbarui Data Program</h3>
            <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden-view')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar flex-grow">
            <input type="hidden" name="prog_id" id="edit_id">
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Nama Kegiatan</label>
                <input type="text" name="name" id="edit_name" required class="input-form mt-1 bg-gray-50">
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Kategori</label>
                <select name="category" id="edit_category" class="input-form mt-1 bg-gray-50">
                    <option value="Pendidikan">Pendidikan</option>
                    <option value="Lingkungan">Lingkungan</option>
                    <option value="Kesehatan">Kesehatan</option>
                    <option value="Sosial">Bantuan Sosial</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Deskripsi</label>
                <textarea name="description" id="edit_desc" required class="input-form mt-1 bg-gray-50" rows="3"></textarea>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Lokasi</label>
                <input type="text" name="location" id="edit_loc" required class="input-form mt-1 bg-gray-50">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase ml-1">Tanggal</label>
                    <input type="date" name="prog_date" id="edit_date" required class="input-form mt-1 bg-gray-50 text-sm">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase ml-1">Waktu</label>
                    <input type="time" name="prog_time" id="edit_time" required class="input-form mt-1 bg-gray-50 text-sm">
                </div>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase ml-1">Kuota</label>
                <input type="number" name="quota" id="edit_quota" required min="1" class="input-form mt-1 bg-gray-50">
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 uppercase ml-1 block mb-1">Ganti Foto Sampul (Opsional)</label>
                <input type="file" name="edit_image_file" accept=".jpg,.jpeg,.png,.webp" class="input-form text-xs bg-gray-50 cursor-pointer py-2">
            </div>
        </div>
        
        <div class="p-5 border-t border-gray-100 flex gap-3 shrink-0 bg-white">
            <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden-view')" class="bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold py-3 rounded-full flex-1 transition">Batal</button>
            <button type="submit" name="edit_program" class="bg-primary-vol text-white font-bold py-3 rounded-full flex-1 shadow-md hover:bg-[#5a1218] transition">Simpan Perubahan</button>
        </div>
        
    </form>
</div>

<script>
    function switchTab(tabName) {
        document.getElementById('tab-program').classList.add('hidden');
        document.getElementById('tab-relawan').classList.add('hidden');
        document.getElementById('tab-profil').classList.add('hidden');
        
        const btnProg = document.getElementById('btn-tab-program');
        const btnRel = document.getElementById('btn-tab-relawan');
        const btnProf = document.getElementById('btn-tab-profil');
        
        const clsInactive = "pb-3 border-b-4 border-transparent font-semibold text-gray-400 hover:text-gray-700 text-lg transition-colors whitespace-nowrap";
        const clsActive = "pb-3 border-b-4 border-primary-vol font-bold text-primary-vol text-lg transition-colors whitespace-nowrap";
        
        btnProg.className = clsInactive;
        btnRel.className = clsInactive;
        btnProf.className = clsInactive;
        
        if(tabName === 'program') {
            document.getElementById('tab-program').classList.remove('hidden');
            btnProg.className = clsActive;
        } else if(tabName === 'relawan') {
            document.getElementById('tab-relawan').classList.remove('hidden');
            btnRel.className = clsActive;
        } else {
            document.getElementById('tab-profil').classList.remove('hidden');
            btnProf.className = clsActive;
        }
    }

    function openEditModal(program) {
        document.getElementById('edit_id').value = program.id;
        document.getElementById('edit_name').value = program.name;
        document.getElementById('edit_desc').value = program.description;
        document.getElementById('edit_loc').value = program.location;
        document.getElementById('edit_date').value = program.prog_date;
        document.getElementById('edit_time').value = program.prog_time ? program.prog_time : '10:00';
        document.getElementById('edit_quota').value = program.quota;
        
        const catSelect = document.getElementById('edit_category');
        if(program.category) {
            for(let i=0; i<catSelect.options.length; i++) {
                if(catSelect.options[i].value === program.category) {
                    catSelect.selectedIndex = i; break;
                }
            }
        }
        document.getElementById('modalEdit').classList.remove('hidden-view');
    }

    function showRelawanDetail(app) {
        document.getElementById('mr_name').innerText = app.user_name;
        document.getElementById('mr_avatar').innerText = app.user_name.charAt(0).toUpperCase();
        document.getElementById('mr_contact').innerText = app.email + ' | ' + app.phone;
        
        const loc = app.user_location ? app.user_location : "Lokasi tidak diatur";
        document.getElementById('mr_location').innerHTML = '<i class="fas fa-map-marker-alt text-primary-vol w-4"></i> ' + loc;
        
        const desc = app.user_desc ? '"'+app.user_desc+'"' : "Belum ada deskripsi profil / pengalaman.";
        document.getElementById('mr_desc').innerText = desc;

        const mot = app.motivation ? '"'+app.motivation+'"' : "Relawan mendaftar tanpa menuliskan motivasi.";
        document.getElementById('mr_motivation').innerText = mot;

        const cvArea = document.getElementById('mr_cv_area');
        if(app.cv_path) {
            cvArea.innerHTML = `<a href="${app.cv_path}" download class="w-full block text-center bg-gray-800 text-white font-bold py-3.5 rounded-full hover:bg-gray-900 transition shadow-sm text-sm"><i class="fas fa-download mr-1"></i> Unduh CV / Resume Pelamar</a>`;
        } else {
            cvArea.innerHTML = `<button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-3.5 rounded-full cursor-not-allowed text-sm">Pelamar Tidak Melampirkan CV</button>`;
        }

        document.getElementById('modalRelawan').classList.remove('hidden-view');
    }

    function showApprovedVols(vols, progName) {
        document.getElementById('mav_progname').innerText = progName;
        const listEl = document.getElementById('mav_list');
        listEl.innerHTML = '';

        if(vols.length === 0) {
            listEl.innerHTML = '<div class="p-8 text-center text-gray-400 font-medium">Belum ada relawan yang disetujui.</div>';
        } else {
            vols.forEach(v => {
                const html = `
                    <div class="p-4 border-b border-gray-100 flex items-center gap-4 hover:bg-gray-50 transition">
                        <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-bold flex-shrink-0">
                            ${v.user_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h6 class="mb-0.5 font-bold text-gray-800 leading-tight">${v.user_name}</h6>
                            <p class="mb-0 text-gray-500 text-xs"><i class="fas fa-envelope mr-1"></i> ${v.email}</p>
                            <p class="mb-0 text-gray-500 text-xs"><i class="fas fa-phone mr-1"></i> ${v.phone}</p>
                        </div>
                    </div>
                `;
                listEl.insertAdjacentHTML('beforeend', html);
            });
        }

        document.getElementById('modalApprovedVols').classList.remove('hidden-view');
    }
</script>
</body>
</html>