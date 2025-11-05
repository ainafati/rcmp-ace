-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 03, 2025 at 05:33 AM
-- Server version: 10.1.38-MariaDB
-- PHP Version: 5.6.40

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phoneNum` varchar(20) NOT NULL,
  `status` varchar(100) NOT NULL,
  `ic_num` varchar(12) NOT NULL,
  `otp_code` int(6) DEFAULT NULL,
  `otp_expiry` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `name`, `email`, `password`, `phoneNum`, `status`, `ic_num`, `otp_code`, `otp_expiry`) VALUES
(4, 'Aina Fatihah', 'aina@unikl.edu.my', '$2y$10$6vx/mzdIYngMOVRzBzmoQ.O8e5/Mck.iOSXRwZs/uSRaKJNcaVYeO', '0167047491', 'active', '012453678216', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `asset_code` varchar(50) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Available',
  `last_return_date` date DEFAULT NULL COMMENT 'Date the asset was last checked in'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`asset_id`, `item_id`, `brand`, `model`, `asset_code`, `status`, `last_return_date`) VALUES
(24, 28, '', '', 'STA-001', 'Reserved', NULL),
(25, 28, '', '', 'STA-002', 'Reserved', NULL),
(26, 28, '', '', 'STA-003', 'Available', NULL),
(27, 28, '', '', 'STA-004', 'Reserved', NULL),
(28, 28, '', '', 'STA-005', 'Available', '2025-10-22'),
(29, 28, '', '', 'STA-006', 'Available', NULL),
(30, 28, '', '', 'STA-007', 'Available', NULL),
(31, 28, '', '', 'STA-008', 'Available', NULL),
(32, 28, '', '', 'STA-009', 'Available', NULL),
(33, 28, '', '', 'STA-010', 'Available', NULL),
(34, 29, 'Dell', 'Latitude 5400', 'STA-001', 'Maintenance', '2025-10-29'),
(35, 29, 'Dell', 'Latitude 5400', 'STA-002', 'Reserved', NULL),
(36, 29, 'Dell', 'Latitude 5400', 'STA-003', 'Reserved', NULL),
(37, 29, 'Dell', 'Latitude 5400', 'STA-004', 'Checked Out', '2025-10-29'),
(38, 29, 'Dell', 'Latitude 5400', 'STA-005', 'Checked Out', '2025-10-29'),
(39, 29, 'Dell', 'Latitude 5400', 'STA-006', 'Reserved', NULL),
(40, 29, 'Dell', 'Latitude 5400', 'STA-007', 'Reserved', NULL),
(41, 29, 'Dell', 'Latitude 5400', 'STA-008', 'Available', NULL),
(42, 29, 'Dell', 'Latitude 5400', 'STA-009', 'Available', NULL),
(43, 29, 'Dell', 'Latitude 5400', 'STA-010', 'Available', NULL),
(44, 29, 'Dell', 'Latitude 5400', 'STA-011', 'Available', NULL),
(45, 29, 'Dell', 'Latitude 5400', 'STA-012', 'Available', NULL),
(78, 35, '', '', 'CAM-0001', 'Available', '2025-10-22'),
(80, 35, '', '', 'CAM-0003', 'Available', '2025-10-22'),
(81, 35, '', '', 'CAM-0004', 'Available', '2025-10-22'),
(82, 35, '', '', 'CAM-0005', 'Available', NULL),
(83, 35, '', '', 'CAM-0006', 'Available', NULL),
(84, 35, '', '', 'CAM-0007', 'Available', NULL),
(85, 36, 'Apple', 'MacBook Pro M1', 'LAP-0001', 'Reserved', NULL),
(86, 36, 'Apple', 'MacBook Pro M1', 'LAP-0002', 'Reserved', NULL),
(87, 36, 'Apple', 'MacBook Pro M1', 'LAP-0003', 'Available', NULL),
(88, 36, 'Apple', 'MacBook Pro M1', 'LAP-0004', 'Reserved', NULL),
(89, 36, 'Apple', 'MacBook Pro M1', 'LAP-0005', 'Reserved', NULL),
(98, 35, '', '', 'CAM-0008', 'Available', NULL),
(99, 35, '', '', 'CAM-0009', 'Available', NULL),
(100, 35, '', '', 'CAM-0010', 'Available', NULL),
(101, 35, '', '', 'CAM-0011', 'Available', NULL),
(102, 35, '', '', 'CAM-0012', 'Available', NULL),
(103, 35, '', '', 'CAM-0013', 'Available', NULL),
(104, 35, '', '', 'CAM-0014', 'Available', NULL),
(105, 37, 'anker', 'PowerPot III', 'CHA-0001', 'Checked Out', NULL),
(106, 37, 'anker', 'PowerPot III', 'CHA-0002', 'Reserved', NULL),
(107, 37, 'anker', 'PowerPot III', 'CHA-0003', 'Reserved', NULL),
(108, 37, 'anker', 'PowerPot III', 'CHA-0004', 'Reserved', NULL),
(109, 37, 'anker', 'PowerPot III', 'CHA-0005', 'Reserved', NULL),
(110, 37, 'anker', 'PowerPot III', 'CHA-0006', 'Reserved', NULL),
(111, 37, 'anker', 'PowerPot III', 'CHA-0007', 'Available', NULL),
(112, 37, '', '', 'CHA-0008', 'Available', NULL),
(113, 37, '', '', 'CHA-0009', 'Available', NULL),
(114, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0015', 'Available', NULL),
(115, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0016', 'Available', NULL),
(116, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0017', 'Available', NULL),
(117, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0018', 'Available', NULL),
(118, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0019', 'Available', NULL),
(119, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0020', 'Available', NULL),
(120, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0021', 'Available', NULL),
(121, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0022', 'Available', '2025-10-22'),
(122, 38, 'Sony', 'Cyber-shot DSC-W800', 'CAM-0023', 'Available', '2025-10-22'),
(123, 38, '', '', 'CAM-0024', 'Maintenance', '2025-10-22'),
(124, 38, '', '', 'CAM-0025', 'Available', '2025-10-22');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `image_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `image_url`) VALUES
(5, 'Cable & Adapter', 'uploads/cat_68f1366341d9e6.66529143.png'),
(6, 'Laptops', 'uploads/cat_68f1863b7228f9.85739171.png'),
(7, 'Camera', 'uploads/cat_68f1366deacf20.73426549.png'),
(9, 'charger', 'uploads/cat_68f1052f26abd9.49129767.png'),
(13, 'Printer', 'uploads/cat_68f83dfd50aff9.99227439.png'),
(14, 'Mouse', 'uploads/cat_68f84043d51c47.01377174.png');

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE `item` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `item`
--

