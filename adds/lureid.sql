-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 02 Oca 2025, 14:11:32
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `lureid`
--
CREATE DATABASE IF NOT EXISTS `lureid` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `lureid`;

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
-- Tablo için tablo yapısı `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `preview_video_url` varchar(255) DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `what_will_learn` text DEFAULT NULL,
  `level` enum('BEGINNER','INTERMEDIATE','ADVANCED') NOT NULL,
  `language` varchar(50) NOT NULL,
  `enrolled_students` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`enrolled_students`)),
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `status` enum('DRAFT','PENDING_REVIEW','PUBLISHED','UNPUBLISHED') DEFAULT 'DRAFT',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `course_categories`
--

CREATE TABLE `course_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `course_categories`
--

INSERT INTO `course_categories` (`category_id`, `name`, `parent_id`, `icon_url`, `created_at`) VALUES
(1, 'Yazılım Geliştirme', NULL, NULL, '2024-12-23 14:16:04'),
(2, 'İş', NULL, NULL, '2024-12-23 14:16:04'),
(3, 'Finans ve Muhasebe', NULL, NULL, '2024-12-23 14:16:04'),
(4, 'BT ve Yazılım', NULL, NULL, '2024-12-23 14:16:04'),
(5, 'Ofis Uygulamaları', NULL, NULL, '2024-12-23 14:16:04'),
(6, 'Kişisel Gelişim', NULL, NULL, '2024-12-23 14:16:04'),
(7, 'Tasarım', NULL, NULL, '2024-12-23 14:16:04'),
(8, 'Pazarlama', NULL, NULL, '2024-12-23 14:16:04'),
(9, 'Sağlık ve Fitness', NULL, NULL, '2024-12-23 14:16:04'),
(10, 'Müzik', NULL, NULL, '2024-12-23 14:16:04'),
(11, 'Yaşam Tarzı', NULL, NULL, '2024-12-23 14:16:04'),
(12, 'Fotoğrafçılık ve Video', NULL, NULL, '2024-12-23 14:16:04'),
(13, 'Eğitim ve Öğretim', NULL, NULL, '2024-12-23 14:16:04'),
(14, 'Yabancı Dil', NULL, NULL, '2024-12-23 14:16:04'),
(15, 'Web Geliştirme - Frontend', 1, NULL, '2024-12-23 14:16:04'),
(16, 'Web Geliştirme - Backend', 1, NULL, '2024-12-23 14:16:04'),
(17, 'Mobil Uygulama Geliştirme', 1, NULL, '2024-12-23 14:16:04'),
(18, 'JavaScript', 1, NULL, '2024-12-23 14:16:04'),
(19, 'React', 1, NULL, '2024-12-23 14:16:04'),
(20, 'Angular', 1, NULL, '2024-12-23 14:16:04'),
(21, 'Vue.js', 1, NULL, '2024-12-23 14:16:04'),
(22, 'Python', 1, NULL, '2024-12-23 14:16:04'),
(23, 'Java', 1, NULL, '2024-12-23 14:16:04'),
(24, 'C#', 1, NULL, '2024-12-23 14:16:04'),
(25, 'PHP', 1, NULL, '2024-12-23 14:16:04'),
(26, 'Node.js', 1, NULL, '2024-12-23 14:16:04'),
(27, 'iOS Geliştirme', 1, NULL, '2024-12-23 14:16:04'),
(28, 'Android Geliştirme', 1, NULL, '2024-12-23 14:16:04'),
(29, 'Flutter', 1, NULL, '2024-12-23 14:16:04'),
(30, 'React Native', 1, NULL, '2024-12-23 14:16:04'),
(31, 'Unity', 1, NULL, '2024-12-23 14:16:04'),
(32, 'Game Development', 1, NULL, '2024-12-23 14:16:04'),
(33, 'Unreal Engine', 1, NULL, '2024-12-23 14:16:04'),
(34, 'Veritabanı Tasarımı', 1, NULL, '2024-12-23 14:16:04'),
(35, 'SQL', 1, NULL, '2024-12-23 14:16:04'),
(36, 'MongoDB', 1, NULL, '2024-12-23 14:16:04'),
(37, 'DevOps', 1, NULL, '2024-12-23 14:16:04'),
(38, 'Git', 1, NULL, '2024-12-23 14:16:04'),
(39, 'Docker', 1, NULL, '2024-12-23 14:16:04'),
(40, 'Girişimcilik', 2, NULL, '2024-12-23 14:16:04'),
(41, 'İletişim Becerileri', 2, NULL, '2024-12-23 14:16:04'),
(42, 'Yönetim ve Liderlik', 2, NULL, '2024-12-23 14:16:04'),
(43, 'Proje Yönetimi', 2, NULL, '2024-12-23 14:16:04'),
(44, 'İş Analizi ve Strateji', 2, NULL, '2024-12-23 14:16:04'),
(45, 'Satış Teknikleri', 2, NULL, '2024-12-23 14:16:04'),
(46, 'İnsan Kaynakları', 2, NULL, '2024-12-23 14:16:04'),
(47, 'E-Ticaret', 2, NULL, '2024-12-23 14:16:04'),
(48, 'Startup Yönetimi', 2, NULL, '2024-12-23 14:16:04'),
(49, 'İş Hukuku', 2, NULL, '2024-12-23 14:16:04'),
(50, 'Risk Yönetimi', 2, NULL, '2024-12-23 14:16:04'),
(51, 'Operasyon Yönetimi', 2, NULL, '2024-12-23 14:16:04'),
(52, 'Muhasebe Temelleri', 3, NULL, '2024-12-23 14:16:04'),
(53, 'Finansal Analiz', 3, NULL, '2024-12-23 14:16:04'),
(54, 'Yatırım Stratejileri', 3, NULL, '2024-12-23 14:16:04'),
(55, 'Borsa ve Hisse Senetleri', 3, NULL, '2024-12-23 14:16:04'),
(56, 'Kripto Paralar', 3, NULL, '2024-12-23 14:16:04'),
(57, 'Risk Yönetimi', 3, NULL, '2024-12-23 14:16:04'),
(58, 'Vergi Planlaması', 3, NULL, '2024-12-23 14:16:04'),
(59, 'Finansal Modelleme', 3, NULL, '2024-12-23 14:16:04'),
(60, 'Excel ile Finans', 3, NULL, '2024-12-23 14:16:04'),
(61, 'Siber Güvenlik', 4, NULL, '2024-12-23 14:16:04'),
(62, 'Ağ Yönetimi', 4, NULL, '2024-12-23 14:16:04'),
(63, 'Cloud Computing', 4, NULL, '2024-12-23 14:16:04'),
(64, 'AWS', 4, NULL, '2024-12-23 14:16:04'),
(65, 'Azure', 4, NULL, '2024-12-23 14:16:04'),
(66, 'Google Cloud', 4, NULL, '2024-12-23 14:16:04'),
(67, 'Linux', 4, NULL, '2024-12-23 14:16:04'),
(68, 'Windows Server', 4, NULL, '2024-12-23 14:16:04'),
(69, 'Virtualization', 4, NULL, '2024-12-23 14:16:04'),
(70, 'IT Service Management', 4, NULL, '2024-12-23 14:16:04'),
(71, 'Microsoft Excel', 5, NULL, '2024-12-23 14:16:04'),
(72, 'Microsoft Word', 5, NULL, '2024-12-23 14:16:04'),
(73, 'Microsoft PowerPoint', 5, NULL, '2024-12-23 14:16:04'),
(74, 'Microsoft Access', 5, NULL, '2024-12-23 14:16:04'),
(75, 'Google Workspace', 5, NULL, '2024-12-23 14:16:04'),
(76, 'SAP', 5, NULL, '2024-12-23 14:16:04'),
(77, 'Excel VBA', 5, NULL, '2024-12-23 14:16:04'),
(78, 'Excel Makrolar', 5, NULL, '2024-12-23 14:16:04'),
(79, 'Microsoft Teams', 5, NULL, '2024-12-23 14:16:04'),
(80, 'Microsoft Project', 5, NULL, '2024-12-23 14:16:04'),
(81, 'OneNote', 5, NULL, '2024-12-23 14:16:04'),
(82, 'LibreOffice', 5, NULL, '2024-12-23 14:16:04'),
(83, 'Liderlik', 6, NULL, '2024-12-23 14:16:04'),
(84, 'Kişisel Verimlilik', 6, NULL, '2024-12-23 14:16:04'),
(85, 'Stres Yönetimi', 6, NULL, '2024-12-23 14:16:04'),
(86, 'Zaman Yönetimi', 6, NULL, '2024-12-23 14:16:04'),
(87, 'İletişim Becerileri', 6, NULL, '2024-12-23 14:16:04'),
(88, 'Hafıza Teknikleri', 6, NULL, '2024-12-23 14:16:04'),
(89, 'Hızlı Okuma', 6, NULL, '2024-12-23 14:16:04'),
(90, 'Motivasyon', 6, NULL, '2024-12-23 14:16:04'),
(91, 'Özgüven Geliştirme', 6, NULL, '2024-12-23 14:16:04'),
(92, 'Kariyer Planlama', 6, NULL, '2024-12-23 14:16:04'),
(93, 'Meditasyon', 6, NULL, '2024-12-23 14:16:04'),
(94, 'Mindfulness', 6, NULL, '2024-12-23 14:16:04'),
(95, 'UI/UX Tasarım', 7, NULL, '2024-12-23 14:16:04'),
(96, 'Grafik Tasarım', 7, NULL, '2024-12-23 14:16:04'),
(97, 'Web Tasarım', 7, NULL, '2024-12-23 14:16:04'),
(98, 'Adobe Photoshop', 7, NULL, '2024-12-23 14:16:04'),
(99, 'Adobe Illustrator', 7, NULL, '2024-12-23 14:16:04'),
(100, 'Adobe XD', 7, NULL, '2024-12-23 14:16:04'),
(101, 'Figma', 7, NULL, '2024-12-23 14:16:04'),
(102, 'Sketch', 7, NULL, '2024-12-23 14:16:04'),
(103, '3D Modelleme', 7, NULL, '2024-12-23 14:16:04'),
(104, 'Blender', 7, NULL, '2024-12-23 14:16:04'),
(105, 'Maya', 7, NULL, '2024-12-23 14:16:04'),
(106, 'AutoCAD', 7, NULL, '2024-12-23 14:16:04'),
(107, 'İç Mimari Tasarım', 7, NULL, '2024-12-23 14:16:04'),
(108, 'Logo Tasarımı', 7, NULL, '2024-12-23 14:16:04'),
(109, 'Marka Tasarımı', 7, NULL, '2024-12-23 14:16:04'),
(110, 'Dijital Pazarlama', 8, NULL, '2024-12-23 14:16:04'),
(111, 'Sosyal Medya Pazarlaması', 8, NULL, '2024-12-23 14:16:04'),
(112, 'SEO', 8, NULL, '2024-12-23 14:16:04'),
(113, 'Google Ads', 8, NULL, '2024-12-23 14:16:04'),
(114, 'Facebook Reklamları', 8, NULL, '2024-12-23 14:16:04'),
(115, 'Instagram Pazarlama', 8, NULL, '2024-12-23 14:16:04'),
(116, 'İçerik Pazarlaması', 8, NULL, '2024-12-23 14:16:04'),
(117, 'E-posta Pazarlaması', 8, NULL, '2024-12-23 14:16:04'),
(118, 'Affiliate Marketing', 8, NULL, '2024-12-23 14:16:04'),
(119, 'Marka Yönetimi', 8, NULL, '2024-12-23 14:16:04'),
(120, 'Copywriting', 8, NULL, '2024-12-23 14:16:04'),
(121, 'Google Analytics', 8, NULL, '2024-12-23 14:16:04'),
(122, 'Fitness Eğitimi', 9, NULL, '2024-12-23 14:16:04'),
(123, 'Yoga', 9, NULL, '2024-12-23 14:16:04'),
(124, 'Pilates', 9, NULL, '2024-12-23 14:16:04'),
(125, 'Beslenme', 9, NULL, '2024-12-23 14:16:04'),
(126, 'Kilo Verme', 9, NULL, '2024-12-23 14:16:04'),
(127, 'Vücut Geliştirme', 9, NULL, '2024-12-23 14:16:04'),
(128, 'Meditasyon', 9, NULL, '2024-12-23 14:16:04'),
(129, 'Spor Beslenme', 9, NULL, '2024-12-23 14:16:04'),
(130, 'Masaj Teknikleri', 9, NULL, '2024-12-23 14:16:04'),
(131, 'Dans', 9, NULL, '2024-12-23 14:16:04'),
(132, 'Gitar', 10, NULL, '2024-12-23 14:16:04'),
(133, 'Piyano', 10, NULL, '2024-12-23 14:16:04'),
(134, 'Ses Eğitimi', 10, NULL, '2024-12-23 14:16:04'),
(135, 'Müzik Teorisi', 10, NULL, '2024-12-23 14:16:04'),
(136, 'Müzik Prodüksiyon', 10, NULL, '2024-12-23 14:16:04'),
(137, 'DJ\'lik', 10, NULL, '2024-12-23 14:16:04'),
(138, 'Davul', 10, NULL, '2024-12-23 14:16:04'),
(139, 'Keman', 10, NULL, '2024-12-23 14:16:04'),
(140, 'Bağlama', 10, NULL, '2024-12-23 14:16:04'),
(141, 'FL Studio', 10, NULL, '2024-12-23 14:16:04'),
(142, 'Logic Pro', 10, NULL, '2024-12-23 14:16:04'),
(143, 'Ableton Live', 10, NULL, '2024-12-23 14:16:04'),
(144, 'Yemek Yapma', 11, NULL, '2024-12-23 14:16:04'),
(145, 'Seyahat', 11, NULL, '2024-12-23 14:16:04'),
(146, 'Ev Dekorasyonu', 11, NULL, '2024-12-23 14:16:04'),
(147, 'Bahçecilik', 11, NULL, '2024-12-23 14:16:04'),
(148, 'El Sanatları', 11, NULL, '2024-12-23 14:16:04'),
(149, 'Güzellik ve Makyaj', 11, NULL, '2024-12-23 14:16:04'),
(150, 'Evcil Hayvan Bakımı', 11, NULL, '2024-12-23 14:16:04'),
(151, 'Astroloji', 11, NULL, '2024-12-23 14:16:04'),
(152, 'Kahve Yapımı', 11, NULL, '2024-12-23 14:16:04'),
(153, 'Moda ve Stil', 11, NULL, '2024-12-23 14:16:04'),
(154, 'Dijital Fotoğrafçılık', 12, NULL, '2024-12-23 14:16:04'),
(155, 'Video Düzenleme', 12, NULL, '2024-12-23 14:16:04'),
(156, 'Adobe Premiere Pro', 12, NULL, '2024-12-23 14:16:04'),
(157, 'Final Cut Pro', 12, NULL, '2024-12-23 14:16:04'),
(158, 'Adobe After Effects', 12, NULL, '2024-12-23 14:16:04'),
(159, 'Sinematografi', 12, NULL, '2024-12-23 14:16:04'),
(160, 'Portre Fotoğrafçılığı', 12, NULL, '2024-12-23 14:16:04'),
(161, 'Manzara Fotoğrafçılığı', 12, NULL, '2024-12-23 14:16:04'),
(162, 'Drone Fotoğrafçılığı', 12, NULL, '2024-12-23 14:16:04'),
(163, 'Lightroom', 12, NULL, '2024-12-23 14:16:04'),
(164, 'DaVinci Resolve', 12, NULL, '2024-12-23 14:16:04'),
(165, 'Youtube İçerik Üretimi', 12, NULL, '2024-12-23 14:16:04'),
(166, 'Online Eğitmenlik', 13, NULL, '2024-12-23 14:16:04'),
(167, 'Eğitim Teknolojileri', 13, NULL, '2024-12-23 14:16:04'),
(168, 'Öğretim Tasarımı', 13, NULL, '2024-12-23 14:16:04'),
(169, 'Sınıf Yönetimi', 13, NULL, '2024-12-23 14:16:04'),
(170, 'Özel Eğitim', 13, NULL, '2024-12-23 14:16:04'),
(171, 'Eğitim Psikolojisi', 13, NULL, '2024-12-23 14:16:04'),
(172, 'Ölçme ve Değerlendirme', 13, NULL, '2024-12-23 14:16:04'),
(173, 'STEM Eğitimi', 13, NULL, '2024-12-23 14:16:04'),
(174, 'Montessori Eğitimi', 13, NULL, '2024-12-23 14:16:04'),
(175, 'Okul Öncesi Eğitim', 13, NULL, '2024-12-23 14:16:04'),
(176, 'İngilizce Başlangıç', 14, NULL, '2024-12-23 14:16:04'),
(177, 'İngilizce İleri Seviye', 14, NULL, '2024-12-23 14:16:04'),
(178, 'İş İngilizcesi', 14, NULL, '2024-12-23 14:16:04'),
(179, 'IELTS Hazırlık', 14, NULL, '2024-12-23 14:16:04'),
(180, 'TOEFL Hazırlık', 14, NULL, '2024-12-23 14:16:04'),
(181, 'Almanca A1-A2', 14, NULL, '2024-12-23 14:16:04'),
(182, 'Almanca B1-B2', 14, NULL, '2024-12-23 14:16:04'),
(183, 'Fransızca', 14, NULL, '2024-12-23 14:16:04'),
(184, 'İspanyolca', 14, NULL, '2024-12-23 14:16:04'),
(185, 'İtalyanca', 14, NULL, '2024-12-23 14:16:04'),
(186, 'Rusça', 14, NULL, '2024-12-23 14:16:04'),
(187, 'Japonca', 14, NULL, '2024-12-23 14:16:04'),
(188, 'Korece', 14, NULL, '2024-12-23 14:16:04'),
(189, 'Çince', 14, NULL, '2024-12-23 14:16:04'),
(190, 'Arapça', 14, NULL, '2024-12-23 14:16:04');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `course_contents`
--

