-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 19 Ara 2024, 11:57:26
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Veritabanı: `lureid`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `badges`
--

CREATE TABLE `badges` (
  `badge_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `requirement_type` enum('PROFILE_COMPLETENESS','WORK_COUNT','RATING','EARNINGS') NOT NULL,
  `requirement_value` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `badges`
--

INSERT INTO `badges` (`badge_id`, `name`, `description`, `icon_url`, `requirement_type`, `requirement_value`, `created_at`) VALUES
(1, 'Altın Geçmiş', 'Profilini %75 ve üzeri tamamlamış kullanıcılara verilen rozet', NULL, 'PROFILE_COMPLETENESS', 75.00, '2024-11-24 02:32:17');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `follows`
--

CREATE TABLE `follows` (
  `user_id` int(11) NOT NULL,
  `following` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`following`)),
  `followers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`followers`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `follows`
--

INSERT INTO `follows` (`user_id`, `following`, `followers`, `created_at`, `updated_at`) VALUES
(113691405, '[395548956]', '[395548956]', '2024-12-02 13:29:26', '2024-12-06 22:19:07'),
(257036736, '[]', '[]', '2024-12-06 10:19:53', NULL),
(384546394, '[395548956]', '[395548956]', '2024-12-04 20:41:47', '2024-12-06 22:19:02'),
(395548956, '[384546394,113691405]', '[768556619,384546394,113691405]', '2024-12-02 13:30:50', '2024-12-06 22:19:07'),
(601838105, '[]', '[]', '2024-12-06 10:18:43', NULL),
(768556619, '[395548956]', '[]', '2024-12-04 14:26:39', '2024-12-04 14:27:29');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `freelancers`
--

CREATE TABLE `freelancers` (
  `freelancer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `identity_number` varchar(20) DEFAULT NULL,
  `profile_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_data`)),
  `professional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`professional_data`)),
  `financial_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`financial_data`)),
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `approval_status` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
  `status` enum('ACTIVE','INACTIVE','BANNED') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `mod_note` text DEFAULT NULL COMMENT 'Moderatör red notu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `freelancers`
--

INSERT INTO `freelancers` (`freelancer_id`, `user_id`, `phone`, `identity_number`, `profile_data`, `professional_data`, `financial_data`, `additional_data`, `approval_status`, `status`, `created_at`, `updated_at`, `mod_note`) VALUES
(18, 395548956, '0542730194', '10622652464', '{\"phone\":\"0542730194\",\"identity_number\":\"10622652464\",\"birth_year\":null,\"location\":{\"country\":null,\"city\":null}}', '{\"experience\":null,\"skills\":[\"\"],\"education\":null,\"certifications\":null,\"portfolio\":null,\"references\":null}', '{\"account_holder\":\"sjkl\\u015fi,dfghjkl\\u015fi,\",\"bank_name\":\"Garanti\",\"iban\":\"23 4567 8902 3456 7892 3456 78\",\"tax_number\":\"7418520963\",\"daily_rate\":\"500\"}', NULL, 'APPROVED', 'ACTIVE', '2024-12-06 22:46:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gigs`
--