INSERT INTO `item` (`item_id`, `item_name`, `category_id`, `description`) VALUES
(28, 'Standard HDMI Cable (1.5m)', 5, 'Generic HDMI cable for connecting laptops to projector'),
(29, 'Staff Laptop', 6, '15-inch laptop for general use by staff member'),
(35, 'Camera EOS 90D', 7, 'Camera ideal for photography and videography'),
(36, 'Macbook Pro ', 6, 'Apple\'s compact and powerful laptops'),
(37, 'type-c charger', 9, 'fast-charging adapter'),
(38, 'Digital Camera', 7, 'compact camera for general documentation');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reserve_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` date NOT NULL,
  `priority` tinyint(1) NOT NULL DEFAULT '3'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reserve_id`, `user_id`, `created_at`, `priority`) VALUES
(33, 14, '2025-10-21', 3),
(34, 14, '2025-10-21', 3),
(35, 14, '2025-10-21', 3),
(36, 14, '2025-10-21', 3),
(37, 14, '2025-10-22', 3),
(38, 14, '2025-10-22', 3),
(39, 15, '2025-10-30', 3),
(43, 14, '2025-10-30', 1),
(44, 17, '2025-10-31', 2);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_assets`
--

CREATE TABLE `reservation_assets` (
  `id` int(11) NOT NULL,
  `reservation_item_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `reservation_assets`
--

INSERT INTO `reservation_assets` (`id`, `reservation_item_id`, `asset_id`) VALUES
(88, 47, 28),
(90, 48, 114),
(103, 50, 105),
(108, 48, 115),
(109, 48, 116),
(110, 48, 117),
(111, 51, 78),
(112, 51, 80),
(113, 51, 81),
(114, 52, 121),
(115, 52, 122),
(116, 52, 123),
(117, 52, 124),
(118, 53, 34),
(119, 53, 37),
(120, 53, 38),
(121, 58, 37),
(122, 58, 38),
(141, 59, 39),
(142, 59, 40);

-- --------------------------------------------------------

--
-- Table structure for table `reservation_items`
--

