-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 14, 2025 at 09:52 PM
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
  `remaining_quarterly` decimal(15,2) DEFAULT '0.00',
  `is_yearly` tinyint(1) DEFAULT '0',
  `parent_id` int(11) DEFAULT NULL,
  `allocated_amount` decimal(15,2) DEFAULT '0.00',
  `spent_amount` decimal(15,2) DEFAULT '0.00',
  `budget_type` enum('governmental','program') COLLATE utf8mb4_unicode_ci DEFAULT 'governmental',
  `program_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activity_based` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `owner_id`, `code_id`, `adding_date`, `year`, `yearly_amount`, `month`, `monthly_amount`, `quarter`, `remaining_yearly`, `remaining_monthly`, `remaining_quarterly`, `is_yearly`, `parent_id`, `allocated_amount`, `spent_amount`, `budget_type`, `program_name`, `activity_based`) VALUES
(1, 5, 5, '2025-09-07 00:00:00', 2017, 100000.00, '', 0.00, 0, 68800.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(2, 5, 5, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 15000.00, 1, 85000.00, 0.00, 45000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(3, 5, 6, '2025-09-07 00:00:00', 2017, 200000.00, '', 0.00, 0, 180000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(4, 5, 6, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 20000.00, 1, 180000.00, 1900.00, 60000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(5, 5, 5, '2025-09-07 00:00:00', 2017, 0.00, 'ጥቅምት', 15000.00, 1, 68800.00, 3000.00, 45000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(7, 5, 7, '2025-09-07 00:00:00', 2017, 12000.00, '', 0.00, 0, 9500.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(8, 5, 7, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1150.00, 1, 9500.00, 2500.00, 7500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(10, 37, 20, '2025-09-07 21:28:27', 2017, 100000.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 1, NULL, 25000.00, 5000.00, 'governmental', NULL, 0),
(11, 37, 20, '2025-09-07 21:28:27', 2017, 0.00, 'መስከረም', 25000.00, 1, 0.00, 0.00, 0.00, 0, 10, 20000.00, 5000.00, 'governmental', NULL, 0),
(12, 37, 20, '2025-09-07 21:45:41', 2017, 100000.00, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(14, 13, 5, '2025-09-07 00:00:00', 2017, 150000.00, '', 0.00, 0, 109300.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(17, 13, 5, '2025-09-07 00:00:00', 2017, 0.00, 'ጥቅምት', 13000.00, 2, 124000.00, 1000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(18, 13, 5, '2025-09-07 00:00:00', 2017, 0.00, 'ህዳር', 13000.00, 2, 111000.00, 7000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(20, 13, 6, '2025-09-07 00:00:00', 2017, 150000.00, '', 0.00, 0, 96000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(21, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 13000.00, 1, 137000.00, 7073.45, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(22, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'ሐምሌ', 10000.00, 1, 127000.00, 10000.00, 30000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(23, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'ነሐሴ', 17000.00, 1, 110000.00, 17000.00, 51000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(24, 22, 6, '2025-09-09 00:00:00', 2017, 100000.00, '', 0.00, 0, 100000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(27, 21, 5, '2025-09-11 00:00:00', 2017, 50000.00, '', 0.00, 0, 50000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0),
(28, 19, 10, '2025-09-11 00:00:00', 2017, 15000.00, '', 0.00, 0, 15000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0),
(30, 5, 9, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 1000.00, 1, 10000.00, 1000.00, 3000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(32, 14, 11, '2025-09-11 00:00:00', 2017, 57000.00, '', 0.00, 0, 51400.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(33, 14, 11, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 5600.00, 0, 51400.00, 5600.00, 16800.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(37, 13, 6, '2025-09-12 00:00:00', 2017, 0.00, 'ጥቅምት', 14000.00, 0, 96000.00, 14000.00, 42000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(40, 13, 5, '2025-09-12 00:00:00', 2017, 0.00, 'መስከረም', 3300.00, 0, 120500.00, 2300.00, 10500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(41, 36, NULL, '2025-09-12 00:00:00', 2017, 20000.00, '', 0.00, 0, 20000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(42, 35, NULL, '2025-09-12 00:00:00', 2017, 120000.00, '', 0.00, 0, 120000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(44, 32, NULL, '2025-09-13 00:00:00', 2017, 200000.00, '', 0.00, 0, 182000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(45, 13, 5, '2025-09-14 00:00:00', 2017, 0.00, 'ታኅሣሥ', 6400.00, 0, 109300.00, 6400.00, 19200.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(46, 12, 5, '2025-09-14 00:00:00', 2017, 100500.00, '', 0.00, 0, 63300.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(47, 12, 5, '2025-09-14 00:00:00', 2017, 0.00, 'Meskerem', 30000.00, 1, 70500.00, 30000.00, 90000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0);

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
(19, '6419', 'Baxa baxsa Meklaalih'),
(20, 'TEST', 'Test Code'),
(21, '12345', 'ALI'),
(22, '4321', '4321');

-- --------------------------------------------------------

--
-- Table structure for table `budget_owners`
--

CREATE TABLE `budget_owners` (
  `id` int(11) NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p_koox` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_owners`
--

INSERT INTO `budget_owners` (`id`, `code`, `name`, `p_koox`) VALUES
(5, '01', 'ዋና ቢሮ ሐላፊ', '341/01/01'),
(6, '02', 'የበሽታ መከላከልና መቆጣጠር ዘርፍ ም/ቢ/ሐላፊ', '341/01/01'),
(7, '03', 'የኦፕሬሽን ዘርፍ ም/ቢ/ሐላፊ', '341/01/01'),
(8, '04', 'የጤናና ጤና ነክ አገልግሎቶች ም/ቢ/ሐላፊ', '341/01/01'),
(9, '05', 'የበሽታ መከላከልና መቆጣጠር ዳያሬክቶሬት', '341/05/19'),
(10, '06', 'የኢንፎርሜሽን ኮሙኒኬሽን ቴክኖሎጂ ዳይሬክቶሬት', '341/01/01'),
(11, '07', 'የክሊኒካል አገልግሎት ዳያሬክቶሬት', '341/03/02'),
(12, '08', 'የግዥ፣ ፋይናንስና ንብረት አስተዳደር ዳይሬክቶሬት', '341/01/01'),
(13, '09', 'የእቅድ ዝግጅት፣ ክትትልና ግምገማ  ዳይሬክቶሬት', '341/07/25'),
(14, '10', 'የኮሙዩኒኬሽን ጉዳዮች ዳይሬክቶሬት', '341/01/01'),
(15, '11', 'የእናቶች ፤ ህፃናት አፍላ ወጣቶች እና አገልግሎት ዳያሬክቶሬት', '341/04/16'),
(16, '12', 'የማህበረሰበ ተሳትፎና የመጀመርያ ደረጃ ጤና እንክብካቤ ዳይሬክቶሬት', '341/06/17'),
(17, '13', 'የሥርአተ ምግብ ማስተባበርያ ዳይሬክቶሬት', '341/04/18'),
(18, '14', 'የሴቶች ማህበራዊ ጉዳዮች አካቶ ትግበራ ዳይሬክቶሬት', '341/01/01'),
(19, '15', 'የህክምና ግብዓቶች አቅርቦትና የፋርማሲ አገልግሎት ዳይሬክቶሬት', '341/02/02'),
(20, '16', 'የውስጥ የኦዲት ዳይሬክቶሬት', '341/01/01'),
(21, '17', 'የጤናና ጤና ነክ አገልግሎቶች ብቃትና ጥራት ማረጋገጥ ዳይሬክቶሬት', '341/07/27'),
(22, '18', 'የሰው ሀብት ልማትና መልካም አስተዳደር ዳይሬክቶሬት', '341/01/01');

-- --------------------------------------------------------

--
-- Table structure for table `budget_owners2`
--

CREATE TABLE `budget_owners2` (
  `id` int(11) NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p_koox` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budget_owners2`
--

INSERT INTO `budget_owners2` (`id`, `code`, `name`, `p_koox`) VALUES
(5, '01', 'ዋና ቢሮ ሐላፊ', '341/01/01'),
(6, '02', 'የበሽታ መከላከልና መቆጣጠር ዘርፍ ም/ቢ/ሐላፊ', '341/01/01'),
(7, '03', 'የኦፕሬሽን ዘርፍ ም/ቢ/ሐላፊ', '341/01/01'),
(8, '04', 'የጤናና ጤና ነክ አገልግሎቶች ም/ቢ/ሐላፊ', '341/01/01'),
(9, '05', 'የበሽታ መከላከልና መቆጣጠር ዳያሬክቶሬት', '341/05/19'),
(10, '06', 'የኢንፎርሜሽን ኮሙኒኬሽን ቴክኖሎጂ ዳይሬክቶሬት', '341/01/01'),
(11, '07', 'የክሊኒካል አገልግሎት ዳያሬክቶሬት', '341/03/02'),
(12, '08', 'የግዥ፣ ፋይናንስና ንብረት አስተዳደር ዳይሬክቶሬት', '341/01/01'),
(13, '09', 'የእቅድ ዝግጅት፣ ክትትልና ግምገማ  ዳይሬክቶሬት', '341/07/25'),
(14, '10', 'የኮሙዩኒኬሽን ጉዳዮች ዳይሬክቶሬት', '341/01/01'),
(15, '11', 'የእናቶች ፤ ህፃናት አፍላ ወጣቶች እና አገልግሎት ዳያሬክቶሬት', '341/04/16'),
(16, '12', 'የማህበረሰበ ተሳትፎና የመጀመርያ ደረጃ ጤና እንክብካቤ ዳይሬክቶሬት', '341/06/17'),
(17, '13', 'የሥርአተ ምግብ ማስተባበርያ ዳይሬክቶሬት', '341/04/18'),
(18, '14', 'የሴቶች ማህበራዊ ጉዳዮች አካቶ ትግበራ ዳይሬክቶሬት', '341/01/01'),
(19, '15', 'የህክምና ግብዓቶች አቅርቦትና የፋርማሲ አገልግሎት ዳይሬክቶሬት', '341/02/02'),
(20, '16', 'የውስጥ የኦዲት ዳይሬክቶሬት', '341/01/01'),
(21, '17', 'የጤናና ጤና ነክ አገልግሎቶች ብቃትና ጥራት ማረጋገጥ ዳይሬክቶሬት', '341/07/27'),
(22, '18', 'የሰው ሀብት ልማትና መልካም አስተዳደር ዳይሬክቶሬት', '341/01/01'),
(23, NULL, 'SDG Pool Fund', 'Program'),
(24, NULL, 'UNDAF', 'Program'),
(25, NULL, 'World Bank', 'Program'),
(26, NULL, 'Health Emergency', 'Program'),
(27, NULL, 'GAVI', 'Program'),
(28, NULL, 'African CDC', 'Program'),
(29, NULL, 'Afar Essential', 'Program'),
(30, NULL, 'Teradeo', 'Program'),
(31, NULL, 'Wash', 'Program'),
(32, NULL, 'EU', 'Program'),
(33, NULL, 'Global Fund', 'Program'),
(34, NULL, 'Grant Pool Fund', 'Program'),
(35, NULL, 'CDC COOP', 'Program'),
(36, NULL, 'Human Capital', 'Program'),
(37, 'TEST', 'Test Owner', NULL),
(38, '77', 'ABC', 'DEF'),
(39, '100', 'LULA', 'LULA');

-- --------------------------------------------------------

--
-- Table structure for table `budget_revisions`
--

CREATE TABLE `budget_revisions` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) DEFAULT NULL,
  `previous_amount` decimal(15,2) DEFAULT NULL,
  `new_amount` decimal(15,2) DEFAULT NULL,
  `revision_date` datetime DEFAULT NULL,
  `revised_by` int(11) DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `name_amharic`, `name_english`, `rate_low`, `rate_medium`, `rate_high`) VALUES
(1, 'መቀሌ', 'Mekele', 348.00, 395.00, 468.00),
(2, 'ሰመራ', 'Semera', 315.00, 361.00, 413.00),
(3, 'ባህርዳር', 'Bahirdar', 344.00, 390.00, 462.00),
(4, 'አሶሳ', 'Asosa', 328.00, 367.00, 416.00),
(5, 'ጅግጅጋ', 'Jigjiga', 314.00, 353.00, 404.00),
(6, 'ጋምቤላ', 'Gambella', 327.00, 367.00, 419.00),
(7, 'ሀረር', 'Harari', 365.00, 407.00, 465.00),
(8, 'ሀዋሳ', 'Hawassa', 359.00, 405.00, 477.00),
(9, 'አዲስ አበባ', 'Addis Ababa', 468.00, 549.00, 724.00),
(10, 'ድሬደዋ', 'Diredawa', 367.00, 408.00, 465.00),
(11, 'አዳማ', 'Adama', 353.00, 396.00, 459.00),
(12, 'አደዐር ወረዳ', 'Adaar Woreda', 585.00, 585.00, 585.00),
(13, 'አፋምቦ ወረዳ', 'Afambo Woreda', 585.00, 585.00, 585.00),
(14, 'አይሳዒታ ከተማ አስተዳደር', 'Aysita City Admin Woreda', 845.00, 845.00, 845.00),
(15, 'አይሳዒታ ወረዳ', 'Ayssaita Woreda', 845.00, 845.00, 845.00),
(16, 'ፍራ ከተማ አስተዳደር', 'Chifra City Administration', 450.00, 450.00, 450.00),
(17, 'ጭፍራ ወረዳ', 'Chifra Woreda', 450.00, 450.00, 450.00),
(18, 'ዱብቲ ከተማ አስተዳደር', 'Dubti City Administration', 585.00, 585.00, 585.00),
(19, 'ዱብቲ ወረዳ', 'Dubti Woreda', 585.00, 585.00, 585.00),
(20, 'ኤሊደዐር ወረዳ', 'Elidar Woreda', 585.00, 585.00, 585.00),
(21, 'ዳቡ ወረዳ', 'Dabu Woreda', 585.00, 585.00, 585.00),
(22, 'ሙሳ አሊ', 'Musa Qalli', 585.00, 585.00, 585.00),
(23, 'ኮሪ ወረዳ', 'Kori Woreda', 585.00, 585.00, 585.00),
(24, 'ሚሌ ከተማ አስተዳደር', 'Mille City Administration', 450.00, 450.00, 450.00),
(25, 'ሚሌ ወረዳ', 'Mille Woreda', 450.00, 450.00, 450.00),
(26, 'ሰመራ ሎጊያ ከተማ አስተዳደር', 'Semera-Logia City Administration', 845.00, 845.00, 845.00),
(27, 'አብዐላ ከተማ አስተዳደር', 'Abala City Administration', 845.00, 845.00, 845.00),
(28, 'አብዐላ ወረዳ', 'Abala Woreda', 845.00, 845.00, 845.00),
(29, 'ፍዴራ ወረዳ', 'Afdera Woreda', 630.00, 630.00, 630.00),
(30, 'ሙራዩም ወረዳ', 'Murayyum Woreda', 630.00, 630.00, 630.00),
(31, 'በራህሌ ወረዳ', 'Berahle Woreda', 630.00, 630.00, 630.00),
(32, 'ቢዱ ወረዳ', 'Bidu Woreda', 585.00, 585.00, 585.00),
(33, 'ደሎል ወረዳ', 'Dalol Woreda', 630.00, 630.00, 630.00),
(34, 'አዱኩዋ ወረዳ', 'Adukuwa Woreda', 630.00, 630.00, 630.00),
(35, 'ኢረብቲ ወረዳ', 'Erabti Woreda', 585.00, 585.00, 585.00),
(36, 'ኮነባ ወረዳ', 'Koneba Woreda', 585.00, 585.00, 585.00),
(37, 'መጋሌ ወረዳ', 'Magale Woreda', 585.00, 585.00, 585.00),
(38, 'ዋሳማ ወረዳ', 'Wassama Woreda', 585.00, 585.00, 585.00),
(39, 'ዕቢዳ ወረዳ', 'Abida Woreda', 450.00, 450.00, 450.00),
(40, 'ወረር አዶብተሊ ከተማ አስተዳደር', 'Warar Adobteli Town Administration', 450.00, 450.00, 450.00),
(41, 'ዕሚባራ ወረዳ', 'Amibara Woreda', 650.00, 650.00, 650.00),
(42, 'አርጎባ ልዩ ወረዳ', 'Argoba Special Woreda', 450.00, 450.00, 450.00),
(43, 'አዋሽ ፈንቲዕሌ ወረዳ', 'Awash Fenteale Woreda', 450.00, 450.00, 450.00),
(44, 'አዋሽ ከተማ', 'Awash Town', 650.00, 650.00, 650.00),
(45, 'ቡሪሞዳይቶ ወረዳ', 'Buremedaytu Woreda', 450.00, 450.00, 450.00),
(46, 'ዱለቻ ወረዳ', 'Dulecha Woreda', 450.00, 450.00, 450.00),
(47, 'ገላዕሉ ወረዳ', 'Galalo Woreda', 450.00, 450.00, 450.00),
(48, 'ገዋኒ ወረዳ', 'Gewane Woreda', 450.00, 450.00, 450.00),
(49, 'ሀንሩካ ወረዳ', 'Hanruka Woreda', 450.00, 450.00, 450.00),
(50, 'አውራ ወረዳ', 'Awura Woreda', 450.00, 450.00, 450.00),
(51, 'ኡዋ ወረዳ', 'Ewa Woreda', 450.00, 450.00, 450.00),
(52, 'ጉሊና ወረዳ', 'Gulina Woreda', 650.00, 650.00, 650.00),
(53, 'መባይ ወረዳ', 'Mebay Woreda', 585.00, 585.00, 585.00),
(54, 'ቴሩ ወረዳ', 'Teru Woreda', 585.00, 585.00, 585.00),
(55, 'ያሎ ወረዳ', 'Yallo Woreda', 450.00, 450.00, 450.00),
(56, 'ዳሊፋጌ ወረዳ', 'Dalifaghe Woreda', 650.00, 650.00, 650.00),
(57, 'ደዌ ወረዳ', 'Dewe Woreda', 450.00, 450.00, 450.00),
(58, 'ሀደለዔላ ወረዳ', 'Hadeleala Woreda', 450.00, 450.00, 450.00),
(59, 'ሰሙሮቢ ወረዳ', 'Semirobi Woreda', 450.00, 450.00, 450.00),
(60, 'ተላላክ ወረዳ', 'Telalak Woreda', 450.00, 450.00, 450.00),
(61, 'አድዓዶ ወረዳ', 'Adqado Woreda', 450.00, 450.00, 450.00),
(62, 'ገረኒ ወረዳ', 'Gereni Woreda', 450.00, 450.00, 450.00),
(63, 'ኪለሉ ወረዳ', 'Kilelu Woreda', 450.00, 450.00, 450.00),
(64, 'ሲባይዲ ወረዳ', 'Sibaybi Woreda', 450.00, 450.00, 450.00),
(65, 'ያንጉዲ ወረዳ', 'Yangude Woreda', 450.00, 450.00, 450.00);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `salary`, `directorate`, `photo`, `created_at`) VALUES
(1, 'Ali Abdela', 11305.00, '06 - ኢንፎርሜሽን ኮሙኒኬሽን ቴክኖሎጂ ዳይሬክቶሬት', NULL, '2025-08-30 20:24:22'),
(2, 'Lula Mahmud', 6000.00, '08 - የግዥ፣ ፋይናንስና ንብረት አስተዳደር ዳይሬክቶሬት', NULL, '2025-08-30 20:24:22'),
(3, 'Niyya Ali', 2000.00, '03 - የኦፕሬሽን ዘርፍ ም/ቢ/ሐላፊ', NULL, '2025-08-30 20:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `employees2`
--

CREATE TABLE `employees2` (
  `id` int(11) NOT NULL,
  `name_am` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_af` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary` decimal(10,2) NOT NULL,
  `directorate` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taamagoli` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees2`
--

INSERT INTO `employees2` (`id`, `name_am`, `name_af`, `salary`, `directorate`, `taamagoli`, `photo`, `created_at`) VALUES
(1, 'Ali Abdela', 'Qali Qabdalla', 12750.00, '06 - ኢንፎርሜሽን ኮሙኒኬሽን ቴክኖሎጂ ዳይሬክቶሬት', '0', NULL, '2025-08-30 20:24:00'),
(2, 'Lula Mahmud', 'Lula Macmud', 6000.00, '08 - የግዥ፣ ፋይናንስና ንብረት አስተዳደር ዳይሬክቶሬት', '0', NULL, '2025-08-30 20:24:00'),
(3, 'Niyya Ali', 'Niyya Qali', 2000.00, '03 - የኦፕሬሽን ዘርፍ ም/ቢ/ሐላፊ', '0', NULL, '2025-08-30 20:24:00');

-- --------------------------------------------------------

--
-- Table structure for table `emp_list`
--

CREATE TABLE `emp_list` (
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salary` decimal(8,2) DEFAULT '0.00',
  `name_am` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taamagoli` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `directorate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo` binary(5) DEFAULT NULL,
  `created_at` varchar(10) DEFAULT NULL,
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `emp_list`
--

INSERT INTO `emp_list` (`name`, `salary`, `name_am`, `taamagoli`, `directorate`, `photo`, `created_at`, `id`) VALUES
('Yaasin Cabiib Acmad', 12657.00, 'ያሲን ሀቢብ አሀመድ', '341/01/01', 'Bureau Head', 0x0000000000, '', 1),
('Abaaba Tsagaye', 0.00, 'አበባ ጸጋየ', '3410101', '', 0x0000000000, '', 2),
('Abaate Darige', 0.00, 'አበተ ደርጌ ጎቸዬ', '3410101', '', 0x0000000000, '', 3),
('Ababawu Walixe', 0.00, 'አበባዉ ወልዴ ገ/ሚሀኤል', '3410617', '', 0x0000000000, '', 4),
('Abiti Aliye Guta', 0.00, 'አብቲ አሊዬ ጉታ', '3410202', '', 0x0000000000, '', 5),
('Abubakar Qarba Qunxe', 11305.00, 'አቡበከር ዓርባ ዑንዴ', '341/01/01', 'የግዥ፣ ፋይናንስና ንብረት አስተዳደር ዳይሬክቶሬት', 0x0000000000, '', 6),
('Abubeker Qabdu', 0.00, 'አቡበከር አብዱ', '3410101', '', 0x0000000000, '', 7),
('Abuula Waadir Yammibule', 0.00, 'አቡላ ዋደር የንቡሌ', '3410101', '', 0x0000000000, '', 8),
('Acmad Adam', 0.00, 'አህመድ አደም ሁመድ', '3410101', '', 0x0000000000, '', 9),
('Acmad Cabiib Acmad', 0.00, 'አህመድ ሀቢብ አህመድ', '341/07/25', 'የእቅድ ዝግጅት፣ ክትትልና ግምገማ  ዳይሬክቶሬት', 0x0000000000, '', 10),
('Acmad Esmaqil M.d', 0.00, 'አህመድ እስማዕል መሀመድ', '3410101', '', 0x0000000000, '', 11),
('Acmad Esmaqil Qali', 0.00, 'አህመድ እስማዕል አሊ', '3410101', '', 0x0000000000, '', 12),
('Acmad Joohar M/d', 0.00, 'አህመድ ጆሀር መሀመድ', '3410101', '', 0x0000000000, '', 13),
('Acmad Macammad', 0.00, 'አህመድ መሀመድ', '3410101', '', 0x0000000000, '', 14),
('Acmad Macammad Hassan', 0.00, 'አህመድ መሀመድ ሀሰን', '3410101', '', 0x0000000000, '', 15),
('Acmad Mogola', 0.00, 'አህመድ ሞጎላ', '3410101', '', 0x0000000000, '', 16),
('Acmad Mustafa', 0.00, 'አህመድ ሙሰጠፋ', '341/03/02', 'የክሊኒካል አገልግሎት ዳያሬክቶሬት', 0x0000000000, '', 17),
('Acmad Nuru', 0.00, 'አህመድ ኑሩ', '3410101', '', 0x0000000000, '', 18),
('Acmad Qa/kqaadir Bareket', 0.00, 'አህመድ አ/ቃድር በረከት', '3410416', '', 0x0000000000, '', 19),
('Acmad Qabdu Baraqo', 0.00, 'አህመድ አብዱ', '3410617', '', 0x0000000000, '', 20),
('Acmad Saalci', 0.00, 'አህመድ ሳሊሂ', '3410725', '', 0x0000000000, '', 21),
('Acmed Qali Cabib', 0.00, 'አህመድ አሊ ሀቢብ', '3410519', '', 0x0000000000, '', 22),
('Acmed Qalibara', 0.00, 'አህመድ አሊበራ', '3410101', '', 0x0000000000, '', 23),
('Adam Qali Cuseen', 0.00, 'አደም አሊ ሁሴን', '3410101', '', 0x0000000000, '', 24),
('Adan Qali', 0.00, 'አደን አሊ', '3410101', '', 0x0000000000, '', 25),
('Adduniya Sadik', 0.00, 'አዱኒያ ሰዲቅ', '3410617', '', 0x0000000000, '', 26),
('Amiin Qali Qaasire', 0.00, 'አሚን አሊ አስሬ', '3410727', '', 0x0000000000, '', 27),
('Amiina Yaasin Macammad', 0.00, 'አሚና ያሲን መሀመድ', '3410101', '', 0x0000000000, '', 28),
('Amin Irisa', 0.00, 'አሚን ኢርሳ', '3410101', '', 0x0000000000, '', 29),
('Amin Maace Sadik', 0.00, 'አሚን ማሄ ሳዲቅ', '3410101', '', 0x0000000000, '', 30),
('Amin Nakrusa', 0.00, 'አሚን ናክሩሳ', '3410101', '', 0x0000000000, '', 31),
('Amin Qarba Qunxe', 0.00, 'አሚን አርባ ኡንዴ', '3410725', '', 0x0000000000, '', 32),
('Amina Amin', 0.00, 'አሚና አሚን ኢብራሂም', '3410101', '', 0x0000000000, '', 33),
('Amina Qabdu Musa', 0.00, 'አሚና አብዱ ሙሳ', '3410101', '', 0x0000000000, '', 34),
('Amina qadu Salih', 0.00, 'አሚና አብዱ ሳሊህ', '3410202', '', 0x0000000000, '', 35),
('Aminat Abdu Eberahim', 0.00, 'አሚናት አብዱ ኢብራሂም', '3410101', '', 0x0000000000, '', 36),
('Asiiya Macammad Taahiro', 0.00, 'አሲያ መሀመድ ጣሂሮ', '3410101', '', 0x0000000000, '', 37),
('Asiya nuur murrale', 0.00, 'አስያ ኑር ሚራለ', '3410727', '', 0x0000000000, '', 38),
('Awwal Abubakar', 0.00, 'አዉል አቡበከር', '3410101', '', 0x0000000000, '', 39),
('Awwal Guddale Qunxe', 0.00, 'አዉል ጉደሌ ኡንዴ', '3410416', '', 0x0000000000, '', 40),
('Awwal Indrs Qali', 0.00, 'አዉል እንድሪስ አሊ', '3410101', '', 0x0000000000, '', 41),
('Awwal Saddik Macammad', 0.00, 'አዉል ሰዲቅ መሀመድ', '3410101', '', 0x0000000000, '', 42),
('Axanu Asafa', 0.00, 'አዳኑ አሰፋ ገ/እግዚአብሄር', '3410101', '', 0x0000000000, '', 43),
('Aydaacis Macammad', 0.00, 'አይደሂስ መሀመድ አዉል', '3410101', '', 0x0000000000, '', 44),
('Aysha Maace Qali', 0.00, 'አይሳ ማሄ አሊ', '3410101', '', 0x0000000000, '', 45),
('Aysha Qali Macammad', 0.00, 'አይሻ አሊ መሀመድ', '3410101', '', 0x0000000000, '', 46),
('Bilaho Qumar Cammadu', 0.00, 'ቢላሆ ኡመር ሀመዱ', '3410101', '', 0x0000000000, '', 47),
('Bileeni Macammad Dawud', 0.00, 'ቢሌኒ መሀመድ ደዉድ', '3410101', '', 0x0000000000, '', 48),
('Birhanu Mulu', 0.00, 'ብርሃኑ ሙሉ', '3410617', '', 0x0000000000, '', 49),
('Birihanuu Haylu Bayene', 0.00, 'ብርሀኑ ኃይሉ', '3410101', '', 0x0000000000, '', 50),
('Buzuunesh Asrat', 0.00, 'ብዙነሽ አስረት', '3410101', '', 0x0000000000, '', 51),
('Cabib Acmad Qali', 0.00, 'ሀቢብ አህመድ አሊ', '3410617', '', 0x0000000000, '', 52),
('cabib Casaan', 0.00, 'ሀቢብ አሰን', '3410418', '', 0x0000000000, '', 53),
('Cabib Maace Biliqa', 0.00, 'ሀቢብ ማሄ', '3410101', '', 0x0000000000, '', 54),
('Cabib Macammad Wasiq', 0.00, 'ሀቢብ መሀመድ ወሲዕ', '3410617', '', 0x0000000000, '', 55),
('Cabib Saqid Macammad', 0.00, 'ሀቢብ ሰኢድ መሀመድ', '3410418', '', 0x0000000000, '', 56),
('Cabiib Qiise', 0.00, 'ሀቢብ ኢሴ', '3410101', '', 0x0000000000, '', 57),
('Cabo Qali Awall', 0.00, 'ሀቦ አሊ አዉል', '3410101', '', 0x0000000000, '', 58),
('cadi Gaqas Macammad', 0.00, 'ሀዲ ገአስ መሀመድ', '3410101', '', 0x0000000000, '', 59),
('Cadi Macammad', 0.00, 'ሀዲ መሀመድ', '3410101', '', 0x0000000000, '', 60),
('Caliima Qali Hmmad', 0.00, 'ሀሊማ አሊ ሀመድ', '3410101', '', 0x0000000000, '', 61),
('Calima Lakiqo Konte', 0.00, 'ሀሊማ ለኦይታ ኮነቴ', '3410101', '', 0x0000000000, '', 62),
('Calima Maace', 0.00, 'ሀሊማ ማሄ አህመድ', '3410101', '', 0x0000000000, '', 63),
('Calimaa Badisaa Qali', 0.00, 'ሀሊማ በዲሳ አሊ', '3410101', '', 0x0000000000, '', 64),
('Camida Acmad Mcammad', 0.00, 'ሀሚዳ አህመድ መሀመድ', '3410617', '', 0x0000000000, '', 65),
('Cammad Noor', 0.00, 'አህመድ ኑር', '3410101', '', 0x0000000000, '', 66),
('Cammad Qali m/d', 0.00, 'ሀመድ አሊ መሀመድ', '3410101', '', 0x0000000000, '', 67),
('Casan Macammad', 0.00, 'ሀሰን መሀመድ አህመድ', '3410101', '', 0x0000000000, '', 68),
('Casan Macammad A.d', 0.00, 'ሀሰን መሀመድ አህመድ', '3410416', '', 0x0000000000, '', 69),
('Casana macammad Bolkoq', 0.00, 'ሀስና መሀመድ ቦልኮዕ', '3410101', '', 0x0000000000, '', 70),
('Casiina Macammad', 0.00, 'ሀስና መሀመድ', '3410101', '', 0x0000000000, '', 71),
('Casna Cummad Qali', 0.00, 'ሀስና ሁመድ አሊ', '3410101', '', 0x0000000000, '', 72),
('Casna Yayyo Macammad', 0.00, 'ሀስና ያዮ መሀመድ', '3410727', '', 0x0000000000, '', 73),
('Cayaat Acmad', 0.00, 'ሀያት አህመድ እንድርስ', '3410101', '', 0x0000000000, '', 74),
('Cayaat Kabbexe', 0.00, 'ሀያት ከበደ', '3410101', '', 0x0000000000, '', 75),
('Cayat Habib', 0.00, 'ሃያት ሀቢብ', '3410101', '', 0x0000000000, '', 76),
('Cayyu Qali Cayyu', 0.00, 'ሃዩ አሊ ሃዩ', '3410101', '', 0x0000000000, '', 77),
('Cumad Cassan', 0.00, 'ሁመድ ሀሰን', '3410725', '', 0x0000000000, '', 78),
('Cummad Aden qali', 0.00, 'ሁመድ አደን አሊ', '3410101', '', 0x0000000000, '', 79),
('Cummad Macammad Xokkba', 0.00, 'ሁመድ መሀመድ ዶክባ', '3410101', '', 0x0000000000, '', 80),
('Cuseen Kaloyta Acmad', 0.00, 'ሁሴን ከሎይታ አህመድ', '3410416', '', 0x0000000000, '', 81),
('Cuseen Macammad', 0.00, 'ሁሴን መሀመድ', '3410101', '', 0x0000000000, '', 82),
('Cuseen Macammad seid', 0.00, 'ሁሴን መሀመድ ሰኢድ', '3410101', '', 0x0000000000, '', 83),
('Cuseen Mocammad', 0.00, 'ሁሴን መሀመድ አህመድ', '3410302', '', 0x0000000000, '', 84),
('Daawud Yusuf', 0.00, 'ደዉድ የሱፍ', '3410617', '', 0x0000000000, '', 85),
('Darasa Ebraahim', 0.00, 'ደራሰ እብራሂም', '3410101', '', 0x0000000000, '', 86),
('Darusalaam Cuseen Casan', 0.00, 'ዳሩሰላም ሁሴን ሀሰን', '3410101', '', 0x0000000000, '', 87),
('Dawud Acmad Dawud', 0.00, 'ዳዉድ አህመድ ደዉድ', '3410202', '', 0x0000000000, '', 88),
('Ebirahim Ouseman', 0.00, 'ኢብራሂም ኡስማን', '3410101', '', 0x0000000000, '', 89),
('Ebraahim Macammad Sherif', 0.00, 'አብራሂም መሀመድ', '3410727', '', 0x0000000000, '', 90),
('Ebrahim Camadu', 0.00, 'ኢብራሂም ሃመዱ', '3410101', '', 0x0000000000, '', 91),
('Edris Siraaj', 0.00, 'እንድሪስ ሲራጅ', '3410101', '', 0x0000000000, '', 92),
('Edrs Darasa Misisso', 0.00, 'እድርስ ደራሳ ሚሲሶ', '3410617', '', 0x0000000000, '', 93),
('Edrs Darrasa Hodale', 0.00, 'ኢድሪስ ደረሳ', '3410725', '', 0x0000000000, '', 94),
('Egaa Cammad Calaato', 0.00, 'ኢጋ ሀመድ ሀለቶ', '3410101', '', 0x0000000000, '', 95),
('Faatuma Muusa qali', 0.00, 'ፋጡማ ሙሣ አሊ', '3410617', '', 0x0000000000, '', 96),
('Farhya Macammad Mullat', 0.00, 'ፈርህያ መሀመድ ሙላት', '3410101', '', 0x0000000000, '', 97),
('Fatima Acmad Yusuf', 0.00, 'ሲ/ር ፈጢማ አህመድ ዩሱፍ', '3410416', '', 0x0000000000, '', 98),
('fatuma Abubakar Sadik', 0.00, 'ፋጡማ አቡበከር ሳዲቅ', '3410727', '', 0x0000000000, '', 99),
('Fatuma Awwal Wagris', 0.00, 'ፈጡማ አዉል', '3410617', '', 0x0000000000, '', 100),
('Fatuma Awwal Wittika', 0.00, 'ፋጡማ አዉል ዊትካ', '3410202', '', 0x0000000000, '', 101),
('Fatuma Endris', 0.00, 'ፈጡማ እንድርስ', '3410202', '', 0x0000000000, '', 102),
('Fatuma Maace Qali', 0.00, 'ፈጡማ ማሄ አሊ', '3410101', '', 0x0000000000, '', 103),
('fatuma Macammad', 0.00, 'ወ.ሪት ፋጡማ መሀመድ አሊ', '3410101', '', 0x0000000000, '', 104),
('Fatuma Qali', 0.00, 'ፋጡማ አሊ', '3410101', '', 0x0000000000, '', 105),
('Fatuma Sadik', 0.00, 'ፋጡማ ሰዲቅ', '3410101', '', 0x0000000000, '', 106),
('Fatuma Yayyo', 0.00, 'ፋጡማ ያዮ', '3410101', '', 0x0000000000, '', 107),
('Fatuma Zanab Kuni', 0.00, 'ፈጡማ ዘናብ ኩኒ', '3410101', '', 0x0000000000, '', 108),
('Fatuuma Edris', 0.00, 'ፈጡማ እደሪስ', '3410101', '', 0x0000000000, '', 109),
('Feticya Cuseen Macammad', 0.00, 'ፋቲሂያ ሁሴን', '3410725', '', 0x0000000000, '', 110),
('Gaddo Cummad', 0.00, 'ገዶ ሁመድ ሉባ', '3410101', '', 0x0000000000, '', 111),
('Gaddo Qabdu Casan', 0.00, 'ገዶ አብዱ ሀሰን', '3410101', '', 0x0000000000, '', 112),
('Getaachawu Yusuf Qali', 0.00, 'ጌታቸዉ ዩሱፍ አሊ', '3410727', '', 0x0000000000, '', 113),
('Gifiti Hawaa Qabdu Cuseen', 0.00, 'ሀዋ አብዱ ሁሴን', '3410416', '', 0x0000000000, '', 114),
('Giftia Qabdalla Qabdulkadier', 0.00, 'አብደላ አብዱልቃድር', '3410101', '', 0x0000000000, '', 115),
('Grima Aragewi Desta', 0.00, 'ግርማ አራጋዊ ድስታ', '3410101', '', 0x0000000000, '', 116),
('Gumqati Feker Macammed', 0.00, 'ጁማአቲ ፈኪር መሀመድ', '3410101', '', 0x0000000000, '', 117),
('Haawa Saqid', 0.00, 'ሃዋ ሰይድ', '3410101', '', 0x0000000000, '', 118),
('Habib Macammed', 0.00, 'ሀቢብ መሀመድ', '3410101', '', 0x0000000000, '', 119),
('Habib Miiro', 0.00, 'ሀቢብ ሚሮ', '3410101', '', 0x0000000000, '', 120),
('Hassen Gardo', 0.00, 'ሃሰን ጋርዶ', '3410202', '', 0x0000000000, '', 121),
('Hawa Dawud', 0.00, 'ሀዋ ደዉድ', '3410101', '', 0x0000000000, '', 122),
('Hawaa Daraasa', 0.00, 'ሲ/ር ሀዋ ደርሳ', '3410302', '', 0x0000000000, '', 123),
('Hawaa Qisee', 0.00, 'ሀዋ ኢሴ', '3410101', '', 0x0000000000, '', 124),
('Hawwa Acmad M/d', 0.00, 'ሀዋ አህመድ መሀመድ', '3410101', '', 0x0000000000, '', 125),
('Hawwa Macammad', 0.00, 'ሃዋ መሀመድ ቡረሃን', '3410101', '', 0x0000000000, '', 126),
('Hawwi Xogga M/d', 0.00, 'ሃዊ ዶጋ መሀመድ', '3410101', '', 0x0000000000, '', 127),
('Ibraahim Macammad', 0.00, 'ኢብራሂም መሀመድ መሄ', '3410101', '', 0x0000000000, '', 128),
('Ibrahim mahmud Qabdalla', 0.00, 'ኢብራሂም ማህሙድ አብደላ', '3410418', '', 0x0000000000, '', 129),
('Indris Acmad Qali', 0.00, 'ኢንድሪስ አህመድ አሊ', '3410101', '', 0x0000000000, '', 130),
('Indris Casaan Yaqidi', 0.00, 'እንድስ ሀሰን ያኢዲ', '3410101', '', 0x0000000000, '', 131),
('Iremiyaas Giriima Tufaa', 0.00, 'ኤርሚያስ ግርማ ቱፋ', '3410101', '', 0x0000000000, '', 132),
('Jamiila Esmaqil M/d', 0.00, 'ጀሚላ እስማዕል መሀመድ', '3410727', '', 0x0000000000, '', 133),
('Jilani Qusman Egga', 0.00, 'ጂላኒ ኡስማን ኢጋ', '3410202', '', 0x0000000000, '', 134),
('Kadiiiga Macammad Muusa', 0.00, 'ከድጃ መሀመድ ሙሳ', '3410725', '', 0x0000000000, '', 135),
('Kadija Hamid Acmad', 0.00, 'ከድጀ ሀሚድ አሀመድ', '3410617', '', 0x0000000000, '', 136),
('Kadijja Qabdalla A/d', 0.00, 'ከድጃ አብዳላ አህመድ', '3410101', '', 0x0000000000, '', 137),
('Kadijja Saqid Qumar', 0.00, 'ከድጃ ሰኢድ ኡመር', '3410101', '', 0x0000000000, '', 138),
('Kadir Acmad', 0.00, 'ከድር አህመድ', '3410101', '', 0x0000000000, '', 139),
('Kadir Macammad Bitiqa', 0.00, 'ከድር መሀመድ ቢትኣ', '3410101', '', 0x0000000000, '', 140),
('kayrya Esmaqil', 0.00, 'ከይረያ እስመእል', '3410101', '', 0x0000000000, '', 141),
('Kedir Macammad', 0.00, 'ከድር መሀመድ', '3410101', '', 0x0000000000, '', 142),
('Khadija Adan', 0.00, 'ከድጀ አደን', '3410725', '', 0x0000000000, '', 143),
('Khadir Adam Darasa', 0.00, 'ከድር አደም', '3410519', '', 0x0000000000, '', 144),
('khadir Macammad Saqid', 0.00, 'ከድር መሀመድ ሰኢድ', '3410725', '', 0x0000000000, '', 145),
('Laali Qalo qali', 0.00, 'ላሊ አሎ አሊ', '3410101', '', 0x0000000000, '', 146),
('lakkiqo Cuseen', 0.00, 'ለኪኦ ሁሴን', '3410101', '', 0x0000000000, '', 147),
('Lamlaam Gebru Hayle', 0.00, 'ለምለም ገብሩ ሀይሌ', '3410101', '', 0x0000000000, '', 148),
('Lemlam Tafarra', 0.00, 'ለምለም ተፋራ', '3410101', '', 0x0000000000, '', 149),
('Lubaa Cummad Uddaytu', 0.00, 'ሉበ ሁመድ ኡደይቱ', '3410101', '', 0x0000000000, '', 150),
('Lubaba Macammad Awwal', 0.00, 'ሉበባ መሀመድ አዉል', '3410725', '', 0x0000000000, '', 151),
('Luuba Qali', 0.00, 'ሉባ አሊ', '3410202', '', 0x0000000000, '', 152),
('Macamad Naasir', 0.00, 'መሀመድ ናስር ኦጎልስ', '3410101', '', 0x0000000000, '', 153),
('Macammad  Adam Qabdalla', 0.00, 'መሀመድ አደም አብደላ', '3410617', '', 0x0000000000, '', 154),
('Macammad Abubakar', 0.00, 'መሀመድ አቡበከር', '3410101', '', 0x0000000000, '', 155),
('Macammad Acmad Ega', 0.00, 'መሀመድ አህመድ ኢጋ', '3410101', '', 0x0000000000, '', 156),
('Macammad Adam', 0.00, 'መሀመድ አደም', '3410302', '', 0x0000000000, '', 157),
('Macammad Awwal', 0.00, 'መሀመድ አወል', '3410101', '', 0x0000000000, '', 158),
('Macammad Awwal balet', 0.00, 'መሀመድ አወል በለጠ', '3410101', '', 0x0000000000, '', 159),
('Macammad Casan', 0.00, 'መሀመድ ሀሰን', '3410101', '', 0x0000000000, '', 160),
('Macammad Cassan Cuseen', 0.00, 'መሀመድ ሀሰን ሁሴን', '3410101', '', 0x0000000000, '', 161),
('Macammad Cuseen Odarra', 0.00, 'መሀመድ ሁሴን ኦዳራ', '3410725', '', 0x0000000000, '', 162),
('Macammad Cuseen Qali', 0.00, 'መሀመድ ሁሴን አሊ', '3410101', '', 0x0000000000, '', 163),
('Macammad Haarun M/d', 0.00, 'መሀመድ ሃሩን መሀመድ', '3410101', '', 0x0000000000, '', 164),
('Macammad Idris Burahan', 0.00, 'መሀመድ ኢንድሪስ ቡርሃን', '3410416', '', 0x0000000000, '', 165),
('Macammad Laale Ibrahim', 0.00, 'መሀመድ ላሌ ኢብራሂም', '3410101', '', 0x0000000000, '', 166),
('Macammad Nuur Edirid', 0.00, 'መሀመድኑር እድርስ', '3410727', '', 0x0000000000, '', 167),
('Macammad Qabdalla', 0.00, 'መሀመድ አብደላ መሀመድ', '3410617', '', 0x0000000000, '', 168),
('Macammad Qabdalla', 0.00, 'መሀመድ አብደላ', '3410617', '', 0x0000000000, '', 169),
('Macammad Qabdalla M/d', 0.00, 'መሀመድ አብደላ መሀመድ', '3410727', '', 0x0000000000, '', 170),
('Macammad Qabdu', 0.00, 'መሀመድ አብዱ', '3410418', '', 0x0000000000, '', 171),
('Macammad Qabdu', 0.00, 'መሀመድ አብዱ', '', '', 0x0000000000, '', 172),
('Macammad Qabdu Cuseen', 0.00, 'መሀመድ አብዱ ሁሴን', '3410101', '', 0x0000000000, '', 173),
('Macammad Qabdukadir M.d', 0.00, 'መ/ድ አብዱቃድር መሀመድ', '3410416', '', 0x0000000000, '', 174),
('Macammad Qabdukadir Muusa', 0.00, 'መሀመድ አብዱቃድር ሙሳ', '3410202', '', 0x0000000000, '', 175),
('Macammad Qali Acmad', 0.00, 'መሀመድ አሊ አህመድ', '3410617', '', 0x0000000000, '', 176),
('Macammad Qali Acmad', 0.00, 'መሀመድ አሊ አህመድ', '3410617', '', 0x0000000000, '', 177),
('Macammad Qali Rashid', 0.00, 'መሀመድ አሊ ራሽድ', '3410101', '', 0x0000000000, '', 178),
('Macammad Qali Sokale', 0.00, 'መሀመድ አሊ ሶከሌ', '3410519', '', 0x0000000000, '', 179),
('Macammad Qiise', 0.00, 'መሀመድ እሴ', '3410101', '', 0x0000000000, '', 180),
('Macammad Qudum', 0.00, 'መሀመድ ኡዱም', '3410101', '', 0x0000000000, '', 181),
('Macammad Qusma M/d', 0.00, 'መሀመድ ኡስማን መሀመድ', '3410101', '', 0x0000000000, '', 182),
('Macammad Saqid', 0.00, 'መሀመድ ሰኢድ አሊ', '3410101', '', 0x0000000000, '', 183),
('Macammad Saqid Adam', 0.00, 'መሀመድ ሰኢድ አደም', '3410202', '', 0x0000000000, '', 184),
('Macammad Saqid Meqe', 0.00, 'መሀመድ ሰኢድ ሜእ', '3410725', '', 0x0000000000, '', 185),
('Macammad Sherif Ebraahim', 0.00, 'መሀመድ ሸሪፍ', '3410101', '', 0x0000000000, '', 186),
('Macammad Tabbasa', 0.00, 'መሀመድ ተብሳ', '3410101', '', 0x0000000000, '', 187),
('Macammad Yimam Acmad', 0.00, 'መሀመድ ይመም አህመድ', '3410202', '', 0x0000000000, '', 188),
('MacammadAwall Qali', 0.00, 'መሀመድ አዉል አሊ', '3410101', '', 0x0000000000, '', 189),
('Macammed Acmad', 0.00, 'መሀመድ አህመድ', '', '', 0x0000000000, '', 190),
('Macammed Acmad Macammed', 0.00, 'መሀመድ አህመድ መሀመድ', '3410101', '', 0x0000000000, '', 191),
('Macammed Ibrahim', 0.00, 'መሀመድ ኢብራሂም', '3410101', '', 0x0000000000, '', 192),
('Macammed Umer', 0.00, 'መሀመድ ኡመር', '341-0101', '', 0x0000000000, '', 193),
('Madiina Jibbirli', 0.00, 'መድና ጅብሪል', '3410101', '', 0x0000000000, '', 194),
('Madina Ebraahim', 0.00, 'መድና ኢብራሂም', '3410101', '', 0x0000000000, '', 195),
('Madina Muusa Darrasa', 0.00, 'መድና ሙሳ ደረሳ', '3410727', '', 0x0000000000, '', 196),
('Madinaa Macammad Qali', 0.00, 'መድና መሀመድ አሊ', '3410101', '', 0x0000000000, '', 197),
('Malika Macammed Awwal', 0.00, 'ማሊካ መሀመድ አወል', '3410617', '', 0x0000000000, '', 198),
('Maqiruf Sheceem', 0.00, 'ማእሩፍ ሸሂም', '3410101', '', 0x0000000000, '', 199),
('Masarat Macammad', 0.00, 'መሠረት መሀመድ አሊ', '3410725', '', 0x0000000000, '', 200),
('Mayiiram Acmad', 0.00, 'መይረም አህመድ', '3410101', '', 0x0000000000, '', 201),
('Maymuna Muusa Qali', 0.00, 'መይሙና ሙሳ አሊ', '3410727', '', 0x0000000000, '', 202),
('Mayraam Canfare', 0.00, 'መይረም ሀንፈሬ', '3410101', '', 0x0000000000, '', 203),
('Mayram Yasin Qali', 0.00, 'መይራም ያሲን አሊ', '3410101', '', 0x0000000000, '', 204),
('Mayrem Qali Yusuf', 0.00, 'መይራም አሊ ዩሱፍ', '3410101', '', 0x0000000000, '', 205),
('Mecammed Celem', 0.00, 'መሀመድ ሄለም', '3410617', '', 0x0000000000, '', 206),
('Merkuriy Ebraahim', 0.00, 'ሜርኩርይ እብራሂም', '3410416', '', 0x0000000000, '', 207),
('Mesfin Ferda W/meryem', 0.00, 'ማስፍን ፍሪዴ ወ/መረይም', '3410101', '', 0x0000000000, '', 208),
('Meyru Qabdu', 0.00, 'መይሮ አብዱ', '3410617', '', 0x0000000000, '', 209),
('Mezgebu Yemam hassen', 0.00, 'መዝገቡ ይማም ሃሰን', '3410101', '', 0x0000000000, '', 210),
('Momiina Casaan Endiris', 0.00, 'ሞሚና ሀሰን እድርስ', '3410202', '', 0x0000000000, '', 211),
('Momiina Qabdalla', 0.00, 'ሲ/ር ሞሚና አብደላ ገአስ', '3410519', '', 0x0000000000, '', 212),
('Momina Ibrahim', 0.00, 'ሞሚና ኢብራሂም', '3410101', '', 0x0000000000, '', 213),
('Momina Maace Yayyo', 0.00, 'ሞሚና ማሄ ያዮ', '3410101', '', 0x0000000000, '', 214),
('Momina Muusa M/d', 0.00, 'ሞሚና ሙሳ መሀመድ', '3410101', '', 0x0000000000, '', 215),
('Momina Reshid', 0.00, 'ሞሚና ረሽድ', '3410101', '', 0x0000000000, '', 216),
('Mubark Edris', 0.00, 'ሙባረክ እንድሪስ', '3410101', '', 0x0000000000, '', 217),
('Muktar Macammad', 0.00, 'ሙክተር መሀመድ', '3410101', '', 0x0000000000, '', 218),
('Mulu Tafarra Ayale', 0.00, 'ሙሉ ተፈራ አየለ', '3410416', '', 0x0000000000, '', 219),
('Mulu Tasamma Abtewu', 0.00, 'ሙሉ ተሰማ አቢተዉ', '3410725', '', 0x0000000000, '', 220),
('Musa Qali Casan', 0.00, 'ሙሣ አሊ', '3410519', '', 0x0000000000, '', 221),
('Musaa Cummad Ibraahim', 0.00, 'ሙሳ ሁመድ እብራሂም', '3410101', '', 0x0000000000, '', 222),
('Mustafa Qiise Macammad', 0.00, 'ሙስጠፋ ኢሴ መሀመድ', '3410416', '', 0x0000000000, '', 223),
('Muusa Qabdu Salic', 0.00, 'ሙሳ አብዱ ሰልህ', '3410418', '', 0x0000000000, '', 224),
('name_af', 0.00, 'name_am', 'taamagoli', 'directorate', 0x70686f746f, 'created_at', 225),
('Naqima Qali Yimar', 0.00, 'ነኢማ አሊ', '3410101', '', 0x0000000000, '', 226),
('Naser Garamewu', 0.00, 'ናስር ገረመዉ', '3410725', '', 0x0000000000, '', 227),
('Nasir Cusen M/d', 0.00, 'ነስር ሁሴን መሀመድ', '3410101', '', 0x0000000000, '', 228),
('Niqina Dikale Casan', 0.00, 'ኒዕና ዲነካሌ ሀሰን', '3410617', '', 0x0000000000, '', 229),
('Nur Canfaare', 0.00, 'ኑር ሀንፋሬ', '3410101', '', 0x0000000000, '', 230),
('Nuru Macammad Dima', 0.00, 'ኑሩ መሀመድ ዲማ', '3410617', '', 0x0000000000, '', 231),
('Nuru Qali Cumad', 0.00, 'ኑሩ አሊ ሁመድ', '3410302', '', 0x0000000000, '', 232),
('Nuuriya Maace Qali', 0.00, 'ኑሪያ ማሄ አሊ', '3410101', '', 0x0000000000, '', 233),
('Nuuru Acmad M/d', 0.00, 'ኑሩ አህመድ መሀመድ', '', '', 0x0000000000, '', 234),
('Nuuru M/d Yusuf', 0.00, 'ኑሩ መሀመድ ዩሱፍ', '3410101', '', 0x0000000000, '', 235),
('Osaamadin Faqiz Qabdulwahab', 0.00, 'ኦሳማዲን ፋዕዝ አብዱልዋብ', '3410101', '', 0x0000000000, '', 236),
('Qabdalla Cuseen Acmad', 0.00, 'አብደላ ሁሴን አህመድ', '3410302', '', 0x0000000000, '', 237),
('Qabdalla Kotiina Acmad', 0.00, 'አብደላ ኮቲና አህመድ', '3410101', '', 0x0000000000, '', 238),
('Qabdalla Macammad Tayib', 0.00, 'አብደላ መሀመድ ጠይብ', '3410101', '', 0x0000000000, '', 239),
('Qabdu Adan Qisee', 0.00, 'አብዱ አደን ኢሴ', '3410101', '', 0x0000000000, '', 240),
('Qabdu Haftam M/d', 0.00, 'አብዱ ሀፍታሙ መሀመድ', '3410101', '', 0x0000000000, '', 241),
('Qabdu Macammad', 0.00, 'አብዱ መሀመድ', '3410101', '', 0x0000000000, '', 242),
('Qabdu Macammed Cummed', 0.00, 'አብዱ መሀመድ ሁመድ', '3410101', '', 0x0000000000, '', 243),
('Qabdu Macolad', 0.00, 'አብዱ መኮለድ', '3410101', '', 0x0000000000, '', 244),
('Qabdu Qali Cummad', 0.00, 'አብዱ አሊ ሁመድ', '3410101', '', 0x0000000000, '', 245),
('Qabdu Qusman Salih', 0.00, 'አብዱ ኡስማን ሳሊህ', '3410101', '', 0x0000000000, '', 246),
('Qabdukadir Cammad', 0.00, 'አብዱቀድር ሀመድ', '3410101', '', 0x0000000000, '', 247),
('QabdulliQaziz Yiimar', 0.00, 'አብዱልአዚዝ ይመር ደዉድ', '3410101', '', 0x0000000000, '', 248),
('Qabdusalam Mustafa', 0.00, 'አብዱሰላም ሙስጠፋ', '3410101', '', 0x0000000000, '', 249),
('Qaddilbar Qali Ibraahim', 0.00, 'አድሊበራ አሊ ኢ/ም', '3410101', '', 0x0000000000, '', 250),
('Qali Adan Cummad', 0.00, 'አሊ አደን ሁመድ', '3410101', '', 0x0000000000, '', 251),
('Qali cajji keero', 0.00, 'አሊ ሀጂ ኬሮ', '3410101', '', 0x0000000000, '', 252),
('Qali Cammadu Qali', 0.00, 'አሊ ሀመዱ አሊ', '3410727', '', 0x0000000000, '', 253),
('Qali Casaan', 0.00, 'አሊ ሀሰን', '3410202', '', 0x0000000000, '', 254),
('Qali Cummad Qali', 0.00, 'አሊ ሁመድ አሊ', '3410202', '', 0x0000000000, '', 255),
('Qali Cuseen Ibraahim', 0.00, 'አሊ ሁሴን ኢብራሂም', '3410101', '', 0x0000000000, '', 256),
('Qali Cuseen Ibrahim', 0.00, 'አሊ ሁሴን ኢብራሂም', '3410202', '', 0x0000000000, '', 257),
('Qali Esmaqil Qali', 0.00, 'አሊ እስማዕል አሊ', '3410302', '', 0x0000000000, '', 258),
('Qali Macammad', 0.00, 'አሊ መሀመድ', '3410101', '', 0x0000000000, '', 259),
('Qali Macammad', 0.00, 'አሊ መሀመድ', '3410101', '', 0x0000000000, '', 260),
('Qali Macammad Qumar', 0.00, 'አሊ መሀመድ ዑመር', '3410202', '', 0x0000000000, '', 261),
('Qali Macammad Tayb', 0.00, 'አሊ መሀመድ ጠይብ', '3410302', '', 0x0000000000, '', 262),
('Qali Macammad Wale', 0.00, 'አሊ መሀመድ ዋሌ', '3410727', '', 0x0000000000, '', 263),
('Qali Qabdalla Qali', 12675.00, 'አሊ አብደላ አሊ', '341/01/01', 'ኢንፎርሜሽን ኮሚኒኬሽን ቴክኖሎጂ ዳይሬክቶሬት', 0x0000000000, '', 264),
('Qali Qabdu', 0.00, 'አሊ አብዱ', '', '', 0x0000000000, '', 265),
('Qali Qabdussamad Wassqi', 0.00, 'አሊ አብዱሰመድ ዋሰእ', '3410416', '', 0x0000000000, '', 266),
('Qali Qusman Maqar', 0.00, 'አሊ ኡስማን መዓር', '3410101', '', 0x0000000000, '', 267),
('Qali Zeynu Qarba', 0.00, 'አሊ ዜይኑ', '3410725', '', 0x0000000000, '', 268),
('Qarabi kadir M/d', 0.00, 'ወ.ሮ አራቢ ከድር መሀመድ', '3410101', '', 0x0000000000, '', 269),
('Qaysa Dulla', 0.00, 'አይሳ ዱላ', '3410101', '', 0x0000000000, '', 270),
('Qeyisa Kaloyta', 0.00, 'ኤይሳ ከሎይታ', '3410101', '', 0x0000000000, '', 271),
('Qeysa Qabdalla Shaami', 0.00, 'ኤይሳ አብደላ', '3410519', '', 0x0000000000, '', 272),
('Qiise Macammad', 0.00, 'ኢሴ መሀመድ', '3410101', '', 0x0000000000, '', 273),
('Qiise Qali', 0.00, 'ኢሴ አሊ', '3410101', '', 0x0000000000, '', 274),
('Qisee Xogaa M/d', 0.00, 'ኢሴ ዶጋ መሀመድ', '3410617', '', 0x0000000000, '', 275),
('Quddima Qali Macammad', 0.00, 'ኡድማ አሊ መሀመድ', '3410101', '', 0x0000000000, '', 276),
('Qumar Acmad', 0.00, 'ኡመር አህመድ ሰሊህ', '3410101', '', 0x0000000000, '', 277),
('Qumar Cummad Qali', 0.00, 'ኡመር ሁመድ አሊ', '3410101', '', 0x0000000000, '', 278),
('Qumar Macammad', 0.00, 'ኡመር መሀመድ', '3410101', '', 0x0000000000, '', 279),
('Qumar Saqad', 0.00, 'ኡመር ሰአድ', '3410101', '', 0x0000000000, '', 280),
('Qusman Qali Macammad', 0.00, 'ኡስማን አሊ መሀመድ', '3410101', '', 0x0000000000, '', 281),
('Rabiqa Saqid Macammad', 0.00, 'ራቢኣ ሰኢድ መሀመድ', '3410101', '', 0x0000000000, '', 282),
('Racima Naguus', 0.00, 'ራሂማ ኑጉስ', '3410416', '', 0x0000000000, '', 283),
('Radiya Acmad Macammad', 0.00, 'ራዲያ አህመድ መሀመድ', '3410101', '', 0x0000000000, '', 284),
('Raxiyet Haylemeyrem', 0.00, 'ራዲዬት ሀይለመይራም', '3410302', '', 0x0000000000, '', 285),
('Ricaana Ebraahim Acmad', 0.00, 'ሪሃና እብራሂም አህመድ', '3410202', '', 0x0000000000, '', 286),
('Riim Adam Bidaaru', 0.00, 'ሪም አደም ቢደሮ', '3410418', '', 0x0000000000, '', 287),
('Rokiya Macammad Cumad', 0.00, 'ሮኪያ መሀመድ ሁመድ', '3410418', '', 0x0000000000, '', 288),
('Saara Qabdu', 0.00, 'ሣራ አብዱ', '3410101', '', 0x0000000000, '', 289),
('Safi Acmad', 0.00, 'ሳፊ አህመድ', '3410101', '', 0x0000000000, '', 290),
('Sahis Jamaal qali', 0.00, 'ሳህስ ጀማል አሊ', '3410418', '', 0x0000000000, '', 291),
('Salcaddin Macammad', 0.00, 'ሳላሃዲን መሀመድ', '3410617', '', 0x0000000000, '', 292),
('Salic Luqugud Adam', 0.00, 'ሷሊህ ሉኡጉድ አደም', '3410101', '', 0x0000000000, '', 293),
('Salic Maqar Qali', 0.00, 'ሷሊህ መዓር አሊ', '3410101', '', 0x0000000000, '', 294),
('Salic Siraj Acmad', 0.00, 'ሰሊህ ሲራጂ', '3410101', '', 0x0000000000, '', 295),
('Samira Yimam', 0.00, 'ሰሚራ ይማም', '3410302', '', 0x0000000000, '', 296),
('Saqaada Qali Abubakar', 0.00, 'ሰአዳ አሊ', '3410519', '', 0x0000000000, '', 297),
('Saqad Seqid Amin', 0.00, 'ሳዓድ ሰኢድ አሚን', '3410101', '', 0x0000000000, '', 298),
('Saqada Macammad Gura', 0.00, 'ሰአዳ መሀመድ ጉራ', '3410101', '', 0x0000000000, '', 299),
('Saqada Macammmad', 0.00, 'ሰአደ መሀመድ', '3410519', '', 0x0000000000, '', 300),
('Saqaxa Yiimam', 0.00, 'ሰአዳ ይማም አሊ', '3410202', '', 0x0000000000, '', 301),
('Saqid Macammad Ibraahim', 0.00, 'ሰኢድ መሀመድ ኢ/ም', '3410202', '', 0x0000000000, '', 302),
('Saqid Macammad Musa', 0.00, 'ሰኢድ መሀመድ ሙሳ', '3410101', '', 0x0000000000, '', 303),
('Saqid Makin Adam', 0.00, 'ሰኢድ መኪን', '3410727', '', 0x0000000000, '', 304),
('Seida Ousman Macammed', 0.00, 'ሰኢዳ ኡስማን መሀመድ', '3410202', '', 0x0000000000, '', 305),
('Shawaaye Kiroos', 0.00, 'ሸዋዬ ኪሮስ', '3410101', '', 0x0000000000, '', 306),
('Shimaalis Awweke', 0.00, 'ሽማልስ አዉቄ', '3410302', '', 0x0000000000, '', 307),
('Siraj Macammed', 0.00, 'ሲራጅ መሀመድ', '3410101', '', 0x0000000000, '', 308),
('Siraji Darasa Salic', 0.00, 'ሲራጂ ደራሳ ሳልህ', '3410101', '', 0x0000000000, '', 309),
('Siraji Ediris', 0.00, 'ሲራጅ ኢድሪስ ኢነይቱ', '3410101', '', 0x0000000000, '', 310),
('Sisay Lirea Kutre', 0.00, 'ሲሳይ ሊሬ', '3410617', '', 0x0000000000, '', 311),
('Sofiya Acmad', 0.00, 'ሶፍያ አህመድ', '3410727', '', 0x0000000000, '', 312),
('Sultan Qabdella', 0.00, 'ሱልጣን አብደላ', '3410617', '', 0x0000000000, '', 313),
('Taahir Cusen', 0.00, 'ጣሂር ሁሴን', '3410202', '', 0x0000000000, '', 314),
('Tafari Matyos', 0.00, 'ተፋሪ መትዮስ', '3410727', '', 0x0000000000, '', 315),
('Tarrefe Getachewu', 0.00, 'ተራፋ ገተቻዉ', '3410101', '', 0x0000000000, '', 316),
('Tasiifaye Balayi Saqid', 0.00, 'ተስፋየ በላይ ሰይድ', '3410617', '', 0x0000000000, '', 317),
('Teyba M.d Akkle', 0.00, 'ጣይባ መሀመድ አክሌ', '3410101', '', 0x0000000000, '', 318),
('Tiruuwark Balawu', 0.00, 'ጥሩወርቅ በለዉ', '3410101', '', 0x0000000000, '', 319),
('Tooyb Aytiile', 0.00, 'ጦይብ አይትሌ', '3410101', '', 0x0000000000, '', 320),
('Toyba Macammad', 0.00, 'ጦይባ መሀመድ', '3410101', '', 0x0000000000, '', 321),
('Toyba Qabdu Wassiq', 0.00, 'ጦባ አብዱ ዋሰዕ', '3410101', '', 0x0000000000, '', 322),
('Toybi Awwal', 0.00, 'ጦይብ አወል', '3410101', '', 0x0000000000, '', 323),
('Usman Cussen qali', 0.00, 'ኡስማን ሁሴን አሊ', '3410101', '', 0x0000000000, '', 324),
('Waasiq Saddik', 0.00, 'ዋስዕ ሰዲቅ መሀመድ', '3410418', '', 0x0000000000, '', 325),
('Waliyuu Macammad', 0.00, 'ወልዩ መሀመድ', '3410101', '', 0x0000000000, '', 326),
('Wasaakmali Qali', 0.00, 'ወሰክመል አሊ', '3410101', '', 0x0000000000, '', 327),
('Wasihun Siisay', 0.00, 'ወስሁን ሲሳይ', '3410101', '', 0x0000000000, '', 328),
('Wegris Hamedu', 0.00, 'ወግሪስ ሃመዱ', '3410416', '', 0x0000000000, '', 329),
('Wittika Noore Wittika', 0.00, 'ዊቲካ ኖሬ ዊቲካ', '3410101', '', 0x0000000000, '', 330),
('Xr Cumed Qarsoyta Koxxali', 0.00, 'ዶ/ር ሁመድ አርሶይታ ኮደሊ', '3410101', '', 0x0000000000, '', 331),
('Yasiin Macammad', 0.00, 'ያሲን መሀመድ', '3410725', '', 0x0000000000, '', 332),
('Yasin Ibraahim Cammad', 0.00, 'ያሲን እብራሂም ሀመድ', '3410725', '', 0x0000000000, '', 333),
('Yasin Salic M/d', 0.00, 'ያሲን ሳሊህ መሀመድ', '3410617', '', 0x0000000000, '', 334),
('Yayyo Sadik Mocammad', 0.00, 'ያዮ ሠድቅ መሀመድ', '3410519', '', 0x0000000000, '', 335),
('Yeeshi Macammad', 0.00, 'የሺ መሀመድ', '3410101', '', 0x0000000000, '', 336),
('Yunus Macammad Egacle', 0.00, 'ዩኑስ መሀመድ ኢገህሌ', '3410727', '', 0x0000000000, '', 337),
('Zahira Awwal wagris', 0.00, 'ዘሀራ አወል ወግሪስ', '', '', 0x0000000000, '', 338),
('Zahra Cammadu Qadumane', 0.00, 'ዘህራ ሀመዱ አዱማኔ', '3410519', '', 0x0000000000, '', 339),
('zahra Sahle', 0.00, 'ዘሀራ ሳሃሌ', '3410617', '', 0x0000000000, '', 340),
('Zaynu Qali Cusen', 0.00, 'ዘይኑ አሊ ሁስን', '3410617', '', 0x0000000000, '', 341),
('Zikra Megos', 0.00, 'ዚክራ ሞገስ', '3410101', '', 0x0000000000, '', 342);

-- --------------------------------------------------------

--
-- Table structure for table `fuel_transactions`
--

CREATE TABLE `fuel_transactions` (
  `id` int(11) NOT NULL,
  `budget_type` enum('governmental','program') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'governmental',
  `owner_id` int(11) DEFAULT NULL,
  `p_koox` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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

INSERT INTO `fuel_transactions` (`id`, `budget_type`, `owner_id`, `p_koox`, `driver_name`, `plate_number`, `et_month`, `previous_gauge`, `current_gauge`, `journey_distance`, `fuel_price`, `refuelable_amount`, `total_amount`, `new_gauge`, `gauge_gap`, `date`) VALUES
(42, 'governmental', 5, '341/01/01', 'grf', '4-01265', 'መስከረም', 0.00, 0.00, 200.00, 120.00, 40.00, 4800.00, 200.00, 200.00, '2025-09-07 04:20:03'),
(43, 'governmental', 5, '341/01/01', 'kk', '4-01265', 'ጥቅምት', 200.00, 200.00, 50.00, 120.00, 10.00, 1200.00, 250.00, 50.00, '2025-09-07 04:21:26'),
(44, 'governmental', 5, '341/01/01', 'mjm', '4-01265', 'መስከረም', 250.00, 250.00, 150.00, 120.00, 30.00, 3600.00, 400.00, 150.00, '2025-09-07 04:25:59'),
(45, 'governmental', 5, '341/01/01', 'kmkm', '4-01265', 'መስከረም', 400.00, 400.00, 275.00, 120.00, 55.00, 6600.00, 675.00, 275.00, '2025-09-07 04:29:03'),
(46, 'governmental', 5, '341/01/01', 'ndh', '4-01264', 'ጥቅምት', 0.00, 0.00, 500.00, 120.00, 100.00, 12000.00, 500.00, 500.00, '2025-09-07 04:33:43'),
(47, 'governmental', 13, '341/07/25', 'ghhh', '4-01264', 'መስከረም', 500.00, 500.00, 200.00, 120.00, 40.00, 4800.00, 700.00, 200.00, '2025-09-07 22:28:35'),
(48, 'governmental', 13, '341/07/25', 'knj', '4-01265', 'መስከረም', 675.00, 675.00, 250.00, 120.00, 50.00, 6000.00, 925.00, 250.00, '2025-09-07 22:32:13'),
(49, 'governmental', 13, '341/07/25', 'hhh', '4-01265', 'መስከረም', 925.00, 925.00, 90.00, 120.00, 18.00, 2160.00, 1015.00, 90.00, '2025-09-07 22:34:15'),
(50, 'governmental', 13, '341/07/25', 'geuh', '4-01265', 'መስከረም', 1015.00, 1015.00, 5.00, 40.00, 1.00, 40.00, 1020.00, 5.00, '2025-09-07 22:37:08'),
(51, 'governmental', 13, NULL, 'ali', '4-12345', 'ጥቅምት', 0.00, 0.00, 200.00, 120.00, 40.00, 4800.00, 200.00, 0.00, '2025-09-13 00:00:00'),
(52, 'program', 32, 'Program', 'eu driver', '4-00903 AF', '', 0.00, 0.00, 500.00, 120.00, 100.00, 12000.00, 500.00, 500.00, '2025-09-14 00:40:59'),
(53, 'program', 32, 'Program', 'ali', '4-01264', '', 700.00, 700.00, 250.00, 120.00, 50.00, 6000.00, 950.00, 250.00, '2025-09-14 00:46:28'),
(54, 'governmental', 13, '341/07/25', 'aaaa', '4-01264', 'ጥቅምት', 950.00, 950.00, 300.00, 120.00, 60.00, 7200.00, 1250.00, 300.00, '2025-09-14 02:01:43'),
(55, 'governmental', 13, '341/07/25', 'gshjg', '4-00903 AF', 'ህዳር', 500.00, 500.00, 250.00, 120.00, 50.00, 6000.00, 750.00, 250.00, '2025-09-14 02:09:03'),
(56, 'governmental', 13, '341/07/25', 'ab', '4-00903 AF', 'መስከረም', 750.00, 750.00, 50.00, 120.00, 10.00, 1200.00, 800.00, 50.00, '2025-09-14 02:37:24'),
(57, 'governmental', 12, '341/01/01', 'aa', '4-00903 AF', 'መስከረም', 800.00, 800.00, 300.00, 120.00, 60.00, 7200.00, 1100.00, 300.00, '2025-09-14 14:58:02');

-- --------------------------------------------------------

--
-- Table structure for table `perdium_transactions`
--

CREATE TABLE `perdium_transactions` (
  `id` int(11) NOT NULL,
  `budget_type` enum('governmental','program') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'governmental',
  `employee_id` int(11) NOT NULL,
  `budget_owner_id` int(11) NOT NULL,
  `p_koox` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `budget_code_id` int(11) DEFAULT NULL,
  `city_id` int(11) NOT NULL,
  `perdium_rate` decimal(10,2) NOT NULL,
  `total_days` int(11) NOT NULL,
  `departure_date` date NOT NULL,
  `arrival_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `et_month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `perdium_transactions`
--

INSERT INTO `perdium_transactions` (`id`, `budget_type`, `employee_id`, `budget_owner_id`, `p_koox`, `budget_code_id`, `city_id`, `perdium_rate`, `total_days`, `departure_date`, `arrival_date`, `total_amount`, `et_month`, `created_at`) VALUES
(1, 'governmental', 1, 13, '341/07/25', 6, 2, 413.00, 15, '2025-09-08', '2025-09-22', 5926.55, 'መስከረም', '2025-09-07 20:06:10'),
(2, 'governmental', 264, 5, '341/01/01', NULL, 9, 724.00, 25, '2025-09-15', '2025-10-10', 18100.00, 'መስከረም', '2025-09-14 18:22:51');

-- --------------------------------------------------------

--
-- Table structure for table `p_budget_owners`
--

CREATE TABLE `p_budget_owners` (
  `id` int(11) NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `p_budget_owners`
--

INSERT INTO `p_budget_owners` (`id`, `code`, `name`) VALUES
(1, '01', 'SDG Pool Fund'),
(2, '02', 'UNDAF'),
(3, '03', 'World Bank'),
(4, '04', 'Health Emergency'),
(5, '05', 'GAVI'),
(6, '06', 'African CDC'),
(7, '07', 'Afar Essential'),
(8, '08', 'Teradeo'),
(9, '09', 'Wash'),
(10, '10', 'EU'),
(11, '11', 'Global Fund'),
(12, '12', 'Grant Pool Fund'),
(13, '13', 'African CDC'),
(14, '14', 'Afar Essential'),
(15, '15', 'Teradeo'),
(16, '16', 'Wash'),
(17, '17', 'EU'),
(18, '18', 'Global Fund'),
(19, '19', 'Grant Pool Fund'),
(20, '20', 'CDC COOP'),
(21, '21', 'Human Capital');

-- --------------------------------------------------------

--
-- Stand-in structure for view `quarterly_budgets`
-- (See below for the actual view)
--
CREATE TABLE `quarterly_budgets` (
`owner_id` int(11)
,`code_id` int(11)
,`year` int(11)
,`quarter` int(11)
,`quarterly_allocated` decimal(37,2)
,`quarterly_spent` decimal(37,2)
,`quarterly_remaining` decimal(38,2)
);

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

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `owner_id`, `code_id`, `employee_name`, `ordered_by`, `reason`, `created_by`, `amount`, `date`, `et_month`, `quarter`, `remaining_month`, `remaining_quarter`, `remaining_year`) VALUES
(2, 5, 7, 'gsush', 'y7y', 'Uhugu', 'admin', 1000.00, '2025-09-07 04:38:25', 'መስከረም', 1, 1500.00, 1500.00, 11000.00),
(3, 5, 7, 'ouiy', 'ihiy', 'Ljpj', 'admin', 350.00, '2025-09-07 04:41:01', 'መስከረም', 1, 150.00, 150.00, 10650.00);

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
(2, 'officer', 'Finance Officer', '$2y$10$vZGY0LOWRsbWb86tDspx4u/.s8GAfn/chMhykhmBDFkd0GDfPRf2y', 'officer', NULL),
(5, 'superadmin', 'Abubeker Arba Oundie', '$2y$10$RtX9Cwe/qXQQM2WcTtFkUONUabOwyANuT6CCJtLl2bISvHnUYXJ9K', 'admin', 'uploads/baa6deb51f4cec7f6e84ec6f6f2f203f.png'),
(6, 'officer2', 'Lemlem', '$2y$10$W1kM7MuyXfJZqGTO6pYv9epy6xUMuKkOxk5M0JbItobTEEgcf5udG', 'officer', 'uploads/99e89f21292bd8f06e4d52c6b9401c24.png');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plate_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chassis_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(10, 'ሀይሎክስ', '4-1237 AF', 'JTEEB71J30F02648'),
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
(48, 'TATA Service Bus', '4-1234567', ''),
(49, 'A', '4-12345', '123456789'),
(50, 'XXX', '5-12345', '1254');

-- --------------------------------------------------------

--
-- Table structure for table `woredas`
--

CREATE TABLE `woredas` (
  `id` int(11) NOT NULL,
  `name_amharic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_english` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_low` decimal(10,2) NOT NULL,
  `rate_medium` decimal(10,2) NOT NULL,
  `rate_high` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `woredas`
--

INSERT INTO `woredas` (`id`, `name_amharic`, `name_english`, `rate_low`, `rate_medium`, `rate_high`) VALUES
(1, 'መቀሌ', 'Mekele', 348.00, 395.00, 468.00),
(2, 'ሰመራ', 'Semera', 315.00, 361.00, 413.00),
(3, 'ባህርዳር', 'Bahirdar', 344.00, 390.00, 462.00),
(4, 'አሶሳ', 'Asosa', 328.00, 367.00, 416.00),
(5, 'ጅግጅጋ', 'Jigjiga', 314.00, 353.00, 404.00),
(6, 'ጋምቤላ', 'Gambella', 327.00, 367.00, 419.00),
(7, 'ሀረር', 'Harari', 365.00, 407.00, 465.00),
(8, 'ሀዋሳ', 'Hawassa', 359.00, 405.00, 477.00),
(9, 'አዲስ አበባ', 'Addis Ababa', 468.00, 549.00, 724.00),
(10, 'ድሬደዋ', 'Diredawa', 367.00, 408.00, 465.00),
(11, 'አዳማ', 'Adama', 353.00, 396.00, 459.00),
(12, 'አደዐር ወረዳ', 'Adaar Woreda', 585.00, 585.00, 585.00),
(13, 'አፋምቦ ወረዳ', 'Afambo Woreda', 585.00, 585.00, 585.00),
(14, 'አይሳዒታ ከተማ አስተዳደር', 'Aysita City Admin Woreda', 845.00, 845.00, 845.00),
(15, 'አይሳዒታ ወረዳ', 'Ayssaita Woreda', 845.00, 845.00, 845.00),
(16, 'ፍራ ከተማ አስተዳደር', 'Chifra City Administration', 450.00, 450.00, 450.00),
(17, 'ጭፍራ ወረዳ', 'Chifra Woreda', 450.00, 450.00, 450.00),
(18, 'ዱብቲ ከተማ አስተዳደር', 'Dubti City Administration', 585.00, 585.00, 585.00),
(19, 'ዱብቲ ወረዳ', 'Dubti Woreda', 585.00, 585.00, 585.00),
(20, 'ኤሊደዐር ወረዳ', 'Elidar Woreda', 585.00, 585.00, 585.00),
(21, 'ዳቡ ወረዳ', 'Dabu Woreda', 585.00, 585.00, 585.00),
(22, 'ሙሳ አሊ', 'Musa Qalli', 585.00, 585.00, 585.00),
(23, 'ኮሪ ወረዳ', 'Kori Woreda', 585.00, 585.00, 585.00),
(24, 'ሚሌ ከተማ አስተዳደር', 'Mille City Administration', 450.00, 450.00, 450.00),
(25, 'ሚሌ ወረዳ', 'Mille Woreda', 450.00, 450.00, 450.00),
(26, 'ሰመራ ሎጊያ ከተማ አስተዳደር', 'Semera-Logia City Administration', 845.00, 845.00, 845.00),
(27, 'አብዐላ ከተማ አስተዳደር', 'Abala City Administration', 845.00, 845.00, 845.00),
(28, 'አብዐላ ወረዳ', 'Abala Woreda', 845.00, 845.00, 845.00),
(29, 'ፍዴራ ወረዳ', 'Afdera Woreda', 630.00, 630.00, 630.00),
(30, 'ሙራዩም ወረዳ', 'Murayyum Woreda', 630.00, 630.00, 630.00),
(31, 'በራህሌ ወረዳ', 'Berahle Woreda', 630.00, 630.00, 630.00),
(32, 'ቢዱ ወረዳ', 'Bidu Woreda', 585.00, 585.00, 585.00),
(33, 'ደሎል ወረዳ', 'Dalol Woreda', 630.00, 630.00, 630.00),
(34, 'አዱኩዋ ወረዳ', 'Adukuwa Woreda', 630.00, 630.00, 630.00),
(35, 'ኢረብቲ ወረዳ', 'Erabti Woreda', 585.00, 585.00, 585.00),
(36, 'ኮነባ ወረዳ', 'Koneba Woreda', 585.00, 585.00, 585.00),
(37, 'መጋሌ ወረዳ', 'Magale Woreda', 585.00, 585.00, 585.00),
(38, 'ዋሳማ ወረዳ', 'Wassama Woreda', 585.00, 585.00, 585.00),
(39, 'ዕቢዳ ወረዳ', 'Abida Woreda', 450.00, 450.00, 450.00),
(40, 'ወረር አዶብተሊ ከተማ አስተዳደር', 'Warar Adobteli Town Administration', 450.00, 450.00, 450.00),
(41, 'ዕሚባራ ወረዳ', 'Amibara Woreda', 650.00, 650.00, 650.00),
(42, 'አርጎባ ልዩ ወረዳ', 'Argoba Special Woreda', 450.00, 450.00, 450.00),
(43, 'አዋሽ ፈንቲዕሌ ወረዳ', 'Awash Fenteale Woreda', 450.00, 450.00, 450.00),
(44, 'አዋሽ ከተማ', 'Awash Town', 650.00, 650.00, 650.00),
(45, 'ቡሪሞዳይቶ ወረዳ', 'Buremedaytu Woreda', 450.00, 450.00, 450.00),
(46, 'ዱለቻ ወረዳ', 'Dulecha Woreda', 450.00, 450.00, 450.00),
(47, 'ገላዕሉ ወረዳ', 'Galalo Woreda', 450.00, 450.00, 450.00),
(48, 'ገዋኒ ወረዳ', 'Gewane Woreda', 450.00, 450.00, 450.00),
(49, 'ሀንሩካ ወረዳ', 'Hanruka Woreda', 450.00, 450.00, 450.00),
(50, 'አውራ ወረዳ', 'Awura Woreda', 450.00, 450.00, 450.00),
(51, 'ኡዋ ወረዳ', 'Ewa Woreda', 450.00, 450.00, 450.00),
(52, 'ጉሊና ወረዳ', 'Gulina Woreda', 650.00, 650.00, 650.00),
(53, 'መባይ ወረዳ', 'Mebay Woreda', 585.00, 585.00, 585.00),
(54, 'ቴሩ ወረዳ', 'Teru Woreda', 585.00, 585.00, 585.00),
(55, 'ያሎ ወረዳ', 'Yallo Woreda', 450.00, 450.00, 450.00),
(56, 'ዳሊፋጌ ወረዳ', 'Dalifaghe Woreda', 650.00, 650.00, 650.00),
(57, 'ደዌ ወረዳ', 'Dewe Woreda', 450.00, 450.00, 450.00),
(58, 'ሀደለዔላ ወረዳ', 'Hadeleala Woreda', 450.00, 450.00, 450.00),
(59, 'ሰሙሮቢ ወረዳ', 'Semirobi Woreda', 450.00, 450.00, 450.00),
(60, 'ተላላክ ወረዳ', 'Telalak Woreda', 450.00, 450.00, 450.00),
(61, 'አድዓዶ ወረዳ', 'Adqado Woreda', 450.00, 450.00, 450.00),
(62, 'ገረኒ ወረዳ', 'Gereni Woreda', 450.00, 450.00, 450.00),
(63, 'ኪለሉ ወረዳ', 'Kilelu Woreda', 450.00, 450.00, 450.00),
(64, 'ሲባይዲ ወረዳ', 'Sibaybi Woreda', 450.00, 450.00, 450.00),
(65, 'ያንጉዲ ወረዳ', 'Yangude Woreda', 450.00, 450.00, 450.00);

-- --------------------------------------------------------

--
-- Structure for view `quarterly_budgets`
--
DROP TABLE IF EXISTS `quarterly_budgets`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `quarterly_budgets`  AS SELECT `budgets`.`owner_id` AS `owner_id`, `budgets`.`code_id` AS `code_id`, `budgets`.`year` AS `year`, `budgets`.`quarter` AS `quarter`, sum(`budgets`.`monthly_amount`) AS `quarterly_allocated`, sum(`budgets`.`spent_amount`) AS `quarterly_spent`, (sum(`budgets`.`monthly_amount`) - sum(`budgets`.`spent_amount`)) AS `quarterly_remaining` FROM `budgets` WHERE (`budgets`.`is_yearly` = FALSE) GROUP BY `budgets`.`owner_id`, `budgets`.`code_id`, `budgets`.`year`, `budgets`.`quarter` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_budget_entry` (`owner_id`,`code_id`,`year`,`month`),
  ADD KEY `idx_owner_code` (`owner_id`,`code_id`),
  ADD KEY `code_id` (`code_id`),
  ADD KEY `fk_parent_budget` (`parent_id`),
  ADD KEY `idx_owner_code_year_type` (`owner_id`,`code_id`,`year`,`budget_type`);

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
-- Indexes for table `budget_owners2`
--
ALTER TABLE `budget_owners2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `budget_revisions`
--
ALTER TABLE `budget_revisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`);

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
-- Indexes for table `employees2`
--
ALTER TABLE `employees2`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `emp_list`
--
ALTER TABLE `emp_list`
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
-- Indexes for table `p_budget_owners`
--
ALTER TABLE `p_budget_owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

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
-- Indexes for table `woredas`
--
ALTER TABLE `woredas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `budget_codes`
--
ALTER TABLE `budget_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `budget_owners`
--
ALTER TABLE `budget_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `budget_owners2`
--
ALTER TABLE `budget_owners2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `budget_revisions`
--
ALTER TABLE `budget_revisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employees2`
--
ALTER TABLE `employees2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `emp_list`
--
ALTER TABLE `emp_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=343;

--
-- AUTO_INCREMENT for table `fuel_transactions`
--
ALTER TABLE `fuel_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `perdium_transactions`
--
ALTER TABLE `perdium_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `p_budget_owners`
--
ALTER TABLE `p_budget_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `woredas`
--
ALTER TABLE `woredas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `budget_owners` (`id`),
  ADD CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`code_id`) REFERENCES `budget_codes` (`id`),
  ADD CONSTRAINT `fk_parent_budget` FOREIGN KEY (`parent_id`) REFERENCES `budgets` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
