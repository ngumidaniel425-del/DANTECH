-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql112.infinityfree.com
-- Generation Time: Mar 06, 2026 at 09:07 AM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41260155_smartscan`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sync`
--

CREATE TABLE `attendance_sync` (
  `id` int(11) NOT NULL,
  `student_adm` varchar(50) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `lesson_name` varchar(50) NOT NULL,
  `period_type` varchar(20) NOT NULL,
  `attendance_date` date NOT NULL,
  `sync_time` timestamp NULL DEFAULT current_timestamp(),
  `status` varchar(10) DEFAULT 'Present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `class_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `lesson_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students_master`
--

CREATE TABLE `students_master` (
  `id` int(11) NOT NULL,
  `admission` varchar(50) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `school_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students_master`
--

INSERT INTO `students_master` (`id`, `admission`, `fullname`, `class_name`, `school_name`) VALUES
(1, '1234', 'John Mwangi Test', 'Ict/l6/jan/2024', 'Kiirua Technical Training Institute');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_sync`
--
ALTER TABLE `attendance_sync`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students_master`
--
ALTER TABLE `students_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student` (`admission`,`school_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_sync`
--
ALTER TABLE `attendance_sync`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students_master`
--
ALTER TABLE `students_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