CREATE TABLE `reservation_items` (
  `id` int(11) NOT NULL,
  `reserve_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(50) NOT NULL,
  `reserve_date` date NOT NULL,
  `return_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` varchar(100) NOT NULL DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `rejection_reason` text,
  `return_condition` varchar(50) DEFAULT NULL,
  `return_remarks` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `reservation_items`
--

INSERT INTO `reservation_items` (`id`, `reserve_id`, `item_id`, `quantity`, `reserve_date`, `return_date`, `reason`, `status`, `approved_by`, `rejection_reason`, `return_condition`, `return_remarks`) VALUES
(47, 33, 28, 2, '2025-10-15', '2025-10-22', 'program', 'Returned', 14, NULL, 'Good', 'Checked in 1 asset(s). See asset status for details.'),
(48, 34, 38, 4, '2025-10-27', '2025-10-21', 'program', 'Returned', 14, NULL, 'Good', 'in good conditions'),
(49, 34, 36, 1, '2025-11-05', '2025-11-10', 'discussion', 'Pending', NULL, NULL, NULL, NULL),
(50, 35, 37, 4, '2025-10-21', '2025-10-28', 'charge missing', 'Checked Out', 14, NULL, NULL, NULL),
(51, 36, 35, 3, '2025-10-22', '2025-10-22', 'night culture program', 'Returned', 15, NULL, 'Good', 'in good conditions'),
(52, 37, 38, 4, '2025-10-22', '2025-10-22', 'nursing program', 'Returned', 15, NULL, '1 asset(s) Damaged', 'Checked in 4 asset(s). See asset status for details.'),
(53, 38, 29, 3, '2025-10-22', '2025-10-29', 'meeting', 'Returned', 15, NULL, '1 asset(s) Damaged', 'Checked in 3 asset(s). See asset status for details.'),
(54, 39, 29, 2, '2025-10-31', '2025-11-03', 'meeting', 'Pending', NULL, NULL, NULL, NULL),
(58, 43, 29, 2, '2025-10-30', '2025-11-03', 'fyp', 'Checked Out', 15, NULL, NULL, NULL),
(59, 44, 29, 2, '2025-10-31', '2025-11-03', 'meeting', 'Approved', 14, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `technician`
--

CREATE TABLE `technician` (
  `tech_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phoneNum` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` varchar(255) NOT NULL,
  `suspension_remarks` text COMMENT 'Reason for suspension',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ic_num` varchar(12) NOT NULL,
  `otp_code` int(6) DEFAULT NULL,
  `otp_expiry` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `technician`
--

INSERT INTO `technician` (`tech_id`, `name`, `phoneNum`, `password`, `email`, `status`, `suspension_remarks`, `created_at`, `ic_num`, `otp_code`, `otp_expiry`) VALUES
(14, 'Emily', '0164429742', '$2y$10$qrhZPmsm8xJi10C4W09aR.0InWXKMxe4kI7H2spO6wvXHC6VXYeka', 'emily@unikl.edu.my', 'Suspended', 'frequent late response to user requests', '2025-10-21 03:34:42', '051109120162', NULL, NULL),
(15, 'Parker', '0112348795', '$2y$10$mgef5zTdaV65tqwWiKTF2.U4VisQubv7W5RraDeDdifeF69Nd4rxC', 'parker@unikl.edu.my', 'active', NULL, '2025-10-21 07:22:53', '023428539210', NULL, NULL),
(16, 'ainafa', '0194428099', '$2y$10$icjEnbMIpOxagz0GivOeuOQL3m83nPzc2gg7/TzWy8QxEEXcZ.uau', '2023692562@student.uitm.edu.my', 'active', NULL, '2025-10-30 01:00:42', '0231212452', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phoneNum` varchar(20) NOT NULL,
  `status` varchar(255) NOT NULL,
  `suspension_remarks` text COMMENT 'Reason for suspension',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ic_num` varchar(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `name`, `email`, `password`, `phoneNum`, `status`, `suspension_remarks`, `created_at`, `ic_num`) VALUES
(14, 'Jason Lee', 'jason.lee@unikl.edu.my', '$2y$10$DuofbEdDVfg4LQe4v.lyteZCfF/mNoBTAtPWipk2mQLsHn6w9P03i', '0149948721', 'active', NULL, '2025-10-21 01:04:09', '051109120162'),
(15, 'Sarah', 'sarah@unikl.edu.my', '$2y$10$FcHzeMWtUY.00Z7SgO6dBen72NLW3pn0XHZH2z.wTCxqVAe2T0Nfe', '0198004372', 'active', NULL, '2025-10-22 08:46:12', '051109110033'),
(16, 'aina', 'aina.fatihah@t.unikl.edu.my', '$2y$10$SnjRhCveLFVuCbv6SnTSAuqxMn3zvcAEL2Lmu/23G7fWolk4WY6wu', '0193007491', 'active', NULL, '2025-10-22 15:46:23', '050209083322'),
(17, 'ainafa', 'ainafthhj@gmail.com', '$2y$10$p/tc2BZnvmrVrLCWnEu13uPIqkB0/.iBGbH3AUkgRp0YM64/Q7HQK', '0167047491', 'active', NULL, '2025-10-28 03:16:55', '051109120162');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reserve_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reservation_assets`
--
ALTER TABLE `reservation_assets`
  ADD PRIMARY KEY (`reservation_item_id`,`asset_id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `id` (`id`);

--
-- Indexes for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reserve_id` (`reserve_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `technician`
--
ALTER TABLE `technician`
  ADD PRIMARY KEY (`tech_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `item`
--
ALTER TABLE `item`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reserve_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `reservation_assets`
--
ALTER TABLE `reservation_assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `reservation_items`
--
ALTER TABLE `reservation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `technician`
--
ALTER TABLE `technician`
  MODIFY `tech_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`);

--
-- Constraints for table `item`
--
ALTER TABLE `item`
  ADD CONSTRAINT `item_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `reservation_assets`
--
ALTER TABLE `reservation_assets`
  ADD CONSTRAINT `reservation_assets_ibfk_1` FOREIGN KEY (`reservation_item_id`) REFERENCES `reservation_items` (`id`),
  ADD CONSTRAINT `reservation_assets_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`);

--
-- Constraints for table `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD CONSTRAINT `reservation_items_ibfk_1` FOREIGN KEY (`reserve_id`) REFERENCES `reservations` (`reserve_id`),
  ADD CONSTRAINT `reservation_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
