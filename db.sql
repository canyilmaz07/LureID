-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 23 Kas 2024, 19:21:03
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
(251081278, '[633509523]', '[633509523]', '2024-11-22 18:15:36', '2024-11-22 23:16:11'),
(633509523, '[251081278]', '[251081278]', '2024-11-22 23:06:14', '2024-11-22 23:16:11');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `freelancers`
--

CREATE TABLE `freelancers` (
  `freelancer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_holder` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `iban` varchar(255) DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `experience_time` int(11) DEFAULT NULL COMMENT 'Experience time in days',
  `availability_status` enum('AVAILABLE','UNAVAILABLE') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `freelancers`
--

INSERT INTO `freelancers` (`freelancer_id`, `user_id`, `account_holder`, `bank_name`, `iban`, `tax_number`, `daily_rate`, `experience_time`, `availability_status`, `created_at`) VALUES
(5, 251081278, 'Can', 'Yapıkredi', 'TR165165165165165156165161', '6516516516', 500.00, 1, 'AVAILABLE', '2024-11-22 23:00:08');

--
-- Tetikleyiciler `freelancers`
--
DELIMITER $$
CREATE TRIGGER `init_freelancer_experience` BEFORE INSERT ON `freelancers` FOR EACH ROW BEGIN
    -- created_at'i Istanbul saatine göre ayarla
    SET NEW.created_at = CONVERT_TZ(CURRENT_TIMESTAMP, @@session.time_zone, '+03:00');
    
    -- Kullanıcının kayıt tarihinden bu yana geçen süreyi Istanbul saatine göre hesapla
    SELECT DATEDIFF(
        CONVERT_TZ(CURRENT_TIMESTAMP, @@session.time_zone, '+03:00'),
        CONVERT_TZ(created_at, @@session.time_zone, '+03:00')
    ) INTO @user_days
    FROM users WHERE user_id = NEW.user_id;
    
    SET NEW.experience_time = @user_days;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_freelancer_experience` BEFORE UPDATE ON `freelancers` FOR EACH ROW BEGIN
    -- CONVERT_TZ ile Istanbul saatine çeviriyoruz
    IF OLD.created_at IS NOT NULL THEN
        SET NEW.experience_time = DATEDIFF(
            CONVERT_TZ(CURRENT_TIMESTAMP, @@session.time_zone, '+03:00'),
            CONVERT_TZ(OLD.created_at, @@session.time_zone, '+03:00')
        );
    END IF;
END
$$
DELIMITER ;

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
(13, 251081278, 633509523, '6740CA389', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `jobs`
--

CREATE TABLE `jobs` (
  `job_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `freelancer_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('OPEN','IN_PROGRESS','DELIVERED','COMPLETED','CANCELLED') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `jobs`
--

INSERT INTO `jobs` (`job_id`, `user_id`, `freelancer_id`, `title`, `description`, `requirements`, `category`, `budget`, `deadline`, `status`, `created_at`, `updated_at`) VALUES
(9, 633509523, 5, 'Yeni', 'iş', 'Yok', 'deneme', 1000.00, NULL, 'COMPLETED', '2024-11-22 23:10:55', '2024-11-22 23:11:43');

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
(39, 251081278, 'ORGANIC', '6740CA389', 0, '2024-11-22 18:15:34'),
(40, 633509523, 'ORGANIC', '67410E470', 1, '2024-11-22 23:06:12');

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
(6, 'admin', '$2y$10$0QqrVX5J7GbVHbgcvRx7fOI/FcfeR3e4MxSBGdlIxKtTMItLhWavS', 'admin@lureid.com', 'ADMIN', 1, '2024-11-19 12:29:38', '2024-11-21 10:30:11'),
(7, 'mod', '$2y$10$.JgApAzxzLdgmlTe1WQxiOZj0bG2A8FHocG6gyCMRwNrabpABlCaS', 'moderator@lureid.com', 'MODERATOR', 1, '2024-11-19 12:29:38', '2024-11-19 12:33:39'),
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
  `transaction_type` enum('DEPOSIT','WITHDRAWAL','TRANSFER','PAYMENT') DEFAULT NULL,
  `status` enum('PENDING','COMPLETED','FAILED','CANCELLED') DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `sender_id`, `receiver_id`, `amount`, `transaction_type`, `status`, `description`, `created_at`) VALUES
(22113755770, 633509523, 251081278, 100.00, 'TRANSFER', 'COMPLETED', 'Transfer to can', '2024-11-22 23:42:31'),
(26181805795, 633509523, 633509523, 1500.00, 'DEPOSIT', 'COMPLETED', 'Credit card deposit to wallet', '2024-11-22 23:38:32'),
(77834045961, 633509523, 633509523, 100.00, 'WITHDRAWAL', 'COMPLETED', 'Withdrawal from wallet', '2024-11-22 23:42:22');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(4) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `two_factor_auth` tinyint(4) DEFAULT 0,
  `user_type` enum('user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token_expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `full_name`, `google_id`, `is_verified`, `remember_token`, `two_factor_auth`, `user_type`, `created_at`, `remember_token_expires_at`) VALUES
(251081278, 'canyilmaz', 'cnylmz735@gmail.com', '$2y$10$cme1nwVDRP15XHMR/xigHuDyh9d2ebxjNePCBPQGrke0jBCg4OfVC', 'Can', NULL, 1, NULL, 0, 'user', '2024-11-22 18:15:34', NULL),
(633509523, 'tospaa1', 'osmananlatici@gmail.com', '$2y$10$udMGDSuI0LcMPbsqExamD.aRyFZuuiQwgZJbqK.X.DUvF83ZCM0IO', 'Osman Taha Anlatıcı', NULL, 1, NULL, 0, 'user', '2024-11-22 23:06:12', NULL);

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ;

--
-- Tablo döküm verisi `user_extended_details`
--

INSERT INTO `user_extended_details` (`detail_id`, `user_id`, `profile_photo_url`, `cover_photo_url`, `basic_info`, `education_history`, `work_experience`, `skills_matrix`, `portfolio_showcase`, `professional_profile`, `network_links`, `achievements`, `community_engagement`, `performance_metrics`, `created_at`, `updated_at`) VALUES
(9, 251081278, 'profile/avatars/251081278.jpg', 'profile/covers/251081278.jpg', '{\"full_name\":\"Can Y\\u0131lmaz\",\"age\":21,\"biography\":\"LureID Founder\",\"location\":{\"city\":\"Antalya\",\"country\":\"T\\u00fcrkiye\"},\"contact\":{\"email\":\"cnylmz735@gmail.com\",\"website\":\"http:\\/\\/can.com\"},\"languages\":[\"T\\u00fcrk\\u00e7e\",\"\\u0130ngilizce\",\"Rus\\u00e7a\"]}', '[{\"level\":\"high_school\",\"institution\":\"Kepez Anadolu Lisesi\",\"degree\":\"Say\\u0131sal\",\"gpa\":85,\"start_date\":\"2018-09\",\"end_date\":\"2022-06\"},{\"level\":\"university\",\"institution\":\"Celal Bayar \\u00dcniversitesi\",\"degree\":\"Bilgisayar Programc\\u0131l\\u0131\\u011f\\u0131\",\"gpa\":3.2,\"start_date\":\"2023-09\",\"end_date\":\"\"}]', '[{\"company\":\"LureID\",\"position\":\"Founder\",\"start_date\":\"2024-11\",\"end_date\":null,\"description\":\"LureID Kurucusu\"},{\"company\":\"Varol Metal \\u00c7elik\",\"position\":\"Full Stack Web Developer\",\"start_date\":\"2024-07\",\"end_date\":\"2024-08\",\"description\":\"Web tasar\\u0131m ve yaz\\u0131l\\u0131m uzman\\u0131\"}]', '{\"technical_skills\":[\"HTML5\",\"CSS3\",\"JavaScript\",\"PHP\",\"MySQL\"],\"soft_skills\":[\"Tasar\\u0131m\",\"\\u0130novatif\",\"Yenilik\\u00e7i\",\"Modern\"],\"tools\":[\"Git\"]}', '[{\"title\":\"LureID\",\"description\":\"Freelancer ve Portf\\u00f6y Merkezi Web Uygulamas\\u0131\",\"url\":\"http:\\/\\/localhost\\/\"},{\"title\":\"Varol Metal \\u00c7elik\",\"description\":\"Metal \\u00c7elik Kond\\u00fcksiyon \\u015eirketi Web Sayfas\\u0131\",\"url\":\"https:\\/\\/www.varolmetalcelik.com\"}]', '{\"summary\":\"Uygulamal\\u0131 full stack web geli\\u015ftiricisi\",\"expertise_areas\":[\"UI & UX Tasar\\u0131m\\u0131\",\"Backend API Developer\"],\"certifications\":[\"Udemy Uygulamal\\u0131 web geli\\u015ftirme kursu sertifikas\\u0131\"]}', '{\"professional\":{\"github\":\"canyilmaz07\"},\"social\":{\"instagram\":\"7canyilmaz\"},\"portfolio_sites\":[]}', '[{\"title\":\"Mezuniyet Diplomas\\u0131\",\"issuer\":\"Manisa Celal Bayar \\u00dcniversitesi\",\"date\":\"2024-11\",\"description\":\"Bilgisayar Programc\\u0131l\\u0131\\u011f\\u0131 Program Mezuniyeti\"}]', NULL, NULL, '2024-11-22 18:15:36', '2024-11-22 22:43:44'),
(10, 633509523, 'profile/avatars/633509523.jpg', 'profile/covers/633509523.jpg', '{\"full_name\":\"Osman Taha Anlat\\u0131c\\u0131\",\"age\":null,\"biography\":\"\",\"location\":{\"city\":\"\",\"country\":\"\"},\"contact\":{\"email\":\"\",\"website\":\"\"},\"languages\":[]}', '[]', '[]', '{\"technical_skills\":[],\"soft_skills\":[],\"tools\":[]}', '[]', '{\"summary\":\"\",\"expertise_areas\":[],\"certifications\":[]}', '{\"professional\":[],\"social\":[],\"portfolio_sites\":[]}', '[]', NULL, NULL, '2024-11-22 23:06:14', '2024-11-22 23:06:56');

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
(1, 251081278, 'es', 'Europe/Istanbul', 'TR', 'DD.MM.YYYY', '24h', '2024-11-23 16:29:57', '2024-11-23 18:05:52', 'dark', 'Roboto');

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

--
-- Tablo döküm verisi `verification`
--

INSERT INTO `verification` (`verification_id`, `user_id`, `code`, `remember_token`, `expiry_date`, `expires_at`) VALUES
(31, 251081278, 'ea5b1a9e08c1493893d8a283587578678593811871b35cfc4a75c9360a3d679a', NULL, NULL, '2024-11-22 21:03:39');

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
(49, 251081278, 1100.00, 50, '2024-11-22 18:15:34', '2024-11-22 23:42:31', '2024-11-22 23:42:31'),
(50, 633509523, 1300.00, 25, '2024-11-22 23:06:12', '2024-11-22 23:42:31', '2024-11-22 23:42:31');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `works`
--

CREATE TABLE `works` (
  `work_id` int(11) NOT NULL,
  `freelancer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `requirements` text NOT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `fixed_price` decimal(10,2) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `visibility` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `works`
--

INSERT INTO `works` (`work_id`, `freelancer_id`, `title`, `description`, `category`, `requirements`, `daily_rate`, `fixed_price`, `tags`, `visibility`, `created_at`, `updated_at`) VALUES
(15, 5, 'Yeni', 'iş', 'deneme', 'Yok', 500.00, 1000.00, '[\"html\",\"css\",\"js\"]', 1, '2024-11-22 23:00:44', NULL);

--
-- Tetikleyiciler `works`
--
DELIMITER $$
CREATE TRIGGER `update_work_timestamp` BEFORE UPDATE ON `works` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `works_media`
--

CREATE TABLE `works_media` (
  `media_id` int(11) NOT NULL,
  `work_id` int(11) NOT NULL,
  `media_paths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`media_paths`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `works_media`
--

INSERT INTO `works_media` (`media_id`, `work_id`, `media_paths`, `created_at`) VALUES
(4, 15, '{\"image_0\":\"public\\/uploads\\/photos\\/67410d1c7eb86_1732316444_bg2.jpg\"}', '2024-11-22 23:00:44');

--
-- Dökümü yapılmış tablolar için indeksler
--

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
  ADD KEY `freelancers_ibfk_1` (`user_id`);

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
  ADD KEY `freelancer_id` (`freelancer_id`),
  ADD KEY `jobs_ibfk_1` (`user_id`);

--
-- Tablo için indeksler `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
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
  ADD KEY `transactions_ibfk_2` (`receiver_id`);

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
-- Tablo için indeksler `works`
--
ALTER TABLE `works`
  ADD PRIMARY KEY (`work_id`),
  ADD KEY `freelancer_id` (`freelancer_id`);

--
-- Tablo için indeksler `works_media`
--
ALTER TABLE `works_media`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `work_id` (`work_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `freelancers`
--
ALTER TABLE `freelancers`
  MODIFY `freelancer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `invitations`
--
ALTER TABLE `invitations`
  MODIFY `invitation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `jobs`
--
ALTER TABLE `jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- Tablo için AUTO_INCREMENT değeri `referral_sources`
--
ALTER TABLE `referral_sources`
  MODIFY `source_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Tablo için AUTO_INCREMENT değeri `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `staff_login_attempts`
--
ALTER TABLE `staff_login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Tablo için AUTO_INCREMENT değeri `temp_users`
--
ALTER TABLE `temp_users`
  MODIFY `temp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=996326877;

--
-- Tablo için AUTO_INCREMENT değeri `user_extended_details`
--
ALTER TABLE `user_extended_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `verification`
--
ALTER TABLE `verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Tablo için AUTO_INCREMENT değeri `wallet`
--
ALTER TABLE `wallet`
  MODIFY `wallet_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- Tablo için AUTO_INCREMENT değeri `works`
--
ALTER TABLE `works`
  MODIFY `work_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Tablo için AUTO_INCREMENT değeri `works_media`
--
ALTER TABLE `works_media`
  MODIFY `media_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Tablo kısıtlamaları `invitations`
--
ALTER TABLE `invitations`
  ADD CONSTRAINT `invitations_ibfk_1` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invitations_ibfk_2` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jobs_ibfk_2` FOREIGN KEY (`freelancer_id`) REFERENCES `freelancers` (`freelancer_id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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

--
-- Tablo kısıtlamaları `works`
--
ALTER TABLE `works`
  ADD CONSTRAINT `works_ibfk_1` FOREIGN KEY (`freelancer_id`) REFERENCES `freelancers` (`freelancer_id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `works_media`
--
ALTER TABLE `works_media`
  ADD CONSTRAINT `works_media_ibfk_1` FOREIGN KEY (`work_id`) REFERENCES `works` (`work_id`) ON DELETE CASCADE;
COMMIT;
