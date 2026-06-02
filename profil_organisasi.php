<?php
session_start();
require 'koneksi.php';

// Cek apakah ada ID organisasi yang dikirim via URL
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$org_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'organizer'");
$stmt->execute([$org_id]);
$org = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$org) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h3>Organisasi Tidak Ditemukan</h3><a href='dashboard.php'>Kembali</a></div>");
}

$stmtProg = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE organizer_id = ?");
$stmtProg->execute([$org_id]);
$total_kegiatan = $stmtProg->fetchColumn();

$stmtVol = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN programs p ON a.program_id = p.id WHERE p.organizer_id = ?");
$stmtVol->execute([$org_id]);
$total_relawan = $stmtVol->fetchColumn();

// Asumsi 4 jam per kegiatan relawan yang sukses disetujui
$stmtJam = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN programs p ON a.program_id = p.id WHERE p.organizer_id = ? AND a.status = 'Disetujui'");
$stmtJam->execute([$org_id]);
$total_jam = $stmtJam->fetchColumn() * 4; 

$programs = $pdo->query("SELECT * FROM programs WHERE organizer_id = '$org_id' ORDER BY prog_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$my_apps = [];
if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'user') {
    $stmtHistory = $pdo->prepare("SELECT program_id, status FROM applications WHERE user_id = ?");
    $stmtHistory->execute([$_SESSION['user_id']]);
    $history = $stmtHistory->fetchAll();
    foreach($history as $h) {
        $my_apps[$h['program_id']] = $h['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil <?= htmlspecialchars($org['name']) ?> - VolunteerOne</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --vol-primary: #7a1c24; --vol-primary-hover: #5a1218; --vol-bg: #f8f9fa; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--vol-bg); }
        .bg-primary-vol { background-color: var(--vol-primary); }
        .text-primary-vol { color: var(--vol-primary); }
        .card-program { background-color: white; border-radius: 12px; overflow: hidden; border: 1px solid #eaeaea; transition: all 0.3s ease; display: flex; flex-direction: column; }
        .card-program:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.06); border-color: #d1d5db; }
        .img-container { position: relative; height: 160px; width: 100%; overflow: hidden; background: #e5e7eb; }
        .img-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .card-program:hover .img-container img { transform: scale(1.05); }
        .badge-category { position: absolute; top: 12px; left: 12px; background: rgba(255,255,255,0.95); color: var(--vol-primary); padding: 4px 12px; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-maroon { background-color: var(--vol-primary); color: white; border-radius: 8px; font-weight: 700; transition: 0.2s; text-align: center; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-maroon:hover { background-color: var(--vol-primary-hover); }
        .progress-bg { width: 100%; background-color: #f3f4f6; border-radius: 9999px; height: 6px; overflow: hidden; margin-top: 4px; }
        .progress-bar { background-color: var(--vol-primary); height: 100%; border-radius: 9999px; }
    </style>
</head>
<body class="pb-12">

    <!-- NAVBAR SEDERHANA -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-50 py-4 px-6 shadow-sm">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <a href="dashboard.php" class="text-gray-500 hover:text-[#7a1c24] font-bold text-sm transition"><i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard</a>
            <div class="font-extrabold text-xl text-[#7a1c24] flex items-center gap-2"><i class="fas fa-hands-helping"></i> VolunteerOne</div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto p-4 md:p-8 mt-4">
        <h1 class="text-3xl font-black text-gray-800 mb-6">Profil Organisasi</h1>

        <!-- KARTU PROFIL UTAMA -->
        <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgba(0,0,0,0.04)] border border-gray-100 overflow-hidden mb-10">
            <div class="p-6 md:p-8">
                <!-- Header Profil -->
                <div class="flex flex-col md:flex-row gap-6 items-start md:items-center mb-6">
                    <img src="https://api.dicebear.com/7.x/initials/svg?seed=<?= urlencode($org['name']) ?>&backgroundColor=ebdcc9" class="w-24 h-24 rounded-2xl border-4 border-gray-50 shadow-sm shrink-0">
                    <div class="flex-grow w-full">
                        <h2 class="text-2xl font-extrabold text-gray-800 mb-1"><?= htmlspecialchars($org['name']) ?></h2>
                        <p class="text-sm text-gray-500 mb-4 font-medium"><span class="font-bold text-gray-800"><?= $total_relawan ?></span> Pendukung <span class="mx-2">|</span> Mitra Penyelenggara</p>
                        <div class="flex gap-2">
                            <button class="bg-[#7a1c24] text-white hover:bg-[#5a1218] px-6 py-2.5 font-bold rounded-lg text-sm shadow-sm transition">Dukung Organisasi</button>
                            <a href="mailto:<?= htmlspecialchars($org['email']) ?>" class="border border-[#7a1c24] text-[#7a1c24] hover:bg-red-50 px-6 py-2.5 font-bold rounded-lg text-sm transition">Kontak</a>
                            <button class="bg-gray-100 text-gray-600 w-10 h-10 rounded-lg flex items-center justify-center hover:bg-gray-200 ml-auto transition"><i class="fas fa-share-alt"></i></button>
                        </div>
                    </div>
                </div>
                
                <!-- Deskripsi Organisasi -->
                <p class="text-sm text-gray-600 leading-relaxed mb-6 font-medium text-justify">
                    <?= !empty($org['description']) ? nl2br(htmlspecialchars($org['description'])) : '<i>Organisasi ini belum menambahkan deskripsi tentang misi dan visi mereka.</i>' ?>
                </p>
                
                <!-- Kategori / Tipe -->
                <div class="flex flex-wrap gap-2 mb-8">
                    <span class="bg-orange-50 text-orange-700 px-4 py-1.5 rounded-full text-[11px] font-bold border border-orange-100 flex items-center gap-1.5"><i class="fas fa-building"></i> <?= htmlspecialchars($org['org_type'] ?? 'Komunitas') ?></span>
                </div>
                
                <hr class="border-gray-100 mb-8">
                
                <!-- Grid Statistik ala Indorelawan (Style Maroon/Cream) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-red-50 text-[#7a1c24] flex items-center justify-center shrink-0"><i class="far fa-user text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Jumlah Relawan</h5>
                            <p class="font-bold text-gray-800 text-base"><?= $total_relawan ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-red-50 text-[#7a1c24] flex items-center justify-center shrink-0"><i class="far fa-clock text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Jam Kerja</h5>
                            <p class="font-bold text-gray-800 text-base"><?= $total_jam ?> Jam</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-red-50 text-[#7a1c24] flex items-center justify-center shrink-0"><i class="far fa-calendar-alt text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Tanggal Pendirian</h5>
                            <p class="font-bold text-gray-800 text-sm"><?= !empty($org['established_date']) ? date('d F Y', strtotime($org['established_date'])) : '-' ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-red-50 text-[#7a1c24] flex items-center justify-center shrink-0"><i class="fas fa-users text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Tipe Lembaga</h5>
                            <p class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($org['org_type'] ?? '-') ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-red-50 text-[#7a1c24] flex items-center justify-center shrink-0"><i class="fas fa-map-marker-alt text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Lokasi Markas</h5>
                            <p class="font-bold text-gray-800 text-sm"><?= !empty($org['location']) ? htmlspecialchars($org['location']) : '-' ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-red-50 text-[#7a1c24] flex items-center justify-center shrink-0"><i class="fas fa-link text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Website Resmi</h5>
                            <?php if(!empty($org['website'])): ?>
                                <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="font-bold text-[#7a1c24] hover:underline text-sm break-all"><?= htmlspecialchars($org['website']) ?></a>
                            <?php else: ?>
                                <p class="font-bold text-gray-800 text-sm">-</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DAFTAR KEGIATAN OLEH ORGANISASI INI -->
        <h3 class="text-2xl font-bold text-gray-800 mb-6">Aktivitas (<?= $total_kegiatan ?>)</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($programs as $p): ?>
                <?php 
                    $status_saya = $my_apps[$p['id']] ?? null; 
                    $s_c = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id=?");
                    $s_c->execute([$p['id']]);
                    $terisi = $s_c->fetchColumn();
                    $persen = ($p['quota'] > 0) ? min(100, round(($terisi / $p['quota']) * 100)) : 0;
                ?>
                <div class="card-program">
                    <div class="img-container">
                        <span class="badge-category"><?= htmlspecialchars($p['category'] ?? 'Sosial') ?></span>
                        <img src="<?= htmlspecialchars($p['image_url'] ?? 'https://images.unsplash.com/photo-1593113589914-075990190da4?w=500&q=80') ?>" alt="Banner">
                    </div>
                    <div class="p-5 flex flex-col flex-grow">
                        <h4 class="font-bold text-gray-900 text-base leading-snug mb-3 line-clamp-2"><?= htmlspecialchars($p['name']) ?></h4>
                        
                        <div class="space-y-2 text-xs text-gray-600 mb-5 font-medium flex-grow">
                            <p class="flex items-start gap-2"><i class="far fa-calendar-alt w-4 text-center mt-0.5 text-gray-400"></i> <?= date('d M Y', strtotime($p['prog_date'])) ?></p>
                            <p class="flex items-start gap-2"><i class="fas fa-map-marker-alt w-4 text-center mt-0.5 text-gray-400"></i> <span class="line-clamp-1"><?= htmlspecialchars($p['location']) ?></span></p>
                        </div>
                        
                        <div class="mt-auto">
                            <div class="flex justify-between text-[11px] font-bold text-gray-500 mb-1">
                                <span>Terkumpul: <span class="text-gray-800"><?= $terisi ?></span></span>
                                <span>Target: <?= $p['quota'] ?></span>
                            </div>
                            <div class="progress-bg mb-4"><div class="progress-bar" style="width: <?= $persen ?>%"></div></div>

                            <div class="border-t border-gray-100 pt-4">
                                <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user'): ?>
                                    <button onclick="alert('Silakan login sebagai Relawan untuk mendaftar.')" class="w-full bg-gray-100 text-gray-500 py-2.5 rounded-lg font-bold text-sm">MASUK UNTUK MENDAFTAR</button>
                                <?php elseif($status_saya): ?>
                                    <button disabled class="w-full bg-gray-100 text-gray-500 py-2.5 rounded-lg font-bold text-sm uppercase">Status: <?= $status_saya ?></button>
                                <?php else: ?>
                                    <form action="dashboard.php" method="POST">
                                        <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                        <!-- Tombol CTA Sesuai Konsep -->
                                        <button type="submit" name="apply_program" class="btn-maroon w-full py-2.5 text-sm shadow-md">JADI RELAWAN</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if(count($programs) == 0): ?>
                <div class="col-span-full text-center py-12 text-gray-500 font-medium bg-white rounded-2xl border border-gray-100 shadow-sm">
                    Organisasi ini belum mempublikasikan kegiatan.
                </div>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>