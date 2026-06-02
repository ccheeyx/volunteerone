<?php
require 'koneksi.php';

$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    // ENKRIPSI PASSWORD DI SINI:
    $password_mentah = $_POST['password'];
    $password_hashed = password_hash($password_mentah, PASSWORD_DEFAULT);
    
    $role = $_POST['role']; 
    
    $is_validated = ($role == 'organizer') ? 0 : 1; 

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, is_validated) VALUES (?, ?, ?, ?, ?, ?)");
        // Masukkan password yang sudah di-hash
        $stmt->execute([$name, $email, $phone, $password_hashed, $role, $is_validated]);
        
        if($role == 'organizer') {
            $message = "Registrasi berhasil! Akun Organisasi Anda sedang menunggu validasi Admin.";
        } else {
            $message = "Registrasi berhasil! Silakan masuk.";
        }
    } catch(PDOException $e) {
        $message = "Error: Email mungkin sudah digunakan.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - VolunteerOne</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .auth-bg { background: linear-gradient(135deg, #7a1c24 0%, #3a0b10 100%); }
    </style>
</head>
<body class="auth-bg min-h-screen flex flex-col justify-center items-center p-6">

    <div class="w-full max-w-md bg-white/10 backdrop-blur-md p-8 rounded-3xl shadow-2xl border border-white/20">
        <h2 class="text-white font-bold text-xl mb-6 text-center">DAFTAR AKUN</h2>
        
        <?php if($message): ?>
            <div class="bg-blue-500/20 border border-blue-500 text-blue-100 p-3 rounded-xl mb-4 text-sm text-center">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="text" name="name" required placeholder="Nama Lengkap / Organisasi" class="w-full bg-white/20 border border-white/30 rounded-xl px-5 py-3 text-white placeholder-white/70 focus:outline-none">
            
            <input type="email" name="email" required placeholder="Email" class="w-full bg-white/20 border border-white/30 rounded-xl px-5 py-3 text-white placeholder-white/70 focus:outline-none">
            
            <input type="tel" name="phone" required placeholder="No. Handphone" class="w-full bg-white/20 border border-white/30 rounded-xl px-5 py-3 text-white placeholder-white/70 focus:outline-none">
            
            <!-- Tambahan Pilihan Role -->
            <div>
                <label class="text-white/80 text-xs font-bold uppercase tracking-wider ml-1 mb-1 block">Daftar Sebagai:</label>
                <select name="role" required class="w-full bg-white/20 border border-white/30 rounded-xl px-5 py-3 text-white focus:outline-none focus:bg-white/30 [&>option]:text-black">
                    <option value="user">Relawan (Individu)</option>
                    <option value="organizer">Organisasi (Komunitas/Lembaga)</option>
                </select>
            </div>

            <input type="password" name="password" required placeholder="Buat Password" class="w-full bg-white/20 border border-white/30 rounded-xl px-5 py-3 text-white placeholder-white/70 focus:outline-none">
            
            <button type="submit" class="w-full bg-white text-[#7a1c24] font-bold py-3.5 rounded-full hover:bg-gray-100 transition shadow-lg mt-6">BUAT AKUN</button>
        </form>

        <p class="text-center text-white/80 text-sm mt-6">
            Sudah punya akun? <a href="index.php" class="text-white font-bold hover:underline">Masuk</a>
        </p>
    </div>

</body>
</html>