CREATE TABLE `course_contents` (
  `content_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content_type` enum('VIDEO','TEXT','QUIZ','EXAMPLE','PRACTICE','ASSIGNMENT') NOT NULL,
  `content_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content_data`)),
  `duration` int(11) DEFAULT NULL COMMENT 'Video süresi (saniye)',
  `order_number` int(11) NOT NULL,
  `is_free` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `course_enrollments`
--

CREATE TABLE `course_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `progress_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' COMMENT 'Tamamlanan içerikler ve ilerleme' CHECK (json_valid(`progress_data`)),
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `completion_date` timestamp NULL DEFAULT NULL,
  `certificate_id` varchar(255) DEFAULT NULL,
  `transaction_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `course_forum`
--

CREATE TABLE `course_forum` (
  `post_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `post_type` enum('QUESTION','DISCUSSION','RESOURCE','ANNOUNCEMENT') NOT NULL,
  `parent_id` int(11) DEFAULT NULL COMMENT 'Yanıtlar için',
  `upvotes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' COMMENT 'Beğenen kullanıcı ID''leri' CHECK (json_valid(`upvotes`)),
  `is_solution` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `course_reviews`
--

CREATE TABLE `course_reviews` (
  `review_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `helpful_votes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`helpful_votes`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `course_sections`
--

CREATE TABLE `course_sections` (
  `section_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `order_number` int(11) NOT NULL,
  `section_type` enum('VIDEO_CONTENT','PRACTICE_EXAMPLES','FORUM') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Tablo için tablo yapısı `instructors`
