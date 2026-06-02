<?php
// WAJIB: Memulai sesi agar variabel session dapat terbaca
session_start(); 

require 'koneksi.php';

// Proteksi Halaman Khusus Relawan (User)
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pesan = '';
$tipe_pesan = 'success';

// --- AUTO-REPAIR DATABASE (Memperbaiki Status Kosong) ---
try {
    $pdo->exec("UPDATE applications SET status = 'Disetujui' WHERE status = '' OR status = 'Diterima'");
} catch(PDOException $e) { }

// --- AUTO-MIGRASI KOLOM BARU ---
try {
    $pdo->query("SELECT motivation FROM applications LIMIT 1");
} catch(Exception $e) {
    $pdo->exec("ALTER TABLE applications ADD COLUMN motivation TEXT NULL");
    $pdo->exec("ALTER TABLE applications ADD COLUMN cv_path VARCHAR(255) NULL");
}

// --- PROSES FITUR BARU: UPDATE PROFIL / IDENTITAS RELAWAN ---
if(isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    try {
        $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, location=?, description=? WHERE id=?");
        $stmt->execute([$name, $phone, $location, $description, $user_id]);
        
        $_SESSION['name'] = $name; // Update nama di sesi
        $pesan = "Data Identitas & Profil berhasil diperbarui!";
        $tipe_pesan = "success";
    } catch(PDOException $e) {
        $pesan = "Gagal memperbarui profil: " . $e->getMessage();
        $tipe_pesan = "error";
    }
}

// --- PROSES PENDAFTARAN DENGAN CEK KUOTA ---
if(isset($_POST['apply_program'])) {
    $prog_id = $_POST['program_id'];
    $motivation = trim($_POST['motivation'] ?? '');
    
    if(empty($prog_id)) {
        $pesan = "Terjadi kesalahan: ID Program tidak valid.";
        $tipe_pesan = "error";
    } else {
        try {
            // Cek apakah relawan sudah mendaftar
            $cek = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? AND program_id = ?");
            $cek->execute([$user_id, $prog_id]);
            
            if($cek->rowCount() == 0) {
                // CEK KUOTA: Pastikan jumlah yang 'Disetujui' belum melebihi kuota
                $stmtCekKuota = $pdo->prepare("
                    SELECT p.quota, 
                           (SELECT COUNT(*) FROM applications a WHERE a.program_id = p.id AND a.status = 'Disetujui') as terisi 
                    FROM programs p WHERE p.id = ?
                ");
                $stmtCekKuota->execute([$prog_id]);
                $dataKuota = $stmtCekKuota->fetch(PDO::FETCH_ASSOC);

                if ($dataKuota && $dataKuota['terisi'] >= $dataKuota['quota']) {
                    $pesan = "Mohon maaf, kuota untuk kegiatan ini sudah penuh.";
                    $tipe_pesan = "error";
                } else {
                    // Proses Upload CV jika kuota masih ada
                    $cv_path = NULL;
                    if(isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                        $allowed = ['pdf', 'doc', 'docx'];
                        $filename = $_FILES['cv_file']['name'];
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        if(in_array(strtolower($ext), $allowed)) {
                            $upload_dir = 'uploads/';
                            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); 
                            
                            $new_name = 'cv_' . $user_id . '_' . time() . '.' . $ext;
                            if(move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $new_name)) {
                                $cv_path = $upload_dir . $new_name;
                            }
                        }
                    }

                    $stmt = $pdo->prepare("INSERT INTO applications (program_id, user_id, status, motivation, cv_path) VALUES (?, ?, 'Menunggu', ?, ?)");
                    $stmt->execute([$prog_id, $user_id, $motivation, $cv_path]);
                    $pesan = "Formulir Pendaftaran & CV Berhasil Dikirim! Menunggu seleksi.";
                    $tipe_pesan = "success";
                }
            } else {
                $pesan = "Anda sudah mendaftar pada kegiatan ini.";
                $tipe_pesan = "error";
            }
        } catch(PDOException $e) {
            $pesan = "Terjadi kesalahan sistem: " . $e->getMessage();
            $tipe_pesan = "error";
        }
    }
}

// Proses jika tombol "Batalkan Pendaftaran" ditekan
if(isset($_POST['cancel_program'])) {
    $app_id = $_POST['app_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ? AND user_id = ? AND status = 'Menunggu'");
        $stmt->execute([$app_id, $user_id]);
        $pesan = "Pendaftaran berhasil dibatalkan.";
        $tipe_pesan = "success";
    } catch(PDOException $e) {
        $pesan = "Gagal membatalkan pendaftaran.";
        $tipe_pesan = "error";
    }
}

// --- AMBIL DATA UNTUK DITAMPILKAN ---
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user_data = $stmtUser->fetch(PDO::FETCH_ASSOC);

$stmtStat = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as total_kegiatan,
        SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) as total_menunggu
    FROM applications WHERE user_id = ?
");
$stmtStat->execute([$user_id]);
$stats = $stmtStat->fetch();
$jml_kegiatan = $stats['total_kegiatan'] ?? 0;
$jml_menunggu = $stats['total_menunggu'] ?? 0;
$total_jam = $jml_kegiatan * 16; 
$total_poin = $jml_kegiatan * 100 + 200; 

