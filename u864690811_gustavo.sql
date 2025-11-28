-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 28, 2025 at 05:42 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u864690811_gustavo`
--

-- --------------------------------------------------------

--
-- Table structure for table `dashboard_banners`
--

CREATE TABLE `dashboard_banners` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `daily_percent` decimal(5,2) NOT NULL,
  `days` int(11) NOT NULL,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `daily_percent`, `days`, `min_amount`, `max_amount`, `image_path`) VALUES
(1, 'Plano 200 Reais', 10.00, 30, 200.00, 200.00, NULL),
(2, 'Plano 400 Reais', 7.50, 30, 400.00, 400.00, NULL),
(3, 'Plano 500 Reais', 8.00, 30, 500.00, 500.00, NULL),
(4, 'Plano 700 Reais', 4.29, 60, 700.00, 700.00, NULL),
(5, 'Plano 900 Reais', 4.44, 60, 900.00, 900.00, NULL),
(6, 'Plano 1200 Reais', 5.00, 60, 1200.00, 1200.00, NULL),
(7, 'Plano 1400 Reais', 6.43, 30, 1400.00, 1400.00, NULL),
(8, 'Plano 2000 Reais', 7.50, 30, 2000.00, 2000.00, NULL),
(9, 'Plano 3000 Reais', 8.33, 30, 3000.00, 3000.00, NULL),
(10, 'Plano 5000 Reais', 5.00, 50, 5000.00, 5000.00, NULL),
(11, 'Plano 7000 Reais', 5.00, 50, 7000.00, 7000.00, NULL),
(12, 'Plano 9000 Reais', 4.44, 50, 9000.00, 9000.00, NULL),
(13, 'Plano 15000 Reais', 6.67, 30, 15000.00, 15000.00, NULL),
(14, '', 0.00, 0, 0.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`) VALUES
(1, 'cpa_percentage', '10', 'Valor da Comissão'),
(2, 'cpa_type', 'percent', 'Tipo de Comissão (percent ou fixed)'),
(3, 'min_deposit', '50', 'Depósito Mínimo'),
(4, 'min_withdraw', '50', 'Saque Mínimo'),
(5, 'whatsapp_number', '5511999999999', 'WhatsApp Suporte'),
(6, 'home_title', 'BEST INVESTMENTS PLAN FOR WORLDWIDE', 'Título Home'),
(7, 'home_subtitle', 'A Profitable platform for high-margin investment', 'Subtítulo Home'),
(8, 'seo_title', 'Hyip Pro | Investimentos', 'Título Navegador SEO'),
(9, 'seo_description', 'Plataforma líder em investimentos.', 'Meta Description'),
(10, 'seo_keywords', 'investimento, renda extra, dinheiro', 'Meta Keywords'),
(11, 'color_primary', '#000080', 'Cor Primária'),
(12, 'color_secondary', '#4ac9ec', 'Cor Secundária'),
(13, 'color_bg', '#ffffff', 'Cor Fundo Site'),
(14, 'color_text', '#ffffff', 'Cor Texto'),
(15, 'color_card_bg', '#f0f0f0', 'Cor Fundo Cards'),
(16, 'color_card_border', '#ffffff', 'Cor Borda Cards'),
(17, 'site_logo', '', 'Caminho da Logo'),
(18, 'site_favicon', '', 'Caminho do Favicon'),
(19, 'home_hero_bg', 'https://hyip-pro.bugfinder.app/assets/upload/contents/BTGfMYjIow86Z5i9TsmUGZdB3C68gt.webp', 'Imagem de Fundo Home'),
(20, 'site_name', 'Imperio Investimentos', 'Nome do Site'),
(21, 'color_sidebar_bg', '#000080', 'Configuração Automática'),
(22, 'color_sidebar_text', '#ffffff', 'Configuração Automática'),
(23, 'color_sidebar_active', '#d6d6d6', 'Configuração Automática'),
(24, 'color_card_text', '#000000', 'Configuração Automática'),
(25, 'color_btn_primary', '#00ff1e', 'Configuração Automática'),
(26, 'color_btn_text', '#ffffff', 'Configuração Automática'),
(27, 'color_success', '#00ff04', 'Configuração Automática'),
(28, 'color_danger', '#fa0000', 'Configuração Automática'),
(29, 'color_warning', '#fff700', 'Configuração Automática');

-- --------------------------------------------------------

--
-- Table structure for table `site_banners`
--

CREATE TABLE `site_banners` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_banners`
--

INSERT INTO `site_banners` (`id`, `image_path`, `created_at`) VALUES
(1, 'img/banner_site_1764273501.png', '2025-11-27 19:58:21'),
(2, 'img/banner_site_1764273507.jpg', '2025-11-27 19:58:27'),
(3, 'img/banner_site_1764273957.png', '2025-11-27 20:05:57'),
(4, 'img/banner_site_1764273962.png', '2025-11-27 20:06:02');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('pending','waiting_admin','waiting_user','closed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'Criou Usuário Completo', 'Email: r3nato90@hotmail.com', '177.120.246.113', '2025-11-27 18:33:03'),
(2, 1, 'Aprovou Deposito', 'R$ 5000.00', '177.120.246.113', '2025-11-27 19:06:42'),
(3, 1, 'Aprovou Deposito', 'R$ 200.00', '177.120.246.113', '2025-11-27 19:06:45');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_messages`
--

