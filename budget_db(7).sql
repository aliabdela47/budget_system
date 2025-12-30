-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 01, 2025 at 08:50 AM
-- Server version: 5.7.34
-- PHP Version: 8.2.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `budget_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `code_id` int(11) DEFAULT NULL,
  `adding_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `year` int(11) DEFAULT NULL,
  `yearly_amount` decimal(15,2) DEFAULT '0.00',
  `month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_amount` decimal(15,2) DEFAULT '0.00',
  `quarter` int(11) DEFAULT NULL,
  `remaining_yearly` decimal(15,2) DEFAULT '0.00',
  `remaining_monthly` decimal(15,2) DEFAULT '0.00',
  `remaining_quarterly` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `owner_id`, `code_id`, `adding_date`, `year`, `yearly_amount`, `month`, `monthly_amount`, `quarter`, `remaining_yearly`, `remaining_monthly`, `remaining_quarterly`) VALUES
(29, 5, 5, '2025-08-22 00:00:00', 2017, 35000.00, '', 0.00, 0, 10000.00, 0.00, 0.00),
(30, 5, 5, '2025-08-22 00:00:00', 2017, 0.00, 'ሐምሌ', 15000.00, 1, 20000.00, 400.00, 45000.00),
(31, 6, 5, '2025-08-22 00:00:00', 2017, 40000.00, '', 0.00, 0, 0.00, 0.00, 0.00),
(32, 6, 5, '2025-08-22 00:00:00', 2017, 0.00, 'ሐምሌ', 30000.00, 1, 10000.00, 0.00, 90000.00),
(33, 5, 5, '2025-08-22 00:00:00', 2017, 0.00, 'ሐምሌ', 10000.00, 1, 10000.00, 10000.00, 30000.00),
(34, 7, 5, '2025-08-25 00:00:00', 2017, 100000.00, '', 0.00, 0, 87000.00, 0.00, 0.00),
(35, 7, 5, '2025-08-25 00:00:00', 2017, 0.00, 'ሐምሌ', 13000.00, 1, 87000.00, 2680.00, 39000.00),
(36, 24, 5, '2025-08-25 00:00:00', 2017, 15000.00, '', 0.00, 0, 15000.00, 0.00, 0.00),
(37, 6, 5, '2025-08-29 00:00:00', 2017, 0.00, 'ነሐሴ', 10000.00, 1, 0.00, 10000.00, 30000.00),
(38, 5, 6, '2025-08-31 00:00:00', 2017, 150000.00, '', 0.00, 0, 135000.00, 0.00, 0.00),
(39, 5, 6, '2025-08-31 00:00:00', 2017, 0.00, 'ነሐሴ', 15000.00, 1, 135000.00, 4257.60, 45000.00);

-- --------------------------------------------------------

--
-- Table structure for table `budget_codes`
--

CREATE TABLE `budget_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_codes`
--

INSERT INTO `budget_codes` (`id`, `code`, `name`) VALUES
(5, '6217', 'Sansii kee Sukutih'),
(6, '6231', 'Ayroh Assentah'),
(7, '6232', 'Transporti Mekláh'),
(8, '6233', 'Qibni Cadih'),
(9, '6251', 'Anbalaal Takke Mihrat Afaafayitte'),
(10, '6252', 'Kiráh'),
(11, '6253', 'Maysaxxgáh'),
(12, '6254', 'Inshuransih'),
(13, '6255', 'Quukah'),
(14, '6256', 'Afaafay Meklaalih'),
(15, '6257', 'Kooraan Afaafayih Meklah'),
(16, '6258', 'Telefoon Afaafay Meklah'),
(17, '6259', 'Leey, Postaa kee Gersi Afaafayih Meklaali'),
(18, '6271', 'Baaxoh Addah Aydkaakanáh'),
(19, '6419', 'Baxa baxsa Meklaalih');

-- --------------------------------------------------------

--
-- Table structure for table `budget_owners`
--

