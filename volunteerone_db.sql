-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 02 Jun 2026 pada 14.07
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.5.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `volunteerone_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak') DEFAULT 'Menunggu',
  `apply_date` datetime DEFAULT current_timestamp(),
  `motivation` text DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `applications`
--

INSERT INTO `applications` (`id`, `program_id`, `user_id`, `status`, `apply_date`, `motivation`, `cv_path`) VALUES
(38, 18, 10, 'Disetujui', '2026-06-02 16:46:03', 'fjdj', NULL),
(39, 18, 15, 'Disetujui', '2026-06-02 16:47:42', 'saya suka mengajar anak-anak', 'uploads/cv_15_1780390062.docx'),
(40, 13, 15, 'Disetujui', '2026-06-02 16:48:22', 'jfkf', NULL),
(41, 14, 16, 'Disetujui', '2026-06-02 16:53:40', 'gdj', NULL),
(42, 21, 16, 'Disetujui', '2026-06-02 19:20:36', 'Saya ingin belajar lebih untuk menjadi bekal dalam berbagi ilmu', NULL),
(43, 2, 16, 'Disetujui', '2026-06-02 19:41:17', 'fgss', NULL),
(44, 21, 10, 'Disetujui', '2026-06-02 19:49:08', 'saya ingin mencoba mengajar', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(150) NOT NULL,
  `prog_date` date NOT NULL,
  `quota` int(11) NOT NULL,
  `rating` int(11) DEFAULT 0,
  `category` varchar(50) DEFAULT 'Sosial',
  `organizer` varchar(100) DEFAULT 'VolunteerOne Official',
  `image_url` varchar(255) DEFAULT 'https://images.unsplash.com/photo-1593113589914-075990190da4?auto=format&fit=crop&q=80&w=500',
  `organizer_id` int(11) DEFAULT NULL,
  `prog_time` varchar(20) DEFAULT '10:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `programs`
--

INSERT INTO `programs` (`id`, `name`, `description`, `location`, `prog_date`, `quota`, `rating`, `category`, `organizer`, `image_url`, `organizer_id`, `prog_time`) VALUES
(2, 'Program Edukasi Anak Jalanan', 'Kegiatan sukarela untuk memberikan pendidikan dasar seperti membaca, menulis, dan berhitung kepada anak-anak jalanan agar mereka mendapatkan akses pembelajaran yang layak.', 'Taman Kota', '2026-06-15', 20, 0, 'Aksi Sosial', 'VolunteerOne Official', 'uploads/banners/banner_admin_1780375682.jpg', NULL, '10:00'),
(3, 'Aksi Bersih Pantai', 'Program peduli lingkungan dengan membersihkan sampah di area pantai serta mengedukasi masyarakat tentang pentingnya menjaga kebersihan laut.', 'Pantai Talise', '2026-06-21', 30, 0, 'Aksi Sosial', 'VolunteerOne Official', 'uploads/banners/banner_1780205415.jpg', NULL, '10:00'),
(6, 'Gerakan Donasi Buku', 'Mengumpulkan dan menyalurkan buku layak baca ke daerah yang membutuhkan serta mengadakan sesi membaca bersama anak-anak.', 'Sekolah', '2026-06-28', 15, 5, 'Aksi Sosial', 'VolunteerOne Official', 'uploads/banners/banner_admin_1780375578.jpg', 6, '10:00'),
(7, 'Pelatihan Skill Digital', 'Memberikan pelatihan dasar seperti penggunaan komputer, desain grafis, atau media sosial untuk meningkatkan keterampilan masyarakat.', 'Balai Desa', '2026-06-12', 30, 5, 'Pendidikan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780213927.jpg', 6, '10:00'),
(8, 'Relawan Tanggap Bencana', 'Menggalang dan menyalurkan bantuan logistik serta membantu korban bencana alam seperti banjir atau gempa.', 'Area Terdampak', '2026-06-27', 50, 5, 'Sosial', 'VolunteerOne Official', 'uploads/banners/banner_org_1780214611.jpg', 6, '10:00'),
(9, 'Kelas Inspirasi Anak', 'Relawan mengajar anak-anak kurang mampu dengan materi dasar dan motivasi pendidikan.', 'Sekolah Pinggiran Kota', '2026-06-25', 20, 5, 'Pendidikan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780292322.jpg', 7, '10:00'),
(10, 'Gerakan Tanam Pohon', 'Menanam pohon di area gundul untuk menjaga lingkungan dan mengurangi polusi udara.', 'Hutan Kota', '2026-06-13', 15, 5, 'Lingkungan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780292402.jpg', 7, '10:00'),
(11, 'Bakti Sosial Kesehatan', 'Pemeriksaan kesehatan gratis dan edukasi hidup sehat kepada masyarakat.', 'Balai Desa', '2026-06-19', 40, 5, 'Kesehatan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780292518.jpg', 7, '10:00'),
(12, 'Donor Darah', 'Kegiatan rutin pengambilan darah untuk memenuhi kebutuhan rumah sakit di seluruh Indonesia.', 'Puskesmas', '2026-06-23', 20, 5, 'Kesehatan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780376619.jpg', 11, '10:00'),
(13, 'Tanggap Darurat Banjir', 'Kegiatan relawan dalam membantu evakuasi korban banjir, distribusi bantuan logistik, serta pendirian posko pengungsian.', 'Makassar', '2026-06-30', 50, 5, 'Sosial', 'VolunteerOne Official', 'uploads/banners/banner_org_1780377507.jpg', 11, '10:00'),
(14, 'Pelatihan Pertolongan Pertama (P3K)', 'Pelatihan dasar pertolongan pertama untuk membekali relawan dan masyarakat dalam menangani kondisi darurat sebelum bantuan medis datang.', 'Kampus Untad', '2026-07-06', 30, 5, 'Pendidikan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780377778.jpg', 11, '10:00'),
(15, 'Dukungan Psikososial Korban Bencana', 'Program pendampingan psikologis untuk membantu korban bencana mengatasi trauma melalui kegiatan konseling dan aktivitas rekreatif.', 'Posko Pengungsian', '2026-06-21', 25, 5, 'Aksi Sosial', 'VolunteerOne Official', 'uploads/banners/banner_admin_1780379846.jpg', NULL, '09:00'),
(16, 'Aksi Bersih Sungai', 'Kegiatan membersihkan sampah di aliran sungai untuk menjaga ekosistem dan mencegah banjir di kawasan perkotaan.', 'Sungai Jeneberang, Makassar', '2026-06-28', 40, 5, 'Lingkungan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780383672.jpg', 12, '10:00'),
(17, 'Gerakan Tanam Pohon', 'Penanaman pohon di area gundul sebagai upaya penghijauan dan mengurangi dampak perubahan iklim.', 'Hutan Kota Makassar', '2026-06-26', 50, 5, 'Lingkungan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780383983.jpg', 12, '10:00'),
(18, 'Kelas Gratis Anak Pesisir', 'Program pengajaran bagi anak-anak di daerah pesisir untuk meningkatkan kemampuan membaca, menulis, dan berhitung.', 'Kampung Nelayan', '2026-07-11', 2, 5, 'Pendidikan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780384279.jpg', 13, '10:00'),
(19, 'Edukasi Gizi & Pola Hidup Sehat', 'Penyuluhan tentang pentingnya gizi seimbang dan pola hidup sehat untuk mencegah penyakit.', 'Puskesmas', '2026-06-03', 20, 5, 'Kesehatan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780384516.jpg', 14, '10:00'),
(20, 'Donasi Buku & Literasi', 'Pengumpulan dan distribusi buku layak baca serta kegiatan membaca bersama anak-anak.', 'Taman Baca Masyarakat', '2026-07-17', 20, 5, 'Pendidikan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780389840.jpg', 13, '10:00'),
(21, 'Pelatihan Guru Sukarelawan', 'Pelatihan metode pengajaran kreatif untuk relawan yang akan mengajar di daerah terpencil.', 'Surabaya', '2026-06-09', 20, 5, 'Pendidikan', 'VolunteerOne Official', 'uploads/banners/banner_org_1780399109.jpg', 17, '10:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'user',
  `is_validated` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `established_date` date DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `org_type` varchar(50) DEFAULT 'Komunitas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `is_validated`, `description`, `established_date`, `location`, `website`, `org_type`) VALUES
(6, 'HMTI', 'hmti@gmail.com', '0813005', '123', 'organizer', 1, NULL, NULL, NULL, NULL, 'Komunitas'),
(7, 'Gerakan Peduli Bersama (GPB)', 'gpb@gmail.com', '12345', '123', 'organizer', 1, 'Gerakan Peduli Bersama adalah organisasi sosial yang berfokus pada kegiatan kemanusiaan, pendidikan, dan lingkungan dengan melibatkan relawan dari berbagai kalangan untuk memberikan dampak positif bagi masyarakat.', '2022-06-15', 'Makassar', '', 'Komunitas'),
(9, 'admin', 'admin@volunteerone.com', '12345', '$2y$12$/VUIjbN2bS9xOpxEy1LTLuRr2jPJP4v7ZNDS5HjYPrAspu1011eAG', 'admin', 1, NULL, NULL, NULL, NULL, 'Komunitas'),
(10, 'Marchelinda Bin', 'marchelindabin@gmail.com', '082237497470', '$2y$12$o3oSStH/wIM.ACC3FiHD9.Qq.toaJbd2u2jGgxZnOSiK7/ytkW/KC', 'user', 1, 'Mahasiswa yang memiliki semangat belajar dan kepedulian sosial tinggi. Aktif mengikuti kegiatan volunteer untuk mengembangkan diri sekaligus memberikan manfaat bagi masyarakat.', NULL, 'Toraja', NULL, 'Komunitas'),
(11, 'Palang Merah Indonesia (PMI)', 'pmi@gmail.com', '0813005', '$2y$12$cte1ORTlGzUmRO0bgTkQ3O/VRnpAFtc5VP96zKuKAohjqGbTqp.ku', 'organizer', 1, 'Organisasi kemanusiaan nasional yang fokus pada bantuan bencana, kesehatan, dan donor darah, dengan ratusan ribu relawan di seluruh Indonesia', '2018-01-14', 'Jakarta', 'https://pmi_id.com', 'Komunitas'),
(12, 'Sahabat Lingkungan Indonesia (SLI)', 'SLI@gmail.com', '99880', '$2y$12$Kj2PYgTFBuP2CRy/ed2gHus0Pp34vEZ3YIuNwi5trULklkahoOeBy', 'organizer', 1, 'Sahabat Lingkungan Indonesia adalah komunitas sosial yang bergerak di bidang pelestarian lingkungan hidup melalui aksi nyata seperti penghijauan, pengelolaan sampah, dan edukasi masyarakat.', '2021-03-10', 'Makassar, Sulawesi Selatan', 'https://instagram.com/sli_indonesia', 'Komunitas'),
(13, 'Komunitas Peduli Pendidikan Nusantara (KPPN)', 'KPPN@gmail.com', '32456', '$2y$12$xhfnoIOlbIKi3edJA6HaEeAuJ6w3k90GZ53oLL44/jVXYuCMUtkE6', 'organizer', 1, 'Komunitas Peduli Pendidikan Nusantara adalah organisasi yang berfokus pada peningkatan kualitas pendidikan di daerah kurang terjangkau melalui program relawan mengajar dan literasi.', '2020-07-05', 'Yogyakarta', 'https://instagram.com/kppn_id', 'Komunitas'),
(14, 'Relawan Sehat Indonesia (RSI)', 'RSI@gmail.com', '445631', '$2y$12$doCXo5YvLezg3KgJTuK/eOj9SxxC27ecxDRo.Am6/yaJieV0Lp4se', 'organizer', 1, 'Relawan Sehat Indonesia merupakan organisasi yang bergerak di bidang kesehatan masyarakat dengan menyediakan layanan kesehatan gratis dan edukasi hidup sehat.', '2019-12-18', 'Surabaya', 'https://relawansehat.id', 'LSM / NGO'),
(15, 'dadi', 'dadi@gmail.com', '085277487240', '$2y$12$NRI4dpoK0qnKvOOSfNGpiO3zY7cOF.MnPkhs50EuLoBnurhmUDF4C', 'user', 1, 'Saya adalah individu yang memiliki minat dalam kegiatan sosial dan kemanusiaan. Aktif berpartisipasi dalam berbagai kegiatan volunteer, saya percaya bahwa kontribusi kecil dapat memberikan dampak besar bagi masyarakat.', NULL, 'Samrat Kota Maju', NULL, 'Komunitas'),
(16, 'kyungsoo', 'kyungsoo@gmail.com', '089237492349', '$2y$12$aco5J2eHAhszwYtU6QbHBuICkHa1TyTp595/8X3Hu3c7r3c5SlVh2', 'user', 1, '', NULL, 'Korea Selatan', NULL, 'Komunitas'),
(17, 'Relawan Pendidikan Indonesia (RPI)', 'RPI@gmail.com', '112256', '$2y$12$pnGsjRchuq8OFuWNQinhuuRAYu6E9kgQ44McYzkQoscu1vWYVj1ta', 'organizer', 1, 'Relawan Pendidikan Indonesia adalah organisasi yang bergerak di bidang pendidikan dengan fokus pada peningkatan kualitas belajar anak-anak di daerah terpencil.', '1019-09-20', 'Surabaya, Jawa Timur', 'https://relawanpendidikan.id', 'Yayasan');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT untuk tabel `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
