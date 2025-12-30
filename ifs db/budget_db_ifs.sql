-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 18, 2025 at 10:17 PM
-- Server version: 5.7.34
-- PHP Version: 8.3.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `budget_db_ifs`
--
CREATE DATABASE IF NOT EXISTS `budget_db_ifs` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `budget_db_ifs`;

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
CREATE TABLE `budgets` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `owner_type` enum('governmental','program') COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_id` int(11) DEFAULT NULL,
  `adding_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `year` int(11) DEFAULT NULL,
  `yearly_amount` decimal(15,2) DEFAULT '0.00',
  `month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ec_month` tinyint(3) UNSIGNED DEFAULT NULL,
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
  `activity_based` tinyint(1) DEFAULT '0',
  `month_key` varchar(20) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (coalesce(`month`,'YEARLY')) STORED,
  `gov_unique_key` varchar(255) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (if((`owner_type` = 'governmental'),concat_ws('-',`owner_id`,`code_id`,`year`,coalesce(`month`,'YEARLY')),NULL)) STORED,
  `prog_unique_key` varchar(255) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (if((`owner_type` = 'program'),concat_ws('-',`owner_id`,`code_id`,`year`,format(`yearly_amount`,2)),NULL)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `owner_id`, `owner_type`, `code_id`, `adding_date`, `year`, `yearly_amount`, `month`, `ec_month`, `monthly_amount`, `quarter`, `remaining_yearly`, `remaining_monthly`, `remaining_quarterly`, `is_yearly`, `parent_id`, `allocated_amount`, `spent_amount`, `budget_type`, `program_name`, `activity_based`) VALUES
