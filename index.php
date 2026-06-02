<?php
require 'koneksi.php';

// ... (kode redirect session tetap sama) ...

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password_input = $_POST['password'];

    try {
        // Cari user berdasarkan email saja
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verifikasi kecocokan password input dengan password hash di database
        if ($user && password_verify($password_input, $user['password'])) {
            
            if ($user['role'] == 'organizer' && isset($user['is_validated']) && $user['is_validated'] == 0) {
                $error = "Login ditolak! Akun Organisasi Anda masih dalam antrean peninjauan oleh Admin Pusat.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] == 'admin') header("Location: admin.php");
                elseif ($user['role'] == 'organizer') header("Location: organisasi.php");
                else header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = "Email atau password yang Anda masukkan salah!";
        }
    } catch (PDOException $e) {
        $error = "Terjadi masalah koneksi sistem: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VolunteerOne - Ambil Peranmu Hari Ini</title>
    
    <!-- 1. FRAMEWORK BOOTSTRAP CSS (Untuk Struktur Grid & Layout Dasar) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 2. FRAMEWORK TAILWIND CSS (Untuk Detail Styling, Warna, dan Animasi) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome Icons & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Konfigurasi untuk menyelaraskan Bootstrap & Tailwind */
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f7f1eb; }
        
        /* Custom Button overriding Bootstrap defaults */
        .btn-maroon {
            background: linear-gradient(135deg, #7a1c24 0%, #5a1218 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(122, 28, 36, 0.2);
        }
        .btn-maroon:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 6px 20px rgba(122, 28, 36, 0.35);
        }
    </style>
</head>
<body class="overflow-x-hidden">

    <!-- NAVBAR BOOTSTRAP (Dimodifikasi dengan Tailwind) -->
    <nav class="navbar navbar-expand-lg sticky-top bg-white shadow-sm py-3 border-b border-gray-200">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center gap-2 font-extrabold text-2xl text-[#7a1c24]" href="#">
                <i class="fas fa-hands-helping bg-[#7a1c24] text-white p-2 rounded-lg"></i> VolunteerOne
            </a>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler border-0 focus:shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Menu Items -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 font-semibold text-gray-600 gap-4">
                    <li class="nav-item"><a class="nav-link hover:text-[#7a1c24] transition-colors" href="#">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link hover:text-[#7a1c24] transition-colors" href="#cara-kerja">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link hover:text-[#7a1c24] transition-colors" href="#">Tentang Kami</a></li>
                </ul>
                <div class="d-flex gap-3 mt-3 mt-lg-0">
                    <a href="register.php" class="btn btn-maroon">Daftar Akun Baru</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION (Penggunaan Grid Bootstrap 'row' & 'col' + Utilitas Tailwind) -->
    <section class="container py-12 md:py-16 min-h-[85vh] flex items-center">
        <div class="row align-items-center justify-content-between w-100">
            
            <!-- Kolom Teks Kiri (Bootstrap: col-lg-6) -->
            <div class="col-lg-6 mb-12 mb-lg-0">
                <div class="inline-block bg-orange-100 text-orange-700 px-4 py-1.5 rounded-full text-xs font-bold mb-4 border border-orange-200 uppercase tracking-widest">
                    <i class="fas fa-fire mr-1"></i> Platform Kemanusiaan #1
                </div>
                <h1 class="display-4 font-black text-gray-900 leading-tight mb-4">
                    Ubah Niat Baik Jadi <span class="text-[#7a1c24] underline decoration-wavy decoration-orange-400">Aksi Nyata</span> Hari Ini.
                </h1>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed font-medium">
                    Banyak relawan telah bergabung. Temukan berbagai organisasi dan aktivitas sosial yang menanti kontribusimu di seluruh Indonesia.
                </p>
                
                <!-- Mini Stats (Grid Bootstrap dalam Grid Tailwind) -->
                <div class="row mt-8 pt-8 border-t border-gray-200">
                    <div class="col-4">
                        <h4 class="font-black text-3xl text-gray-900">10K+</h4>
                        <p class="text-xs text-gray-500 font-bold uppercase mt-1">Relawan Aktif</p>
                    </div>
                    <div class="col-4">
                        <h4 class="font-black text-3xl text-gray-900">5K+</h4>
                        <p class="text-xs text-gray-500 font-bold uppercase mt-1">Aksi Sosial</p>
                    </div>
                    <div class="col-4">
                        <h4 class="font-black text-3xl text-gray-900">2.7K</h4>
                        <p class="text-xs text-gray-500 font-bold uppercase mt-1">Organisasi</p>
                    </div>
                </div>
            </div>
            
            <!-- Kolom Kanan: FORM LOGIN (Bootstrap: col-lg-5) -->
            <div class="col-lg-5 offset-lg-1 relative">
                <!-- Background dekorasi (Tailwind) -->
                <div class="absolute inset-0 bg-gradient-to-tr from-[#7a1c24]/20 to-orange-400/20 rounded-[3rem] blur-2xl transform scale-105"></div>
                
                <!-- Kotak Form Login -->
                <div class="bg-gradient-to-br from-[#7a1c24] to-[#3a0b10] p-8 md:p-10 rounded-[2.5rem] shadow-2xl relative z-10 border border-red-900/50">
                    <div class="text-center text-white mb-8">
                        <div class="bg-white/10 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-md border border-white/25">
                            <i class="fas fa-hands-helping text-3xl"></i>
                        </div>
                        <h2 class="text-2xl font-extrabold tracking-wide mb-1">MASUK KE SISTEM</h2>
                        <p class="text-white/80 font-medium text-xs">Selamat Datang Kembali di VolunteerOne</p>
                    </div>

                    <?php if($error): ?>
                        <div class="bg-red-500/20 border border-red-500 text-red-100 p-3.5 rounded-xl mb-5 text-sm text-center font-medium flex items-center gap-2">
                            <i class="fas fa-exclamation-circle text-base shrink-0"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="text-white/80 text-xs font-bold uppercase tracking-wider ml-1 mb-1 block">Surel (Email)</label>
                            <input type="email" name="email" required placeholder="nama@gmail.com" class="w-full bg-white/10 border border-white/20 rounded-xl px-5 py-3.5 text-white placeholder-white/40 focus:outline-none focus:bg-white/20 focus:border-white/50 transition duration-200">
                        </div>
                        
                        <div>
                            <label class="text-white/80 text-xs font-bold uppercase tracking-wider ml-1 mb-1 block">Kata Sandi</label>
                            <input type="password" name="password" required placeholder="••••••••" class="w-full bg-white/10 border border-white/20 rounded-xl px-5 py-3.5 text-white placeholder-white/40 focus:outline-none focus:bg-white/20 focus:border-white/50 transition duration-200">
                        </div>
                        
                        <button type="submit" class="w-full bg-white text-[#7a1c24] hover:bg-gray-100 font-extrabold py-3.5 rounded-full transition duration-300 shadow-lg mt-6 tracking-wide text-sm">MASUK SEKARANG</button>
                    </form>

                    <p class="text-center text-white/80 text-sm mt-6 font-medium">
                        Belum terdaftar? <a href="register.php" class="text-white font-extrabold hover:underline">Daftar Akun Baru</a>
                    </p>
                </div>
            </div>
            
        </div>
    </section>

    <!-- CARA KERJA SECTION (Cards Bootstrap + Utilities Tailwind) -->
    <section id="cara-kerja" class="bg-white py-20 border-t border-gray-100">
        <div class="container text-center max-w-5xl">
            <h2 class="text-3xl font-black text-gray-900 mb-3">Bagaimana Cara Kerjanya?</h2>
            <p class="text-gray-500 font-medium mb-12">Hanya butuh 3 langkah mudah untuk mulai memberikan dampak positif.</p>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 bg-[#f7f1eb] rounded-[2rem] p-6 hover:-translate-y-2 transition-transform duration-300">
                        <div class="card-body">
                            <div class="w-16 h-16 bg-white text-[#7a1c24] rounded-2xl flex items-center justify-center text-2xl shadow-sm mx-auto mb-5 rotate-3"><i class="fas fa-user-plus"></i></div>
                            <h5 class="card-title font-bold text-gray-800">1. Buat Akun</h5>
                            <p class="card-text text-sm text-gray-600 mt-2">Daftar secara gratis sebagai relawan individu atau institusi penyelenggara.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 bg-[#f7f1eb] rounded-[2rem] p-6 hover:-translate-y-2 transition-transform duration-300">
                        <div class="card-body">
                            <div class="w-16 h-16 bg-white text-orange-500 rounded-2xl flex items-center justify-center text-2xl shadow-sm mx-auto mb-5 -rotate-3"><i class="fas fa-search"></i></div>
                            <h5 class="card-title font-bold text-gray-800">2. Pilih Kegiatan</h5>
                            <p class="card-text text-sm text-gray-600 mt-2">Telusuri berbagai kampanye sosial yang sesuai dengan minat dan lokasimu.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 bg-[#f7f1eb] rounded-[2rem] p-6 hover:-translate-y-2 transition-transform duration-300">
                        <div class="card-body">
                            <div class="w-16 h-16 bg-white text-green-500 rounded-2xl flex items-center justify-center text-2xl shadow-sm mx-auto mb-5 rotate-3"><i class="fas fa-hands-helping"></i></div>
                            <h5 class="card-title font-bold text-gray-800">3. Beraksi & Berdampak</h5>
                            <p class="card-text text-sm text-gray-600 mt-2">Tunggu persetujuan, lalu turun langsung ke lapangan untuk beraksi!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER BOOTSTRAP -->
    <footer class="bg-gray-900 text-white pt-16 pb-8">
        <div class="container">
            <div class="row mb-8">
                <div class="col-md-6 mb-6 mb-md-0">
                    <h3 class="font-extrabold text-2xl flex items-center gap-2 mb-4"><i class="fas fa-hands-helping text-[#7a1c24]"></i> VolunteerOne</h3>
                    <p class="text-gray-400 text-sm max-w-sm">Wadah online untuk mempertemukan relawan dan organisasi komunitas sosial di seluruh Indonesia.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5 class="font-bold mb-4">Didukung Oleh Framework:</h5>
                    <div class="d-flex justify-content-md-end gap-3 flex-wrap">
                        <span class="bg-gray-800 px-4 py-2 rounded-lg font-bold text-gray-300 border border-gray-700"><i class="fab fa-bootstrap text-purple-500"></i> Bootstrap 5</span>
                        <span class="bg-gray-800 px-4 py-2 rounded-lg font-bold text-gray-300 border border-gray-700">Tailwind CSS</span>
                        <span class="bg-gray-800 px-4 py-2 rounded-lg font-bold text-gray-300 border border-gray-700"><i class="fab fa-php text-blue-400"></i> PHP 8</span>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center text-sm text-gray-500">
                &copy; 2026 VolunteerOne Framework Hybrid Integration. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>