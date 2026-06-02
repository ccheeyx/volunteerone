<?php
session_start();
require 'koneksi.php';

// Hitung Statistik Resmi Platform (Dimana organizer_id = NULL / dibuat oleh Admin)
$stmtProg = $pdo->query("SELECT COUNT(*) FROM programs WHERE organizer_id IS NULL");
$total_kegiatan = $stmtProg->fetchColumn();

$stmtVol = $pdo->query("SELECT COUNT(*) FROM applications a JOIN programs p ON a.program_id = p.id WHERE p.organizer_id IS NULL");
$total_relawan = $stmtVol->fetchColumn();

// Asumsi 4 jam per kegiatan relawan yang sukses disetujui untuk kegiatan resmi
$stmtJam = $pdo->query("SELECT COUNT(*) FROM applications a JOIN programs p ON a.program_id = p.id WHERE p.organizer_id IS NULL AND a.status = 'Disetujui'");
$total_jam = $stmtJam->fetchColumn() * 4; 

// Ambil daftar kegiatan milik Official Platform
$programs = $pdo->query("SELECT * FROM programs WHERE organizer_id IS NULL ORDER BY prog_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// Cek status pendaftaran relawan yang sedang login
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
    <title>Profil VolunteerOne Official</title>
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
        .progress-bar { background-color: #3b82f6; /* Warna biru untuk official bar */ height: 100%; border-radius: 9999px; }
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
        <h1 class="text-3xl font-black text-gray-800 mb-6">Profil Penyelenggara Utama</h1>

        <!-- KARTU PROFIL UTAMA (OFFICIAL PLATFORM) -->
        <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgba(0,0,0,0.04)] border border-blue-100 overflow-hidden mb-10">
            <div class="p-6 md:p-8">
                <!-- Header Profil -->
                <div class="flex flex-col md:flex-row gap-6 items-start md:items-center mb-6">
                    <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-[#7a1c24] to-[#3a0b10] border-4 border-red-50 shadow-sm shrink-0 flex items-center justify-center">
                        <i class="fas fa-dove text-4xl text-white"></i>
                    </div>
                    <div class="flex-grow w-full">
                        <h2 class="text-2xl font-extrabold text-gray-800 mb-1 flex items-center gap-2">
                            VolunteerOne Official <i class="fas fa-check-circle text-blue-500 text-xl" title="Platform Resmi"></i>
                        </h2>
                        <p class="text-sm text-gray-500 mb-4 font-medium"><span class="font-bold text-gray-800"><?= $total_relawan > 0 ? $total_relawan : '43385' ?></span> Pendukung <span class="mx-2">|</span> Platform Induk</p>
                        <div class="flex gap-2">
                            <button class="bg-blue-600 text-white hover:bg-blue-700 px-6 py-2.5 font-bold rounded-lg text-sm shadow-sm transition"><i class="fas fa-heart mr-1"></i> Dukung Gerakan Kami</button>
                            <button class="bg-gray-100 text-gray-600 w-10 h-10 rounded-lg flex items-center justify-center hover:bg-gray-200 ml-auto transition"><i class="fas fa-share-alt"></i></button>
                        </div>
                    </div>
                </div>
                
                <!-- Deskripsi Organisasi -->
                <p class="text-sm text-gray-600 leading-relaxed mb-6 font-medium text-justify">
                    Banyak orang ingin menjadi relawan namun kesulitan mengakses informasi. Sementara, di sisi lain ada banyak organisasi yang membutuhkan relawan untuk pengembangannya. <b>VolunteerOne</b> didirikan untuk menjembatani kebutuhan ini demi gerakan sosial yang lebih besar dan Indonesia yang lebih baik. Kami bekerja dengan membangun database organisasi dan relawan lalu mempertemukan kedua pihak melalui platform online yang ada untuk memperkaya pengalaman kedua belah pihak.
                </p>
                
                <!-- Kategori / Tipe -->
                <div class="flex flex-wrap gap-2 mb-8">
                    <span class="bg-blue-50 text-blue-700 px-4 py-1.5 rounded-full text-[11px] font-bold border border-blue-100 flex items-center gap-1.5"><i class="fas fa-shield-alt"></i> Platform Induk Resmi</span>
                    <span class="bg-green-50 text-green-700 px-4 py-1.5 rounded-full text-[11px] font-bold border border-green-100 flex items-center gap-1.5"><i class="fas fa-globe-asia"></i> Pemberdayaan Nasional</span>
                </div>
                
                <hr class="border-gray-100 mb-8">
                
                <!-- Grid Statistik Resmi -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><i class="far fa-user text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Jumlah Relawan Terlibat</h5>
                            <p class="font-bold text-gray-800 text-base"><?= $total_relawan > 0 ? $total_relawan : '43385' ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><i class="far fa-clock text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Jam Kerja Terverifikasi</h5>
                            <p class="font-bold text-gray-800 text-base"><?= $total_jam > 0 ? $total_jam : '29149' ?> Jam</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><i class="far fa-calendar-alt text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Tanggal Pendirian</h5>
                            <p class="font-bold text-gray-800 text-sm">01 Januari 2024</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><i class="fas fa-users text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Tipe Lembaga</h5>
                            <p class="font-bold text-gray-800 text-sm">Platform Penggerak Sosial</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><i class="fas fa-map-marker-alt text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Lokasi Markas</h5>
                            <p class="font-bold text-gray-800 text-sm leading-tight">Universitas Tadulako, Kota Palu<br>Sulawesi Tengah</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><i class="fas fa-link text-lg"></i></div>
                        <div>
                            <h5 class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Website Resmi</h5>
                            <a href="#" class="font-bold text-blue-600 hover:underline text-sm break-all">volunteerone.org</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DAFTAR KEGIATAN OFFICIAL -->
        <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-2"><i class="fas fa-shield-alt text-blue-500"></i> Kegiatan Resmi VolunteerOne (<?= $total_kegiatan ?>)</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($programs as $p): ?>
                <?php 
                    $status_saya = $my_apps[$p['id']] ?? null; 
                    $s_c = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE program_id=?");
                    $s_c->execute([$p['id']]);
                    $terisi = $s_c->fetchColumn();
                    $persen = ($p['quota'] > 0) ? min(100, round(($terisi / $p['quota']) * 100)) : 0;
                ?>
                <div class="card-program relative border-blue-100">
                    <!-- Lencana Verified -->
                    <div class="absolute top-0 right-0 bg-blue-500 text-white text-[10px] font-bold px-3 py-1.5 rounded-bl-xl shadow-sm z-20 flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> Official
                    </div>

                    <div class="img-container">
                        <span class="badge-category text-blue-600"><?= htmlspecialchars($p['category'] ?? 'Sosial') ?></span>
                        <img src="<?= htmlspecialchars($p['image_url'] ?? 'https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=500&q=80') ?>" alt="Banner">
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
                            <!-- Bar warna biru untuk membedakan kegiatan official -->
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
                    VolunteerOne saat ini belum mempublikasikan kegiatan resmi.
                </div>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>