CREATE TABLE `ticket_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','withdraw','referral_bonus','investment_return','investment_buy') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `amount`, `status`, `description`, `created_at`) VALUES
(1, 3, 'deposit', 200.00, 'approved', NULL, '2025-11-27 18:35:08'),
(2, 3, 'investment_buy', 400.00, 'approved', 'Compra Plano: Plano 400 Reais', '2025-11-27 18:36:59'),
(3, 3, 'deposit', 5000.00, 'approved', 'Aguardando Pagamento', '2025-11-27 18:45:42'),
(4, 3, 'investment_buy', 0.00, 'approved', 'Compra: ', '2025-11-28 15:37:46'),
(5, 3, 'investment_buy', 500.00, 'approved', 'Compra: Plano 500 Reais', '2025-11-28 16:03:35'),
(6, 3, 'investment_buy', 200.00, 'approved', 'Compra: Plano 200 Reais', '2025-11-28 16:17:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `balance` decimal(15,2) DEFAULT 0.00,
  `referrer_id` int(11) DEFAULT NULL,
  `pix_key_type` varchar(20) DEFAULT NULL,
  `pix_key` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `whatsapp`, `cpf`, `username`, `role`, `balance`, `referrer_id`, `pix_key_type`, `pix_key`, `created_at`) VALUES
(1, 'Admin 1', 'admin@admin.com', '$2y$10$N03kx64nYreCys7boNz2gOtbD5X3jVKbSozCKrk6VehWeSq5NPHYm', NULL, NULL, NULL, 'admin', 0.00, NULL, NULL, NULL, '2025-11-27 17:36:52'),
(2, 'Admin 2', 'admin1@admin1.com', '$2y$10$ThK.w/e.g.s.t.a.v.o.ThK.w/e.g.s.t.a.v.o.ThK.w/e.g.s.t.a.v.o', NULL, NULL, NULL, 'admin', 0.00, NULL, NULL, NULL, '2025-11-27 17:36:52'),
(3, 'Renato Gomes da Conceição Júnior', 'r3nato90@hotmail.com', '$2y$10$LLzimf02yYe5JeLtNjhRzuibekpmpnT319dl1AfmT1t/1q62Wng7u', '84999032426', '08415755414', 'r3nato90', 'user', 6100.00, NULL, NULL, 'renato@4lifeidiomas.com', '2025-11-27 18:33:03'),
(4, 'geremias', 'geremiasalferdo@gmail.com', '$2y$10$Q0pRvN21QffWGh1LZQr./ez7oDEW4R9fLQ3Hc4KmHnxLFLauSERgO', '3232323232', '390.139.430-39', 'opadada', 'user', 0.00, NULL, 'cpf', '323232233232', '2025-11-27 20:09:38'),
(6, 'Gustavo henrique', 'gustavo@gmail.com', '$2y$10$fKqvDucPP.YH8SIz8BGWC.AVYRMnhzgbawwgm73JKslqR7zrr4Wpi', '73883828288', NULL, NULL, 'user', 0.00, NULL, NULL, NULL, '2025-11-28 15:34:37');

-- --------------------------------------------------------

--
-- Table structure for table `user_investments`
--

CREATE TABLE `user_investments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `daily_return` decimal(15,2) NOT NULL,
  `total_return` decimal(15,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','completed') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_investments`
--

INSERT INTO `user_investments` (`id`, `user_id`, `plan_id`, `amount`, `daily_return`, `total_return`, `start_date`, `end_date`, `status`) VALUES
(1, 3, 2, 400.00, 30.00, 900.00, '2025-11-27', '2025-12-27', 'active'),
(2, 3, 14, 0.00, 0.00, 0.00, '2025-11-28', '2025-11-28', 'active'),
(3, 3, 3, 500.00, 40.00, 1200.00, '2025-11-28', '2025-12-28', 'active'),
(4, 3, 1, 200.00, 20.00, 600.00, '2025-11-28', '2025-12-28', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `dashboard_banners`
--
ALTER TABLE `dashboard_banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `site_banners`
--
ALTER TABLE `site_banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `referrer_id` (`referrer_id`);

--
-- Indexes for table `user_investments`
--
ALTER TABLE `user_investments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dashboard_banners`
--
ALTER TABLE `dashboard_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `site_banners`
--
ALTER TABLE `site_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_investments`
--
ALTER TABLE `user_investments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD CONSTRAINT `ticket_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_investments`
--
ALTER TABLE `user_investments`
  ADD CONSTRAINT `user_investments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_investments_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