--

CREATE TABLE `instructors` (
  `instructor_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `expertise_areas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`expertise_areas`)),
  `education` text DEFAULT NULL,
  `teaching_experience` text DEFAULT NULL,
  `total_students` int(11) DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `earnings_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`earnings_data`)),
  `payout_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`payout_details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `visibility` enum('public','private','followers','connections') NOT NULL DEFAULT 'public',
  `owner_id` int(11) NOT NULL,
  `collaborators` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' COMMENT 'Array of user IDs who can edit the project' CHECK (json_valid(`collaborators`)),
  `invite_code` varchar(255) DEFAULT NULL COMMENT 'Unique code for inviting collaborators',
  `invite_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('active','archived','deleted') NOT NULL DEFAULT 'active',
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'For future extensibility' CHECK (json_valid(`additional_data`)),
  `file_path` varchar(255) DEFAULT NULL,
  `preview_image` varchar(255) DEFAULT NULL
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
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`badge_id`);

--
-- Tablo için indeksler `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `courses_ibfk_2` (`category_id`),
  ADD KEY `courses_ibfk_3` (`subcategory_id`);

--
-- Tablo için indeksler `course_categories`
--
ALTER TABLE `course_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Tablo için indeksler `course_contents`
--
ALTER TABLE `course_contents`
  ADD PRIMARY KEY (`content_id`),
  ADD KEY `course_contents_ibfk_1` (`section_id`);