CREATE TABLE `gigs` (
  `gig_id` int(11) NOT NULL,
  `freelancer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `requirements` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `pricing_type` enum('ONE_TIME','DAILY','WEEKLY','MONTHLY') DEFAULT 'ONE_TIME',
  `delivery_time` int(11) NOT NULL COMMENT 'Gün cinsinden teslimat süresi',
  `revision_count` int(11) DEFAULT 1,
  `status` enum('PENDING_REVIEW','APPROVED','REJECTED','ACTIVE','PAUSED','DELETED') DEFAULT 'PENDING_REVIEW',
  `media_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Fotoğraf ve video yolları' CHECK (json_valid(`media_data`)),
  `agreement_accepted` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `milestones_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `nda_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `mod_note` text DEFAULT NULL COMMENT 'Moderatör red notu',
  `deliverables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deliverables`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `gigs`
--

INSERT INTO `gigs` (`gig_id`, `freelancer_id`, `title`, `category`, `subcategory`, `description`, `requirements`, `price`, `pricing_type`, `delivery_time`, `revision_count`, `status`, `media_data`, `agreement_accepted`, `views`, `milestones_data`, `nda_data`, `created_at`, `updated_at`, `mod_note`, `deliverables`) VALUES
(21, 18, 'l', 'Grafik & Tasarım', 'Web & Mobil Arayüz Tasarımı', '<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>', 'dsfghjklşiişf<<uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk', 450.00, 'WEEKLY', 3, 2, 'APPROVED', '{\"images\":[\"uploads\\/photos\\/gig_67538000a9224.png\"],\"video\":null}', 1, 0, 'null', 'null', '2024-12-06 22:51:44', '2024-12-06 22:52:30', NULL, '[\".psd\",\".jpeg\",\".jpg\",\".png\",\".eps\",\".tiff\",\".cdr\",\".ai\"]');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gig_categories`
--

CREATE TABLE `gig_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `gig_categories`
--

INSERT INTO `gig_categories` (`category_id`, `name`, `parent_id`, `created_at`) VALUES
(1, 'Web, Yazılım & Teknoloji', NULL, '2024-11-24 05:03:11'),
(2, 'Grafik & Tasarım', NULL, '2024-11-24 05:03:11'),
(3, 'Dijital Pazarlama', NULL, '2024-11-24 05:03:11'),
(4, 'Yazı & Çeviri', NULL, '2024-11-24 05:03:11'),
(5, 'Video & Animasyon', NULL, '2024-11-24 05:03:11'),
(6, 'Müzik & Ses', NULL, '2024-11-24 05:03:11'),
(7, 'İş & Yönetim', NULL, '2024-11-24 05:03:11'),
(8, 'Veri & Analiz', NULL, '2024-11-24 05:03:11'),
(9, 'Eğitim & Öğretim', NULL, '2024-11-24 05:03:11'),
(10, 'Danışmanlık & Hukuk', NULL, '2024-11-24 05:03:11'),
(11, 'Web Geliştirme', 1, '2024-11-24 05:03:11'),
(12, 'Mobil Uygulama Geliştirme', 1, '2024-11-24 05:03:11'),
(13, 'E-Ticaret Geliştirme', 1, '2024-11-24 05:03:11'),
(14, 'WordPress Geliştirme', 1, '2024-11-24 05:03:11'),
(15, 'Yazılım Test & QA', 1, '2024-11-24 05:03:11'),
(16, 'Oyun Geliştirme', 1, '2024-11-24 05:03:11'),
(17, 'Veritabanı Yönetimi', 1, '2024-11-24 05:03:11'),
(18, 'DevOps & Sistem Yönetimi', 1, '2024-11-24 05:03:11'),
(19, 'API Geliştirme', 1, '2024-11-24 05:03:11'),
(20, 'Siber Güvenlik', 1, '2024-11-24 05:03:11'),
(21, 'Logo & Marka Tasarımı', 2, '2024-11-24 05:03:11'),
(22, 'Web & Mobil Arayüz Tasarımı', 2, '2024-11-24 05:03:11'),
(23, 'İllüstrasyon', 2, '2024-11-24 05:03:11'),
(24, '3D Modelleme & Render', 2, '2024-11-24 05:03:11'),
(25, 'Ambalaj Tasarımı', 2, '2024-11-24 05:03:11'),
(26, 'Sosyal Medya Tasarımı', 2, '2024-11-24 05:03:11'),
(27, 'Karakter Tasarımı', 2, '2024-11-24 05:03:11'),
(28, 'Baskı Tasarımı', 2, '2024-11-24 05:03:11'),
(29, 'Sunum Tasarımı', 2, '2024-11-24 05:03:11'),
(30, 'Maskot Tasarımı', 2, '2024-11-24 05:03:11'),
(31, 'SEO', 3, '2024-11-24 05:03:11'),
(32, 'Sosyal Medya Yönetimi', 3, '2024-11-24 05:03:11'),
(33, 'Google Ads', 3, '2024-11-24 05:03:11'),
(34, 'Facebook & Instagram Ads', 3, '2024-11-24 05:03:11'),
(35, 'E-posta Pazarlama', 3, '2024-11-24 05:03:11'),
(36, 'İçerik Pazarlama', 3, '2024-11-24 05:03:11'),
(37, 'Influencer Marketing', 3, '2024-11-24 05:03:11'),
(38, 'Affiliate Marketing', 3, '2024-11-24 05:03:11'),
(39, 'Pazar Araştırması', 3, '2024-11-24 05:03:11'),
(40, 'Marka Stratejisi', 3, '2024-11-24 05:03:11'),
(41, 'Makale & Blog Yazarlığı', 4, '2024-11-24 05:03:11'),
(42, 'SEO Metin Yazarlığı', 4, '2024-11-24 05:03:11'),
(43, 'Teknik Yazarlık', 4, '2024-11-24 05:03:11'),
(44, 'İngilizce Çeviri', 4, '2024-11-24 05:03:11'),
(45, 'Almanca Çeviri', 4, '2024-11-24 05:03:11'),
(46, 'Fransızca Çeviri', 4, '2024-11-24 05:03:11'),
(47, 'İspanyolca Çeviri', 4, '2024-11-24 05:03:11'),
(48, 'Rusça Çeviri', 4, '2024-11-24 05:03:11'),
(49, 'Editörlük & Redaksiyon', 4, '2024-11-24 05:03:11'),
(50, 'Proje Dokümantasyonu', 4, '2024-11-24 05:03:11'),
(51, 'Video Düzenleme', 5, '2024-11-24 05:03:11'),
(52, '2D Animasyon', 5, '2024-11-24 05:03:11'),
(53, '3D Animasyon', 5, '2024-11-24 05:03:11'),
(54, 'Motion Graphics', 5, '2024-11-24 05:03:11'),
(55, 'Video Prodüksiyon', 5, '2024-11-24 05:03:11'),
(56, 'Whiteboard Animasyon', 5, '2024-11-24 05:03:11'),
(57, 'İntro & Outro Tasarımı', 5, '2024-11-24 05:03:11'),
(58, 'Drone Çekimi', 5, '2024-11-24 05:03:11'),
(59, 'Reklam Filmi', 5, '2024-11-24 05:03:11'),
(60, 'Explainer Video', 5, '2024-11-24 05:03:11'),
(61, 'Ses Düzenleme', 6, '2024-11-24 05:03:11'),
(62, 'Müzik Prodüksiyon', 6, '2024-11-24 05:03:11'),
(63, 'Seslendirme', 6, '2024-11-24 05:03:11'),
(64, 'Jingle & Reklam Müziği', 6, '2024-11-24 05:03:11'),
(65, 'Podcast Düzenleme', 6, '2024-11-24 05:03:11'),
(66, 'Mix & Mastering', 6, '2024-11-24 05:03:11'),
(67, 'Ses Efektleri', 6, '2024-11-24 05:03:11'),
(68, 'Beste Yapımı', 6, '2024-11-24 05:03:11'),
(69, 'Şarkı Sözü Yazımı', 6, '2024-11-24 05:03:11'),
(70, 'Aranje', 6, '2024-11-24 05:03:11'),
(71, 'Proje Yönetimi', 7, '2024-11-24 05:03:11'),
(72, 'Sanal Asistanlık', 7, '2024-11-24 05:03:11'),
(73, 'İş Planı Hazırlama', 7, '2024-11-24 05:03:11'),
(74, 'Finansal Analiz', 7, '2024-11-24 05:03:11'),
(75, 'İnsan Kaynakları', 7, '2024-11-24 05:03:11'),
(76, 'Müşteri Hizmetleri', 7, '2024-11-24 05:03:11'),
(77, 'Stratejik Planlama', 7, '2024-11-24 05:03:11'),
(78, 'Risk Analizi', 7, '2024-11-24 05:03:11'),
(79, 'Operasyon Yönetimi', 7, '2024-11-24 05:03:11'),
(80, 'Tedarik Zinciri Yönetimi', 7, '2024-11-24 05:03:11'),
(81, 'Veri Analizi', 8, '2024-11-24 05:03:11'),
(82, 'Veri Görselleştirme', 8, '2024-11-24 05:03:11'),
(83, 'İş Zekası (BI)', 8, '2024-11-24 05:03:11'),
(84, 'Makine Öğrenmesi', 8, '2024-11-24 05:03:11'),
(85, 'Web Analytics', 8, '2024-11-24 05:03:11'),
(86, 'Excel & VBA', 8, '2024-11-24 05:03:11'),
(87, 'Python ile Veri Analizi', 8, '2024-11-24 05:03:11'),
(88, 'R ile Veri Analizi', 8, '2024-11-24 05:03:11'),
(89, 'SQL & Veritabanı', 8, '2024-11-24 05:03:11'),
(90, 'Büyük Veri (Big Data)', 8, '2024-11-24 05:03:11'),
(91, 'Online Ders', 9, '2024-11-24 05:03:11'),
(92, 'Ders İçeriği Hazırlama', 9, '2024-11-24 05:03:11'),
(93, 'Test Hazırlama', 9, '2024-11-24 05:03:11'),
(94, 'Eğitim Videosu', 9, '2024-11-24 05:03:11'),
(95, 'Dil Öğretimi', 9, '2024-11-24 05:03:11'),
(96, 'Yazılım Eğitimi', 9, '2024-11-24 05:03:11'),
(97, 'Matematik & Fen', 9, '2024-11-24 05:03:11'),
(98, 'Müzik Eğitimi', 9, '2024-11-24 05:03:11'),
(99, 'İş Eğitimi', 9, '2024-11-24 05:03:11'),
(100, 'Kişisel Gelişim', 9, '2024-11-24 05:03:11'),
(101, 'Hukuki Danışmanlık', 10, '2024-11-24 05:03:11'),
(102, 'İş Danışmanlığı', 10, '2024-11-24 05:03:11'),
(103, 'Finansal Danışmanlık', 10, '2024-11-24 05:03:11'),
(104, 'Kariyer Danışmanlığı', 10, '2024-11-24 05:03:11'),
(105, 'E-Ticaret Danışmanlığı', 10, '2024-11-24 05:03:11'),
(106, 'Patent & Marka Tescil', 10, '2024-11-24 05:03:11'),
(107, 'Vergi Danışmanlığı', 10, '2024-11-24 05:03:11'),
(108, 'Yatırım Danışmanlığı', 10, '2024-11-24 05:03:11'),
(109, 'İnsan Kaynakları Danışmanlığı', 10, '2024-11-24 05:03:11'),
(110, 'Startup Danışmanlığı', 10, '2024-11-24 05:03:11');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `invitations`
--

CREATE TABLE `invitations` (
  `invitation_id` int(11) NOT NULL,
  `inviter_id` int(11) NOT NULL,
  `invited_user_id` int(11) NOT NULL,
  `invitation_code` varchar(255) DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `invitations`
--

INSERT INTO `invitations` (`invitation_id`, `inviter_id`, `invited_user_id`, `invitation_code`, `used_at`) VALUES
(21, 113691405, 395548956, '674DB6106', NULL),
(22, 113691405, 768556619, '674DB6106', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `jobs`
--

CREATE TABLE `jobs` (
  `job_id` int(11) NOT NULL,
  `gig_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL COMMENT 'User who purchased the gig',
  `freelancer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `requirements` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `budget` decimal(10,2) NOT NULL,
  `status` enum('PENDING','IN_PROGRESS','UNDER_REVIEW','REVISION_REQUESTED','COMPLETED','CANCELLED','DISPUTED') NOT NULL DEFAULT 'PENDING',
  `delivery_deadline` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `revision_count` int(11) DEFAULT 0,
  `max_revisions` int(11) DEFAULT 1,
  `milestones_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`milestones_data`)),
  `deliverables_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deliverables_data`)),
  `transaction_id` bigint(20) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `client_rating` int(11) DEFAULT NULL,
  `freelancer_rating` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `jobs`
--

INSERT INTO `jobs` (`job_id`, `gig_id`, `client_id`, `freelancer_id`, `title`, `description`, `requirements`, `category`, `subcategory`, `budget`, `status`, `delivery_deadline`, `completed_at`, `revision_count`, `max_revisions`, `milestones_data`, `deliverables_data`, `transaction_id`, `cancellation_reason`, `client_rating`, `freelancer_rating`, `created_at`, `updated_at`) VALUES
(16, 21, 113691405, 18, 'l', '<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>\r\n<p>dsfghjklşiişf&lt;&lt;uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk</p>', 'dsfghjklşiişf<<uışi,şldsadgiş,işkjhglşkjdffhjki,şilgfdfghğ,şlhgcvjklşi,şjvcxhjklilkjgcxcvhjklkjgcxcvhjklşljhfxzhjklşlkgcxzxfhjkjhcghjıopkjvbbhjk', 'Grafik & Tasarım', 'Web & Mobil Arayüz Tasarımı', 450.00, 'COMPLETED', '2024-12-15 10:54:37', '2024-12-12 12:55:51', 0, 2, 'null', '{\"files\":[\"675add3435300_675874489b8b4_7e7a242c6b7eacc1.jpg\"],\"note\":\"hayr\",\"delivered_at\":\"2024-12-12 13:55:16\"}', 66685252486, NULL, NULL, NULL, '2024-12-12 12:54:37', '2024-12-12 12:55:51');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `job_reviews`
--

CREATE TABLE `job_reviews` (
  `review_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `freelancer_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `job_reviews`
--

INSERT INTO `job_reviews` (`review_id`, `job_id`, `client_id`, `freelancer_id`, `rating`, `review_text`, `created_at`) VALUES
(9, 16, 113691405, 18, 5, 'nsachsac', '2024-12-12 12:55:51');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('SUCCESS','FAILED') NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `browser_version` varchar(50) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `verified` tinyint(4) DEFAULT 0 COMMENT '0: Doğrulanmadı, 1: Giriş işlemi doğrulandı'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `login_attempts`
--

INSERT INTO `login_attempts` (`attempt_id`, `user_id`, `ip_address`, `attempt_time`, `status`, `country`, `city`, `region`, `isp`, `timezone`, `browser`, `browser_version`, `os`, `verified`) VALUES
(101, 395548956, '5.27.22.140', '2024-12-06 22:18:15', 'SUCCESS', 'Türkiye', 'Izmir', 'İzmir Province', 'Turkcell Internet', 'Europe/Istanbul', 'Chrome', '131.0.0.0', 'Windows', 0),
(102, 113691405, '5.27.22.140', '2024-12-06 22:18:38', 'SUCCESS', 'Türkiye', 'Izmir', 'İzmir Province', 'Turkcell Internet', 'Europe/Istanbul', 'Chrome', '131.0.0.0', 'Windows', 0),
(103, 395548956, '5.25.162.177', '2024-12-11 07:36:06', 'SUCCESS', 'Türkiye', 'Izmir', 'İzmir Province', 'Turkcell Internet', 'Europe/Istanbul', 'Chrome', '131.0.0.0', 'Windows', 0),
(104, 395548956, '5.25.162.177', '2024-12-11 07:58:49', 'SUCCESS', 'Türkiye', 'Izmir', 'İzmir Province', 'Turkcell Internet', 'Europe/Istanbul', 'Chrome', '131.0.0.0', 'Windows', 0),
(105, 395548956, '5.25.162.177', '2024-12-11 08:31:49', 'SUCCESS', 'Türkiye', 'Izmir', 'İzmir Province', 'Turkcell Internet', 'Europe/Istanbul', 'Chrome', '131.0.0.0', 'Windows', 0),
(106, 395548956, '5.25.162.177', '2024-12-11 10:00:17', 'SUCCESS', 'Türkiye', 'Izmir', 'İzmir Province', 'Turkcell Internet', 'Europe/Istanbul', 'Chrome', '131.0.0.0', 'Windows', 0),
(107, 395548956, '194.27.158.149', '2024-12-12 12:51:52', 'SUCCESS', 'Türkiye', 'Muğla', 'Muğla', 'National Academic Network and Information Center', 'Europe/Istanbul', 'Chrome', '131.0.0.0', 'Windows', 0),
(108, 113691405, '194.27.158.149', '2024-12-12 12:53:33', 'SUCCESS', 'Türkiye', 'Muğla', 'Muğla', 'National Academic Network and Information Center', 'Europe/Istanbul', 'Chrome', '128.0.0.0', 'Windows', 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `projects`
--

CREATE TABLE `projects` (
  `project_id` varchar(25) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `project_root` varchar(255) NOT NULL COMMENT 'JSON dosyasının yolu',
  `preview_image` varchar(255) NOT NULL,
  `likes_data` longtext NOT NULL DEFAULT '[]' COMMENT 'Beğenen kullanıcı ID''lerini içeren JSON array',
  `views` int(11) DEFAULT 0,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `visibility` enum('PUBLIC','PRIVATE') DEFAULT 'PUBLIC',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `referral_sources`
--

CREATE TABLE `referral_sources` (
  `source_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `source_type` enum('ORGANIC','REFERRAL','ADVERTISEMENT','SOCIAL_MEDIA') DEFAULT NULL,
  `specific_source` varchar(255) DEFAULT NULL,
  `is_referral_signup` tinyint(4) DEFAULT NULL,
  `join_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `referral_sources`
--

INSERT INTO `referral_sources` (`source_id`, `user_id`, `source_type`, `specific_source`, `is_referral_signup`, `join_date`) VALUES
(58, 113691405, 'ORGANIC', '674DB6106', 0, '2024-12-02 13:29:24'),
(59, 395548956, 'ORGANIC', '674DB6776', 1, '2024-12-02 13:30:48'),
(60, 768556619, 'ORGANIC', '67506685F', 1, '2024-12-04 14:26:37'),
(61, 384546394, 'ORGANIC', '6750BE7FC', 0, '2024-12-04 20:41:47'),
(62, 601838105, 'ORGANIC', '6752CF7C9', 0, '2024-12-06 10:18:43'),
(63, 257036736, 'ORGANIC', '6752CFBB4', 0, '2024-12-06 10:19:51');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('ADMIN','MODERATOR','SUPPORT') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `staff`
--

INSERT INTO `staff` (`staff_id`, `username`, `password`, `email`, `role`, `is_active`, `created_at`, `last_login`) VALUES
(6, 'admin', '$2y$10$0QqrVX5J7GbVHbgcvRx7fOI/FcfeR3e4MxSBGdlIxKtTMItLhWavS', 'admin@lureid.com', 'ADMIN', 1, '2024-11-19 12:29:38', '2024-12-04 22:13:53'),
(7, 'mod', '$2y$10$.JgApAzxzLdgmlTe1WQxiOZj0bG2A8FHocG6gyCMRwNrabpABlCaS', 'moderator@lureid.com', 'MODERATOR', 1, '2024-11-19 12:29:38', '2024-11-25 14:43:58'),
(8, 'sup', '$2y$10$R0QQciU4BasDU.T54J.Brep6koZiGzBKEzUt94jEwd5Ov88zsmUq2', 'support@lureid.com', 'SUPPORT', 1, '2024-11-19 12:29:38', '2024-11-19 15:54:20');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `staff_login_attempts`
--

CREATE TABLE `staff_login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('SUCCESS','FAILED') NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `isp` varchar(255) DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `browser_version` varchar(50) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `billing_period` enum('MONTHLY','YEARLY') DEFAULT 'MONTHLY',
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `next_billing_date` timestamp NOT NULL DEFAULT (current_timestamp() + interval 1 month),
  `status` enum('ACTIVE','CANCELLED','EXPIRED') DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `subscriptions`
--

INSERT INTO `subscriptions` (`subscription_id`, `user_id`, `subscription_name`, `price`, `billing_period`, `start_date`, `next_billing_date`, `status`) VALUES
(7, 395548956, 'ID+ Pro', 324.35, 'YEARLY', '2024-12-06 22:21:46', '2025-12-06 22:21:46', 'CANCELLED');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `temp_gigs`
--

CREATE TABLE `temp_gigs` (
  `temp_gig_id` int(11) NOT NULL,
  `freelancer_id` int(11) NOT NULL,
  `current_step` int(11) DEFAULT 1 COMMENT '1: Temel Bilgiler, 2: Detaylar, 3: Gereksinimler, 4: Fiyat ve Teslimat, 5: Medya',
  `form_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Her adımdaki form verileri' CHECK (json_valid(`form_data`)),
  `pricing_type` enum('ONE_TIME','DAILY','WEEKLY','MONTHLY') DEFAULT 'ONE_TIME',
  `media_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Geçici fotoğraf ve video yolları' CHECK (json_valid(`media_data`)),
  `milestones_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`milestones_data`)),
  `nda_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nda_data`)),
  `agreement_accepted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deliverables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deliverables`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `temp_users`
--

CREATE TABLE `temp_users` (
  `temp_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `verification_code` varchar(255) DEFAULT NULL,
  `invite_code` varchar(255) DEFAULT NULL,
  `referral_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 24 hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` bigint(20) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `transaction_type` enum('DEPOSIT','WITHDRAWAL','TRANSFER','PAYMENT','COINS_RECEIVED','COINS_USED') DEFAULT NULL,
  `status` enum('PENDING','COMPLETED','FAILED','CANCELLED') DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `sender_id`, `receiver_id`, `amount`, `transaction_type`, `status`, `description`, `created_at`) VALUES
(66685252486, 113691405, 395548956, 450.00, 'PAYMENT', 'COMPLETED', 'Payment for gig: l', '2024-12-12 12:54:37');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(4) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `two_factor_auth` tinyint(4) DEFAULT 0,
  `user_type` enum('user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token_expires_at` timestamp NULL DEFAULT NULL,
  `subscription_plan` enum('basic','id_plus','id_plus_pro') NOT NULL DEFAULT 'basic'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `phone`, `password`, `full_name`, `google_id`, `is_verified`, `remember_token`, `two_factor_auth`, `user_type`, `created_at`, `remember_token_expires_at`, `subscription_plan`) VALUES
(113691405, 'can', 'CAN@CAN.c', NULL, '$2y$10$DYcn9FVutlBmN3TimYX3ZOEzHn2W7KEr.GwjLsUQFmrNJhYgaqoG.', 'Can', NULL, 1, NULL, 0, 'user', '2024-12-02 13:29:24', NULL, 'basic'),
(257036736, 'mehmetuzar', 'mehmet@uzar.com', NULL, '$2y$10$wUld3G2m7jxWF9YB.PZvFO05QVrYMK4WIH0YRUH3Y78H2EHhlTHsW', 'Mehmet Uzar', NULL, 1, NULL, 0, 'user', '2024-12-06 10:19:51', NULL, 'basic'),
(384546394, 'canyilmaz', 'cnylmz735@gmail.com', NULL, '', 'Can Yılmaz', '105226956217972839065', 1, NULL, 0, 'user', '2024-12-04 20:41:47', NULL, 'basic'),
(395548956, 'tospaa1', 'osmananlatici@gmail.com', NULL, '$2y$10$Ohq8R.RJkkA8HR/R2N4yVuZodh87G35F.nJp4XVVJqTWflJjyArsy', 'osman', NULL, 1, NULL, 0, 'user', '2024-12-02 13:30:48', NULL, 'id_plus_pro'),
(601838105, 'denemehesap', 'lurephpmailersmtp@gmail.com', NULL, '', 'can yılmaz', '111671799081259037329', 1, NULL, 0, 'user', '2024-12-06 10:18:43', NULL, 'basic'),
(768556619, 'emir', 'emirpaytar2005@gmail.com', NULL, '$2y$10$5DV7oB9WIU6v3QL7xWxK5uvFz.TVkpVtOpKG.zFtP8ArL8vmpeZBm', 'emir', NULL, 1, NULL, 0, 'user', '2024-12-04 14:26:37', NULL, 'basic');

--
-- Tetikleyiciler `users`
--
DELIMITER $$
CREATE TRIGGER `create_user_settings` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    INSERT INTO user_settings (user_id) VALUES (NEW.user_id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_extended_details`
--

CREATE TABLE `user_extended_details` (
  `detail_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_photo_url` varchar(255) DEFAULT 'undefined',
  `cover_photo_url` varchar(255) DEFAULT 'undefined',
  `basic_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`basic_info`)),
  `education_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education_history`)),
  `work_experience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`work_experience`)),
  `skills_matrix` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills_matrix`)),
  `portfolio_showcase` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`portfolio_showcase`)),
  `professional_profile` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`professional_profile`)),
  `network_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`network_links`)),
  `achievements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`achievements`)),
  `community_engagement` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`community_engagement`)),
  `performance_metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`performance_metrics`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `profile_completeness` decimal(5,2) DEFAULT 0.00,
  `owned_badges` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`owned_badges`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `user_extended_details`
--

INSERT INTO `user_extended_details` (`detail_id`, `user_id`, `profile_photo_url`, `cover_photo_url`, `basic_info`, `education_history`, `work_experience`, `skills_matrix`, `portfolio_showcase`, `professional_profile`, `network_links`, `achievements`, `community_engagement`, `performance_metrics`, `created_at`, `updated_at`, `profile_completeness`, `owned_badges`) VALUES
(24, 113691405, 'profile/avatars/113691405.jpg', 'undefined', '{\"full_name\": \"Can\", \"age\": null, \"biography\": null, \"location\": {\"city\": null, \"country\": null}, \"contact\": {\"email\": null, \"website\": null}, \"languages\": []}', NULL, NULL, '{\"technical_skills\": [], \"soft_skills\": [], \"tools\": []}', NULL, NULL, '{\"professional\": {}, \"social\": {}, \"portfolio_sites\": {}}', NULL, NULL, NULL, '2024-12-02 13:29:26', '2024-12-06 20:51:06', 0.00, '[]'),
(25, 395548956, 'profile/avatars/395548956.jpg', 'profile/covers/395548956.jpg', '{\"full_name\":\"osman\",\"age\":20,\"biography\":\"ben osman taha anlat\\u0131c\\u0131 web sitesi , hosting , seo ile ilgili \\u00e7al\\u0131\\u015fmalar yapmaktay\\u0131m yakla\\u015f\\u0131k 8 y\\u0131ld\\u0131r reklam sekt\\u00f6r\\u00fcndeyim \\nprofesyonellerle \\u00e7al\\u0131\\u015fmak i\\u00e7in do\\u011fru adre\\u20ba\",\"location\":{\"city\":\"bursa\",\"country\":\"t\\u00fcrkiye\"},\"contact\":{\"email\":\"osmananlatici@gmail.com\",\"website\":\"medyametot.com\"},\"languages\":[\"t\\u00fcrk\\u00e7e\",\"ingilizce\"]}', '[{\"level\":\"university\",\"institution\":\"MCB\\u00dc\",\"degree\":\"Bilgisayar programc\\u0131l\\u0131\\u011f\\u0131\",\"gpa\":2.7,\"start_date\":\"2023-09\",\"end_date\":\"2025-06\"}]', '[{\"company\":\"Burhan reklam \",\"position\":\"cnc makinalar\\u0131 ve bask\\u0131 makinkar\\u0131 kontrol\\u00fc , bak\\u0131m\\u0131 ,tamiri\",\"start_date\":\"2018-06\",\"end_date\":\"2022-03\",\"description\":\"reklam at\\u00f6lyesinde t\\u00fcm makinalar\\u0131n bak\\u0131m\\u0131  tamiri ve operat\\u00f6rl\\u00fc\\u011f\\u00fc \"},{\"company\":\"medya metot\",\"position\":\"web master , grafiker\",\"start_date\":\"2022-06\",\"end_date\":\"2024-08\",\"description\":\"web sitesi ve genel tasar\\u0131m uzman\\u0131 \"}]', '{\"technical_skills\":[\"programlama (web)\",\"tasar\\u0131m\",\"cnc makine operat\\u00f6rl\\u00fc\\u011f\\u00fc\"],\"soft_skills\":[\"lider\",\"joker eleman\",\"hi\\u00e7bir \\u015fey imkans\\u0131z de\\u011fildir\"],\"tools\":[\"corel\",\"autocad\",\"dreamviewer\",\"photoshop\"]}', '[{\"title\":\"medya metot\",\"description\":\"kendi firmam\\u0131z i\\u00e7in olu\\u015fturmakta oldu\\u011fumuz web sitemiz\",\"url\":\"www.medyametot.com\"},{\"title\":\"skljdalja\",\"description\":\"klsfjkasld\",\"url\":\"kldsjfljs\"}]', '{\"summary\":\"y\\u0131llard\\u0131r s\\u00fcr\\u00fcn\\u00fcyor\",\"expertise_areas\":[\"hosting hizmetleri\"],\"certifications\":[\"sasa\"]}', '{\"professional\":{\"github\":\"tospaa1\"},\"social\":{\"instagram\":\"osmantahaaa\"},\"portfolio_sites\":[]}', '[{\"title\":\"jksfsdkjf\",\"issuer\":\"kjfsdfkjs\",\"date\":\"2024-11\",\"description\":\"ljkldjgskldf\"}]', NULL, NULL, '2024-12-02 13:30:50', '2024-12-06 22:43:19', 79.00, '[1]'),
(26, 768556619, 'profile/avatars/768556619.jpg', 'undefined', '{\"full_name\":\"emir\",\"age\":20,\"biography\":\"kemrogkreg\",\"location\":{\"city\":\"ferreg\",\"country\":\"qrgefwe\"},\"contact\":{\"email\":\"fewfew@f.f\",\"website\":\"efwefw.c\"},\"languages\":[\"T\\u00fcrk\\u00e7e\",\"\\u0130ngilizce\",\"Rus\\u00e7a\"]}', '[]', '[]', '{\"technical_skills\":[],\"soft_skills\":[],\"tools\":[]}', '[]', '{\"summary\":\"\",\"expertise_areas\":[],\"certifications\":[]}', '{\"professional\":[],\"social\":[],\"portfolio_sites\":[]}', '[]', NULL, NULL, '2024-12-04 14:26:39', '2024-12-04 14:31:13', 20.00, '[]'),
(27, 384546394, 'profile/avatars/384546394.jpg', 'undefined', '{\"full_name\":\"Can Y\\u0131lmaz\",\"age\":20,\"biography\":\"efwfew\",\"location\":{\"city\":\"fewfew\",\"country\":\"ewffew\"},\"contact\":{\"email\":\"fewfew\",\"website\":\"fewfew\"},\"languages\":[\"fewfew\"]}', '[]', '[]', '{\"technical_skills\":[],\"soft_skills\":[],\"tools\":[]}', '[]', '{\"summary\":\"\",\"expertise_areas\":[],\"certifications\":[]}', '{\"professional\":[],\"social\":[],\"portfolio_sites\":[]}', '[]', NULL, NULL, '2024-12-04 20:41:47', '2024-12-04 22:13:19', 20.00, '[]'),
(28, 601838105, 'profile/avatars/601838105.jpg', 'undefined', '{\"full_name\":\"can y\\u0131lmaz\",\"age\":null,\"biography\":null,\"location\":{\"city\":null,\"country\":null},\"contact\":{\"email\":null,\"website\":null},\"languages\":[]}', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-06 10:18:43', '2024-12-06 10:19:10', 12.50, '[]'),
(29, 257036736, 'profile/avatars/257036736.jpg', 'undefined', '{\"full_name\": \"Mehmet Uzar\", \"age\": null, \"biography\": null, \"location\": {\"city\": null, \"country\": null}, \"contact\": {\"email\": null, \"website\": null}, \"languages\": []}', NULL, NULL, '{\"technical_skills\": [], \"soft_skills\": [], \"tools\": []}', NULL, NULL, '{\"professional\": {}, \"social\": {}, \"portfolio_sites\": {}}', NULL, NULL, NULL, '2024-12-06 10:19:53', '2024-12-06 10:19:53', 0.00, '[]');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `language` varchar(10) DEFAULT 'tr' COMMENT 'Kullanıcı arayüz dili',
  `timezone` varchar(100) DEFAULT 'Europe/Istanbul' COMMENT 'Kullanıcı saat dilimi',
  `region` varchar(10) DEFAULT 'TR' COMMENT 'Kullanıcı bölgesi (ülke kodu)',
  `date_format` varchar(20) DEFAULT 'DD.MM.YYYY' COMMENT 'Tarih formatı',
  `time_format` varchar(20) DEFAULT '24h' COMMENT '12h veya 24h',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `theme` enum('light','dark') DEFAULT 'light',
  `font_family` varchar(50) DEFAULT 'Inter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `user_settings`
--

INSERT INTO `user_settings` (`setting_id`, `user_id`, `language`, `timezone`, `region`, `date_format`, `time_format`, `created_at`, `updated_at`, `theme`, `font_family`) VALUES
(18, 113691405, 'tr', 'Europe/Istanbul', 'TR', 'DD.MM.YYYY', '24h', '2024-12-02 13:29:24', NULL, 'light', 'Inter'),
(19, 395548956, 'tr', 'Europe/Istanbul', 'TR', 'DD.MM.YYYY', '24h', '2024-12-02 13:30:48', NULL, 'light', 'Inter'),
(20, 768556619, 'tr', 'Europe/Istanbul', 'TR', 'DD.MM.YYYY', '24h', '2024-12-04 14:26:37', '2024-12-04 14:30:43', 'light', 'Inter'),
(21, 384546394, 'tr', 'Europe/Istanbul', 'TR', 'DD.MM.YYYY', '24h', '2024-12-04 20:41:47', NULL, 'light', 'Inter'),
(22, 601838105, 'tr', 'Europe/Istanbul', 'TR', 'DD.MM.YYYY', '24h', '2024-12-06 10:18:43', NULL, 'light', 'Inter'),
(23, 257036736, 'tr', 'Europe/Istanbul', 'TR', 'DD.MM.YYYY', '24h', '2024-12-06 10:19:51', NULL, 'light', 'Inter');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `verification`
--

CREATE TABLE `verification` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `expiry_date` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `wallet`
--

CREATE TABLE `wallet` (
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `coins` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `last_transaction_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `wallet`
--