(26, 5, 'governmental', 5, '2025-09-07 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 47800.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(27, 5, 'governmental', 5, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 15000.00, 1, 85000.00, 0.00, 45000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(28, 5, 'governmental', 7, '2025-09-07 00:00:00', 2017, 12000.00, '', NULL, 0.00, 0, 9500.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(29, 5, 'governmental', 7, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 1150.00, 1, 9500.00, 2500.00, 7500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(30, 37, 'governmental', 20, '2025-09-07 21:28:27', 2017, 100000.00, NULL, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 1, NULL, 25000.00, 5000.00, 'governmental', NULL, 0),
(31, 37, 'governmental', 20, '2025-09-07 21:28:27', 2017, 0.00, 'መስከረም', 1, 25000.00, 1, 0.00, 0.00, 0.00, 0, 10, 20000.00, 5000.00, 'governmental', NULL, 0),
(32, 13, 'governmental', 5, '2025-09-07 00:00:00', 2017, 150000.00, '', NULL, 0.00, 0, 109300.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(33, 13, 'governmental', 5, '2025-09-07 00:00:00', 2017, 0.00, 'ጥቅምት', 2, 13000.00, 2, 124000.00, 1000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(34, 13, 'governmental', 5, '2025-09-07 00:00:00', 2017, 0.00, 'ህዳር', 3, 13000.00, 2, 111000.00, 7000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(35, 13, 'governmental', 6, '2025-09-07 00:00:00', 2017, 150000.00, '', NULL, 0.00, 0, 96000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(36, 13, 'governmental', 6, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 13000.00, 1, 137000.00, 7073.45, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(37, 13, 'governmental', 6, '2025-09-07 00:00:00', 2017, 0.00, 'ሐምሌ', 11, 10000.00, 1, 127000.00, 10000.00, 30000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(38, 13, 'governmental', 6, '2025-09-07 00:00:00', 2017, 0.00, 'ነሐሴ', 12, 17000.00, 1, 110000.00, 17000.00, 51000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(39, 22, 'governmental', 6, '2025-09-09 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 80000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(40, 21, 'governmental', 5, '2025-09-11 00:00:00', 2017, 50000.00, '', NULL, 0.00, 0, 50000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0),
(41, 19, 'governmental', 10, '2025-09-11 00:00:00', 2017, 15000.00, '', NULL, 0.00, 0, 15000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0),
(42, 5, 'governmental', 9, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 1, 1000.00, 1, 10000.00, 1000.00, 3000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(43, 14, 'governmental', 11, '2025-09-11 00:00:00', 2017, 57000.00, '', NULL, 0.00, 0, 51400.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(44, 14, 'governmental', 11, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 1, 5600.00, 0, 51400.00, 5600.00, 16800.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(45, 13, 'governmental', 6, '2025-09-12 00:00:00', 2017, 0.00, 'ጥቅምት', 2, 14000.00, 0, 96000.00, 14000.00, 42000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(46, 13, 'governmental', 5, '2025-09-12 00:00:00', 2017, 0.00, 'መስከረም', 1, 3300.00, 0, 120500.00, 1700.00, 10500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(47, 13, 'governmental', 5, '2025-09-14 00:00:00', 2017, 0.00, 'ታኅሣሥ', 4, 6400.00, 0, 109300.00, 6400.00, 19200.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(48, 12, 'governmental', 5, '2025-09-14 00:00:00', 2017, 100500.00, '', NULL, 0.00, 0, 63300.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(49, 12, 'governmental', 5, '2025-09-14 00:00:00', 2017, 0.00, 'Meskerem', 1, 30000.00, 1, 70500.00, 30000.00, 90000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(50, 16, 'governmental', 5, '2025-09-15 00:00:00', 2017, 130000.00, '', NULL, 0.00, 0, 116500.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(51, 16, 'governmental', 5, '2025-09-15 00:00:00', 2017, 0.00, 'Meskerem', 1, 13500.00, 1, 116500.00, 13500.00, 40500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(52, 19, 'program', NULL, '2025-09-15 00:00:00', 2017, 300000.00, '', NULL, 0.00, 0, 3000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(53, 16, 'program', NULL, '2025-09-15 00:00:00', 2017, 250000.00, '', NULL, 0.00, 0, 250000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(54, 15, 'program', NULL, '2025-09-15 00:00:00', 2017, 3000000.00, '', NULL, 0.00, 0, 3000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(55, 10, 'program', NULL, '2025-09-15 00:00:00', 2017, 350000.00, '', NULL, 0.00, 0, 344634.25, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(56, 12, 'governmental', 6, '2025-09-17 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 3969.45, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(57, 12, 'governmental', 6, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 50000.00, 1, 50000.00, 50000.00, 150000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(58, 6, 'program', NULL, '2025-09-17 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99975501.75, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(59, 7, 'program', NULL, '2025-09-17 00:00:00', 2017, 2000000.00, '', NULL, 0.00, 0, 1883988.25, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(60, 5, 'program', NULL, '2025-09-17 00:00:00', 2017, 350000.00, '', NULL, 0.00, 0, 337874.25, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(61, 6, 'governmental', 5, '2025-09-17 00:00:00', 2017, 200000.00, '', NULL, 0.00, 0, 175000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(62, 6, 'governmental', 5, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 25000.00, 1, 175000.00, 25000.00, 75000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(63, 7, 'governmental', 5, '2025-09-17 00:00:00', 2017, 250000.00, '', NULL, 0.00, 0, 215000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(64, 7, 'governmental', 5, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 35000.00, 1, 215000.00, 35000.00, 105000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(65, 5, 'governmental', 5, '2025-09-18 00:00:00', 2017, 0.00, 'Tikimt', 2, 2500.00, 1, 49300.00, 2500.00, 7500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(67, 20, 'program', NULL, '2025-09-24 00:00:00', 2017, 3000000.00, '', NULL, 0.00, 0, 2967041.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(68, 21, 'program', NULL, '2025-09-24 00:00:00', 2017, 9000000.00, '', NULL, 0.00, 0, 9000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(69, 11, 'program', NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99552718.85, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(70, 12, 'program', NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 96860650.68, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(71, 100, 'program', NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99979479.50, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(72, 9, 'program', NULL, '2025-09-30 00:00:00', 2017, 1000000.00, '', NULL, 0.00, 0, 891326.75, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(73, 8, 'program', NULL, '2025-10-02 00:00:00', 2017, 10000000.00, '', NULL, 0.00, 0, 10000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(74, 5, 'governmental', 6, '2025-10-14 00:00:00', 2017, 390000.00, '', NULL, 0.00, 0, 269345.20, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(75, 5, 'governmental', 6, '2025-10-14 00:00:00', 2017, 0.00, 'Meskerem', 1, 32500.00, 1, 357500.00, 30514.25, 97500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(89, 7, 'governmental', 6, '2025-10-22 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 90000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(90, 7, 'governmental', 6, '2025-10-22 00:00:00', 2017, 0.00, 'Meskerem', 1, 10000.00, 1, 90000.00, 6324.25, 30000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(91, 14, 'governmental', 6, '2025-11-06 00:00:00', 2017, 200000.00, '', NULL, 0.00, 0, 180000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(92, 14, 'governmental', 6, '2025-11-06 00:00:00', 2017, 0.00, 'Meskerem', 1, 20000.00, 1, 180000.00, 9554.85, 60000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(93, 2, 'governmental', NULL, '2025-11-10 00:00:00', 2017, 169000000.00, '', NULL, 0.00, 0, 168476069.98, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(94, 5, 'governmental', 6, '2025-11-18 00:00:00', 2017, 0.00, 'Hidar', 3, 50000.00, 1, 269345.20, 37207.40, 150000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(95, 22, 'governmental', 6, '2025-11-29 00:00:00', 2017, 0.00, 'Hidar', 3, 20000.00, 1, 80000.00, 5990.60, 60000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(96, 10, 'governmental', 6, '2025-12-13 00:00:00', 2017, 200000.00, '', NULL, 0.00, 0, 165000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(97, 10, 'governmental', 6, '2025-12-13 00:00:00', 2017, 0.00, 'Tahsas', 4, 25000.00, 2, 175000.00, 25000.00, 75000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(98, 10, 'governmental', 6, '2025-12-13 00:00:00', 2017, 0.00, 'Tikimt', 2, 10000.00, 1, 165000.00, 4866.85, 30000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `budgets2`
--

DROP TABLE IF EXISTS `budgets2`;
CREATE TABLE `budgets2` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `code_id` int(11) DEFAULT NULL,
  `adding_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `year` int(11) DEFAULT NULL,
  `yearly_amount` decimal(15,2) DEFAULT '0.00',
  `month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ec_month` tinyint(3) UNSIGNED DEFAULT NULL,
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
-- Dumping data for table `budgets2`
--

INSERT INTO `budgets2` (`id`, `owner_id`, `code_id`, `adding_date`, `year`, `yearly_amount`, `month`, `ec_month`, `monthly_amount`, `quarter`, `remaining_yearly`, `remaining_monthly`, `remaining_quarterly`, `is_yearly`, `parent_id`, `allocated_amount`, `spent_amount`, `budget_type`, `program_name`, `activity_based`) VALUES
(1, 5, 5, '2025-09-07 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 47800.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(2, 5, 5, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 15000.00, 1, 85000.00, 0.00, 45000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(7, 5, 7, '2025-09-07 00:00:00', 2017, 12000.00, '', NULL, 0.00, 0, 9500.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(8, 5, 7, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 1150.00, 1, 9500.00, 2500.00, 7500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(10, 37, 20, '2025-09-07 21:28:27', 2017, 100000.00, NULL, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 1, NULL, 25000.00, 5000.00, 'governmental', NULL, 0),
(11, 37, 20, '2025-09-07 21:28:27', 2017, 0.00, 'መስከረም', 1, 25000.00, 1, 0.00, 0.00, 0.00, 0, 10, 20000.00, 5000.00, 'governmental', NULL, 0),
(12, 37, 20, '2025-09-07 21:45:41', 2017, 100000.00, NULL, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(14, 13, 5, '2025-09-07 00:00:00', 2017, 150000.00, '', NULL, 0.00, 0, 109300.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(17, 13, 5, '2025-09-07 00:00:00', 2017, 0.00, 'ጥቅምት', 2, 13000.00, 2, 124000.00, 1000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(18, 13, 5, '2025-09-07 00:00:00', 2017, 0.00, 'ህዳር', 3, 13000.00, 2, 111000.00, 7000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(20, 13, 6, '2025-09-07 00:00:00', 2017, 150000.00, '', NULL, 0.00, 0, 96000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(21, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 13000.00, 1, 137000.00, 7073.45, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(22, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'ሐምሌ', 11, 10000.00, 1, 127000.00, 10000.00, 30000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(23, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'ነሐሴ', 12, 17000.00, 1, 110000.00, 17000.00, 51000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(24, 22, 6, '2025-09-09 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 100000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(27, 21, 5, '2025-09-11 00:00:00', 2017, 50000.00, '', NULL, 0.00, 0, 50000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0),
(28, 19, 10, '2025-09-11 00:00:00', 2017, 15000.00, '', NULL, 0.00, 0, 15000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0),
(30, 5, 9, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 1, 1000.00, 1, 10000.00, 1000.00, 3000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(32, 14, 11, '2025-09-11 00:00:00', 2017, 57000.00, '', NULL, 0.00, 0, 51400.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(33, 14, 11, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 1, 5600.00, 0, 51400.00, 5600.00, 16800.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(37, 13, 6, '2025-09-12 00:00:00', 2017, 0.00, 'ጥቅምት', 2, 14000.00, 0, 96000.00, 14000.00, 42000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(40, 13, 5, '2025-09-12 00:00:00', 2017, 0.00, 'መስከረም', 1, 3300.00, 0, 120500.00, 1700.00, 10500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(45, 13, 5, '2025-09-14 00:00:00', 2017, 0.00, 'ታኅሣሥ', 4, 6400.00, 0, 109300.00, 6400.00, 19200.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(46, 12, 5, '2025-09-14 00:00:00', 2017, 100500.00, '', NULL, 0.00, 0, 63300.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(47, 12, 5, '2025-09-14 00:00:00', 2017, 0.00, 'Meskerem', 1, 30000.00, 1, 70500.00, 30000.00, 90000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(50, 16, 5, '2025-09-15 00:00:00', 2017, 130000.00, '', NULL, 0.00, 0, 116500.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(51, 16, 5, '2025-09-15 00:00:00', 2017, 0.00, 'Meskerem', 1, 13500.00, 1, 116500.00, 13500.00, 40500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(65, 19, NULL, '2025-09-15 00:00:00', 2017, 300000.00, '', NULL, 0.00, 0, 3000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(69, 16, NULL, '2025-09-15 00:00:00', 2017, 250000.00, '', NULL, 0.00, 0, 250000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(70, 15, NULL, '2025-09-15 00:00:00', 2017, 3000000.00, '', NULL, 0.00, 0, 3000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(76, 10, NULL, '2025-09-15 00:00:00', 2017, 350000.00, '', NULL, 0.00, 0, 344634.25, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(77, 12, 6, '2025-09-17 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 3969.45, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(78, 12, 6, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 50000.00, 1, 50000.00, 50000.00, 150000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(80, 6, NULL, '2025-09-17 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99975501.75, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(81, 7, NULL, '2025-09-17 00:00:00', 2017, 2000000.00, '', NULL, 0.00, 0, 1904564.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(82, 5, NULL, '2025-09-17 00:00:00', 2017, 350000.00, '', NULL, 0.00, 0, 337874.25, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(83, 6, 5, '2025-09-17 00:00:00', 2017, 200000.00, '', NULL, 0.00, 0, 175000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(84, 6, 5, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 25000.00, 1, 175000.00, 25000.00, 75000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(85, 7, 5, '2025-09-17 00:00:00', 2017, 250000.00, '', NULL, 0.00, 0, 215000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(86, 7, 5, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 35000.00, 1, 215000.00, 35000.00, 105000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(90, 5, 5, '2025-09-18 00:00:00', 2017, 0.00, 'Tikimt', 2, 2500.00, 1, 49300.00, 2500.00, 7500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(91, 2, NULL, '2025-09-22 00:00:00', 2017, 170000000.00, '', NULL, 0.00, 0, 169370238.34, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(139, 20, NULL, '2025-09-24 00:00:00', 2017, 3000000.00, '', NULL, 0.00, 0, 2967041.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(140, 21, NULL, '2025-09-24 00:00:00', 2017, 9000000.00, '', NULL, 0.00, 0, 9000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(141, 11, NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99552718.85, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(142, 12, NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 100000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(143, 100, NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99979479.50, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(145, 9, NULL, '2025-09-30 00:00:00', 2017, 1000000.00, '', NULL, 0.00, 0, 891326.75, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(146, 8, NULL, '2025-10-02 00:00:00', 2017, 10000000.00, '', NULL, 0.00, 0, 10000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0),
(147, 5, 6, '2025-10-14 00:00:00', 2017, 390000.00, '', NULL, 0.00, 0, 319345.20, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0),
(148, 5, 6, '2025-10-14 00:00:00', 2017, 0.00, 'Meskerem', 1, 32500.00, 1, 357500.00, 32500.00, 97500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `budgets_old_oct20`
--

DROP TABLE IF EXISTS `budgets_old_oct20`;
CREATE TABLE `budgets_old_oct20` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `code_id` int(11) DEFAULT NULL,
  `adding_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `year` int(11) DEFAULT NULL,
  `yearly_amount` decimal(15,2) DEFAULT '0.00',
  `month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ec_month` tinyint(3) UNSIGNED DEFAULT NULL,
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
  `activity_based` tinyint(1) DEFAULT '0',
  `month_key` varchar(20) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (coalesce(`month`,'YEARLY')) STORED,
  `owner_type` enum('governmental','program') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'governmental'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budgets_old_oct20`
--

INSERT INTO `budgets_old_oct20` (`id`, `owner_id`, `code_id`, `adding_date`, `year`, `yearly_amount`, `month`, `ec_month`, `monthly_amount`, `quarter`, `remaining_yearly`, `remaining_monthly`, `remaining_quarterly`, `is_yearly`, `parent_id`, `allocated_amount`, `spent_amount`, `budget_type`, `program_name`, `activity_based`, `owner_type`) VALUES
(1, 5, 5, '2025-09-07 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 47800.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(2, 5, 5, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 15000.00, 1, 85000.00, 0.00, 45000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(7, 5, 7, '2025-09-07 00:00:00', 2017, 12000.00, '', NULL, 0.00, 0, 9500.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(8, 5, 7, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 1150.00, 1, 9500.00, 2500.00, 7500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(10, 37, 20, '2025-09-07 21:28:27', 2017, 100000.00, NULL, NULL, 0.00, NULL, 0.00, 0.00, 0.00, 1, NULL, 25000.00, 5000.00, 'governmental', NULL, 0, 'governmental'),
(11, 37, 20, '2025-09-07 21:28:27', 2017, 0.00, 'መስከረም', 1, 25000.00, 1, 0.00, 0.00, 0.00, 0, 10, 20000.00, 5000.00, 'governmental', NULL, 0, 'governmental'),
(14, 13, 5, '2025-09-07 00:00:00', 2017, 150000.00, '', NULL, 0.00, 0, 109300.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(17, 13, 5, '2025-09-07 00:00:00', 2017, 0.00, 'ጥቅምት', 2, 13000.00, 2, 124000.00, 1000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(18, 13, 5, '2025-09-07 00:00:00', 2017, 0.00, 'ህዳር', 3, 13000.00, 2, 111000.00, 7000.00, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(20, 13, 6, '2025-09-07 00:00:00', 2017, 150000.00, '', NULL, 0.00, 0, 96000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(21, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'መስከረም', 1, 13000.00, 1, 137000.00, 7073.45, 39000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(22, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'ሐምሌ', 11, 10000.00, 1, 127000.00, 10000.00, 30000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(23, 13, 6, '2025-09-07 00:00:00', 2017, 0.00, 'ነሐሴ', 12, 17000.00, 1, 110000.00, 17000.00, 51000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(24, 22, 6, '2025-09-09 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 100000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(27, 21, 5, '2025-09-11 00:00:00', 2017, 50000.00, '', NULL, 0.00, 0, 50000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0, 'governmental'),
(28, 19, 10, '2025-09-11 00:00:00', 2017, 15000.00, '', NULL, 0.00, 0, 15000.00, 0.00, 0.00, 0, NULL, 0.00, 0.00, 'governmental', '', 0, 'governmental'),
(30, 5, 9, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 1, 1000.00, 1, 10000.00, 1000.00, 3000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(32, 14, 11, '2025-09-11 00:00:00', 2017, 57000.00, '', NULL, 0.00, 0, 51400.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(33, 14, 11, '2025-09-11 00:00:00', 2017, 0.00, 'መስከረም', 1, 5600.00, 0, 51400.00, 5600.00, 16800.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(37, 13, 6, '2025-09-12 00:00:00', 2017, 0.00, 'ጥቅምት', 2, 14000.00, 0, 96000.00, 14000.00, 42000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(40, 13, 5, '2025-09-12 00:00:00', 2017, 0.00, 'መስከረም', 1, 3300.00, 0, 120500.00, 1700.00, 10500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(45, 13, 5, '2025-09-14 00:00:00', 2017, 0.00, 'ታኅሣሥ', 4, 6400.00, 0, 109300.00, 6400.00, 19200.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(46, 12, 5, '2025-09-14 00:00:00', 2017, 100500.00, '', NULL, 0.00, 0, 63300.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(47, 12, 5, '2025-09-14 00:00:00', 2017, 0.00, 'Meskerem', 1, 30000.00, 1, 70500.00, 30000.00, 90000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(50, 16, 5, '2025-09-15 00:00:00', 2017, 130000.00, '', NULL, 0.00, 0, 116500.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(51, 16, 5, '2025-09-15 00:00:00', 2017, 0.00, 'Meskerem', 1, 13500.00, 1, 116500.00, 13500.00, 40500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(65, 19, NULL, '2025-09-15 00:00:00', 2017, 300000.00, '', NULL, 0.00, 0, 3000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(69, 16, NULL, '2025-09-15 00:00:00', 2017, 250000.00, '', NULL, 0.00, 0, 250000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(70, 15, NULL, '2025-09-15 00:00:00', 2017, 3000000.00, '', NULL, 0.00, 0, 3000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(76, 10, NULL, '2025-09-15 00:00:00', 2017, 350000.00, '', NULL, 0.00, 0, 344634.25, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(77, 12, 6, '2025-09-17 00:00:00', 2017, 100000.00, '', NULL, 0.00, 0, 3969.45, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(78, 12, 6, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 50000.00, 1, 50000.00, 50000.00, 150000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(80, 6, NULL, '2025-09-17 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99975501.75, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(81, 7, NULL, '2025-09-17 00:00:00', 2017, 2000000.00, '', NULL, 0.00, 0, 1904564.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(82, 5, NULL, '2025-09-17 00:00:00', 2017, 350000.00, '', NULL, 0.00, 0, 337874.25, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(83, 6, 5, '2025-09-17 00:00:00', 2017, 200000.00, '', NULL, 0.00, 0, 175000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(84, 6, 5, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 25000.00, 1, 175000.00, 25000.00, 75000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(85, 7, 5, '2025-09-17 00:00:00', 2017, 250000.00, '', NULL, 0.00, 0, 215000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(86, 7, 5, '2025-09-17 00:00:00', 2017, 0.00, 'Meskerem', 1, 35000.00, 1, 215000.00, 35000.00, 105000.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(90, 5, 5, '2025-09-18 00:00:00', 2017, 0.00, 'Tikimt', 2, 2500.00, 1, 49300.00, 2500.00, 7500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(91, 2, NULL, '2025-09-22 00:00:00', 2017, 170000000.00, '', NULL, 0.00, 0, 169370238.34, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(139, 20, NULL, '2025-09-24 00:00:00', 2017, 3000000.00, '', NULL, 0.00, 0, 2967041.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(140, 21, NULL, '2025-09-24 00:00:00', 2017, 9000000.00, '', NULL, 0.00, 0, 9000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(141, 11, NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99552718.85, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(142, 12, NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 100000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(143, 100, NULL, '2025-09-24 00:00:00', 2017, 100000000.00, '', NULL, 0.00, 0, 99979479.50, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(145, 9, NULL, '2025-09-30 00:00:00', 2017, 1000000.00, '', NULL, 0.00, 0, 891326.75, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(146, 8, NULL, '2025-10-02 00:00:00', 2017, 10000000.00, '', NULL, 0.00, 0, 10000000.00, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'program', NULL, 0, 'program'),
(147, 5, 6, '2025-10-14 00:00:00', 2017, 390000.00, '', NULL, 0.00, 0, 319345.20, 0.00, 0.00, 1, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental'),
(148, 5, 6, '2025-10-14 00:00:00', 2017, 0.00, 'Meskerem', 1, 32500.00, 1, 357500.00, 32500.00, 97500.00, 0, NULL, 0.00, 0.00, 'governmental', NULL, 0, 'governmental');

-- --------------------------------------------------------

--
-- Table structure for table `budget_codes`
--

DROP TABLE IF EXISTS `budget_codes`;
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

DROP TABLE IF EXISTS `budget_owners`;
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

DROP TABLE IF EXISTS `budget_owners2`;
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

DROP TABLE IF EXISTS `budget_revisions`;
CREATE TABLE `budget_revisions` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) DEFAULT NULL,
  `previous_amount` decimal(15,2) DEFAULT NULL,
  `new_amount` decimal(15,2) DEFAULT NULL,
  `revision_date` datetime DEFAULT NULL,
  `revised_by` int(11) DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
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
(12, 'አደዐር ወረዳ', 'Adaar Woreda', 1142.70, 1333.80, 1606.80),
(13, 'አፋምቦ ወረዳ', 'Afambo Woreda', 1142.70, 1333.80, 1606.80),
(14, 'አይሳዒታ ከተማ አስተዳደር', 'Aysita City Admin Woreda', 1288.30, 1465.10, 1752.40),
(15, 'አይሳዒታ ወረዳ', 'Ayssaita Woreda', 1288.30, 1465.10, 1752.40),
(16, 'ጭፍራ ከተማ አስተዳደር', 'Chifra City Administration', 879.00, 1026.00, 1236.00),
(17, 'ጭፍራ ወረዳ', 'Chifra Woreda', 879.00, 1026.00, 1236.00),
(18, 'ዱብቲ ከተማ አስተዳደር', 'Dubti City Administration', 1142.70, 1333.80, 1606.80),
(19, 'ዱብቲ ወረዳ', 'Dubti Woreda', 1142.70, 1333.80, 1606.80),
(20, 'ኤሊደዐር ወረዳ', 'Elidar Woreda', 1142.70, 1333.80, 1606.80),
(21, 'ዳቡ ወረዳ', 'Daabu Woreda', 1142.70, 1333.80, 1606.80),
(22, 'ሙሳ አሊ', 'Musa Qalli', 1142.70, 1333.80, 1606.80),
(23, 'ኮሪ ወረዳ', 'Kori Woreda', 1142.70, 1333.80, 1606.80),
(24, 'ሚሌ ከተማ አስተዳደር', 'Mille City Administration', 879.00, 1026.00, 1236.00),
(25, 'ሚሌ ወረዳ', 'Mille Woreda', 879.00, 1026.00, 1236.00),
(26, 'ሰመራ ሎጊያ ከተማ አስተዳደር', 'Semera-Logia City Administration', 1103.00, 1264.00, 1446.00),
(27, 'አብዐላ ከተማ አስተዳደር', 'Abala City Administration', 1288.30, 1465.10, 1752.40),
(28, 'አብዐላ ወረዳ', 'Abala Woreda', 1288.30, 1465.10, 1752.40),
(29, 'አፍዴራ ወረዳ', 'Afdera Woreda', 1230.60, 1436.40, 1730.40),
(30, 'ሙራዩም ወረዳ', 'Murayyum Woreda', 1142.70, 1333.80, 1606.80),
(31, 'በራህሌ ወረዳ', 'Berahle Woreda', 1230.60, 1436.40, 1730.40),
(32, 'ቢዱ ወረዳ', 'Bidu Woreda', 1142.70, 1333.80, 1606.80),
(33, 'ደሎል ወረዳ', 'Dalol Woreda', 1230.60, 1436.40, 1730.40),
(34, 'አዱኩዋ ወረዳ', 'Adukuwa Woreda', 1142.70, 1333.80, 1606.80),
(35, 'ኢረብቲ ወረዳ', 'Erabti Woreda', 1142.70, 1333.80, 1606.80),
(36, 'ኮነባ ወረዳ', 'Koneba Woreda', 1142.70, 1333.80, 1606.80),
(37, 'መጋሌ ወረዳ', 'Magale Woreda', 1142.70, 1333.80, 1606.80),
(38, 'ዋሳማ ወረዳ', 'Wassama Woreda', 1142.70, 1333.80, 1606.80),
(39, 'ዕቢዳ ወረዳ', 'Abida Woreda', 879.00, 1026.00, 1236.00),
(40, 'ወረር አዶብተሊ ከተማ አስተዳደር', 'Warar Adobteli Town Administration', 879.00, 1026.00, 1236.00),
(41, 'ዕሚባራ ወረዳ', 'Amibara Woreda', 991.00, 1227.00, 1348.00),
(42, 'አርጎባ ልዩ ወረዳ', 'Argoba Special Woreda', 879.00, 1026.00, 1348.00),
(43, 'አዋሽ ፈንቲዕሌ ወረዳ', 'Awash Fenteale Woreda', 879.00, 1026.00, 1236.00),
(44, 'አዋሽ ከተማ', 'Awash Town', 879.00, 1026.00, 1236.00),
(45, 'ቡሪሞዳይቶ ወረዳ', 'Buremedaytu Woreda', 879.00, 1026.00, 1236.00),
(46, 'ዱለቻ ወረዳ', 'Dulecha Woreda', 879.00, 1026.00, 1236.00),
(47, 'ገላዕሉ ወረዳ', 'Galalo Woreda', 879.00, 1026.00, 1236.00),
(48, 'ገዋኒ ወረዳ', 'Gewane Woreda', 879.00, 1026.00, 1236.00),
(49, 'ሀንሩካ ወረዳ', 'Hanruka Woreda', 879.00, 1026.00, 1236.00),
(50, 'አውራ ወረዳ', 'Awura Woreda', 879.00, 1026.00, 1236.00),
(51, 'ኡዋ ወረዳ', 'Ewa Woreda', 879.00, 1026.00, 1236.00),
(52, 'ጉሊና ወረዳ', 'Gulina Woreda', 991.00, 1227.00, 1348.00),
(53, 'መባይ ወረዳ', 'Mebay Woreda', 1142.70, 1333.80, 1606.80),
(54, 'ቴሩ ወረዳ', 'Teru Woreda', 1142.70, 1333.80, 1606.80),
(55, 'ያሎ ወረዳ', 'Yallo Woreda', 879.00, 1026.00, 1236.00),
(56, 'ዳሊፋጌ ወረዳ', 'Dalifaghe Woreda', 991.00, 1227.00, 1348.00),
(57, 'ደዌ ወረዳ', 'Dewe Woreda', 879.00, 1026.00, 1236.00),
(58, 'ሀደለዔላ ወረዳ', 'Hadeleala Woreda', 879.00, 1026.00, 1236.00),
(59, 'ሰሙሮቢ ወረዳ', 'Semirobi Woreda', 879.00, 1026.00, 1236.00),
(60, 'ተላላክ ወረዳ', 'Telalak Woreda', 879.00, 1026.00, 1236.00),
(61, 'አድዓዶ ወረዳ', 'Adqado Woreda', 879.00, 1026.00, 1236.00),
(62, 'ገረኒ ወረዳ', 'Gereni Woreda', 1142.70, 1333.80, 1606.80),
(63, 'ኪለሉ ወረዳ', 'Kilelu Woreda', 879.00, 1026.00, 1236.00),
(64, 'ሲባይዲ ወረዳ', 'Sibaybi Woreda', 879.00, 1026.00, 1236.00),
(65, 'ያንጉዲ ወረዳ', 'Yangude Woreda', 991.00, 1227.00, 1348.00);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
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

DROP TABLE IF EXISTS `employees2`;
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_budget_entry` (`owner_id`,`code_id`,`year`,`month`),
  ADD UNIQUE KEY `uniq_budget_owner_code_year_monthkey` (`budget_type`,`owner_id`,`code_id`,`year`,`month_key`),
  ADD UNIQUE KEY `uniq_gov_budget` (`gov_unique_key`),
  ADD UNIQUE KEY `uniq_prog_budget` (`prog_unique_key`),
  ADD KEY `idx_owner_code` (`owner_id`,`code_id`),
  ADD KEY `idx_budgets_owner_code_year_ecmonth` (`owner_id`,`code_id`,`year`,`ec_month`),
  ADD KEY `indx_budgets_owner_year_yearly` (`owner_id`,`year`,`monthly_amount`),
  ADD KEY `idx_budget_owner_code_year_month` (`budget_type`,`owner_id`,`code_id`,`year`,`month`);

--
-- Indexes for table `budgets2`
--
ALTER TABLE `budgets2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_budget_entry` (`owner_id`,`code_id`,`year`,`month`),
  ADD KEY `idx_owner_code` (`owner_id`,`code_id`),
  ADD KEY `idx_budgets_owner_code_year_ecmonth` (`owner_id`,`code_id`,`year`,`ec_month`),
  ADD KEY `indx_budgets_owner_year_yearly` (`owner_id`,`year`,`monthly_amount`);

--
-- Indexes for table `budgets_old_oct20`
--
ALTER TABLE `budgets_old_oct20`
  ADD PRIMARY KEY (`id`);

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
  ADD UNIQUE KEY `code_indx` (`code`) USING BTREE;

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `budgets2`
--
ALTER TABLE `budgets2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `budgets_old_oct20`
--
ALTER TABLE `budgets_old_oct20`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `budget_codes`
--
ALTER TABLE `budget_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `budget_owners`
--
ALTER TABLE `budget_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