--
-- Tablo için indeksler `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `course_enrollments_ibfk_1` (`course_id`);

--
-- Tablo için indeksler `course_forum`
--
ALTER TABLE `course_forum`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `course_forum_ibfk_1` (`course_id`);

--
-- Tablo için indeksler `course_reviews`
--
ALTER TABLE `course_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_reviews_ibfk_1` (`course_id`);

--
-- Tablo için indeksler `course_sections`
--
ALTER TABLE `course_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `course_sections_ibfk_1` (`course_id`);

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
-- Tablo için indeksler `instructors`
--
ALTER TABLE `instructors`
  ADD PRIMARY KEY (`instructor_id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD UNIQUE KEY `invite_code` (`invite_code`),
  ADD KEY `owner_id` (`owner_id`);

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
-- Tablo için AUTO_INCREMENT değeri `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `course_categories`
--
ALTER TABLE `course_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- Tablo için AUTO_INCREMENT değeri `course_contents`
--
ALTER TABLE `course_contents`
  MODIFY `content_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Tablo için AUTO_INCREMENT değeri `course_enrollments`
--
ALTER TABLE `course_enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `course_forum`
--
ALTER TABLE `course_forum`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `course_reviews`
--
ALTER TABLE `course_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `course_sections`
--
ALTER TABLE `course_sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- Tablo için AUTO_INCREMENT değeri `freelancers`
--
ALTER TABLE `freelancers`
  MODIFY `freelancer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Tablo için AUTO_INCREMENT değeri `gigs`
--
ALTER TABLE `gigs`
  MODIFY `gig_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Tablo için AUTO_INCREMENT değeri `gig_categories`
--
ALTER TABLE `gig_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- Tablo için AUTO_INCREMENT değeri `instructors`
--
ALTER TABLE `instructors`
  MODIFY `instructor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `invitations`
--
ALTER TABLE `invitations`
  MODIFY `invitation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Tablo için AUTO_INCREMENT değeri `jobs`
--
ALTER TABLE `jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Tablo için AUTO_INCREMENT değeri `job_reviews`
--
ALTER TABLE `job_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- Tablo için AUTO_INCREMENT değeri `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `referral_sources`
--
ALTER TABLE `referral_sources`
  MODIFY `source_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

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
  MODIFY `temp_gig_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Tablo için AUTO_INCREMENT değeri `temp_users`
--
ALTER TABLE `temp_users`
  MODIFY `temp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=997949467;

--
-- Tablo için AUTO_INCREMENT değeri `user_extended_details`
--
ALTER TABLE `user_extended_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- Tablo için AUTO_INCREMENT değeri `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Tablo için AUTO_INCREMENT değeri `verification`
--
ALTER TABLE `verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Tablo için AUTO_INCREMENT değeri `wallet`
--
ALTER TABLE `wallet`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `instructors` (`instructor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `course_categories` (`category_id`),
  ADD CONSTRAINT `courses_ibfk_3` FOREIGN KEY (`subcategory_id`) REFERENCES `course_categories` (`category_id`);

--
-- Tablo kısıtlamaları `course_categories`
--
ALTER TABLE `course_categories`
  ADD CONSTRAINT `course_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `course_categories` (`category_id`);

--
-- Tablo kısıtlamaları `course_contents`
--
ALTER TABLE `course_contents`
  ADD CONSTRAINT `course_contents_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `course_sections` (`section_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `course_enrollments`
--
ALTER TABLE `course_enrollments`
  ADD CONSTRAINT `course_enrollments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_enrollments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_enrollments_ibfk_3` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`);

--
-- Tablo kısıtlamaları `course_forum`
--
ALTER TABLE `course_forum`
  ADD CONSTRAINT `course_forum_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_forum_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_forum_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `course_forum` (`post_id`);

--
-- Tablo kısıtlamaları `course_reviews`
--
ALTER TABLE `course_reviews`
  ADD CONSTRAINT `course_reviews_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `course_sections`
--
ALTER TABLE `course_sections`
  ADD CONSTRAINT `course_sections_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

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
-- Tablo kısıtlamaları `instructors`
--
ALTER TABLE `instructors`
  ADD CONSTRAINT `instructors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
