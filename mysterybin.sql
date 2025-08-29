-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 29, 2025 at 09:17 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mysterybin`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`) VALUES
(1, 'admin', '123');

-- --------------------------------------------------------

--
-- Table structure for table `bin_status`
--

CREATE TABLE `bin_status` (
  `id` int(11) NOT NULL,
  `is_full` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bin_status`
--

INSERT INTO `bin_status` (`id`, `is_full`, `updated_at`) VALUES
(1, 0, '2025-08-29 05:07:47');

--
-- Triggers `bin_status`
--
DELIMITER $$
CREATE TRIGGER `after_bin_status_update` AFTER UPDATE ON `bin_status` FOR EACH ROW BEGIN
    IF NEW.is_full = 1 THEN
        INSERT INTO notifications (message, type) 
        VALUES ('Bin is completely full and needs immediate attention!', 'danger');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `type` enum('info','warning','danger','success') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `message`, `type`, `is_read`, `created_at`, `updated_at`) VALUES
(1, 'Bin is 85% full. Please empty soon.', 'warning', 0, '2025-08-29 05:41:02', '2025-08-29 05:41:02'),
(2, 'Reward \"Free Coffee\" is running low (20%).', 'danger', 0, '2025-08-29 05:41:02', '2025-08-29 05:41:02'),
(3, 'New collection record: 1250g collected today!', 'success', 1, '2025-08-29 05:41:02', '2025-08-29 05:41:02'),
(4, 'System maintenance scheduled for tomorrow at 2 AM.', 'info', 1, '2025-08-29 05:41:02', '2025-08-29 05:41:02'),
(5, 'User John Doe redeemed a reward.', 'info', 0, '2025-08-29 05:41:02', '2025-08-29 05:41:02');

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL,
  `reward_name` varchar(50) NOT NULL,
  `level` int(11) NOT NULL CHECK (`level` >= 0 and `level` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`id`, `reward_name`, `level`) VALUES
(1, 'Reward A', 100),
(2, 'Reward B', 100);

--
-- Triggers `rewards`
--
DELIMITER $$
CREATE TRIGGER `after_reward_update` AFTER UPDATE ON `rewards` FOR EACH ROW BEGIN
    IF NEW.level < 25 AND OLD.level >= 25 THEN
        INSERT INTO notifications (message, type) 
        VALUES (CONCAT('Reward "', NEW.reward_name, '" is running low (', NEW.level, '%).'), 'danger');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `weight` float NOT NULL,
  `reward_dispensed` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `transaction_id`, `weight`, `reward_dispensed`, `created_at`) VALUES
(1, 'TRX001', 125.5, 'Coffee Coupon', '2023-10-15 01:23:45'),
(2, 'TRX002', 230, 'Snack Voucher', '2023-10-15 03:45:22'),
(3, 'TRX003', 85.2, 'None', '2023-10-15 06:12:33'),
(4, 'TRX004', 310.7, 'Coffee Coupon', '2023-10-16 00:55:10'),
(5, 'TRX005', 175.3, 'Discount Code', '2023-10-16 04:30:45'),
(6, 'TRX006', 420.8, 'Free Drink', '2023-10-16 07:22:18'),
(7, 'TRX007', 95, 'None', '2023-10-17 02:11:34'),
(8, 'TRX008', 265.4, 'Snack Voucher', '2023-10-17 05:45:29'),
(9, 'TRX009', 185.6, 'Coffee Coupon', '2023-10-18 01:33:47'),
(10, 'TRX010', 335.2, 'Free Drink', '2023-10-18 06:20:15'),
(11, 'TRX011', 150, 'Discount Code', '2023-10-19 03:05:38'),
(12, 'TRX012', 275.8, 'Coffee Coupon', '2023-10-19 08:40:22'),
(13, 'TRX013', 90.5, 'None', '2023-10-20 02:30:11'),
(14, 'TRX014', 220.3, 'Snack Voucher', '2023-10-20 05:15:49'),
(15, 'TRX015', 395.7, 'Free Drink', '2023-10-21 01:45:26'),
(16, 'TRX016', 145.2, 'Coffee Coupon', '2023-10-21 04:20:33'),
(17, 'TRX017', 310, 'Discount Code', '2023-10-22 03:10:45'),
(18, 'TRX018', 180.6, 'None', '2023-10-22 07:35:18'),
(19, 'TRX019', 255.9, 'Snack Voucher', '2023-10-23 02:40:27'),
(20, 'TRX020', 425.4, 'Free Drink', '2023-10-23 06:50:12'),
(21, 'TRX021', 130.7, 'Coffee Coupon', '2023-10-24 01:15:39'),
(22, 'TRX022', 290.2, 'Discount Code', '2023-10-24 05:25:44'),
(23, 'TRX023', 165.8, 'None', '2023-10-25 03:30:15'),
(24, 'TRX024', 340.5, 'Snack Voucher', '2023-10-25 08:05:28'),
(25, 'TRX025', 205.3, 'Coffee Coupon', '2023-10-26 02:20:37'),
(26, 'TRX026', 375.9, 'Free Drink', '2023-10-26 06:45:19'),
(27, 'TRX027', 115.6, 'Discount Code', '2023-10-27 04:10:42'),
(28, 'TRX028', 245, 'None', '2023-10-27 07:30:25'),
(29, 'TRX029', 330.4, 'Snack Voucher', '2023-10-28 03:40:33'),
(30, 'TRX030', 195.7, 'Coffee Coupon', '2023-10-28 06:15:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bin_status`
--
ALTER TABLE `bin_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bin_status`
--
ALTER TABLE `bin_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `rewards`
--
ALTER TABLE `rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