CREATE TABLE `budget_owners` (
  `id` int(11) NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_owners`
--

INSERT INTO `budget_owners` (`id`, `code`, `name`) VALUES
(5, '01', 'ዋና ቢሮ ሐላፊ'),
(6, '02', 'የበሽታ መከላከልና መቆጣጠር ዘርፍ ም/ቢ/ሐላፊ'),
(7, '03', 'የኦፕሬሽን ዘርፍ ም/ቢ/ሐላፊ'),
(8, '04', 'የጤናና ጤና ነክ አገልግሎቶች ም/ቢ/ሐላፊ'),
(9, '05', 'የበሽታ መከላከልና መቆጣጠር ዳያሬክቶሬት'),
(10, '06', 'የኢንፎርሜሽን ኮሙኒኬሽን ቴክኖሎጂ ዳይሬክቶሬት'),
(11, '07', 'የክሊኒካል አገልግሎት ዳያሬክቶሬት'),
(12, '08', 'የግዥ፣ ፋይናንስና ንብረት አስተዳደር ዳይሬክቶሬት'),
(13, '09', 'የእቅድ ዝግጅት፣ ክትትልና ግምገማ  ዳይሬክቶሬት'),
(14, '10', 'የኮሙዩኒኬሽን ጉዳዮች ዳይሬክቶሬት'),
(15, '11', 'የእናቶች ፤ ህፃናት አፍላ ወጣቶች እና አገልግሎት ዳያሬክቶሬት'),
(16, '12', 'የማህበረሰበ ተሳትፎና የመጀመርያ ደረጃ ጤና እንክብካቤ ዳይሬክቶሬት'),
(17, '13', 'የሥርአተ ምግብ ማስተባበርያ ዳይሬክቶሬት'),
(18, '14', 'የሴቶች ማህበራዊ ጉዳዮች አካቶ ትግበራ ዳይሬክቶሬት'),
(19, '15', 'የህክምና ግብዓቶች አቅርቦትና የፋርማሲ አገልግሎት ዳይሬክቶሬት'),
(20, '16', 'የውስጥ የኦዲት ዳይሬክቶሬት'),
(21, '17', 'የጤናና ጤና ነክ አገልግሎቶች ብቃትና ጥራት ማረጋገጥ ዳይሬክቶሬት'),
(22, '18', 'የሰው ሀብት ልማትና መልካም አስተዳደር ዳይሬክቶሬት'),
(23, NULL, 'SDG Pool Fund'),
(24, NULL, 'UNDAF'),
(25, NULL, 'World Bank'),
(26, NULL, 'Health Emergency'),
(27, NULL, 'GAVI'),
(28, NULL, 'African CDC'),
(29, NULL, 'Afar Essential'),
(30, NULL, 'Teradeo'),
(31, NULL, 'Wash'),
(32, NULL, 'EU'),
(33, NULL, 'Global Fund'),
(34, NULL, 'Grant Pool Fund'),
(35, NULL, 'CDC COOP'),
(36, NULL, 'Human Capital');

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `name_amharic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_english` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_low` decimal(10,2) NOT NULL,
  `rate_medium` decimal(10,2) NOT NULL,
  `rate_high` decimal(10,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `name_amharic`, `name_english`, `rate_low`, `rate_medium`, `rate_high`) VALUES
(1, 'መቀሌ', 'Mekele', 348.00, 395.00, 468.00),
(2, 'ሰመራ', 'Semera', 315.00, 361.00, 413.00),
(3, 'ባህርዳር', 'Bahir Dar', 344.00, 390.00, 462.00),
(4, 'አሶሳ', 'Assosa', 328.00, 367.00, 416.00),
(5, 'ጅግጅጋ', 'Jijiga', 314.00, 353.00, 404.00),
(6, 'ጋምቤላ', 'Gambela', 327.00, 367.00, 419.00),
(7, 'ሀረር', 'Harar', 365.00, 407.00, 465.00),
(8, 'ሀዋሳ', 'Hawassa', 359.00, 405.00, 477.00),
(9, 'አዲስ አበባ', 'Addis Ababa', 468.00, 549.00, 724.00),
(10, 'ድሬደዋ', 'Dire Dawa', 367.00, 408.00, 465.00),
(11, 'አዳማ', 'Adama', 353.00, 396.00, 459.00);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `directorate` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `salary`, `directorate`, `photo`, `created_at`) VALUES
(1, 'Ali Abdela', 11305.00, '06 - ኢንፎርሜሽን ኮሙኒኬሽን ቴክኖሎጂ ዳይሬክቶሬት', NULL, '2025-08-30 20:24:22'),
(2, 'Lula Mahmud', 6000.00, '08 - የግዥ፣ ፋይናንስና ንብረት አስተዳደር ዳይሬክቶሬት', NULL, '2025-08-30 20:24:22'),
(3, 'Niyya Ali', 2000.00, '03 - የኦፕሬሽን ዘርፍ ም/ቢ/ሐላፊ', NULL, '2025-08-30 20:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `fuel_transactions`
--

CREATE TABLE `fuel_transactions` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `driver_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `et_month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_gauge` decimal(10,2) DEFAULT NULL,
  `current_gauge` decimal(10,2) DEFAULT NULL,
  `journey_distance` decimal(10,2) DEFAULT NULL,
  `fuel_price` decimal(10,2) DEFAULT NULL,
  `refuelable_amount` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `new_gauge` decimal(10,2) DEFAULT NULL,
  `gauge_gap` decimal(10,2) DEFAULT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fuel_transactions`
--

INSERT INTO `fuel_transactions` (`id`, `owner_id`, `driver_name`, `plate_number`, `et_month`, `previous_gauge`, `current_gauge`, `journey_distance`, `fuel_price`, `refuelable_amount`, `total_amount`, `new_gauge`, `gauge_gap`, `date`) VALUES
(2, 10, 'ali', '4-01265', 'ነሐሴ', 0.00, 0.00, 150.00, 120.00, 30.00, 3600.00, 150.00, 150.00, '2025-08-21 22:29:16'),
(3, 10, 'vv', '4-01265', 'ነሐሴ', 150.00, 150.00, 100.00, 120.00, 20.00, 2400.00, 250.00, 100.00, '2025-08-21 22:32:57'),
(4, 10, 'driverb', '4-01264', 'ሐምሌ', 0.00, 0.00, 200.00, 120.00, 40.00, 4800.00, 200.00, 200.00, '2025-08-21 22:40:58'),
(5, 10, 'ggg', '4-01265', 'ሐምሌ', 250.00, 250.00, 150.00, 120.00, 30.00, 3600.00, 400.00, 150.00, '2025-08-21 22:46:57'),
(6, 10, 'hh', '4-1237 AF', 'ሐምሌ', 0.00, 0.00, 150.00, 120.00, 15.00, 1800.00, 15.00, 15.00, '2025-08-22 15:31:06'),
(8, 11, 'aliabdela', '4-01263 AF', 'ነሐሴ', 0.00, 0.00, 150.00, 120.00, 30.00, 3600.00, 150.00, 150.00, '2025-08-22 16:42:39'),
(9, 11, 'vv', '4-01263 AF', 'ነሐሴ', 150.00, 150.00, 100.00, 120.00, 20.00, 2400.00, 250.00, 100.00, '2025-08-22 16:46:21'),
(11, 11, 'a', '4-01275 AF', 'ነሐሴ', 0.00, 0.00, 60.00, 120.00, 12.00, 1440.00, 60.00, 60.00, '2025-08-22 19:44:51'),
(12, 11, '', '4-01275 AF', 'ነሐሴ', 60.00, 60.00, 25.00, 120.00, 5.00, 600.00, 85.00, 25.00, '2025-08-22 19:54:41'),
(13, 11, '', '4-01265', 'ሐምሌ', 400.00, 400.00, 50.00, 120.00, 10.00, 1200.00, 450.00, 50.00, '2025-08-22 20:02:15'),
(14, 11, '', '4-01265', 'ነሐሴ', 450.00, 450.00, 10.00, 120.00, 2.00, 240.00, 460.00, 10.00, '2025-08-22 20:03:12'),
(15, 10, '', '4-01264', 'ነሐሴ', 200.00, 200.00, 25.00, 120.00, 5.00, 600.00, 225.00, 25.00, '2025-08-22 20:08:20'),
(16, 10, '', '4-01264', 'ነሐሴ', 225.00, 225.00, 30.00, 120.00, 6.00, 720.00, 255.00, 30.00, '2025-08-22 20:14:00'),
(17, 10, '', '4-01265', 'ሐምሌ', 460.00, 460.00, 120.00, 120.00, 24.00, 2880.00, 580.00, 120.00, '2025-08-22 20:20:07'),
(18, 5, '', '4-01264', 'ሐምሌ', 255.00, 255.00, 200.00, 120.00, 40.00, 4800.00, 455.00, 200.00, '2025-08-22 20:36:20'),
(19, 6, '', '4-01264', 'ሐምሌ', 455.00, 455.00, 300.00, 120.00, 60.00, 7200.00, 755.00, 300.00, '2025-08-22 20:36:51'),
(20, 5, '', '4-01263 AF', 'ሐምሌ', 250.00, 250.00, 50.00, 120.00, 10.00, 1200.00, 300.00, 50.00, '2025-08-22 20:40:12'),
(21, 6, '', '', 'ሐምሌ', 0.00, 0.00, 100.00, 120.00, 20.00, 2400.00, 100.00, 100.00, '2025-08-22 20:42:20'),
(22, 6, '', '4-01264', 'ሐምሌ', 755.00, 755.00, 250.00, 120.00, 50.00, 6000.00, 1005.00, 250.00, '2025-08-22 21:08:58'),
(23, 5, '', '4-01265', 'ሐምሌ', 580.00, 580.00, 250.00, 120.00, 50.00, 6000.00, 830.00, 250.00, '2025-08-22 23:19:44'),
(24, 5, '', '4-01265', 'ሐምሌ', 830.00, 830.00, 125.00, 120.00, 25.00, 3000.00, 955.00, 125.00, '2025-08-22 23:20:54'),
(25, 5, '', '4-01265', 'ሐምሌ', 955.00, 955.00, 400.00, 120.00, 80.00, 9600.00, 1355.00, 400.00, '2025-08-22 23:29:18'),
(26, 6, '', '4-01264', 'ሐምሌ', 1005.00, 1005.00, 150.00, 120.00, 30.00, 3600.00, 1155.00, 150.00, '2025-08-23 02:48:40'),
(27, 6, 't', '4-01265', 'ሐምሌ', 1355.00, 1355.00, 55.00, 120.00, 11.00, 1320.00, 1410.00, 55.00, '2025-08-23 03:59:56'),
(28, 6, 'a', '4-01299 AF', 'ሐምሌ', 0.00, 0.00, 50.00, 120.00, 10.00, 1200.00, 50.00, 50.00, '2025-08-23 04:04:35'),
(29, 6, 'ali', '4-00471 AF', 'ሐምሌ', 0.00, 0.00, 35.00, 120.00, 7.00, 840.00, 35.00, 35.00, '2025-08-23 04:07:47'),
(30, 7, 'ayalewu eshetu', '4-17719 ET', 'ሐምሌ', 0.00, 0.00, 150.00, 120.00, 30.00, 3600.00, 150.00, 150.00, '2025-08-25 22:42:51'),
(31, 7, 'ay', '4-17719 ET', 'ሐምሌ', 150.00, 150.00, 30.00, 120.00, 6.00, 720.00, 180.00, 30.00, '2025-08-25 22:45:12'),
(32, 7, 'abdela', '4-1234567', 'ሐምሌ', 0.00, 0.00, 150.00, 120.00, 30.00, 3600.00, 150.00, 150.00, '2025-08-27 05:17:31'),
(33, 7, 'lula', '4-01250 AF', 'ሐምሌ', 0.00, 0.00, 50.00, 120.00, 10.00, 1200.00, 50.00, 50.00, '2025-08-27 05:18:19'),
(34, 7, 'Niyya', '4-01203', 'ሐምሌ', 0.00, 0.00, 15.00, 120.00, 3.00, 360.00, 15.00, 15.00, '2025-08-28 00:53:06'),
(35, 6, 'aa', '4-01283 AF', 'ሐምሌ', 0.00, 0.00, 300.00, 120.00, 60.00, 7200.00, 300.00, 300.00, '2025-08-29 14:51:44'),
(36, 6, 'ff', '4-01198 AF', 'ሐምሌ', 0.00, 0.00, 10.00, 120.00, 2.00, 240.00, 10.00, 10.00, '2025-08-29 15:21:24');

-- --------------------------------------------------------

--
-- Table structure for table `perdium_transactions`
--

CREATE TABLE `perdium_transactions` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `budget_owner_id` int(11) NOT NULL,
  `budget_code_id` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `perdium_rate` decimal(10,2) NOT NULL,
  `total_days` int(11) NOT NULL,
  `departure_date` date NOT NULL,
  `arrival_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `et_month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `perdium_transactions`
--

INSERT INTO `perdium_transactions` (`id`, `employee_id`, `budget_owner_id`, `budget_code_id`, `city_id`, `perdium_rate`, `total_days`, `departure_date`, `arrival_date`, `total_amount`, `et_month`, `created_at`) VALUES
(1, 1, 5, 6, 9, 724.00, 10, '2025-09-01', '2025-09-10', 6950.40, 'ነሐሴ', '2025-08-31 02:36:25'),
(2, 2, 5, 6, 1, 395.00, 10, '2025-09-01', '2025-09-10', 3792.00, 'ነሐሴ', '2025-08-31 02:42:18');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `code_id` int(11) DEFAULT NULL,
  `employee_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordered_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` mediumtext COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `et_month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quarter` int(11) DEFAULT NULL,
  `remaining_month` decimal(15,2) DEFAULT NULL,
  `remaining_quarter` decimal(15,2) DEFAULT NULL,
  `remaining_year` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','officer') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `password_hash`, `role`, `profile_picture`) VALUES
(1, 'admin', 'Ali Abdela', '$2y$10$RYvzdURJXXYtC61mnCZrK.jjFvZa4GBglg9Yq9Wzafs2YtJQ4hJZi', 'admin', 'uploads/b843ed9daa37f608a986bb1828cbfae5.png'),
(2, 'officer', 'Budget Officer', '$2y$10$qw4.EExY9yooLTAa0DEjVOFZ1Yan5L9/5W1NS6yRVTakjQw03SIwy', 'officer', NULL),
(5, 'superadmin', 'Super Admin', '$2y$10$3cqATTRmoVykR4k5okNPNuuw0fdhTxzoJ2cm.UpX.EyX6/RoxCwpu', 'admin', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plate_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chassis_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `model`, `plate_no`, `chassis_no`) VALUES
(1, 'ሀይሎክስ', '4-01265', 'AHTKB8CD60298739'),
(2, 'ሀይሎክስ', '4-01264', 'AHTKB8CD302987832'),
(3, 'ሀይሎ ክች', '4-01263 AF', 'AHTKB8CD702987834'),
(4, 'ሀይሎክስ', '4-01274 AF', NULL),
(5, 'ሀይሎክስ', '4-01275 AF', NULL),
(6, 'ሀይሎክስ', '4-01203', NULL),
(7, 'ሀይሎክስ', '4-01283 AF', 'JTEEB71J30F029582'),
(8, 'ሀይሎክስ', '4-01198 AF', 'JTEEB71J6014901'),
(9, 'ሀይሎክስ', '4-00471 AF', NULL),
(10, 'ሀይሎክስ', '4-1237 AF', 'JTEEB71J30F026486'),
(11, 'ላንድክሩዘር', '4-1238 AF', 'JTEEB71J0F026485'),
(12, 'ሃይሎክች', '4-01066 AF', 'JTRB71j00F006200'),
(13, 'ላንድክሩዘር', '4-01299 AF', 'AHTFR22G006106874'),
(14, ' ', '4-01302 AF', 'JTEBH9FJ80K198638'),
(15, 'ላንድክሩዘር', '4-01301 AF', 'JTEEB71J9041570'),
(16, ' ', '4-01184 AF', 'AHTKB8CD702984139'),
(17, 'ላንድክሩዘር', '4-01250 AF', 'JTEEB71J30F024351'),
(18, ' ', '4-01248 AF', 'JTEEB71J00F024291'),
(19, 'ላንድክሩዘር', '4-01249 AF', 'JTEEB71J70F024322'),
(20, 'አምቡላንስ', '4-01068 AF', 'JTERB71J70F004847'),
(21, 'አምቡላንስ', '4-01065 AF', 'JTERB71J20F005145'),
(22, ' ', '4-01303', NULL),
(23, 'ሃርድቶፕ', '4-01247 AF', 'JTEEB71J60F024294'),
(24, 'ሃርድቶፕ', '4-01246 AF', 'JTEEB71J70F024319'),
(25, 'ሃርድቶፕ', '4-01245 AF', 'JTEEB71J30F024317'),
(26, 'ሃርድቶፕ', '4-01244 AF', 'JTEEB71J30F024298'),
(27, 'ሃርድቶፕ', '4-01243 AF', 'JTEEB71J00F024324'),
(28, 'ሃርድቶፕ', '4-01241 AF', 'JTEEB7160F024313'),
(29, 'ሃርድቶፕ', '4-01242 AF', 'JTEEB71J10F024350'),
(30, 'ሃርድቶፕ', '4-01203 AF', 'JTEEB71J00F018018524'),
(31, 'ሃርድቶፕ', '4-00910 AF', 'JTEEB71J607040487'),
(32, 'ሃርድቶፕ', '4-00912 AF', 'JTEEB71J507040562'),
(33, 'ላንድክሩዘር', '4-05621', 'JTEEB71J907031119'),
(34, 'ቶያታ ሃርድቶፕ', '4-32792', 'JTEEB71J0014550'),
(35, 'ቶያታ ሃርድቶፕ', '4-01259 AF', 'JTERB71X0F024221'),
(36, 'ሃርድቶፕ', '4-00903 AF', 'JTEEB71J307043198'),
(37, 'ሃርድቶፕ', '4-00909 AF', 'JTEEB71J507040559'),
(38, 'ሃርድቶፕ', '4-00906 AF', 'JTEEB71307043220'),
(39, 'ሃርድቶፕ', '4-00908 AF', 'JTEEB71J507040643'),
(40, 'ሃርድቶፕ', '4-00911 AF', 'JTEEB71J607040540'),
(41, 'ሃርድቶፕ', '4-00904 AF', 'JTEEB7J307043248'),
(42, 'ሃርድቶፕ', '4-00907 AF', 'JTEEB71J507043199'),
(43, 'ሃርድቶፕ', '4-00905 AF', 'JTEEB71507043168'),
(44, 'ሃርድቶፕ', '4-00902 AF', 'JTEEB71J407043226'),
(45, 'ላንድክሩዘር', '4-17719 ET', ''),
(48, 'TATA Service Bus', '4-1234567', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner_code` (`owner_id`,`code_id`),
  ADD KEY `fk_code` (`code_id`);

--
-- Indexes for table `budget_codes`
--
ALTER TABLE `budget_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `budget_owners`
--
ALTER TABLE `budget_owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fuel_transactions`
--
ALTER TABLE `fuel_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `perdium_transactions`
--
ALTER TABLE `perdium_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `budget_owner_id` (`budget_owner_id`),
  ADD KEY `budget_code_id` (`budget_code_id`),
  ADD KEY `city_id` (`city_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `owner_id` (`owner_id`,`code_id`,`et_month`,`date`),
  ADD UNIQUE KEY `owner_id_2` (`owner_id`,`code_id`,`amount`,`et_month`,`date`),
  ADD KEY `code_id` (`code_id`),
  ADD KEY `idx_month` (`et_month`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `budget_codes`
--
ALTER TABLE `budget_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `budget_owners`
--
ALTER TABLE `budget_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `fuel_transactions`
--
ALTER TABLE `fuel_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `perdium_transactions`
--
ALTER TABLE `perdium_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