INSERT INTO `wallet` (`wallet_id`, `user_id`, `balance`, `coins`, `created_at`, `updated_at`, `last_transaction_date`) VALUES
(70, 113691405, 0.00, 0, '2024-12-02 13:29:24', '2024-12-11 07:44:57', '2024-12-06 22:54:01'),
(71, 395548956, 900.00, 0, '2024-12-02 13:30:48', '2024-12-12 12:55:51', '2024-12-12 12:55:51'),
(72, 768556619, 0.00, 0, '2024-12-04 14:26:37', '2024-12-05 15:33:44', '2024-12-04 14:26:37'),
(73, 384546394, 1550.00, 0, '2024-12-04 20:41:47', '2024-12-11 10:16:35', '2024-12-11 10:16:35'),
(74, 601838105, 0.00, 0, '2024-12-06 10:18:43', NULL, '2024-12-06 10:18:43'),
(75, 257036736, 0.00, 0, '2024-12-06 10:19:51', NULL, '2024-12-06 10:19:51');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`badge_id`);

--
-- Tablo için indeksler `follows`
--
ALTER TABLE `follows`
  ADD PRIMARY KEY (`user_id`);

--
-- Tablo için indeksler `freelancers`
--
ALTER TABLE `freelancers`
  ADD PRIMARY KEY (`freelancer_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `gigs`
--
ALTER TABLE `gigs`
  ADD PRIMARY KEY (`gig_id`),
  ADD KEY `freelancer_id` (`freelancer_id`);

--
-- Tablo için indeksler `gig_categories`
--
ALTER TABLE `gig_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Tablo için indeksler `invitations`
--
ALTER TABLE `invitations`
  ADD PRIMARY KEY (`invitation_id`),
  ADD KEY `invitations_ibfk_1` (`inviter_id`),
  ADD KEY `invitations_ibfk_2` (`invited_user_id`);

--
-- Tablo için indeksler `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `gig_id` (`gig_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `freelancer_id` (`freelancer_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Tablo için indeksler `job_reviews`
--
ALTER TABLE `job_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_job_review` (`job_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `freelancer_id` (`freelancer_id`);

--
-- Tablo için indeksler `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `referral_sources`
--
ALTER TABLE `referral_sources`
  ADD PRIMARY KEY (`source_id`),
  ADD KEY `referral_sources_ibfk_1` (`user_id`);

--
-- Tablo için indeksler `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `staff_login_attempts`
--
ALTER TABLE `staff_login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Tablo için indeksler `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `temp_gigs`
--
ALTER TABLE `temp_gigs`
  ADD PRIMARY KEY (`temp_gig_id`),
  ADD KEY `freelancer_id` (`freelancer_id`);

--
-- Tablo için indeksler `temp_users`
--
ALTER TABLE `temp_users`
  ADD PRIMARY KEY (`temp_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `transactions_ibfk_1` (`sender_id`),
  ADD KEY `transactions_ibfk_2` (`receiver_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`);

--
-- Tablo için indeksler `user_extended_details`
--
ALTER TABLE `user_extended_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `user_extended_details_user_id` (`user_id`);

--
-- Tablo için indeksler `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `verification`
--
ALTER TABLE `verification`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `verification_ibfk_1` (`user_id`);

--
-- Tablo için indeksler `wallet`
--
ALTER TABLE `wallet`
  ADD PRIMARY KEY (`wallet_id`),
  ADD KEY `wallet_ibfk_1` (`user_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `badges`
--
ALTER TABLE `badges`
  MODIFY `badge_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `freelancers`
--
ALTER TABLE `freelancers`
  MODIFY `freelancer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Tablo için AUTO_INCREMENT değeri `gigs`
--
ALTER TABLE `gigs`
  MODIFY `gig_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Tablo için AUTO_INCREMENT değeri `gig_categories`
--
ALTER TABLE `gig_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- Tablo için AUTO_INCREMENT değeri `invitations`
--
ALTER TABLE `invitations`
  MODIFY `invitation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Tablo için AUTO_INCREMENT değeri `jobs`
--
ALTER TABLE `jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Tablo için AUTO_INCREMENT değeri `job_reviews`
--
ALTER TABLE `job_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- Tablo için AUTO_INCREMENT değeri `referral_sources`
--
ALTER TABLE `referral_sources`
  MODIFY `source_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- Tablo için AUTO_INCREMENT değeri `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `staff_login_attempts`
--
ALTER TABLE `staff_login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Tablo için AUTO_INCREMENT değeri `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Tablo için AUTO_INCREMENT değeri `temp_gigs`
--
ALTER TABLE `temp_gigs`
  MODIFY `temp_gig_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Tablo için AUTO_INCREMENT değeri `temp_users`
--
ALTER TABLE `temp_users`
  MODIFY `temp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=997949467;

--
-- Tablo için AUTO_INCREMENT değeri `user_extended_details`
--
ALTER TABLE `user_extended_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Tablo için AUTO_INCREMENT değeri `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Tablo için AUTO_INCREMENT değeri `verification`
--
ALTER TABLE `verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Tablo için AUTO_INCREMENT değeri `wallet`
--
ALTER TABLE `wallet`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `follows`
--
ALTER TABLE `follows`
  ADD CONSTRAINT `follows_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `freelancers`
--
ALTER TABLE `freelancers`
  ADD CONSTRAINT `freelancers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `gigs`
--
ALTER TABLE `gigs`
  ADD CONSTRAINT `gigs_ibfk_1` FOREIGN KEY (`freelancer_id`) REFERENCES `freelancers` (`freelancer_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `gig_categories`
--
ALTER TABLE `gig_categories`
  ADD CONSTRAINT `gig_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `gig_categories` (`category_id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `invitations`
--
ALTER TABLE `invitations`
  ADD CONSTRAINT `invitations_ibfk_1` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invitations_ibfk_2` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`gig_id`) REFERENCES `gigs` (`gig_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jobs_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jobs_ibfk_3` FOREIGN KEY (`freelancer_id`) REFERENCES `freelancers` (`freelancer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jobs_ibfk_4` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `job_reviews`
--
ALTER TABLE `job_reviews`
  ADD CONSTRAINT `job_reviews_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_reviews_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_reviews_ibfk_3` FOREIGN KEY (`freelancer_id`) REFERENCES `freelancers` (`freelancer_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `referral_sources`
--
ALTER TABLE `referral_sources`
  ADD CONSTRAINT `referral_sources_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `staff_login_attempts`
--
ALTER TABLE `staff_login_attempts`
  ADD CONSTRAINT `staff_login_attempts_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `temp_gigs`
--
ALTER TABLE `temp_gigs`
  ADD CONSTRAINT `temp_gigs_ibfk_1` FOREIGN KEY (`freelancer_id`) REFERENCES `freelancers` (`freelancer_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `user_extended_details`
--
ALTER TABLE `user_extended_details`
  ADD CONSTRAINT `fk_user_extended_details_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `verification`
--
ALTER TABLE `verification`
  ADD CONSTRAINT `verification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `wallet`
--
ALTER TABLE `wallet`
  ADD CONSTRAINT `wallet_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;
