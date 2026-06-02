<?php
// WAJIB: Memulai sesi di awal file
session_start();

require 'koneksi.php';

// Proteksi Halaman Khusus Admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$pesan = '';
$tipe_pesan = 'success';

// 1. Proses Tambah Program Baru (Dengan Upload Foto Sampul)
if(isset($_POST['add_program'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $location = $_POST['location'];
    $date = $_POST['prog_date'];
    $time = $_POST['prog_time'] ?? '10:00';
    $quota = $_POST['quota'];
    $category = $_POST['category'] ?? 'Sosial';
    
    // Proses Upload Foto Sampul
    $image_url = 'https://images.unsplash.com/photo-1593113589914-075990190da4?w=500&q=80'; // Gambar Default
    if(isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['image_file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if(in_array(strtolower($ext), $allowed)) {
            $upload_dir = 'uploads/banners/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); 
            
            $new_name = 'banner_admin_' . time() . '.' . $ext;
            if(move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $new_name)) {
                $image_url = $upload_dir . $new_name; 
            }
        }
    }

    try {
        // organizer_id dibiarkan NULL yang berarti ini program milik VolunteerOne Official
        $stmt = $pdo->prepare("INSERT INTO programs (name, description, location, prog_date, prog_time, quota, category, image_url, rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 5.0)");
        $stmt->execute([$name, $desc, $location, $date, $time, $quota, $category, $image_url]);
        $pesan = "Program kegiatan VolunteerOne Official berhasil dipublikasikan!";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal menambahkan program: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// 2. Proses Edit Program (Dengan Upload Foto Baru Opsional)
if(isset($_POST['edit_program'])) {
    $prog_id = $_POST['prog_id'];
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $location = trim($_POST['location']);
    $date = $_POST['prog_date'];
    $time = $_POST['prog_time'] ?? '10:00';
    $quota = intval($_POST['quota']);
    $category = $_POST['category'] ?? 'Sosial';

    $sql = "UPDATE programs SET name=?, description=?, location=?, prog_date=?, prog_time=?, quota=?, category=?";
    $params = [$name, $desc, $location, $date, $time, $quota, $category];

    if(isset($_FILES['edit_image_file']) && $_FILES['edit_image_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = pathinfo($_FILES['edit_image_file']['name'], PATHINFO_EXTENSION);
        
        if(in_array(strtolower($ext), $allowed)) {
            $upload_dir = 'uploads/banners/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); 
            $new_name = 'banner_admin_' . time() . '.' . $ext;
            if(move_uploaded_file($_FILES['edit_image_file']['tmp_name'], $upload_dir . $new_name)) {
                $sql .= ", image_url=?";
                $params[] = $upload_dir . $new_name;
            }
        }
    }

    $sql .= " WHERE id=?";
    $params[] = $prog_id;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pesan = "Data program berhasil diperbarui!";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal memperbarui data: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// 3. Proses Update Status Pendaftaran Relawan (Terima/Tolak)
if(isset($_POST['status']) && isset($_POST['app_id'])) {
    $app_id = $_POST['app_id'];
    $status = $_POST['status']; 
    
    $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->execute([$status, $app_id]);
    $pesan = "Status pendaftaran relawan berhasil diperbarui menjadi $status!";
    $tipe_pesan = 'success';
}

// 4. Proses Validasi Akun Organisasi
if(isset($_POST['validate_org'])) {
    $org_id = $_POST['org_id'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_validated = 1 WHERE id = ?");
        $stmt->execute([$org_id]);
        $pesan = "Akun Organisasi berhasil disetujui dan diaktifkan!";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal memvalidasi organisasi: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// 5. Proses Hapus Program
if(isset($_POST['delete_program'])) {
    $prog_id = $_POST['prog_id'];
    try {
        $delApp = $pdo->prepare("DELETE FROM applications WHERE program_id = ?");
        $delApp->execute([$prog_id]);
        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->execute([$prog_id]);
        $pesan = "Program beserta data pendaftarnya berhasil dihapus!";
        $tipe_pesan = 'success';
    } catch(PDOException $e) {
        $pesan = "Gagal menghapus program: " . $e->getMessage();
        $tipe_pesan = 'error';
    }
}

// --- AMBIL DATA UNTUK DITAMPILKAN ---
// STATISTIK DASHBOARD
$stat_programs = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
$stat_all_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$stat_all_orgs = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'organizer'")->fetchColumn();
$stat_pending_vols = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Menunggu'")->fetchColumn();

// Ambil Organisasi yang butuh validasi (Antrean)
$unvalidated_orgs = $pdo->query("SELECT * FROM users WHERE role = 'organizer' AND is_validated = 0 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$stat_pending_orgs = count($unvalidated_orgs);

// Ambil SEMUA Organisasi yang terdaftar di sistem
$all_organizations = $pdo->query("SELECT * FROM users WHERE role = 'organizer' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil Data Semua Program
$programs = $pdo->query("
    SELECT p.*, u.name AS organizer_name 
    FROM programs p 
    LEFT JOIN users u ON p.organizer_id = u.id 
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil Data Pelamar Khusus untuk Program Buatan VolunteerOne (organizer_id IS NULL)
$applications = $pdo->query("
    SELECT a.id as app_id, u.name as user_name, u.email, u.phone, u.location as user_location, u.description as user_desc, 
           p.name as prog_name, a.status, a.apply_date, a.motivation, a.cv_path
    FROM applications a 
    JOIN users u ON a.user_id = u.id 
    JOIN programs p ON a.program_id = p.id 
    WHERE p.organizer_id IS NULL
    ORDER BY CASE WHEN a.status = 'Menunggu' THEN 1 ELSE 2 END, a.apply_date DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin - VolunteerOne</title>
    <!-- Framework CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --vol-primary: #7a1c24;
            --vol-secondary: #5a1218;
            --vol-bg: #f7f1eb;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--vol-bg); color: #2C2C2C; }
        .card-cream { background-color: #ebdcc9; border: 1px solid rgba(255,255,255,0.6); }
        .bg-primary-vol { background-color: var(--vol-primary); }
        .text-primary-vol { color: var(--vol-primary); }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.02); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #dcbfa2; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--vol-primary); }
        
        .btn-primary-vol { 
            background: linear-gradient(135deg, var(--vol-primary) 0%, var(--vol-secondary) 100%); 
            color: white !important; 
            border-radius: 50px; 
            transition: all 0.3s ease; 
            box-shadow: 0 4px 15px rgba(122, 28, 36, 0.2); 
            border: none;
        }
        .btn-primary-vol:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(122, 28, 36, 0.3); color: white !important; }
        
        .input-form { 
            background-color: rgba(255,255,255,0.95); 
            border: 1px solid #dcbfa2; 
            border-radius: 12px; 
            padding: 10px 16px; 
            width: 100%; 
            outline: none; 
            transition: all 0.2s ease; 
            color: #2C2C2C;
            font-size: 0.9rem;
        }
        .input-form:focus { border-color: var(--vol-primary); box-shadow: 0 0 0 3px rgba(122, 28, 36, 0.15); background-color: #fff; }
        
        /* Animasi Toast */
        @keyframes slideDown { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .toast-animate { animation: slideDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        /* Modal Adjustments */
        .modal-overlay { background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1050; }
        
        /* Navbar Z-index fix */
        .admin-navbar { z-index: 1040 !important; position: sticky; top: 0; }
        
        /* Fix Sticky Form Sidebar */
        .sticky-sidebar { position: sticky; top: 100px; z-index: 10; }
    </style>
</head>
<body class="pb-12">

<!-- NAVBAR -->
<nav class="bg-primary-vol text-white p-4 shadow-lg admin-navbar">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white bg-opacity-25 p-2 rounded-lg backdrop-blur-sm"><i class="fas fa-shield-alt text-xl"></i></div>
            <h1 class="font-bold text-xl tracking-wide mb-0">Panel Admin Pusat</h1>
        </div>
        <div class="d-flex align-items-center gap-4">
            <span class="text-sm font-semibold d-none d-md-block">Halo, <?= htmlspecialchars($_SESSION['name']) ?></span>
            <a href="logout.php" class="btn btn-light rounded-pill text-primary-vol fw-bold px-4 py-2 text-sm shadow-sm transition hover:bg-gray-100">Logout</a>
        </div>
    </div>
</nav>

<!-- TOAST NOTIFICATION -->
<?php if($pesan): ?>
<div id="toastAlert" class="fixed top-20 left-1/2 transform -translate-x-1/2 z-50 toast-animate">
    <div class="<?= $tipe_pesan == 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700' ?> border-l-4 px-6 py-4 rounded-xl shadow-xl flex items-center gap-3">
        <i class="fas <?= $tipe_pesan == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-2xl"></i>
        <span class="font-bold"><?= $pesan ?></span>
    </div>
</div>
<script>setTimeout(() => { document.getElementById('toastAlert').style.opacity = '0'; setTimeout(()=>document.getElementById('toastAlert').remove(),500); }, 3500);</script>
<?php endif; ?>

<main class="container py-4 mt-4">
    
    <!-- DASHBOARD STATISTIK -->
    <h2 class="fs-4 fw-bold text-dark mb-4"><i class="fas fa-chart-pie text-primary-vol me-2"></i> Ringkasan Sistem</h2>
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="bg-white p-4 rounded-4 shadow-sm border flex items-center gap-4 hover:shadow-md transition h-100">
                <div class="bg-blue-50 text-blue-600 w-12 h-12 rounded-full flex items-center justify-center text-xl shrink-0"><i class="fas fa-bullhorn"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Program</p><h3 class="text-2xl font-black text-gray-800 m-0"><?= $stat_programs ?></h3></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="bg-white p-4 rounded-4 shadow-sm border flex items-center gap-4 hover:shadow-md transition h-100">
                <div class="bg-green-50 text-green-500 w-12 h-12 rounded-full flex items-center justify-center text-xl shrink-0"><i class="fas fa-user-check"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Relawan Individu</p><h3 class="text-2xl font-black text-gray-800 m-0"><?= $stat_all_users ?></h3></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="bg-white p-4 rounded-4 shadow-sm border flex items-center gap-4 hover:shadow-md transition h-100">
                <div class="bg-purple-50 text-purple-600 w-12 h-12 rounded-full flex items-center justify-center text-xl shrink-0"><i class="fas fa-building"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Organisasi Mitra</p><h3 class="text-2xl font-black text-gray-800 m-0"><?= $stat_all_orgs ?></h3></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="bg-white p-4 rounded-4 shadow-sm border flex items-center gap-4 hover:shadow-md transition h-100">
                <div class="bg-orange-50 text-orange-500 w-12 h-12 rounded-full flex items-center justify-center text-xl shrink-0"><i class="fas fa-clock"></i></div>
                <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Menunggu Diproses</p><h3 class="text-2xl font-black text-gray-800 m-0"><?= $stat_pending ?></h3></div>
            </div>
        </div>
    </div>

    <div class="row g-5">
        
        <!-- KOLOM KIRI: Form Buat Program Official -->
        <div class="col-lg-4">
            <div class="card-cream p-4 p-md-5 rounded-4 shadow-sm sticky-sidebar bg-white bg-opacity-75 backdrop-blur-md">
                <h3 class="fw-bold fs-5 mb-4 text-primary-vol flex items-center gap-2"><i class="fas fa-plus-circle fs-4"></i> Rilis Program Pusat</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase ml-1">Nama Kegiatan</label>
                        <input type="text" name="name" required class="input-form" placeholder="Cth: Relawan Admin">
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase ml-1">Kategori</label>
                        <select name="category" class="input-form bg-white">
                            <option value="Aksi Sosial">Aksi Sosial</option>
                            <option value="Pendidikan">Pendidikan</option>
                            <option value="Lingkungan">Lingkungan</option>
                            <option value="Kesehatan">Kesehatan</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase ml-1">Deskripsi Singkat</label>
                        <textarea name="description" required class="input-form" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase ml-1">Lokasi</label>
                        <input type="text" name="location" required class="input-form" placeholder="Alamat">
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="text-[11px] font-bold text-gray-500 uppercase ml-1">Tanggal</label><input type="date" name="prog_date" required class="input-form"></div>
                        <div class="col-6"><label class="text-[11px] font-bold text-gray-500 uppercase ml-1">Waktu</label><input type="time" name="prog_time" value="09:00" required class="input-form"></div>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase ml-1">Kuota</label>
                        <input type="number" name="quota" required min="1" class="input-form" placeholder="Maks.">
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-500 uppercase ml-1 block mb-1">Foto Sampul (Opsional)</label>
                        <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp" class="input-form text-xs bg-white cursor-pointer py-2">
                    </div>
                    <button type="submit" name="add_program" class="btn btn-primary-vol w-100 py-3 fw-bold shadow-sm mt-4">Publikasikan Program</button>
                </form>
            </div>
        </div>

        <!-- KOLOM KANAN: Manajemen TABS -->
        <div class="col-lg-8">
            <div class="flex gap-4 md:gap-6 border-b border-gray-200 mb-6 overflow-x-auto custom-scrollbar pb-1">
                <button onclick="switchTab('validasi')" id="btn-tab-validasi" class="pb-3 border-b-4 border-primary-vol font-bold text-primary-vol text-[15px] transition-colors whitespace-nowrap bg-transparent">
                    Seleksi Relawan
                    <?php if($stat_pending > 0): ?><span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full ml-1"><?= $stat_pending ?></span><?php endif; ?>
                </button>
                <button onclick="switchTab('organisasi')" id="btn-tab-organisasi" class="pb-3 border-b-4 border-transparent font-semibold text-gray-400 hover:text-gray-700 text-[15px] transition-colors whitespace-nowrap bg-transparent">
                    Antrean Validasi Org.
                    <?php if($stat_pending_orgs > 0): ?><span class="bg-orange-500 text-white text-[10px] px-2 py-0.5 rounded-full ml-1"><?= $stat_pending_orgs ?></span><?php endif; ?>
                </button>
                <button onclick="switchTab('daftar_org')" id="btn-tab-daftar_org" class="pb-3 border-b-4 border-transparent font-semibold text-gray-400 hover:text-gray-700 text-[15px] transition-colors whitespace-nowrap bg-transparent">
                    Daftar Semua Organisasi
                </button>
                <button onclick="switchTab('program')" id="btn-tab-program" class="pb-3 border-b-4 border-transparent font-semibold text-gray-400 hover:text-gray-700 text-[15px] transition-colors whitespace-nowrap bg-transparent">
                    Kelola Semua Program
                </button>
            </div>

            <!-- TAB 1: SELEKSI RELAWAN OFFICIAL -->
            <div id="tab-validasi" class="d-block">
                <div class="bg-white rounded-4 shadow-sm border overflow-hidden">
                    <div class="p-4 bg-blue-50 bg-opacity-50 border-b border-blue-100">
                        <p class="text-xs text-gray-600 font-medium leading-relaxed m-0"><i class="fas fa-info-circle text-blue-500 mr-1"></i> Admin hanya mengelola seleksi pendaftar untuk kegiatan <b>VolunteerOne Official</b>. Keputusan seleksi relawan pada kegiatan mitra, sepenuhnya merupakan hak akses mitra organisasi masing-masing.</p>
                    </div>

                    <?php if(count($applications) == 0): ?>
                        <div class="p-5 text-center text-gray-400 font-medium my-5">Belum ada relawan mendaftar di program Anda.</div>
                    <?php else: ?>
                        <div class="max-h-[600px] overflow-y-auto custom-scrollbar p-2">
                            <?php foreach($applications as $app): ?>
                                <div class="p-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:bg-gray-50 rounded-3xl transition border-b border-gray-50 last:border-0 <?= $app['status'] == 'Menunggu' ? 'bg-orange-50 bg-opacity-30' : '' ?>">
                                    <div class="flex items-start gap-4 flex-grow">
                                        <div class="w-12 h-12 rounded-full bg-red-50 text-primary-vol flex items-center justify-center font-bold text-xl shrink-0 mt-1">
                                            <?= strtoupper(substr($app['user_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <h5 class="font-bold text-gray-800 text-base leading-tight mb-1"><?= htmlspecialchars($app['user_name']) ?></h5>
                                            <p class="text-xs text-primary-vol font-bold mb-2"><i class="fas fa-bookmark mr-1 opacity-70"></i> <?= htmlspecialchars($app['prog_name']) ?></p>
                                            <button type="button" class="btn btn-link p-0 text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 text-decoration-none" onclick='showRelawanDetail(<?= json_encode($app) ?>)'>
                                                <i class="fas fa-id-card"></i> Tinjau Profil & Form Aplikasi
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="w-full md:w-auto flex flex-col items-end gap-2 shrink-0">
                                        <?php if($app['status'] == 'Menunggu'): ?>
                                            <form method="POST" class="flex gap-2 m-0">
                                                <input type="hidden" name="app_id" value="<?= $app['app_id'] ?>">
                                                <button type="submit" name="status" value="Disetujui" class="btn btn-sm bg-green-100 text-green-700 hover:bg-green-500 hover:text-white rounded-pill px-4 fw-bold transition shadow-sm border border-green-200"><i class="fas fa-check mr-1"></i> Terima</button>
                                                <button type="submit" name="status" value="Ditolak" class="btn btn-sm bg-red-100 text-red-700 hover:bg-red-500 hover:text-white rounded-pill px-4 fw-bold transition shadow-sm border border-red-200"><i class="fas fa-times mr-1"></i> Tolak</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge rounded-pill px-4 py-2 <?= $app['status'] == 'Disetujui' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                                <i class="fas <?= $app['status'] == 'Disetujui' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> <?= $app['status'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 2: ANTREAN VALIDASI ORGANISASI -->
            <div id="tab-organisasi" class="d-none">
                <div class="bg-white rounded-4 shadow-sm border overflow-hidden">
                    <?php if($stat_pending_orgs == 0): ?>
                        <div class="p-12 text-center text-gray-400 my-5">
                            <i class="fas fa-check-circle text-5xl mb-3 opacity-30"></i>
                            <p class="font-medium text-lg m-0">Tidak ada antrean validasi organisasi.</p>
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-orange-50 border-b border-orange-100 text-orange-700 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i> Organisasi di bawah ini tidak dapat mempublikasikan program sampai Anda menyetujuinya.
                        </div>
                        <div class="max-h-[600px] overflow-y-auto custom-scrollbar p-2">
                            <?php foreach($unvalidated_orgs as $org): ?>
                                <div class="p-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:bg-gray-50 rounded-3xl transition border-b border-gray-50 last:border-0 mx-2 my-1">
                                    <div class="flex items-start gap-4">
                                        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-500 flex items-center justify-center font-bold text-xl shrink-0 mt-1">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-bold text-gray-800 text-base leading-tight mb-1"><?= htmlspecialchars($org['name']) ?></h5>
                                            <p class="text-xs text-gray-600 mb-1"><i class="far fa-envelope mr-1"></i> <?= htmlspecialchars($org['email']) ?></p>
                                            <p class="text-xs text-gray-500 font-medium m-0"><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($org['phone']) ?></p>
                                        </div>
                                    </div>
                                    <div class="w-full md:w-auto flex justify-end">
                                        <form method="POST" class="m-0">
                                            <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                            <button type="submit" name="validate_org" class="btn btn-sm btn-primary-vol rounded-pill px-4 fw-bold shadow-sm flex items-center gap-2"><i class="fas fa-check-circle"></i> Setujui & Aktifkan</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: DAFTAR SEMUA ORGANISASI TERDAFTAR -->
            <div id="tab-daftar_org" class="d-none">
                <div class="row g-4">
                    <?php foreach($all_organizations as $org): ?>
                        <div class="col-md-6">
                            <div class="bg-white border p-4 rounded-4 shadow-sm h-100 d-flex flex-column">
                                <div class="flex justify-between items-start mb-3">
                                    <h5 class="font-bold text-gray-800 text-base line-clamp-1 pr-2 m-0"><?= htmlspecialchars($org['name']) ?></h5>
                                    <?php if($org['is_validated'] == 1): ?>
                                        <span class="badge bg-green-100 text-green-700 border border-green-200 shrink-0">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-red-100 text-red-600 border border-red-200 shrink-0">Belum Valid</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 mb-2"><i class="fas fa-envelope text-gray-400 w-4"></i> <?= htmlspecialchars($org['email']) ?></p>
                                <p class="text-xs text-gray-500 mb-3"><i class="fas fa-phone text-gray-400 w-4"></i> <?= htmlspecialchars($org['phone']) ?></p>
                                
                                <div class="bg-gray-50 p-2 rounded-xl border border-gray-100 text-xs text-gray-600 font-medium mb-3">
                                    <i class="fas fa-map-marker-alt text-primary-vol mr-1"></i> <?= htmlspecialchars($org['location'] ?? 'Lokasi belum diatur') ?>
                                </div>

                                <div class="mt-auto">
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 rounded-pill fw-bold text-xs" onclick='showOrgDetail(<?= htmlspecialchars(json_encode($org), ENT_QUOTES, 'UTF-8') ?>)'>
                                        <i class="fas fa-eye me-1"></i> Lihat Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(count($all_organizations) == 0): ?>
                        <div class="col-12 p-5 text-center bg-white rounded-4 text-gray-400 border my-4">
                            Belum ada organisasi yang terdaftar di platform.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 4: KELOLA SEMUA PROGRAM -->
            <div id="tab-program" class="d-none">
                <div class="row g-4">
                    <?php foreach($programs as $p): ?>
                        <?php 
                            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id = ?");
                            $stmtC->execute([$p['id']]);
                            $pendaftar = $stmtC->fetchColumn();
                            
                            $is_official = is_null($p['organizer_id']);
                            $owner_name = $is_official ? 'VolunteerOne Official' : $p['organizer_name'];
                        ?>
                        <div class="col-md-6">
                            <div class="bg-white border p-4 rounded-4 shadow-sm hover:shadow-md transition relative flex flex-col h-100">
                                
                                <div class="flex justify-between items-start mb-3">
                                    <span class="bg-gray-100 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider inline-block">ID: #<?= $p['id'] ?></span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider <?= $is_official ? 'text-blue-600 bg-blue-50' : 'text-orange-600 bg-orange-50' ?> px-2 py-0.5 rounded">
                                        <i class="fas <?= $is_official ? 'fa-shield-alt' : 'fa-building' ?>"></i> <?= htmlspecialchars($owner_name) ?>
                                    </span>
                                </div>
                                
                                <h5 class="font-extrabold text-gray-800 text-base mb-2 leading-snug line-clamp-2"><?= htmlspecialchars($p['name']) ?></h5>
                                
                                <div class="space-y-1 mb-4 text-xs text-gray-600 font-medium flex-grow">
                                    <p class="m-0"><i class="fas fa-map-marker-alt text-primary-vol w-4 text-center"></i> <span class="line-clamp-1 align-bottom inline-block w-[85%]"><?= htmlspecialchars($p['location']) ?></span></p>
                                    <p class="m-0"><i class="far fa-calendar-alt text-primary-vol w-4 text-center"></i> <?= date('d M Y', strtotime($p['prog_date'])) ?></p>
                                </div>
                                
                                <div class="bg-gray-50 p-2 rounded-xl flex justify-between items-center border border-gray-100 mb-3">
                                    <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Pendaftar</div>
                                    <div class="font-black text-primary-vol text-base"><?= $pendaftar ?> <span class="text-[10px] text-gray-400 font-semibold">/ <?= $p['quota'] ?></span></div>
                                </div>

                                <div class="flex gap-2 mt-auto">
                                    <button type="button" onclick='openEditModalAdmin(<?= json_encode($p) ?>)' class="btn btn-light btn-sm flex-1 rounded-pill fw-bold text-xs border text-gray-700 hover:bg-gray-200">Edit Info</button>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Peringatan: Menghapus program ini akan menghapus semua data pendaftar terkait. Lanjutkan?');">
                                        <input type="hidden" name="prog_id" value="<?= $p['id'] ?>">
                                        <button type="submit" name="delete_program" class="btn btn-sm bg-red-50 text-red-500 hover:bg-red-500 hover:text-white rounded-pill px-3 transition border border-red-100">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(count($programs) == 0): ?>
                        <div class="col-12 p-5 text-center bg-white rounded-4 text-gray-400 border my-4">
                            Belum ada program di sistem.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MODAL BACA PROFIL RELAWAN -->
<div class="modal fade" id="modalRelawan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-primary-vol text-white border-0 p-4">
                <h5 class="modal-title fw-bold fs-6"><i class="fas fa-user-circle me-2"></i> Detail Profil Pelamar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="flex items-center gap-3 mb-4 pb-4 border-bottom">
                    <div class="w-14 h-14 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold text-2xl shrink-0" id="mr_avatar">U</div>
                    <div>
                        <h4 class="fw-bold text-lg text-gray-800 leading-tight mb-1" id="mr_name">Nama Relawan</h4>
                        <p class="text-xs text-gray-500 m-0" id="mr_contact">email@mail.com</p>
                    </div>
                </div>
                
                <h5 class="font-bold text-[10px] uppercase text-gray-400 tracking-wider mb-1">Domisili</h5>
                <p class="text-sm font-medium text-gray-700 mb-3" id="mr_location"><i class="fas fa-map-marker-alt text-primary-vol w-4"></i> Lokasi</p>
                
                <h5 class="font-bold text-[10px] uppercase text-gray-400 tracking-wider mb-1">Pengalaman / Deskripsi Diri</h5>
                <div class="bg-gray-50 p-3 rounded-xl text-xs text-gray-700 italic mb-4 border border-gray-100" id="mr_desc"></div>

                <h5 class="font-bold text-[10px] uppercase text-gray-400 tracking-wider mb-1">Motivasi Mendaftar (Aplikasi)</h5>
                <div class="bg-blue-50 p-3 rounded-xl text-xs text-gray-800 mb-4 border border-blue-100" id="mr_motivation"></div>

                <div id="mr_cv_area" class="mt-2">
                    <!-- Tombol Download CV Injected Here -->
                </div>
            </div>
            <div class="modal-footer p-3 bg-light border-top-0">
                <button type="button" class="btn btn-secondary btn-sm w-100 fw-bold rounded-pill" data-bs-dismiss="modal">Tutup Profil</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL BACA DETAIL ORGANISASI -->
<div class="modal fade" id="modalDetailOrg" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-primary-vol text-white border-0 p-4">
                <h5 class="modal-title fw-bold fs-6"><i class="fas fa-building me-2"></i> Profil Organisasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4 pb-4 border-bottom">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-secondary border flex-shrink-0" style="width: 60px; height: 60px;">
                        <i class="fas fa-building fs-3 text-muted"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold fs-5 text-dark mb-1" id="mdo_name">Nama Organisasi</h4>
                        <span class="badge bg-danger text-uppercase" id="mdo_type" style="font-size: 0.65rem;">Tipe</span>
                        <span id="mdo_status" class="ms-1"></span>
                    </div>
                </div>

                <div class="row g-3 mb-4 bg-light p-2 rounded-3 border mx-0">
                    <div class="col-6">
                        <p class="fw-bold text-muted text-uppercase mb-1" style="font-size:0.65rem; letter-spacing:1px;">Email / Telepon</p>
                        <p class="text-dark fw-medium mb-0" style="font-size:0.75rem;" id="mdo_contact"></p>
                    </div>
                    <div class="col-6 border-start">
                        <p class="fw-bold text-muted text-uppercase mb-1" style="font-size:0.65rem; letter-spacing:1px;">Berdiri Sejak</p>
                        <p class="text-dark fw-medium mb-0" style="font-size:0.75rem;" id="mdo_est"></p>
                    </div>
                </div>

                <h6 class="fw-bold text-muted text-uppercase mb-2" style="font-size:0.7rem; letter-spacing:1px;">Website & Lokasi Markas</h6>
                <p class="text-dark fw-medium mb-4" style="font-size:0.8rem;" id="mdo_locweb"></p>

                <h6 class="fw-bold text-muted text-uppercase mb-2" style="font-size:0.7rem; letter-spacing:1px;">Profil / Tentang Organisasi</h6>
                <div class="bg-info bg-opacity-10 border border-info border-opacity-25 p-3 rounded-3 text-dark mb-2" style="font-size:0.8rem;" id="mdo_desc"></div>
            </div>
            <div class="modal-footer p-3 bg-light border-top-0">
                <button type="button" class="btn btn-secondary btn-sm w-100 fw-bold rounded-pill" data-bs-dismiss="modal">Tutup Profil</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDIT PROGRAM OLEH ADMIN -->
<div class="modal fade" id="modalEditAdmin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg flex flex-col max-h-[90vh]">
            <div class="modal-header bg-primary-vol text-white border-0 p-4 shrink-0">
                <h5 class="modal-title fw-bold fs-6"><i class="fas fa-edit me-2"></i> Edit Data Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-0 overflow-y-auto custom-scrollbar">
                <form method="POST" enctype="multipart/form-data" class="p-4 space-y-3 m-0">
                    <input type="hidden" name="prog_id" id="ea_id">
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase ml-1">Nama Kegiatan</label>
                        <input type="text" name="name" id="ea_name" required class="input-form bg-gray-50 py-2">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase ml-1">Kategori Isu</label>
                        <select name="category" id="ea_category" class="input-form bg-gray-50 py-2">
                            <option value="Aksi Sosial">Aksi Sosial</option>
                            <option value="Pendidikan">Pendidikan</option>
                            <option value="Lingkungan">Lingkungan</option>
                            <option value="Kesehatan">Kesehatan</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase ml-1">Deskripsi</label>
                        <textarea name="description" id="ea_desc" required class="input-form bg-gray-50 py-2" rows="3"></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase ml-1">Lokasi</label>
                        <input type="text" name="location" id="ea_loc" required class="input-form bg-gray-50 py-2">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="text-[10px] font-bold text-gray-500 uppercase ml-1">Tanggal</label>
                            <input type="date" name="prog_date" id="ea_date" required class="input-form bg-gray-50 py-2">
                        </div>
                        <div class="col-6">
                            <label class="text-[10px] font-bold text-gray-500 uppercase ml-1">Waktu</label>
                            <input type="time" name="prog_time" id="ea_time" required class="input-form bg-gray-50 py-2">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase ml-1 block mb-1">Kuota</label>
                        <input type="number" name="quota" id="ea_quota" required min="1" class="input-form bg-gray-50 py-2">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-500 uppercase ml-1 block mb-1">Ganti Foto Sampul (Opsional)</label>
                        <input type="file" name="edit_image_file" accept=".jpg,.jpeg,.png,.webp" class="input-form text-xs bg-gray-50 cursor-pointer py-1.5">
                    </div>
                    
                    <div class="pt-3 mt-2 border-top flex gap-2 shrink-0">
                        <button type="button" class="btn btn-light btn-sm flex-1 rounded-pill fw-bold text-gray-600 border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_program" class="btn btn-primary-vol btn-sm flex-1 rounded-pill fw-bold shadow-sm">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Menggunakan JS Bootstrap Asli untuk Modal agar tidak konflik Tailwind -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function switchTab(tabName) {
        // Hide all
        document.getElementById('tab-validasi').classList.remove('d-block');
        document.getElementById('tab-validasi').classList.add('d-none');
        document.getElementById('tab-organisasi').classList.remove('d-block');
        document.getElementById('tab-organisasi').classList.add('d-none');
        document.getElementById('tab-program').classList.remove('d-block');
        document.getElementById('tab-program').classList.add('d-none');
        document.getElementById('tab-daftar_org').classList.remove('d-block');
        document.getElementById('tab-daftar_org').classList.add('d-none');
        
        // Buttons
        const btnVal = document.getElementById('btn-tab-validasi');
        const btnOrg = document.getElementById('btn-tab-organisasi');
        const btnProg = document.getElementById('btn-tab-program');
        const btnDaftarOrg = document.getElementById('btn-tab-daftar_org');
        
        const classInactive = "pb-3 border-b-4 border-transparent font-semibold text-gray-400 hover:text-gray-700 text-[15px] transition-colors whitespace-nowrap bg-transparent";
        const classActive = "pb-3 border-b-4 border-primary-vol font-bold text-primary-vol text-[15px] transition-colors whitespace-nowrap bg-transparent";

        btnVal.className = classInactive;
        btnOrg.className = classInactive;
        btnProg.className = classInactive;
        btnDaftarOrg.className = classInactive;

        // Show active
        if(tabName === 'validasi') {
            document.getElementById('tab-validasi').classList.remove('d-none');
            document.getElementById('tab-validasi').classList.add('d-block');
            btnVal.className = classActive;
        } else if (tabName === 'organisasi') {
            document.getElementById('tab-organisasi').classList.remove('d-none');
            document.getElementById('tab-organisasi').classList.add('d-block');
            btnOrg.className = classActive;
        } else if (tabName === 'daftar_org') {
            document.getElementById('tab-daftar_org').classList.remove('d-none');
            document.getElementById('tab-daftar_org').classList.add('d-block');
            btnDaftarOrg.className = classActive;
        } else {
            document.getElementById('tab-program').classList.remove('d-none');
            document.getElementById('tab-program').classList.add('d-block');
            btnProg.className = classActive;
        }
    }

    // Modal Edit Admin (Menggunakan JS Bootstrap)
    function openEditModalAdmin(program) {
        document.getElementById('ea_id').value = program.id;
        document.getElementById('ea_name').value = program.name;
        document.getElementById('ea_desc').value = program.description;
        document.getElementById('ea_loc').value = program.location;
        document.getElementById('ea_date').value = program.prog_date;
        document.getElementById('ea_time').value = program.prog_time ? program.prog_time : '10:00';
        document.getElementById('ea_quota').value = program.quota;
        
        const catSelect = document.getElementById('ea_category');
        if(program.category) {
            for(let i=0; i<catSelect.options.length; i++) {
                if(catSelect.options[i].value === program.category) {
                    catSelect.selectedIndex = i; break;
                }
            }
        }
        
        var editModal = new bootstrap.Modal(document.getElementById('modalEditAdmin'));
        editModal.show();
    }

    // Modal Detail Pelamar (Menggunakan JS Bootstrap)
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
            cvArea.innerHTML = `<a href="${app.cv_path}" download class="btn btn-dark btn-sm w-100 fw-bold rounded-pill shadow-sm py-2"><i class="fas fa-download me-1"></i> Unduh CV / Resume Pelamar</a>`;
        } else {
            cvArea.innerHTML = `<button disabled class="btn btn-light btn-sm w-100 fw-bold rounded-pill text-muted py-2 border">Pelamar Tidak Melampirkan CV</button>`;
        }

        var relawanModal = new bootstrap.Modal(document.getElementById('modalRelawan'));
        relawanModal.show();
    }

    // Modal Detail Organisasi (Menggunakan JS Bootstrap)
    function showOrgDetail(org) {
        document.getElementById('mdo_name').innerText = org.name;
        document.getElementById('mdo_type').innerText = org.org_type ? org.org_type : 'Organisasi';
        
        const statusBadge = org.is_validated == 1 
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success ms-1">Aktif</span>' 
            : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger ms-1">Belum Valid</span>';
        document.getElementById('mdo_status').innerHTML = statusBadge;

        document.getElementById('mdo_contact').innerHTML = `${org.email}<br>${org.phone}`;

        let estDate = "Belum diatur";
        if (org.established_date) {
            const d = new Date(org.established_date);
            estDate = d.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
        }
        document.getElementById('mdo_est').innerText = estDate;

        const loc = org.location ? org.location : 'Lokasi belum diatur';
        const web = org.website ? `<a href="${org.website}" target="_blank" class="text-decoration-none fw-bold" style="color: var(--vol-primary);">${org.website}</a>` : 'Website belum diatur';
        document.getElementById('mdo_locweb').innerHTML = `<i class="fas fa-map-marker-alt text-muted me-1"></i> ${loc}<br><i class="fas fa-link text-muted me-1 mt-2"></i> ${web}`;

        const desc = org.description ? org.description : "<i>Organisasi ini belum melengkapi deskripsi profil mereka.</i>";
        document.getElementById('mdo_desc').innerHTML = desc;

        var orgModal = new bootstrap.Modal(document.getElementById('modalDetailOrg'));
        orgModal.show();
    }
</script>
</body>
</html>