$programs = $pdo->query("
    SELECT p.*, u.name AS organizer_name 
    FROM programs p 
    LEFT JOIN users u ON p.organizer_id = u.id 
    ORDER BY p.prog_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stmtHistory = $pdo->prepare("
    SELECT a.id as app_id, p.id as prog_id, p.name, p.description, p.location, p.prog_date, p.prog_time, a.status, a.apply_date, p.quota, p.organizer_id 
    FROM applications a 
    JOIN programs p ON a.program_id = p.id 
    WHERE a.user_id = ? 
    ORDER BY a.apply_date DESC
");
$stmtHistory->execute([$user_id]);
$history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

$my_apps = [];
$next_activity = null;
foreach($history as &$h) { 
    if(empty($h['status']) || trim($h['status']) == '') {
        $h['status'] = 'Disetujui';
        try { $pdo->exec("UPDATE applications SET status = 'Disetujui' WHERE id = " . $h['app_id']); } catch(Exception $e) {}
    }
    $my_apps[$h['prog_id']] = $h['status'];
    if($h['status'] == 'Disetujui' && $next_activity == null) {
        $next_activity = $h;
    }
}
unset($h); 

$recommendation = null;
foreach($programs as $p) {
    if(!isset($my_apps[$p['id']])) {
        $recommendation = $p;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>VolunteerOne - Ambil Peranmu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --vol-primary: #7a1c24; --vol-primary-hover: #5a1218; --vol-bg: #f8f9fa; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--vol-bg); color: #333333; -webkit-tap-highlight-color: transparent; display: flex; flex-direction: column; min-height: 100vh;}
        
        .card-program { background-color: white; border-radius: 16px; overflow: hidden; border: 1px solid #eaeaea; transition: all 0.3s ease; display: flex; flex-direction: column; height: 100%; }
        .card-program:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.06); border-color: #d1d5db; }
        
        /* IMAGE MASKING EFEK GRADASI DI KARTU */
        .img-container { 
            position: relative; 
            height: 180px; 
            width: 100%; 
            overflow: hidden; 
            background: white; 
            -webkit-mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 100%);
            mask-image: linear-gradient(to bottom, rgba(0,0,0,1) 50%, rgba(0,0,0,0) 100%);
        }
        .img-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .card-program:hover .img-container img { transform: scale(1.05); }

        .badge-category { position: absolute; top: 12px; left: 12px; background: rgba(255,255,255,0.95); color: var(--vol-primary); padding: 4px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 20; }
        
        /* PERBAIKAN: Menambahkan kembali class input-form */
        .input-form { background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 12px; padding: 12px 16px; width: 100%; outline: none; transition: all 0.3s ease; }
        .input-form:focus { border-color: #7a1c24; box-shadow: 0 0 0 3px rgba(122, 28, 36, 0.15); background-color: #fff; }

        .desktop-nav { border-bottom: 1px solid #eaeaea; background: white; height: 80px; }
        .nav-link-custom { color: #6b7280; font-weight: 600; display: flex; align-items: center; height: 100%; padding: 0 10px; border-bottom: 3px solid transparent; transition: 0.2s; cursor: pointer; text-decoration: none; }
        .nav-link-custom:hover { color: var(--vol-primary); }
        .nav-link-custom.active { color: var(--vol-primary); border-bottom-color: var(--vol-primary); }
        
        .bottom-nav { position: fixed; bottom: 0; width: 100%; background: white; display: flex; justify-content: space-around; padding: 10px 0 15px 0; border-top: 1px solid #eaeaea; z-index: 50; }
        .mob-item { display: flex; flex-direction: column; align-items: center; color: #9ca3af; font-size: 0.65rem; cursor: pointer; transition: 0.2s; font-weight: 600; }
        .mob-item.active { color: var(--vol-primary); }
        .mob-item i { font-size: 1.2rem; margin-bottom: 4px; }
        
        .hidden-view { display: none !important; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .modal-overlay { background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); z-index: 1050; }
        
        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #dcbfa2; border-radius: 10px; }
    </style>
</head>
<body>

    <!-- TOP NAVIGATION -->
    <nav class="desktop-nav sticky-top z-40 d-none d-md-block">
        <div class="max-w-6xl mx-auto px-6 flex justify-between items-center h-full">
            <div class="flex items-center gap-8 h-full">
                <a href="#" class="font-extrabold text-2xl flex items-center gap-2 text-decoration-none" style="color: var(--vol-primary);">
                    <i class="fas fa-hands-helping"></i> VolunteerOne
                </a>
                <div class="flex gap-8 h-full">
                    <div class="nav-link-custom active" id="desk-nav-home" onclick="switchView('home')">Beranda</div>
                    <div class="nav-link-custom" id="desk-nav-search" onclick="switchView('search')">Cari Aktivitas</div>
                    <div class="nav-link-custom gap-2" id="desk-nav-history" onclick="switchView('history')">
                        Aktivitas Saya 
                        <?php if($jml_menunggu > 0): ?><span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full"><?= $jml_menunggu ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-800 leading-tight"><?= htmlspecialchars($_SESSION['name']) ?></p>
                    <p class="text-xs text-gray-500 font-medium">Relawan</p>
                </div>
                <div class="cursor-pointer" onclick="switchView('profile')">
                    <img src="https://api.dicebear.com/7.x/initials/svg?seed=<?= urlencode($_SESSION['name']) ?>&backgroundColor=7a1c24" class="w-10 h-10 rounded-full border border-gray-200">
                </div>
                <a href="logout.php" class="ml-2 text-gray-400 hover:text-red-500 transition"><i class="fas fa-sign-out-alt text-xl"></i></a>
            </div>
        </div>
    </nav>

    <!-- MOBILE HEADER -->
    <header class="md:hidden bg-white px-5 py-4 flex justify-between items-center sticky-top z-40 border-b border-gray-100">
        <div class="font-extrabold text-xl flex items-center gap-2" style="color: var(--vol-primary);">
            <i class="fas fa-hands-helping"></i> <span id="mobile-title">Beranda</span>
        </div>
        <img src="https://api.dicebear.com/7.x/initials/svg?seed=<?= urlencode($_SESSION['name']) ?>&backgroundColor=7a1c24" class="w-9 h-9 rounded-full" onclick="switchView('profile')">
    </header>

    <!-- NOTIFIKASI TOAST -->
    <?php if($pesan): ?>
        <div class="max-w-6xl mx-auto px-5 mt-5">
            <div class="p-4 rounded-xl text-sm font-bold flex items-center justify-between shadow-sm <?= $tipe_pesan == 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?> fade-in" id="toastMsg">
                <div class="flex items-center gap-3">
                    <i class="fas <?= $tipe_pesan == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-lg"></i>
                    <span><?= $pesan ?></span>
                </div>
                <button onclick="document.getElementById('toastMsg').style.display='none'"><i class="fas fa-times opacity-50"></i></button>
            </div>
            <script>setTimeout(() => { document.getElementById('toastMsg').style.opacity='0'; setTimeout(()=>document.getElementById('toastMsg').remove(), 300); }, 4000);</script>
        </div>
    <?php endif; ?>

    <main class="flex-grow max-w-6xl mx-auto p-5 pb-24 md:pb-12 w-full">
        
        <!-- ========================================== -->
        <!-- VIEW 1: BERANDA                            -->
        <!-- ========================================== -->
        <div id="view-home" class="view-section fade-in">
            <div class="bg-white rounded-3xl p-6 md:p-8 mb-8 border border-gray-100 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6 relative overflow-hidden">
                <div class="absolute right-0 top-0 opacity-5 pointer-events-none transform translate-x-1/4 -translate-y-1/4">
                    <i class="fas fa-globe-asia text-9xl"></i>
                </div>
                <div class="z-10 w-full md:w-2/3">
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800 mb-2">Ambil Peran Jadi Relawan</h2>
                    <p class="text-gray-500 text-sm md:text-base mb-5 font-medium">Ubah niat baik jadi aksi baik hari ini bersama VolunteerOne.</p>
                    <button class="bg-[#7a1c24] hover:bg-[#5a1218] text-white px-8 py-3 rounded-full text-sm font-bold shadow-md transition" onclick="switchView('search')">CARI AKTIVITAS</button>
                </div>
                <div class="w-full md:w-1/3 grid grid-cols-2 gap-4 z-10">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 text-center">
                        <h3 class="text-3xl font-black" style="color: var(--vol-primary);"><?= $jml_kegiatan ?></h3>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-1">Total Aksi</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 text-center">
                        <h3 class="text-3xl font-black text-orange-500"><?= $total_jam ?></h3>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-1">Jam Relawan</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-xl font-bold text-gray-800">Sedang Tren di VolunteerOne</h3>
                <button class="text-sm font-bold text-[#7a1c24] hover:underline" onclick="switchView('search')">Lihat Semua</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php 
                $count = 0;
                foreach($programs as $p): 
                    if(isset($my_apps[$p['id']])) continue; 
                    if($count >= 3) break;
                    $count++;
                    
                    $kategori = $p['category'] ?? 'Aksi Sosial';
                    $organizer = !empty($p['organizer_name']) ? $p['organizer_name'] : 'VolunteerOne Official';
                    $is_official = empty($p['organizer_name']);
                    $img = $p['image_url'] ?? 'https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=500&q=80';
                    
                    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ? AND status = 'Disetujui'");
                    $stmtC->execute([$p['id']]);
                    $terisi = $stmtC->fetchColumn();
                    $persen = ($p['quota'] > 0) ? min(100, round(($terisi / $p['quota']) * 100)) : 0;
                    $isFull = $terisi >= $p['quota'];
                ?>
                    <div class="card-program h-full" data-date="<?= strtotime($p['prog_date']) ?>" data-title="<?= strtolower($p['name']) ?>" data-loc="<?= strtolower($p['location']) ?>" data-org="<?= strtolower($organizer) ?>" data-cat="<?= $kategori ?>" data-applied="false">
                        <div class="img-container">
                            <span class="badge-category"><?= htmlspecialchars($kategori) ?></span>
                            <img src="<?= htmlspecialchars($img) ?>" alt="Banner">
                        </div>
                        <div class="p-5 flex flex-col flex-grow relative z-20 -mt-5">
                            <h4 class="font-bold text-gray-900 text-base leading-snug mb-1 line-clamp-2"><?= htmlspecialchars($p['name']) ?></h4>
                            
                            <p class="text-[11px] text-gray-500 font-medium mb-4 flex items-center gap-1.5">
                                <i class="fas <?= $is_official ? 'fa-shield-alt text-blue-500' : 'fa-building text-orange-500' ?>"></i>
                                <?php if(!$is_official && !empty($p['organizer_id'])): ?>
                                    <a href="profil_organisasi.php?id=<?= $p['organizer_id'] ?>" class="text-gray-700 hover:text-[#7a1c24] hover:underline font-bold transition-colors"><?= htmlspecialchars($organizer) ?></a>
                                <?php else: ?>
                                    <a href="profil_volunteerone.php" class="text-gray-700 hover:text-blue-600 hover:underline font-bold transition-colors"><?= htmlspecialchars($organizer) ?> <i class="fas fa-check-circle text-blue-500 ml-0.5"></i></a>
                                <?php endif; ?>
                            </p>
                            
                            <div class="space-y-2 text-xs text-gray-600 mb-5 font-medium flex-grow">
                                <p class="flex items-start gap-2"><i class="far fa-calendar-alt w-4 text-center mt-0.5 text-gray-400"></i> <?= date('d M Y', strtotime($p['prog_date'])) ?></p>
                                <p class="flex items-start gap-2"><i class="fas fa-map-marker-alt w-4 text-center mt-0.5 text-gray-400"></i> <span class="line-clamp-1"><?= htmlspecialchars($p['location']) ?></span></p>
                            </div>
                            
                            <div class="mt-auto">
                                <div class="flex justify-between text-[11px] font-bold text-gray-500 mb-1">
                                    <span>Disetujui: <span class="<?= $isFull ? 'text-red-600' : 'text-gray-800' ?>"><?= $terisi ?></span></span>
                                    <span>Kuota: <?= $p['quota'] ?></span>
                                </div>
                                <div class="progress-bg"><div class="progress-bar <?= $isFull ? 'bg-red-500' : '' ?>" style="width: <?= $persen ?>%"></div></div>
                                
                                <div class="mt-4 flex gap-2">
                                    <?php if($isFull): ?>
                                        <button class="w-full bg-gray-200 text-gray-500 py-2.5 rounded-lg text-xs font-bold shadow-sm" disabled>KUOTA PENUH</button>
                                    <?php else: ?>
                                        <button class="flex-1 bg-[#7a1c24] hover:bg-[#5a1218] text-white py-2.5 rounded-lg text-xs font-bold shadow-sm transition" onclick="showConfirmModal('apply', '<?= $p['id'] ?>', '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">JADI RELAWAN</button>
                                    <?php endif; ?>
                                    <button class="flex-1 border border-[#7a1c24] text-[#7a1c24] hover:bg-red-50 bg-white py-2.5 rounded-lg text-xs font-bold transition" onclick="showDetailModal(<?= htmlspecialchars(json_encode($p)) ?>, <?= $terisi ?>)">DETAIL</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if($count == 0): ?>
                    <div class="col-span-full bg-white p-8 rounded-3xl border border-gray-100 text-center text-gray-500">
                        Belum ada rekomendasi program baru untuk Anda saat ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- VIEW 2: CARI AKTIVITAS                     -->
        <!-- ========================================== -->
        <div id="view-search" class="view-section hidden-view fade-in">
            <div class="bg-white p-5 md:p-6 rounded-3xl shadow-sm border border-gray-100 mb-6 flex flex-col gap-4">
                
                <div class="flex flex-col md:flex-row gap-4 items-center">
                    <div class="relative w-full md:w-1/2">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="inputSearchText" onkeyup="filterProgramsJS()" class="w-full bg-gray-50 border border-gray-200 rounded-full py-3 pl-11 pr-4 text-sm focus:outline-none focus:border-[#7a1c24] transition-all" placeholder="Cari isu, organisasi, atau lokasi...">
                    </div>
                    
                    <div class="flex gap-2 w-full md:w-1/2 overflow-x-auto pb-1">
                        <button class="btn-category active bg-gray-800 text-white px-5 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition" data-cat="all" onclick="setCategory(this, 'all')">Semua Isu</button>
                        <button class="btn-category bg-gray-100 text-gray-600 hover:bg-gray-200 px-5 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition" data-cat="Pendidikan" onclick="setCategory(this, 'Pendidikan')">Pendidikan</button>
                        <button class="btn-category bg-gray-100 text-gray-600 hover:bg-gray-200 px-5 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition" data-cat="Lingkungan" onclick="setCategory(this, 'Lingkungan')">Lingkungan</button>
                        <button class="btn-category bg-gray-100 text-gray-600 hover:bg-gray-200 px-5 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition" data-cat="Kesehatan" onclick="setCategory(this, 'Kesehatan')">Kesehatan</button>
                        <button class="btn-category bg-gray-100 text-gray-600 hover:bg-gray-200 px-5 py-2.5 rounded-full text-xs font-bold whitespace-nowrap transition" data-cat="Sosial" onclick="setCategory(this, 'Sosial')">Sosial</button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center pt-3 border-t border-gray-100 gap-3">
                    <div class="flex bg-gray-50 rounded-full p-1 border border-gray-200 w-full sm:w-auto">
                        <div class="search-tab active px-6 py-1.5 rounded-full text-xs font-bold bg-white shadow-sm cursor-pointer" data-tab="semua" onclick="setTabFilter(this, 'semua')">Semua</div>
                        <div class="search-tab px-6 py-1.5 rounded-full text-xs font-bold text-gray-500 cursor-pointer" data-tab="tersedia" onclick="setTabFilter(this, 'tersedia')">Tersedia</div>
                    </div>
                    
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <span class="text-xs font-bold text-gray-500 uppercase">Urutkan:</span>
                        <select id="sortFilter" onchange="filterProgramsJS()" class="bg-white border border-gray-200 text-xs rounded-full py-2 px-3 focus:outline-none focus:border-[#7a1c24] font-semibold text-gray-700">
                            <option value="terbaru">Aktivitas Terbaru</option>
                            <option value="terdekat">Pelaksanaan Terdekat</option>
                            <option value="terjauh">Pelaksanaan Terjauh</option>
                        </select>
                    </div>
                </div>

            </div>

            <div id="no-result-msg" class="hidden text-center py-16 bg-white rounded-3xl border border-gray-100 shadow-sm mb-6">
                <i class="fas fa-search text-6xl text-gray-200 mb-4"></i>
                <p class="text-lg text-gray-500 font-bold mb-1">Tidak ada aktivitas yang sesuai filter.</p>
                <button onclick="document.getElementById('inputSearchText').value=''; setCategory(document.querySelector('.btn-category[data-cat=\'all\']'), 'all');" class="text-sm text-blue-500 font-bold hover:underline">Reset Pencarian</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="programListContainer">
                <?php foreach($programs as $p): ?>
                    <?php 
                        $status_saya = $my_apps[$p['id']] ?? null; 
                        $kategori = $p['category'] ?? 'Aksi Sosial';
                        $organizer = !empty($p['organizer_name']) ? $p['organizer_name'] : 'VolunteerOne Official';
                        $is_official = empty($p['organizer_name']);
                        $img = $p['image_url'] ?? 'https://images.unsplash.com/photo-1593113589914-075990190da4?w=500&q=80';
                        
                        $s_c = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id=? AND status='Disetujui'");
                        $s_c->execute([$p['id']]);
                        $terisi = $s_c->fetchColumn();
                        $persen = ($p['quota'] > 0) ? min(100, round(($terisi / $p['quota']) * 100)) : 0;
                        $isFull = $terisi >= $p['quota'];

                        $is_applied = $status_saya ? 'true' : 'false';
                    ?>
                    <div class="card-program h-full program-card-item" data-date="<?= strtotime($p['prog_date']) ?>" data-title="<?= strtolower($p['name']) ?>" data-loc="<?= strtolower($p['location']) ?>" data-org="<?= strtolower($organizer) ?>" data-cat="<?= $kategori ?>" data-applied="<?= $is_applied ?>">
                        <div class="img-container">
                            <span class="badge-category"><?= htmlspecialchars($kategori) ?></span>
                            <img src="<?= htmlspecialchars($img) ?>" alt="Banner" loading="lazy">
                        </div>
                        <div class="p-5 flex flex-col flex-grow relative z-20 -mt-5">
                            <h4 class="font-bold text-gray-900 text-base leading-snug mb-1 line-clamp-2"><?= htmlspecialchars($p['name']) ?></h4>
                            
                            <p class="text-[11px] text-gray-500 font-medium mb-4 flex items-center gap-1.5 line-clamp-1">
                                <i class="fas <?= $is_official ? 'fa-shield-alt text-blue-500' : 'fa-building text-orange-500' ?>"></i>
                                <?php if(!$is_official && !empty($p['organizer_id'])): ?>
                                    <a href="profil_organisasi.php?id=<?= $p['organizer_id'] ?>" class="text-gray-700 hover:text-[#7a1c24] hover:underline font-bold transition-colors"><?= htmlspecialchars($organizer) ?></a>
                                <?php else: ?>
                                    <a href="profil_volunteerone.php" class="text-gray-700 hover:text-blue-600 hover:underline font-bold transition-colors"><?= htmlspecialchars($organizer) ?> <i class="fas fa-check-circle text-blue-500 ml-0.5"></i></a>
                                <?php endif; ?>
                            </p>
                            
                            <div class="space-y-2 text-xs text-gray-600 mb-5 font-medium flex-grow">
                                <p class="flex items-start gap-2"><i class="far fa-calendar-alt w-4 text-center mt-0.5 text-gray-400"></i> <span class="keterangan-tanggal"><?= date('d M Y', strtotime($p['prog_date'])) ?></span></p>
                                <p class="flex items-start gap-2"><i class="fas fa-map-marker-alt w-4 text-center mt-0.5 text-gray-400"></i> <span class="line-clamp-1"><?= htmlspecialchars($p['location']) ?></span></p>
                            </div>
                            
                            <div class="mt-auto">
                                <div class="flex justify-between text-[11px] font-bold text-gray-500 mb-1">
                                    <span>Disetujui: <span class="<?= $isFull ? 'text-red-600' : 'text-gray-800' ?>"><?= $terisi ?></span></span>
                                    <span>Kuota: <?= $p['quota'] ?></span>
                                </div>
                                <div class="progress-bg"><div class="progress-bar <?= $isFull ? 'bg-red-500' : '' ?>" style="width: <?= $persen ?>%"></div></div>
                                
                                <div class="mt-4 flex gap-2">
                                    <?php if($status_saya): ?>
                                        <?php $sc = ($status_saya=='Disetujui')?'bg-green-100 text-green-700 border border-green-200':'bg-gray-100 text-gray-500 border border-gray-200'; ?>
                                        <button class="w-full <?= $sc ?> py-2.5 rounded-lg font-bold text-xs flex-1 cursor-default text-center uppercase tracking-wide">Status: <?= $status_saya ?></button>
                                    <?php elseif($isFull): ?>
                                        <button class="w-full bg-gray-200 text-gray-500 py-2.5 rounded-lg text-xs font-bold shadow-sm" disabled>KUOTA PENUH</button>
                                    <?php else: ?>
                                        <button class="flex-1 bg-[#7a1c24] hover:bg-[#5a1218] text-white py-2.5 rounded-lg text-xs font-bold shadow-sm transition" onclick="showConfirmModal('apply', '<?= $p['id'] ?>', '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">JADI RELAWAN</button>
                                    <?php endif; ?>
                                    <button class="flex-1 border border-[#7a1c24] text-[#7a1c24] hover:bg-red-50 bg-white py-2.5 rounded-lg text-xs font-bold transition" onclick="showDetailModal(<?= htmlspecialchars(json_encode($p)) ?>, <?= $terisi ?>)">DETAIL</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- VIEW 3: AKTIVITAS SAYA -->
        <div id="view-history" class="view-section hidden-view fade-in">
            <h2 class="text-2xl font-extrabold text-gray-800 mb-6">Aktivitas Saya</h2>
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-4 md:p-8">
                <?php if(count($history) == 0): ?>
                    <div class="text-center py-16">
                        <i class="fas fa-clipboard-list text-6xl text-gray-200 mb-4"></i>
                        <h3 class="text-lg font-bold text-gray-700">Belum ada aktivitas</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-6">Ayo mulai cari kegiatan sosial pertamamu!</p>
                        <button class="bg-[#7a1c24] text-white px-6 py-2.5 rounded-full text-sm font-bold shadow-md hover:bg-[#5a1218]" onclick="switchView('search')">Eksplor Aktivitas</button>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-5">
                        <?php foreach($history as $h): ?>
                            <?php 
                                $bgs = $h['status'] == 'Disetujui' ? 'bg-green-50 text-green-600 border-green-200' : ($h['status'] == 'Menunggu' ? 'bg-orange-50 text-orange-600 border-orange-200' : 'bg-red-50 text-red-600 border-red-200');
                                $ics = $h['status'] == 'Disetujui' ? 'fa-check-circle' : ($h['status'] == 'Menunggu' ? 'fa-clock' : 'fa-times-circle');
                            ?>
                            <div class="border border-gray-200 rounded-2xl p-5 hover:shadow-sm transition bg-white">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <div class="flex gap-4 w-full md:w-auto">
                                        <div class="hidden sm:flex w-16 h-16 bg-gray-50 rounded-xl border border-gray-100 items-center justify-center text-gray-400 text-2xl shrink-0"><i class="fas fa-hands-helping"></i></div>
                                        <div>
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="<?= $bgs ?> border px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"><i class="fas <?= $ics ?> mr-1"></i> <?= $h['status'] ?></span>
                                                <span class="text-xs text-gray-400 font-medium"><i class="far fa-calendar-alt"></i> <?= date('d M Y', strtotime($h['prog_date'])) ?></span>
                                            </div>
                                            <h5 class="font-bold text-base text-gray-800 leading-tight mb-1"><a href="javascript:void(0)" onclick="switchView('search')" class="hover:underline hover:text-[#7a1c24]"><?= htmlspecialchars($h['name']) ?></a></h5>
                                            <p class="text-xs text-gray-500 font-medium"><i class="fas fa-map-marker-alt text-gray-400 w-3"></i> <?= htmlspecialchars($h['location']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="w-full md:w-auto text-right border-t border-gray-100 md:border-0 pt-3 md:pt-0 mt-2 md:mt-0 flex flex-row md:flex-col justify-between md:justify-end items-center md:items-end gap-2">
                                        <p class="text-[11px] text-gray-400">Diajukan: <?= date('d M, H:i', strtotime($h['apply_date'])) ?></p>
                                        <?php if($h['status'] == 'Menunggu'): ?>
                                            <button class="text-xs font-bold text-red-500 border border-red-500 hover:bg-red-50 px-4 py-1.5 rounded-lg transition" onclick="showConfirmModal('cancel', '<?= $h['app_id'] ?>', '')">Batalkan</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if($h['status'] == 'Disetujui'): ?>
                                    <div class="mt-4 bg-green-50 border border-green-200 p-4 rounded-xl">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-envelope-open-text text-green-600 text-lg mt-0.5"></i>
                                            <div>
                                                <h6 class="text-sm font-bold text-green-800 mb-1">Pesan dari Penyelenggara</h6>
                                                <p class="text-xs text-green-700 leading-relaxed m-0">Selamat! Anda telah terpilih untuk mengikuti kegiatan ini. Silakan bersiap dan hadir tepat waktu di lokasi pada tanggal yang ditentukan. Terima kasih atas niat baik Anda!</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif($h['status'] == 'Ditolak'): ?>
                                    <div class="mt-4 bg-red-50 border border-red-200 p-4 rounded-xl flex items-start gap-3">
                                        <i class="fas fa-exclamation-triangle text-red-500 text-lg mt-0.5"></i>
                                        <div>
                                            <h6 class="text-sm font-bold text-red-800 mb-1">Informasi Penyelenggara</h6>
                                            <p class="text-xs text-red-700 leading-relaxed m-0">Mohon maaf, kuota relawan untuk kegiatan ini sudah terpenuhi atau profil Anda belum sesuai dengan kriteria yang dicari. Tetap semangat mencari kegiatan lain!</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- VIEW 4: PROFIL -->
        <div id="view-profile" class="view-section hidden-view fade-in max-w-3xl mx-auto">
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 text-center relative mt-16">
                <!-- Avatar -->
                <div class="absolute -top-16 left-1/2 transform -translate-x-1/2">
                    <img src="https://api.dicebear.com/7.x/initials/svg?seed=<?= urlencode($_SESSION['name']) ?>&backgroundColor=7a1c24" class="w-32 h-32 rounded-full border-4 border-white shadow-lg bg-white">
                </div>
                
                <h3 class="font-black text-2xl text-gray-800 mt-12 mb-1"><?= htmlspecialchars($_SESSION['name']) ?></h3>
                <p class="text-sm text-gray-500 mb-4 font-medium"><i class="far fa-envelope mr-1"></i> <?= htmlspecialchars($_SESSION['email'] ?? 'relawan@mail.com') ?></p>
                <div class="bg-gray-50 text-gray-600 rounded-2xl p-4 mb-8 italic text-sm border border-gray-100">
                    <?= !empty($user_data['description']) ? nl2br(htmlspecialchars($user_data['description'])) : '"Belum ada deskripsi profil. Silakan edit profil untuk menambahkan pengalaman Anda agar penyelenggara dapat mengenal Anda lebih baik."' ?>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div class="bg-[#7a1c24] text-white flex flex-col items-center justify-center p-4 rounded-2xl shadow-sm">
                        <i class="far fa-calendar-check text-3xl mb-2 opacity-80"></i>
                        <h3 class="text-3xl font-black mb-1"><?= $jml_kegiatan ?></h3>
                        <p class="text-xs uppercase tracking-widest font-bold opacity-80">Aksi Selesai</p>
                    </div>
                    <div class="text-white flex flex-col items-center justify-center p-4 rounded-2xl shadow-sm" style="background: linear-gradient(135deg, #b06222 0%, #87410c 100%);">
                        <i class="fas fa-star text-3xl mb-2 opacity-80 text-yellow-300"></i>
                        <h3 class="text-3xl font-black mb-1"><?= $total_poin ?></h3>
                        <p class="text-xs uppercase tracking-widest font-bold opacity-80">Total Poin</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left">
                    <button class="bg-white border border-gray-200 p-4 rounded-2xl font-bold text-gray-700 hover:border-[#7a1c24] hover:text-[#7a1c24] transition flex justify-between items-center group" onclick="document.getElementById('modalEditProfile').classList.remove('hidden-view')">
                        <span class="flex items-center gap-3"><i class="fas fa-user-edit text-gray-400 group-hover:text-[#7a1c24] transition w-5"></i> Edit Profil & Info</span>
                        <i class="fas fa-chevron-right text-gray-300"></i>
                    </button>
                    <a href="logout.php" class="bg-red-50 border border-red-100 p-4 rounded-2xl font-bold text-red-600 hover:bg-red-500 hover:text-white transition flex justify-between items-center">
                        <span class="flex items-center gap-3"><i class="fas fa-sign-out-alt w-5"></i> Keluar Sesi</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- FOOTER SEDERHANA ALA INDORELAWAN -->
    <footer class="bg-gray-100 border-t border-gray-200 py-6 md:py-4 mt-auto w-full">
        <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center md:items-start gap-4 md:gap-8">
            <div class="flex-1 flex flex-col md:flex-row items-center md:items-start gap-4 md:gap-6 text-center md:text-left">
                <div class="shrink-0"><h2 class="font-extrabold text-xl text-[#7a1c24] italic leading-none"><i class="fas fa-hands-helping text-lg"></i> VolunteerOne<span class="text-xs not-italic font-medium text-gray-800">.org</span></h2></div>
                <div>
                    <p class="text-[11px] text-gray-600 leading-snug max-w-xl m-0">Wadah online mempertemukan relawan dan organisasi sosial. <span class="italic font-medium text-gray-800">"Ubah niat baik jadi aksi baik hari ini."</span></p>
                    <p class="text-[10px] text-gray-500 mt-1 m-0">Universitas Tadulako, Kota Palu, Sulawesi Tengah 94118</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- BOTTOM NAVIGATION (MOBILE ONLY) -->
    <nav class="bottom-nav md:hidden">
        <div class="mob-item active" id="mob-nav-home" onclick="switchView('home')">
            <i class="fas fa-home"></i> <span>Beranda</span>
        </div>
        <div class="mob-item" id="mob-nav-search" onclick="switchView('search')">
            <i class="fas fa-search"></i> <span>Cari</span>
        </div>
        <div class="mob-item relative" id="mob-nav-history" onclick="switchView('history')">
            <?php if($jml_menunggu > 0): ?><span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1 bg-red-500 w-2 h-2 rounded-full border border-white"></span><?php endif; ?>
            <i class="fas fa-clipboard-list"></i> <span>Aktivitas</span>
        </div>
        <div class="mob-item" id="mob-nav-profile" onclick="switchView('profile')">
            <i class="far fa-user"></i> <span>Profil</span>
        </div>
    </nav>

    <!-- MODAL: DETAIL KEGIATAN (CLEAN UI UPDATE) -->
    <div id="modalDetail" class="fixed inset-0 modal-overlay flex items-center justify-center hidden-view p-4 z-[1050]">
        <div class="bg-white rounded-[24px] flex flex-col max-h-[90vh] w-full max-w-lg mx-auto overflow-hidden shadow-2xl relative">
            
            <!-- HEADER / IMAGE PURE BANNER (Tanpa Teks & Gradasi Gelap) -->
            <div class="relative h-48 md:h-56 flex-shrink-0 w-full bg-gray-100 border-b border-gray-200">
                <img id="mdlImg" src="" class="w-full h-full object-cover object-center">
                
                <button class="absolute top-4 right-4 bg-white/70 hover:bg-white text-gray-800 w-8 h-8 rounded-full flex items-center justify-center transition backdrop-blur-md z-20 shadow-sm" onclick="closeModal('modalDetail')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- BODY CLEAN UI (Teks dan Detail dipindah ke area putih) -->
            <div class="p-6 overflow-y-auto custom-scrollbar bg-white">
                <!-- Judul & Penyelenggara -->
                <div class="mb-5 pb-5 border-b border-gray-100">
                    <span id="mdlCat" class="bg-red-50 text-[#7a1c24] px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider mb-3 inline-block border border-red-100">Kategori</span>
                    <h3 id="mdlTitle" class="text-xl md:text-2xl font-black text-gray-800 leading-tight mb-4">Judul Kegiatan</h3>
                    
                    <div class="flex items-center gap-3">
                        <div id="mdlOrgIconContainer" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center shrink-0 border border-gray-100">
                            <span id="mdlOrgIcon" class="text-xl"></span>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider m-0">Diselenggarakan oleh</p>
                            <p id="mdlOrg" class="text-sm font-bold text-gray-800 m-0">Organisasi</p>
                        </div>
                    </div>
                </div>

                <h4 class="font-bold text-sm text-gray-800 mb-2">Tentang Aktivitas</h4>
                <p id="mdlDesc" class="text-sm text-gray-600 leading-relaxed mb-6">Deskripsi...</p>
                
                <h4 class="font-bold text-sm text-gray-800 mb-3">Detail Pelaksanaan</h4>
                <div class="bg-gray-50 rounded-xl p-4 space-y-3 text-sm text-gray-700 font-medium border border-gray-100">
                    <div class="flex gap-3 items-start"><i class="far fa-calendar-alt mt-0.5 text-[#7a1c24] w-4 text-center"></i> <span id="mdlDate"></span></div>
                    <div class="flex gap-3 items-start"><i class="far fa-clock mt-0.5 text-[#7a1c24] w-4 text-center"></i> <span id="mdlTime"></span></div>
                    <div class="flex gap-3 items-start"><i class="fas fa-map-marker-alt mt-0.5 text-[#7a1c24] w-4 text-center"></i> <span id="mdlLoc"></span></div>
                    <div class="flex gap-3 items-start"><i class="fas fa-users mt-0.5 text-[#7a1c24] w-4 text-center"></i> <span>Dibutuhkan <span id="mdlQuota" class="font-bold text-gray-800"></span> Relawan (Terisi: <span id="mdlTerkumpul"></span>)</span></div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="p-4 bg-white border-t border-gray-100 flex-shrink-0">
                <button class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3.5 rounded-full text-sm transition" onclick="closeModal('modalDetail')">TUTUP KETERANGAN</button>
            </div>
        </div>
    </div>

    <!-- MODAL: FORMULIR PENDAFTARAN & UPLOAD CV -->
    <div id="modalConfirm" class="fixed inset-0 modal-overlay flex items-center justify-center hidden-view p-4 z-[1050]">
        <div class="modal-card w-full max-w-lg bg-white rounded-[24px] overflow-hidden shadow-2xl relative flex flex-col">
            <div class="bg-[#7a1c24] text-white p-5 flex justify-between items-center shrink-0">
                <h3 class="font-bold text-lg" id="confTitle"><i class="fas fa-file-signature mr-2"></i> Formulir Aplikasi</h3>
                <button onclick="closeModal('modalConfirm')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
            </div>
            
            <form method="POST" id="confForm" class="p-6 bg-white space-y-4 overflow-y-auto" enctype="multipart/form-data">
                <input type="hidden" name="program_id" id="confProgId" value="">
                <input type="hidden" name="app_id" id="confAppId" value="">
                
                <div id="applyFormArea">
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-sm text-blue-800 mb-4 font-medium">
                        Mendaftar untuk: <span class="font-bold" id="applyProgName">Nama Program</span>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1">Mengapa Anda tertarik & cocok?</label>
                        <textarea name="motivation" id="motInput" rows="3" class="input-form mt-1 text-sm" placeholder="Ceritakan motivasi dan keahlian Anda..."></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1">Upload CV / Resume (Opsional)</label>
                        <div class="mt-1 border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:bg-gray-50 transition cursor-pointer relative">
                            <input type="file" name="cv_file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept=".pdf,.doc,.docx">
                            <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600 font-medium m-0">Klik atau seret file ke sini</p>
                            <p class="text-xs text-gray-400 m-0">PDF, DOC (Maks. 2MB)</p>
                        </div>
                    </div>
                </div>

                <div id="cancelArea" class="hidden">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-circle text-red-500 text-5xl mb-3"></i>
                        <p class="text-gray-600 font-medium">Apakah Anda yakin ingin membatalkan pendaftaran ini?</p>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-gray-100 flex gap-3 mt-6">
                    <button type="button" class="bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold py-3.5 rounded-full flex-1 transition text-sm" onclick="closeModal('modalConfirm')">Batal</button>
                    <button type="submit" id="confBtnAction" name="apply_program" class="bg-[#7a1c24] text-white font-bold py-3.5 rounded-full flex-1 shadow-md text-sm hover:bg-[#5a1218]">Kirim Aplikasi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT PROFIL -->
    <div id="modalEditProfile" class="fixed inset-0 modal-overlay flex items-center justify-center hidden-view p-4 z-[1050]">
        <div class="modal-card w-full max-w-lg bg-white rounded-[24px] overflow-hidden shadow-2xl relative flex flex-col">
            <div class="bg-[#7a1c24] text-white p-5 flex justify-between items-center shrink-0">
                <h3 class="font-bold text-lg"><i class="fas fa-user-edit mr-2"></i> Perbarui Identitas</h3>
                <button onclick="document.getElementById('modalEditProfile').classList.add('hidden-view')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form method="POST" class="p-6 space-y-4 bg-white overflow-y-auto">
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase ml-1">Nama Lengkap</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user_data['name']) ?>" required class="input-form mt-1">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1">No. WhatsApp</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user_data['phone']) ?>" required class="input-form mt-1">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-500 uppercase ml-1">Kota Domisili</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($user_data['location'] ?? '') ?>" required class="input-form mt-1">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase ml-1">Pengalaman / Bio Singkat</label>
                    <textarea name="description" rows="3" class="input-form mt-1 text-sm"><?= htmlspecialchars($user_data['description'] ?? '') ?></textarea>
                </div>
                <div class="pt-4 border-t border-gray-100 flex gap-3">
                    <button type="button" onclick="document.getElementById('modalEditProfile').classList.add('hidden-view')" class="bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold py-3.5 rounded-full flex-1 text-sm">Batal</button>
                    <button type="submit" name="update_profile" class="bg-[#7a1c24] text-white font-bold py-3.5 rounded-full flex-1 shadow-md text-sm hover:bg-[#5a1218]">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPT LOGIKA UTAMA -->
    <script>
        // ROUTER / NAVIGATION LOGIC
        function switchView(viewName) {
            document.querySelectorAll('.view-section').forEach(el => el.classList.add('hidden-view'));
            document.getElementById('view-' + viewName).classList.remove('hidden-view');
            
            document.querySelectorAll('.mob-item').forEach(el => el.classList.remove('active'));
            const mobNav = document.getElementById('mob-nav-' + viewName);
            if(mobNav) mobNav.classList.add('active');

            document.querySelectorAll('.nav-link-custom').forEach(el => el.classList.remove('active'));
            const deskNav = document.getElementById('desk-nav-' + viewName);
            if(deskNav) deskNav.classList.add('active');
            
            const headerText = document.getElementById('mobile-title');
            if(headerText) {
                if(viewName === 'home') headerText.innerText = 'Beranda';
                else if(viewName === 'search') headerText.innerText = 'Cari Aktivitas';
                else if(viewName === 'history') headerText.innerText = 'Aktivitas Saya';
                else headerText.innerText = 'Profil Saya';
            }

            localStorage.setItem('volunteer_last_view', viewName);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const lastView = localStorage.getItem('volunteer_last_view') || 'home';
            switchView(lastView);
        });

        // FITUR FILTER & SORTING REAL-TIME
        function filterProgramsJS() {
            const query = document.getElementById('inputSearchText').value.toLowerCase();
            const activeCat = document.querySelector('.btn-category.active').dataset.cat;
            const activeTab = document.querySelector('.search-tab.active').dataset.tab;
            const sortVal = document.getElementById('sortFilter').value; 

            const container = document.getElementById('programListContainer');
            const cards = document.querySelectorAll('.program-card-item');
            let cardsArray = Array.from(cards);
            let foundCount = 0;

            cardsArray.forEach(card => {
                const title = card.dataset.title;
                const loc = card.dataset.loc;
                const org = card.dataset.org;
                const cat = card.dataset.cat;
                const applied = card.dataset.applied === "true";

                let isMatch = true;
                if(query && !title.includes(query) && !loc.includes(query) && !org.includes(query)) isMatch = false;
                if(activeCat !== 'all' && cat !== activeCat) isMatch = false;
                
                // Perbaikan Filter Tersedia
                // Menyembunyikan program yang sudah diaply
                if(activeTab === 'tersedia' && applied) isMatch = false;

                if(isMatch) {
                    card.style.display = 'block';
                    foundCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Perbaikan Sorting
            cardsArray.sort((a, b) => {
                // Konversi tanggal string dari html menjadi objek tanggal
                // Format teks di html: dd MMMM yyyy (contoh: 21 Juni 2026)
                let dateA = new Date(a.querySelector('.keterangan-tanggal').innerText).getTime();
                let dateB = new Date(b.querySelector('.keterangan-tanggal').innerText).getTime();

                let now = Date.now();
                
                // Mencegah NaN karena parsing gagal
                if(isNaN(dateA)) dateA = parseInt(a.dataset.date) * 1000;
                if(isNaN(dateB)) dateB = parseInt(b.dataset.date) * 1000;

                // Logika Sorting yang Sebenarnya
                if (sortVal === 'terbaru') return dateB - dateA; // Tanggal paling baru (paling jauh ke masa depan) di atas
                
                if (sortVal === 'terdekat') {
                    // Cari selisih mutlak dari waktu sekarang
                    return Math.abs(dateA - now) - Math.abs(dateB - now);
                }
                if (sortVal === 'terjauh') {
                    // Cari selisih mutlak paling besar dari waktu sekarang
                    return Math.abs(dateB - now) - Math.abs(dateA - now);
                }
                
                return 0; // Default
            });

            cardsArray.forEach(card => container.appendChild(card));

            const noResult = document.getElementById('no-result-msg');
            if(noResult) noResult.style.display = foundCount === 0 ? 'block' : 'none';
        }

        function setCategory(btn, catName) {
            document.querySelectorAll('.btn-category').forEach(b => {
                b.classList.remove('bg-gray-800', 'text-white', 'active');
                b.classList.add('bg-gray-100', 'text-gray-600');
            });
            btn.classList.remove('bg-gray-100', 'text-gray-600');
            btn.classList.add('bg-gray-800', 'text-white', 'active');
            btn.dataset.cat = catName;
            filterProgramsJS();
        }

        function setTabFilter(btn, tabName) {
            document.querySelectorAll('.search-tab').forEach(b => {
                b.classList.remove('active', 'bg-white', 'shadow-sm', 'text-gray-800');
                b.classList.add('text-gray-500');
            });
            btn.classList.remove('text-gray-500');
            btn.classList.add('active', 'bg-white', 'shadow-sm', 'text-gray-800');
            btn.dataset.tab = tabName;
            filterProgramsJS();
        }

        // MODAL LOGIC
        function showDetailModal(program, terkumpul = 0) {
            document.getElementById('mdlTitle').innerText = program.name;
            document.getElementById('mdlDesc').innerText = program.description;
            
            // Menentukan Ikon & Text berdasarkan siapa yang membuat
            const isOfficial = !program.organizer_name;
            const orgName = isOfficial ? 'VolunteerOne Official <i class="fas fa-check-circle text-blue-500 ml-1 text-xs"></i>' : program.organizer_name;
            const iconClass = isOfficial ? 'fas fa-shield-alt text-blue-500' : 'fas fa-building text-orange-500';
            const iconBgClass = isOfficial ? 'w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center shrink-0 border border-blue-100' : 'w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center shrink-0 border border-orange-100';
            const profileLink = isOfficial ? 'profil_volunteerone.php' : 'profil_organisasi.php?id=' + program.organizer_id;
            
            document.getElementById('mdlOrg').innerHTML = `<a href="${profileLink}" class="text-gray-800 hover:text-[#7a1c24] hover:underline font-bold transition-colors">${orgName}</a>`;
            document.getElementById('mdlOrgIcon').className = iconClass;
            document.getElementById('mdlOrgIconContainer') ? document.getElementById('mdlOrgIconContainer').className = iconBgClass : null; 
            
            document.getElementById('mdlCat').innerText = program.category ? program.category : 'Aksi Sosial';
            document.getElementById('mdlImg').src = program.image_url ? program.image_url : 'https://images.unsplash.com/photo-1593113589914-075990190da4?w=500&q=80';
            
            const dateObj = new Date(program.prog_date);
            document.getElementById('mdlDate').innerText = dateObj.toLocaleDateString('id-ID', {weekday:'long', day:'numeric', month:'long', year:'numeric'});
            document.getElementById('mdlTime').innerText = program.prog_time ? program.prog_time : '10:00';
            document.getElementById('mdlLoc').innerText = program.location;
            document.getElementById('mdlQuota').innerText = program.quota;
            document.getElementById('mdlTerkumpul').innerText = terkumpul;
            
            document.getElementById('modalDetail').classList.remove('hidden-view');
        }

        function showConfirmModal(actionType, id, progName) {
            const mTitle = document.getElementById('confTitle');
            const mBtn = document.getElementById('confBtnAction');
            const formArea = document.getElementById('applyFormArea');
            const cancelArea = document.getElementById('cancelArea');
            const motInput = document.getElementById('motInput');
            
            if(actionType === 'apply') {
                mTitle.innerHTML = '<i class="fas fa-file-signature mr-2"></i> Formulir Aplikasi';
                document.getElementById('applyProgName').innerText = progName;
                formArea.style.display = 'block';
                cancelArea.style.display = 'none';
                
                if(motInput) motInput.required = true; 
                
                mBtn.innerText = "Kirim Permintaa & CV";
                mBtn.name = "apply_program";
                mBtn.className = "bg-[#7a1c24] hover:bg-[#5a1218] text-white w-full font-bold py-3.5 rounded-full flex-1 shadow-md text-sm transition";
                document.getElementById('confProgId').value = id;
            } else if(actionType === 'cancel') {
                mTitle.innerHTML = '<i class="fas fa-exclamation-triangle mr-2 text-red-500"></i> Batalkan Kegiatan';
                formArea.style.display = 'none';
                cancelArea.style.display = 'block';
                
                if(motInput) motInput.required = false; 
                
                mBtn.innerText = "Ya, Batalkan";
                mBtn.name = "cancel_program";
                mBtn.className = "bg-red-500 hover:bg-red-600 text-white font-bold rounded-full flex-1 py-3.5 text-sm transition shadow-md w-full";
                document.getElementById('confAppId').value = id;
            }

            document.getElementById('modalConfirm').classList.remove('hidden-view');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden-view');
        }
    </script>
</body>
</html>