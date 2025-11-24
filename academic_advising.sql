-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2025 at 03:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `academic_advising`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `department`, `email`, `created_at`) VALUES
(1, 'admin', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'admin@dlsu.edu.ph', '2025-11-24 04:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `advising_deadlines`
--

CREATE TABLE `advising_deadlines` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `deadline_date` date NOT NULL,
  `term` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `advising_schedules`
--

CREATE TABLE `advising_schedules` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `available_time` time NOT NULL,
  `is_booked` tinyint(1) DEFAULT 0,
  `booked_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booklet_edit_requests`
--

CREATE TABLE `booklet_edit_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `booklet_record_id` int(11) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bulk_upload_history`
--

CREATE TABLE `bulk_upload_history` (
  `id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_type` enum('students','professors','courses') NOT NULL,
  `filename` varchar(255) NOT NULL,
  `total_records` int(11) DEFAULT 0,
  `successful_records` int(11) DEFAULT 0,
  `failed_records` int(11) DEFAULT 0,
  `error_log` text DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bulk_upload_history`
--

INSERT INTO `bulk_upload_history` (`id`, `uploaded_by`, `upload_type`, `filename`, `total_records`, `successful_records`, `failed_records`, `error_log`, `upload_date`) VALUES
(1, 1, 'courses', 'Final_Combined_CPE_Curriculum.csv', 124, 0, 124, 'Row 1: Incomplete data (expected 7 columns, got 6)\nRow 2: Incomplete data (expected 7 columns, got 6)\nRow 3: Incomplete data (expected 7 columns, got 6)\nRow 4: Incomplete data (expected 7 columns, got 6)\nRow 5: Incomplete data (expected 7 columns, got 6)\nRow 6: Incomplete data (expected 7 columns, got 6)\nRow 7: Incomplete data (expected 7 columns, got 6)\nRow 8: Incomplete data (expected 7 columns, got 6)\nRow 9: Incomplete data (expected 7 columns, got 6)\nRow 10: Incomplete data (expected 7 columns, got 6)\nRow 11: Incomplete data (expected 7 columns, got 6)\nRow 12: Incomplete data (expected 7 columns, got 6)\nRow 13: Incomplete data (expected 7 columns, got 6)\nRow 14: Incomplete data (expected 7 columns, got 6)\nRow 15: Incomplete data (expected 7 columns, got 6)\nRow 16: Incomplete data (expected 7 columns, got 6)\nRow 17: Incomplete data (expected 7 columns, got 6)\nRow 18: Incomplete data (expected 7 columns, got 6)\nRow 19: Incomplete data (expected 7 columns, got 6)\nRow 20: Incomplete data (expected 7 columns, got 6)\nRow 21: Incomplete data (expected 7 columns, got 6)\nRow 22: Incomplete data (expected 7 columns, got 6)\nRow 23: Incomplete data (expected 7 columns, got 6)\nRow 24: Incomplete data (expected 7 columns, got 6)\nRow 25: Incomplete data (expected 7 columns, got 6)\nRow 26: Incomplete data (expected 7 columns, got 6)\nRow 27: Incomplete data (expected 7 columns, got 6)\nRow 28: Incomplete data (expected 7 columns, got 6)\nRow 29: Incomplete data (expected 7 columns, got 6)\nRow 30: Incomplete data (expected 7 columns, got 6)\nRow 31: Incomplete data (expected 7 columns, got 6)\nRow 32: Incomplete data (expected 7 columns, got 6)\nRow 33: Incomplete data (expected 7 columns, got 6)\nRow 34: Incomplete data (expected 7 columns, got 6)\nRow 35: Incomplete data (expected 7 columns, got 6)\nRow 36: Incomplete data (expected 7 columns, got 6)\nRow 37: Incomplete data (expected 7 columns, got 6)\nRow 38: Incomplete data (expected 7 columns, got 6)\nRow 39: Incomplete data (expected 7 columns, got 6)\nRow 40: Incomplete data (expected 7 columns, got 6)\nRow 41: Incomplete data (expected 7 columns, got 6)\nRow 42: Incomplete data (expected 7 columns, got 6)\nRow 43: Incomplete data (expected 7 columns, got 6)\nRow 44: Incomplete data (expected 7 columns, got 6)\nRow 45: Incomplete data (expected 7 columns, got 6)\nRow 46: Incomplete data (expected 7 columns, got 6)\nRow 47: Incomplete data (expected 7 columns, got 6)\nRow 48: Incomplete data (expected 7 columns, got 6)\nRow 49: Incomplete data (expected 7 columns, got 6)\nRow 50: Incomplete data (expected 7 columns, got 6)\nRow 51: Incomplete data (expected 7 columns, got 6)\nRow 52: Incomplete data (expected 7 columns, got 6)\nRow 53: Incomplete data (expected 7 columns, got 6)\nRow 54: Incomplete data (expected 7 columns, got 6)\nRow 55: Incomplete data (expected 7 columns, got 6)\nRow 56: Incomplete data (expected 7 columns, got 6)\nRow 57: Incomplete data (expected 7 columns, got 6)\nRow 58: Incomplete data (expected 7 columns, got 6)\nRow 59: Incomplete data (expected 7 columns, got 6)\nRow 60: Incomplete data (expected 7 columns, got 6)\nRow 61: Incomplete data (expected 7 columns, got 6)\nRow 62: Incomplete data (expected 7 columns, got 6)\nRow 63: Incomplete data (expected 7 columns, got 6)\nRow 64: Incomplete data (expected 7 columns, got 6)\nRow 65: Incomplete data (expected 7 columns, got 6)\nRow 66: Incomplete data (expected 7 columns, got 6)\nRow 67: Incomplete data (expected 7 columns, got 6)\nRow 68: Incomplete data (expected 7 columns, got 6)\nRow 69: Incomplete data (expected 7 columns, got 6)\nRow 70: Incomplete data (expected 7 columns, got 6)\nRow 71: Incomplete data (expected 7 columns, got 6)\nRow 72: Incomplete data (expected 7 columns, got 6)\nRow 73: Incomplete data (expected 7 columns, got 6)\nRow 74: Incomplete data (expected 7 columns, got 6)\nRow 75: Incomplete data (expected 7 columns, got 6)\nRow 76: Incomplete data (expected 7 columns, got 6)\nRow 77: Incomplete data (expected 7 columns, got 6)\nRow 78: Incomplete data (expected 7 columns, got 6)\nRow 79: Incomplete data (expected 7 columns, got 6)\nRow 80: Incomplete data (expected 7 columns, got 6)\nRow 81: Incomplete data (expected 7 columns, got 6)\nRow 82: Incomplete data (expected 7 columns, got 6)\nRow 83: Incomplete data (expected 7 columns, got 6)\nRow 84: Incomplete data (expected 7 columns, got 6)\nRow 85: Incomplete data (expected 7 columns, got 6)\nRow 86: Incomplete data (expected 7 columns, got 6)\nRow 87: Incomplete data (expected 7 columns, got 6)\nRow 88: Incomplete data (expected 7 columns, got 6)\nRow 89: Incomplete data (expected 7 columns, got 6)\nRow 90: Incomplete data (expected 7 columns, got 6)\nRow 91: Incomplete data (expected 7 columns, got 6)\nRow 92: Incomplete data (expected 7 columns, got 6)\nRow 93: Incomplete data (expected 7 columns, got 6)\nRow 94: Incomplete data (expected 7 columns, got 6)\nRow 95: Incomplete data (expected 7 columns, got 6)\nRow 96: Incomplete data (expected 7 columns, got 6)\nRow 97: Incomplete data (expected 7 columns, got 6)\nRow 98: Incomplete data (expected 7 columns, got 6)\nRow 99: Incomplete data (expected 7 columns, got 6)\nRow 100: Incomplete data (expected 7 columns, got 6)\nRow 101: Incomplete data (expected 7 columns, got 6)\nRow 102: Incomplete data (expected 7 columns, got 6)\nRow 103: Incomplete data (expected 7 columns, got 6)\nRow 104: Incomplete data (expected 7 columns, got 6)\nRow 105: Incomplete data (expected 7 columns, got 6)\nRow 106: Incomplete data (expected 7 columns, got 6)\nRow 107: Incomplete data (expected 7 columns, got 6)\nRow 108: Incomplete data (expected 7 columns, got 6)\nRow 109: Incomplete data (expected 7 columns, got 6)\nRow 110: Incomplete data (expected 7 columns, got 6)\nRow 111: Incomplete data (expected 7 columns, got 6)\nRow 112: Incomplete data (expected 7 columns, got 6)\nRow 113: Incomplete data (expected 7 columns, got 6)\nRow 114: Incomplete data (expected 7 columns, got 6)\nRow 115: Incomplete data (expected 7 columns, got 6)\nRow 116: Incomplete data (expected 7 columns, got 6)\nRow 117: Incomplete data (expected 7 columns, got 6)\nRow 118: Incomplete data (expected 7 columns, got 6)\nRow 119: Incomplete data (expected 7 columns, got 6)\nRow 120: Incomplete data (expected 7 columns, got 6)\nRow 121: Incomplete data (expected 7 columns, got 6)\nRow 122: Incomplete data (expected 7 columns, got 6)\nRow 123: Incomplete data (expected 7 columns, got 6)\nRow 124: Incomplete data (expected 7 columns, got 6)', '2025-11-24 06:29:02'),
(2, 1, 'courses', 'Final_Combined_CPE_Curriculum.csv', 103, 103, 0, '', '2025-11-24 06:31:50'),
(3, 1, 'students', 'students.csv', 30, 30, 0, '', '2025-11-24 14:46:27'),
(4, 1, 'professors', 'professors.csv', 10, 10, 0, '', '2025-11-24 14:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `course_catalog`
--

CREATE TABLE `course_catalog` (
  `id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(200) NOT NULL,
  `units` int(11) NOT NULL,
  `program` varchar(100) NOT NULL,
  `term` varchar(20) NOT NULL,
  `course_type` enum('major','minor','elective','general_education') DEFAULT 'major',
  `prerequisites` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_catalog`
--

INSERT INTO `course_catalog` (`id`, `course_code`, `course_name`, `units`, `program`, `term`, `course_type`, `prerequisites`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'FNDMATH', 'Foundation in Math (FOUN)', 5, '0', 'Term 1', 'major', '', 1, '2025-11-24 04:45:26', '2025-11-24 06:31:50'),
(2, 'PROLOGI', 'Programming Logic and Design Lecture (1E)', 2, '0', 'Term 2', 'major', '', 1, '2025-11-24 04:45:26', '2025-11-24 06:31:50'),
(3, 'LBYCPA1', 'Programming Logic and Design Laboratory (1E)', 2, '0', 'Term 2', 'major', 'PROLOGI (C)', 1, '2025-11-24 04:45:26', '2025-11-24 06:31:50'),
(4, 'CALENG1', 'Differential Calculus (1A)', 3, '0', 'Term 2', 'major', 'FNDMATH (H)', 1, '2025-11-24 04:45:26', '2025-11-24 06:31:50'),
(5, 'CSSWENG', 'Software Engineering', 3, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 04:45:26', '2025-11-24 04:45:26'),
(6, 'CSALGCM', 'Design and Analysis of Algorithms', 3, 'BS Computer Engineering', 'Term 2', 'major', 'PROLOGI(H)', 1, '2025-11-24 04:45:26', '2025-11-24 04:45:26'),
(8, 'CSNETWK', 'Computer Networks', 3, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 04:45:26', '2025-11-24 04:45:26'),
(9, 'CSARCH2', 'Computer Architecture 2', 3, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 04:45:26', '2025-11-24 04:45:26'),
(10, 'REMETHS', 'Methods of Research for CpE (1E)', 3, '0', 'Term 8', 'major', 'ENGDATA/ GEPCOMM/ LOGDSGN (H/H/H)', 1, '2025-11-24 04:45:26', '2025-11-24 06:31:50'),
(11, 'DSIGPRO', 'Digital Signal Processing Lecture (1E)', 3, '0', 'Term 9', 'major', 'FDCNSYS/ EMBDSYS (H/S)', 1, '2025-11-24 04:45:26', '2025-11-24 06:31:50'),
(12, 'CSINPRO', 'Internship Program', 3, 'BS Computer Engineering', 'Term 12', 'major', '', 1, '2025-11-24 04:45:26', '2025-11-24 04:45:26'),
(13, 'LCC..01', 'Lasallian Core Curriculum (Placeholder 01)', 3, 'BS Computer Engineering', 'Term 1', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(14, 'NSTP101', 'National Service Training Program-General Orientation', 0, 'BS Computer Engineering', 'Term 1', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(16, 'BASCHEM', 'Basic Chemistry', 3, 'BS Computer Engineering', 'Term 1', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(17, 'BASPHYS', 'Basic Physics', 3, 'BS Computer Engineering', 'Term 1', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(18, 'FNDSTAT', 'Foundation in Statistics (FOUN)', 3, 'BS Computer Engineering', 'Term 1', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(21, 'COEDISC', 'Computer Engineering as a Discipline (1E)', 1, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(23, 'LCC..02', 'Lasallian Core Curriculum (Placeholder 02)', 3, 'BS Computer Engineering', 'Term 2', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(24, 'NSTPCW1', 'National Service Training Program 1 (2D)', -3, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(25, 'LBYEC2A', 'Computer Fundamentals and Programming 1', 1, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(26, 'LCC.. 04', 'Lasallian Core Curriculum (Placeholder 04)', 3, 'BS Computer Engineering', 'Term 2', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(27, 'LCC..03', 'Lasallian Core Curriculum (Placeholder 03)', 3, 'BS Computer Engineering', 'Term 2', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(28, 'LCC..06', 'Lasallian Core Curriculum (Placeholder 06)', 3, 'BS Computer Engineering', 'Term 3', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(29, 'LBYCPEI', 'Object Oriented Programming Laboratory (1E)', 2, 'BS Computer Engineering', 'Term 3', 'major', 'LBYCPA1 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(30, 'LCC..05', 'Lasallian Core Curriculum (Placeholder 05)', 3, 'BS Computer Engineering', 'Term 3', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(31, 'LCC..07', 'Lasallian Core Curriculum (Placeholder 07)', 3, 'BS Computer Engineering', 'Term 3', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(32, 'LBYEC2B', 'Computer Fundamentals and Programming 2', 1, 'BS Computer Engineering', 'Term 3', 'major', 'LBYEC2A (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(33, 'LBYPH1A', 'Physics for Engineers Laboratory (1B)', 1, 'BS Computer Engineering', 'Term 3', 'major', 'ENGPHYS (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(34, 'CALENG2', 'Integral Calculus (1A)', 3, 'BS Computer Engineering', 'Term 3', 'major', 'CALENG1 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(35, 'LASARE1', 'Lasallian Recollection 1', 0, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(36, 'SAS1000', 'Students Affairs Service 1000 (LS)', 0, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(37, 'LCLSONE', 'Lasallian Studies 1', -1, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(38, 'NSTPCW2', 'National Service Training Program 2 (2D)', -3, 'BS Computer Engineering', 'Term 3', 'major', 'NSTPCW1 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(39, 'ENGPHYS', 'Physics for Engineers (1B)', 3, 'BS Computer Engineering', 'Term 3', 'major', 'CALENG1 / BASPHYS (S / H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(40, 'PE1CRDO', 'Cardio Fitness', 2, 'BS Computer Engineering', 'Term 4', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(41, 'LBYCH1A', 'Chemistry for Engineers Laboratory (1B)', 1, 'BS Computer Engineering', 'Term 4', 'major', 'ENGCHEM (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(42, 'ENGCHEM', 'Chemistry for Engineers (1B)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'BASCHEM (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(43, 'FUNDCKT', 'Fundamentals of Electrical Circuits Lecture (1D)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'ENGPHYS (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(44, 'DISCRMT', 'Discrete Mathematics (1E)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'CALENG1 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(45, 'LBYEC2M', 'Fundamentals of Electrical Circuits Lab (1D)', 1, 'BS Computer Engineering', 'Term 4', 'major', 'FUNDCKT (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(46, 'DATSRAL', 'Data Structures and Algorithms Lecture (1E)', 1, 'BS Computer Engineering', 'Term 4', 'major', 'LBYCPEI (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(47, 'CALENG3', 'Differential Equations (1A)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'CALENG2 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(48, 'LBYCPA2', 'Data Structures and Algorithms Laboratory (1E)', 2, 'BS Computer Engineering', 'Term 4', 'major', 'DATSRAL (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(49, 'SAS2000', 'Student Affairs Series 2', 0, 'BS Computer Engineering', 'Term 5', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(50, 'ENGDATA', 'Engineering Data Analysis (1A)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'CALENG2/ FNDSTAT (S / H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(51, 'NUMMETS', 'Numerical Methods (1E)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'CALENG3 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(52, 'FUNDLEC', 'Fundamentals of Electronic Circuits Lecture (1D)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'FUNDCKT (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(53, 'LBYCPC2', 'Fundamentals of Electronic Circuits Laboratory (1D)', 1, 'BS Computer Engineering', 'Term 5', 'major', 'FUNDLEC (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(54, 'SOFDESG', 'Software Design Lecture (1E)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'LBYCPA2 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(55, 'LBYCPD2', 'Software Design Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 5', 'major', 'SOFDESG (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(56, 'ENGENVI', 'Environmental Science and Engineering', 3, 'BS Computer Engineering', 'Term 5', 'major', 'ENGCHEM (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(57, 'PE2FTEX', 'Functional Exercise', 2, 'BS Computer Engineering', 'Term 5', 'general_education', 'PE1CRDO (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(58, 'PETHREE', 'Generic Code', 2, 'BS Computer Engineering', 'Term 6', 'general_education', 'PE1 / PE2 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(59, 'LCC..08', 'Lasallian Core Curriculum (Placeholder 08)', 3, 'BS Computer Engineering', 'Term 6', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(60, 'LBYME1C', 'Computer-Aided Drafting (CAD) for ECE and CpE (1C)', 1, 'BS Computer Engineering', 'Term 6', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(61, 'LBYCPC3', 'Feedback and Control System Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 6', 'major', 'FDCNSYS (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(62, 'FDCNSYS', 'Feedback and Control Systems (1E)', 3, 'BS Computer Engineering', 'Term 6', 'major', 'NUMMETS/FUNDCKT (H/H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(63, 'LBYCPG4', 'Logic Circuits and Design Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 6', 'major', 'LOGDSGN (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(64, 'LOGDSGN', 'Logic Circuits and Design Lecture (1E)', 3, 'BS Computer Engineering', 'Term 6', 'major', 'FUNDLEC (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(65, 'MXSIGFN', 'Fundamentals of Mixed Signals and Sensors (1E)', 3, 'BS Computer Engineering', 'Term 6', 'major', 'FUNDLEC (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(66, 'LASARE2', 'Lasallian Recollection 2', 0, 'BS Computer Engineering', 'Term 6', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(67, 'LCLSTWO', 'Lasallian Studies 2', -1, 'BS Computer Engineering', 'Term 6', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(68, 'LBYCPG2', 'Basic Computer Systems Administration', 1, 'BS Computer Engineering', 'Term 7', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(69, 'PEDFOUR', 'Generic Code', 2, 'BS Computer Engineering', 'Term 7', 'general_education', 'PE1/PE2/PE3 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(70, 'DIGDACM', 'Data and Digital Communications (1E)', 3, 'BS Computer Engineering', 'Term 7', 'major', 'FUNDLEC (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(71, 'LBYCPF2', 'Introduction to HDL Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 7', 'major', 'LBYCPA1/FUNDLEC (H/H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(72, 'LBYEC3B', 'Intelligent Systems for Engineering', 1, 'BS Computer Engineering', 'Term 7', 'major', 'LBYEC2A/ ENGDATA (H/H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(73, 'LBYCPB3', 'Computer Engineering Drafting and Design Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 7', 'major', 'LOGDSGN (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(74, 'LCC..09', 'Lasallian Core Curriculum (Placeholder 09)', 3, 'BS Computer Engineering', 'Term 7', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(75, 'MICPROS', 'Microprocessors Lecture (1E)', 3, 'BS Computer Engineering', 'Term 7', 'major', 'LOGDSGN (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(76, 'LBYCPA3', 'Microprocessors Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 7', 'major', 'MICPROS (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(77, 'LBYCPG3', 'Online Technologies Laboratory', 1, 'BS Computer Engineering', 'Term 8', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(78, 'LCC..10', 'Lasallian Core Curriculum (Placeholder 10)', 3, 'BS Computer Engineering', 'Term 8', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(80, 'OPESSYS', 'Operating Systems Lec (1E)', 3, 'BS Computer Engineering', 'Term 8', 'major', 'LBYCPA2 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(81, 'LBYCPM3', 'Embedded Systems Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 8', 'major', 'EMBDSYS (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(82, 'LBYCPO1', 'Operating Systems Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 8', 'major', 'OPESSYS (c)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(83, 'LBYCPD3', 'Computer Architecture and Organization Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 8', 'major', 'CSYSARC (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(84, 'CSYSARC', 'Computer Architecture and Organization Lecture (1E)', 3, 'BS Computer Engineering', 'Term 8', 'major', 'MICPROS (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(85, 'EMBDSYS', 'Embedded Systems Lecture (1E)', 3, 'BS Computer Engineering', 'Term 8', 'major', 'MICPROS (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(86, 'LBYCPF3', 'CpE Elective 1 Laboratory (1F)', 1, 'BS Computer Engineering', 'Term 9', 'major', 'CPECOG1 (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(87, 'CPECOG1', 'CpE Elective 1 Lecture (1F)', 2, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS/THSCP4A (H/C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(88, 'CPEPRAC', 'CpE Laws and Professional Practice (1E)', 2, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(89, 'THSCP4A', 'CpE Practice and Design 1 (1E)', 1, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS/ REMETHS (H/H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(90, 'OCHESAF', 'Basic Occupational Health and Safety (1E)', 3, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(91, 'LBYCPA4', 'Digital Signal Processing Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 9', 'major', 'DSIGPRO (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(92, 'LASARE3', 'Lasallian Recollection 3', 0, 'BS Computer Engineering', 'Term 9', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(93, 'LCC..11', 'Lasallian Core Curriculum (Placeholder 11)', 3, 'BS Computer Engineering', 'Term 9', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(94, 'LCLSTRI', 'Lasallian Studies 3', -1, 'BS Computer Engineering', 'Term 9', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(96, 'SAS3000', 'Student Affairs Series 3', 0, 'BS Computer Engineering', 'Term 10', 'major', 'SAS2000 (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(97, 'LBYCPH3', 'CpE Elective 2 Laboratory (1F)', 1, 'BS Computer Engineering', 'Term 10', 'major', 'CPECOG2 (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(98, 'CPECOG2', 'CpE Elective 2 Lecture (1F)', 2, 'BS Computer Engineering', 'Term 10', 'major', 'THSCP4A (S)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(99, 'CPECAPS', 'Operational Technologies', 1, 'BS Computer Engineering', 'Term 10', 'major', 'LBYCPH3/ LBYCPB4 (C/C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(100, 'LBYCPB4', 'Computer Networks and Security Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 10', 'major', 'CONETSC (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(101, 'ENGTREP', 'Technopreneurship 101 (1C)', 3, 'BS Computer Engineering', 'Term 10', 'major', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(102, 'CONETSC', 'Computer Networks and Security Lecture (1E)', 3, 'BS Computer Engineering', 'Term 10', 'major', 'DIGDACM (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(103, 'EMERTEC', 'Emerging Technologies in CpE (1E)', 3, 'BS Computer Engineering', 'Term 10', 'major', 'EMBDSYS (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(104, 'LCC..12', 'Lasallian Core Curriculum (Placeholder 12)', 3, 'BS Computer Engineering', 'Term 10', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(105, 'THSCP4B', 'CpE Practice and Design 2 (1E)', 1, 'BS Computer Engineering', 'Term 10', 'major', 'THSCP4A (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(106, 'PRCGECP', 'Practicum for CpE (1E)', 3, 'BS Computer Engineering', 'Term 11', 'major', 'REMETHS (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(107, 'ENGMANA', 'Engineering Management', 2, 'BS Computer Engineering', 'Term 12', 'major', 'CALENG1 (S)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(108, 'LCC..15', 'Lasallian Core Curriculum (Placeholder 15)', 3, 'BS Computer Engineering', 'Term 12', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(109, 'ECNOMIC', 'Engineering Economics for CpE (1C)', 3, 'BS Computer Engineering', 'Term 12', 'major', 'CALENG1 (S)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(110, 'LBYCPC4', 'CPE Elective 3 Laboratory (1F)', 1, 'BS Computer Engineering', 'Term 12', 'major', 'CPECOG3 (C)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(111, 'CPECOG3', 'CpE Elective 3 Lecture (1F)', 2, 'BS Computer Engineering', 'Term 12', 'major', 'THSCP4A (S)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(112, 'THSCP4C', 'CpE Practice and Design 3 (1E)', 1, 'BS Computer Engineering', 'Term 12', 'major', 'THSCP4B (H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(113, 'LCC..14', 'Lasallian Core Curriculum (Placeholder 14)', 3, 'BS Computer Engineering', 'Term 12', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(114, 'LCC..13', 'Lasallian Core Curriculum (Placeholder 13)', 3, 'BS Computer Engineering', 'Term 12', 'general_education', '', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50'),
(115, 'CPETRIP', 'Seminars and Field Trips for CpE (1E)', 1, 'BS Computer Engineering', 'Term 12', 'major', 'EMBDSYS/CPECAPS (H/H)', 1, '2025-11-24 06:31:50', '2025-11-24 06:31:50');

-- --------------------------------------------------------

--
-- Table structure for table `course_prerequisites`
--

CREATE TABLE `course_prerequisites` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `prerequisite_course_code` varchar(20) NOT NULL,
  `prerequisite_type` enum('hard','soft','co-requisite') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `current_subjects`
--

CREATE TABLE `current_subjects` (
  `id` int(11) NOT NULL,
  `study_plan_id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(200) DEFAULT NULL,
  `units` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `current_subject_prerequisites`
--

CREATE TABLE `current_subject_prerequisites` (
  `id` int(11) NOT NULL,
  `current_subject_id` int(11) NOT NULL,
  `prerequisite_code` varchar(20) NOT NULL,
  `prerequisite_type` enum('hard','soft','co-requisite') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `from_professor_id` int(11) NOT NULL,
  `to_student_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `send_immediately` tinyint(1) DEFAULT 1,
  `scheduled_send_time` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `planned_subjects`
--

CREATE TABLE `planned_subjects` (
  `id` int(11) NOT NULL,
  `study_plan_id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(200) DEFAULT NULL,
  `units` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `planned_subjects`
--

INSERT INTO `planned_subjects` (`id`, `study_plan_id`, `subject_code`, `subject_name`, `units`) VALUES
(1, 1, 'COEDISC', 'Computer Engineering as a Discipline (1E)', 1),
(2, 1, 'LBYEC2A', 'Computer Fundamentals and Programming 1', 1),
(3, 1, 'LCC..03', 'Lasallian Core Curriculum (Placeholder 03)', 3),
(4, 1, 'LCC..02', 'Lasallian Core Curriculum (Placeholder 02)', 3),
(5, 1, 'LCC.. 04', 'Lasallian Core Curriculum (Placeholder 04)', 3);

-- --------------------------------------------------------

--
-- Table structure for table `planned_subject_prerequisites`
--

CREATE TABLE `planned_subject_prerequisites` (
  `id` int(11) NOT NULL,
  `planned_subject_id` int(11) NOT NULL,
  `prerequisite_code` varchar(20) NOT NULL,
  `prerequisite_type` enum('hard','soft','co-requisite') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `professors`
--

CREATE TABLE `professors` (
  `id` int(11) NOT NULL,
  `id_number` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `must_change_password` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `professors`
--

INSERT INTO `professors` (`id`, `id_number`, `first_name`, `middle_name`, `last_name`, `department`, `email`, `must_change_password`, `created_at`) VALUES
(3, 10012345, 'Maria', 'Santos', 'Garcia', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'maria.garcia@dlsu.edu.ph', 0, '2025-11-24 04:45:26'),
(35, 20010001, 'Ricardo', 'Alfonso', 'Dela Cruz', 'GCOE-DECE', 'ricardo.delacruz@dlsu.edu.ph', 1, '2025-11-24 14:46:34'),
(36, 20010002, 'Maria', 'Teresa', 'Santos', 'GCOE-DECE', 'maria.santos@dlsu.edu.ph', 1, '2025-11-24 14:46:34'),
(37, 20010003, 'John', 'Peter', 'Lim', 'GCOE-DECE', 'john.lim@dlsu.edu.ph', 1, '2025-11-24 14:46:34'),
(38, 20010004, 'Emmanuel', 'Jose', 'Reyes', 'GCOE-DECE', 'emmanuel.reyes@dlsu.edu.ph', 1, '2025-11-24 14:46:34'),
(39, 20010005, 'Catherine', 'Anne', 'Garcia', 'GCOE-DECE', 'catherine.garcia@dlsu.edu.ph', 1, '2025-11-24 14:46:34'),
(40, 20010006, 'Francis', 'Benedict', 'Tan', 'GCOE-DECE', 'francis.tan@dlsu.edu.ph', 1, '2025-11-24 14:46:34'),
(41, 20010007, 'Jennifer', 'Louise', 'Mendoza', 'GCOE-DECE', 'jennifer.mendoza@dlsu.edu.ph', 1, '2025-11-24 14:46:35'),
(42, 20010008, 'Paolo', 'Gabriel', 'Torres', 'GCOE-DECE', 'paolo.torres@dlsu.edu.ph', 1, '2025-11-24 14:46:35'),
(43, 20010009, 'Sarah', 'Elizabeth', 'Yap', 'GCOE-DECE', 'sarah.yap@dlsu.edu.ph', 1, '2025-11-24 14:46:35'),
(44, 20010010, 'Vincent', 'Ray', 'Bautista', 'GCOE-DECE', 'vincent.bautista@dlsu.edu.ph', 1, '2025-11-24 14:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `program_profiles`
--

CREATE TABLE `program_profiles` (
  `id` int(11) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `total_units` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(100) NOT NULL DEFAULT 'The Department of Electronics, Computer, and Electrical Engineering (DECE)',
  `max_failed_units` int(11) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program_profiles`
--

INSERT INTO `program_profiles` (`id`, `program_name`, `program_code`, `total_units`, `description`, `department`, `max_failed_units`, `created_at`, `updated_at`) VALUES
(1, 'BS Computer Engineering', 'BSCpE', 180, 'Bachelor of Science in Computer Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 30, '2025-11-24 04:45:26', '2025-11-24 04:45:26'),
(2, 'BS Electronics and Communications Engineering', 'BSECE', 180, 'Bachelor of Science in Electronics and Communications Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 30, '2025-11-24 04:45:26', '2025-11-24 04:45:26'),
(3, 'BS Electrical Engineering', 'BSEE', 180, 'Bachelor of Science in Electrical Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 30, '2025-11-24 04:45:26', '2025-11-24 04:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `id_number` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `college` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `program` varchar(100) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `parent_guardian_name` varchar(200) NOT NULL,
  `parent_guardian_number` varchar(20) NOT NULL,
  `advisor_id` int(11) DEFAULT NULL,
  `advising_cleared` tinyint(1) DEFAULT 0,
  `accumulated_failed_units` int(11) DEFAULT 0,
  `must_change_password` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `id_number`, `first_name`, `middle_name`, `last_name`, `college`, `department`, `program`, `specialization`, `phone_number`, `email`, `parent_guardian_name`, `parent_guardian_number`, `advisor_id`, `advising_cleared`, `accumulated_failed_units`, `must_change_password`, `created_at`) VALUES
(2, 12012345, 'Juan', 'Santos', 'Dela Cruz', 'Gokongwei College of Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'BS Computer Engineering', 'N/A', '+63 917 123 4567', 'juan_delacruz@dlsu.edu.ph', 'Maria Dela Cruz', '+63 918 765 4321', 3, 1, 0, 0, '2025-11-24 04:45:26'),
(4, 12314501, 'Christian James', 'Buensalido', 'Alado', 'Gokongwei College of Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'BS Electronics and Communications Engineering', 'N/A', '+63 928 741 0304', 'christian_alado@dlsu.edu.ph', 'Christy Alado', '+63 908 813 8135', 35, 0, 0, 1, '2025-11-24 14:25:40'),
(5, 12100001, 'Miguel', 'Jose', 'Santos', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 111 2233', 'miguel_santos@dlsu.edu.ph', 'Maria Santos', '+63 918 111 2233', 40, 0, 0, 1, '2025-11-24 14:46:25'),
(6, 12100002, 'Angela', 'Marie', 'Reyes', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 222 3344', 'angela_reyes@dlsu.edu.ph', 'Roberto Reyes', '+63 918 222 3344', 40, 0, 0, 1, '2025-11-24 14:46:25'),
(7, 12100003, 'Carlos', 'Antonio', 'Cruz', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 333 4455', 'carlos_cruz@dlsu.edu.ph', 'Elena Cruz', '+63 918 333 4455', 41, 0, 0, 1, '2025-11-24 14:46:25'),
(8, 12100004, 'Sofia', 'Grace', 'Dizon', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 444 5566', 'sofia_dizon@dlsu.edu.ph', 'Ricardo Dizon', '+63 918 444 5566', 37, 0, 0, 1, '2025-11-24 14:46:25'),
(9, 12100005, 'Gabriel', 'Luis', 'Mendoza', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 555 6677', 'gabriel_mendoza@dlsu.edu.ph', 'Patricia Mendoza', '+63 918 555 6677', 37, 0, 0, 1, '2025-11-24 14:46:25'),
(10, 12100006, 'Hannah', 'Rose', 'Garcia', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 666 7788', 'hannah_garcia@dlsu.edu.ph', 'Fernando Garcia', '+63 918 666 7788', 37, 0, 0, 1, '2025-11-24 14:46:25'),
(11, 12100007, 'Rafael', 'Paolo', 'Bautista', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 777 8899', 'rafael_bautista@dlsu.edu.ph', 'Theresa Bautista', '+63 918 777 8899', 41, 0, 0, 1, '2025-11-24 14:46:25'),
(12, 12100008, 'Julia', 'Nicole', 'Torres', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 888 9900', 'julia_torres@dlsu.edu.ph', 'Manuel Torres', '+63 918 888 9900', 41, 0, 0, 1, '2025-11-24 14:46:25'),
(13, 12100009, 'Ethan', 'James', 'Lopez', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 999 0011', 'ethan_lopez@dlsu.edu.ph', 'Carmen Lopez', '+63 918 999 0011', 42, 0, 0, 1, '2025-11-24 14:46:25'),
(14, 12100010, 'Isabella', 'Mae', 'Gonzales', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 000 1122', 'isabella_gonzales@dlsu.edu.ph', 'Jose Gonzales', '+63 918 000 1122', 42, 0, 0, 1, '2025-11-24 14:46:25'),
(15, 12100011, 'Liam', 'Alexander', 'Ramos', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 123 4567', 'liam_ramos@dlsu.edu.ph', 'Susan Ramos', '+63 918 123 4567', 43, 0, 0, 1, '2025-11-24 14:46:26'),
(16, 12100012, 'Ava', 'Victoria', 'Flores', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 234 5678', 'ava_flores@dlsu.edu.ph', 'Ramon Flores', '+63 918 234 5678', 43, 0, 0, 1, '2025-11-24 14:46:26'),
(17, 12100013, 'Noah', 'Elijah', 'Castillo', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 345 6789', 'noah_castillo@dlsu.edu.ph', 'Lydia Castillo', '+63 918 345 6789', 43, 0, 0, 1, '2025-11-24 14:46:26'),
(18, 12100014, 'Mia', 'Elizabeth', 'Villanueva', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 456 7890', 'mia_villanueva@dlsu.edu.ph', 'Antonio Villanueva', '+63 918 456 7890', 43, 0, 0, 1, '2025-11-24 14:46:26'),
(19, 12100015, 'Lucas', 'Daniel', 'Rivera', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 567 8901', 'lucas_rivera@dlsu.edu.ph', 'Cecilia Rivera', '+63 918 567 8901', 44, 0, 0, 1, '2025-11-24 14:46:26'),
(20, 12100016, 'Chloe', 'Sophia', 'Aquino', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 678 9012', 'chloe_aquino@dlsu.edu.ph', 'Eduardo Aquino', '+63 918 678 9012', 44, 0, 0, 1, '2025-11-24 14:46:26'),
(21, 12100017, 'Mason', 'Caleb', 'Pineda', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 789 0123', 'mason_pineda@dlsu.edu.ph', 'Rosario Pineda', '+63 918 789 0123', 44, 0, 0, 1, '2025-11-24 14:46:26'),
(22, 12100018, 'Zoe', 'Isabelle', 'Tan', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 890 1234', 'zoe_tan@dlsu.edu.ph', 'Benjamin Tan', '+63 918 890 1234', 44, 0, 0, 1, '2025-11-24 14:46:26'),
(23, 12100019, 'Aiden', 'Matthew', 'Lim', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 901 2345', 'aiden_lim@dlsu.edu.ph', 'Grace Lim', '+63 918 901 2345', 44, 0, 0, 1, '2025-11-24 14:46:26'),
(24, 12100020, 'Ella', 'Madison', 'Chua', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 012 3456', 'ella_chua@dlsu.edu.ph', 'Peter Chua', '+63 918 012 3456', 3, 0, 0, 1, '2025-11-24 14:46:26'),
(25, 12100021, 'Jacob', 'Ryan', 'Pascual', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 123 5678', 'jacob_pascual@dlsu.edu.ph', 'Margarita Pascual', '+63 918 123 5678', 39, 0, 0, 1, '2025-11-24 14:46:26'),
(26, 12100022, 'Lily', 'Samantha', 'De Leon', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 234 6789', 'lily_deleon@dlsu.edu.ph', 'Francisco De Leon', '+63 918 234 6789', 39, 0, 0, 1, '2025-11-24 14:46:26'),
(27, 12100023, 'Logan', 'Andrew', 'Malig', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 345 7890', 'logan_malig@dlsu.edu.ph', 'Catherine Malig', '+63 918 345 7890', 38, 0, 0, 1, '2025-11-24 14:46:26'),
(28, 12100024, 'Grace', 'Olivia', 'Mercado', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 456 8901', 'grace_mercado@dlsu.edu.ph', 'Alberto Mercado', '+63 918 456 8901', 38, 0, 0, 1, '2025-11-24 14:46:26'),
(29, 12100025, 'Jack', 'Henry', 'Salazar', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 567 9012', 'jack_salazar@dlsu.edu.ph', 'Teresa Salazar', '+63 918 567 9012', 36, 0, 0, 1, '2025-11-24 14:46:26'),
(30, 12100026, 'Amelia', 'Claire', 'Aguilar', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 678 0123', 'amelia_aguilar@dlsu.edu.ph', 'Danilo Aguilar', '+63 918 678 0123', 36, 0, 0, 1, '2025-11-24 14:46:26'),
(31, 12100027, 'William', 'Isaac', 'Delos Santos', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 789 1234', 'william_delossantos@dlsu.edu.ph', 'Marianne Delos Santos', '+63 918 789 1234', 35, 0, 0, 1, '2025-11-24 14:46:26'),
(32, 12100028, 'Harper', 'Faith', 'Ferrer', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Computer Engineering', 'N/A', '+63 917 890 2345', 'harper_ferrer@dlsu.edu.ph', 'Gregorio Ferrer', '+63 918 890 2345', 35, 0, 0, 1, '2025-11-24 14:46:26'),
(33, 12100029, 'James', 'Owen', 'Magno', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electronics and Communications Engineering', 'N/A', '+63 917 901 3456', 'james_magno@dlsu.edu.ph', 'Jocelyn Magno', '+63 918 901 3456', 39, 0, 0, 1, '2025-11-24 14:46:27'),
(34, 12100030, 'Emily', 'Rose', 'Domingo', 'Gokongwei College of Engineering', 'GCOE-DECE', 'BS Electrical Engineering', 'N/A', '+63 917 012 4567', 'emily_domingo@dlsu.edu.ph', 'Alfredo Domingo', '+63 918 012 4567', 3, 0, 0, 1, '2025-11-24 14:46:27');

-- --------------------------------------------------------

--
-- Table structure for table `student_advising_booklet`
--

CREATE TABLE `student_advising_booklet` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `term` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(200) DEFAULT NULL,
  `units` int(11) NOT NULL,
  `grade` decimal(3,2) DEFAULT NULL,
  `is_failed` tinyint(1) DEFAULT 0,
  `remarks` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modified_by` enum('student','professor','admin') DEFAULT 'admin',
  `approval_status` enum('approved','pending','rejected') DEFAULT 'approved',
  `approval_notes` text DEFAULT NULL,
  `previous_grade` decimal(3,2) DEFAULT NULL,
  `edit_requested_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_concerns`
--

CREATE TABLE `student_concerns` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `study_plan_id` int(11) DEFAULT NULL,
  `term` varchar(50) NOT NULL,
  `concern` text NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `study_plans`
--

CREATE TABLE `study_plans` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `term` varchar(50) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `grade_screenshot` varchar(255) DEFAULT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `certified` tinyint(1) DEFAULT 0,
  `wants_meeting` tinyint(1) DEFAULT 0,
  `selected_schedule_id` int(11) DEFAULT NULL,
  `cleared` tinyint(1) DEFAULT 0,
  `adviser_feedback` text DEFAULT NULL,
  `screenshot_reupload_requested` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reupload_reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `study_plans`
--

INSERT INTO `study_plans` (`id`, `student_id`, `term`, `academic_year`, `grade_screenshot`, `submission_date`, `certified`, `wants_meeting`, `selected_schedule_id`, `cleared`, `adviser_feedback`, `screenshot_reupload_requested`, `created_at`, `updated_at`, `reupload_reason`, `status`) VALUES
(1, 2, 'Term 2', '2025-2026', NULL, '2025-11-24 06:32:55', 1, 1, NULL, 1, '', 0, '2025-11-24 06:32:55', '2025-11-24 06:42:00', NULL, 'pending');

--
-- Triggers `study_plans`
--
DELIMITER $$
CREATE TRIGGER `after_study_plan_cleared` AFTER UPDATE ON `study_plans` FOR EACH ROW BEGIN
    -- If plan is newly cleared
    IF NEW.cleared = 1 AND OLD.cleared = 0 THEN
        -- Insert planned subjects into booklet (current term courses)
        INSERT INTO student_advising_booklet 
            (student_id, academic_year, term, course_code, course_name, units, grade, is_failed, remarks, modified_by, approval_status)
        SELECT 
            NEW.student_id,
            NEW.academic_year,
            -- Extract term number from term string (e.g., "Term 1" -> 1)
            CAST(SUBSTRING_INDEX(NEW.term, ' ', -1) AS UNSIGNED),
            ps.subject_code,
            ps.subject_name,
            ps.units,
            NULL, -- Grade initially NULL
            0, -- Not failed initially
            'In Progress',
            'student',
            'approved'
        FROM planned_subjects ps
        WHERE ps.study_plan_id = NEW.id
        ON DUPLICATE KEY UPDATE 
            course_name = VALUES(course_name),
            units = VALUES(units);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `term_gpa_summary`
--

CREATE TABLE `term_gpa_summary` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `term` int(11) NOT NULL,
  `term_gpa` decimal(3,2) DEFAULT NULL,
  `cgpa` decimal(3,2) DEFAULT NULL,
  `total_units_taken` int(11) DEFAULT 0,
  `total_units_passed` int(11) DEFAULT 0,
  `total_units_failed` int(11) DEFAULT 0,
  `accumulated_failed_units` int(11) DEFAULT 0,
  `trimestral_honors` varchar(50) DEFAULT NULL,
  `adviser_signature` varchar(255) DEFAULT NULL,
  `signature_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_login_info`
--

CREATE TABLE `user_login_info` (
  `id` int(11) NOT NULL,
  `id_number` varchar(10) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','professor','student') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_login_info`
--

INSERT INTO `user_login_info` (`id`, `id_number`, `username`, `password`, `user_type`, `created_at`) VALUES
(1, NULL, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-11-24 04:45:26'),
(2, '12012345', '12012345', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-24 04:45:26'),
(3, '10012345', '10012345', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'professor', '2025-11-24 04:45:26'),
(4, '12314501', '12314501', '$2y$10$86sQTHpikfs7SVz.L8afv.t7w3xtjs9rzWoG8jrjDVEP0TaNJbwMK', 'student', '2025-11-24 14:25:40'),
(5, '12100001', '12100001', '$2y$10$FkHjGwQjZNFL/X1AO45buOGIYKGpeqgklMQv/j/CgnymFH.MiBl2K', 'student', '2025-11-24 14:46:25'),
(6, '12100002', '12100002', '$2y$10$8y3ubtdnwWNjNSoipnBPXuPmFLl/aZABsK4/SGUx2jvCYDeenwmQq', 'student', '2025-11-24 14:46:25'),
(7, '12100003', '12100003', '$2y$10$NjE0GGLUKyM939r0WT.dUu3DSneq0ptRH1I4CY6/0W9A7cv6esebe', 'student', '2025-11-24 14:46:25'),
(8, '12100004', '12100004', '$2y$10$KejQGhwZFuw5J4Ujjk7Sg.DtoQjB2Hx//HVJI4J5a9iLHm2Kj67dy', 'student', '2025-11-24 14:46:25'),
(9, '12100005', '12100005', '$2y$10$BxKPmM.MMW88665VZUCKf.9BcAB/auli27VpHwM1f1FEbl.WbhjLC', 'student', '2025-11-24 14:46:25'),
(10, '12100006', '12100006', '$2y$10$w/l.yzJP/nPTuMtpOQqkz.LBTdoPayoniYjhZ6pIuGb6XKe5OhluK', 'student', '2025-11-24 14:46:25'),
(11, '12100007', '12100007', '$2y$10$qqNGpwfRSLcMKkRIslV3zu7DZ9cELPMqZQTSjuUiQ1IVfgFm40bqi', 'student', '2025-11-24 14:46:25'),
(12, '12100008', '12100008', '$2y$10$hyMkGaPnsdwcT5hTu0mVK.GTGbYievJWeEwnvuqItCQCgQWzO5c4O', 'student', '2025-11-24 14:46:25'),
(13, '12100009', '12100009', '$2y$10$xiIlg02roHgl.8JrkY9ao.i.vnBhjALZpoF3.2ojcmhdN4fez2dTu', 'student', '2025-11-24 14:46:25'),
(14, '12100010', '12100010', '$2y$10$x2IH5auGPKzBsoYj6cf2C.OcbCUR/pGRc/RcVkgoWWxfEpsf6rgnm', 'student', '2025-11-24 14:46:25'),
(15, '12100011', '12100011', '$2y$10$WyQYpXfzApWP9MsOLPf4mugi/qaqVmJHeI34JkrWwXa2Tjs65DqIG', 'student', '2025-11-24 14:46:26'),
(16, '12100012', '12100012', '$2y$10$E91Hn0Hw1KAlrJO7bbNXR.pyRQIsKxyTjVr0uhF.1NyTc/udEeK96', 'student', '2025-11-24 14:46:26'),
(17, '12100013', '12100013', '$2y$10$yb6wO..VsFItEJWAVBNVjuYRox9/XvpGBhr4Ch3VnKcnln3hFi1j6', 'student', '2025-11-24 14:46:26'),
(18, '12100014', '12100014', '$2y$10$ccmU6dVlSLRJZFmni6XNt.sodcfvS.izpP.n.c9xh2DzOOOedF.XC', 'student', '2025-11-24 14:46:26'),
(19, '12100015', '12100015', '$2y$10$WljnOydVqGZSDVt..6fy.eGcEMZLDd0bQXKkoLqRAqZiOAfCI7Aou', 'student', '2025-11-24 14:46:26'),
(20, '12100016', '12100016', '$2y$10$NvYU1v.GM4lEA9qePoA4i.OXvCoJI30pGD0jXPFhp5RF9lOVerpY2', 'student', '2025-11-24 14:46:26'),
(21, '12100017', '12100017', '$2y$10$alRI/U.03yHoC5Fvt0yKB.3hXIcFA72iF4capVZTbIBWYS.2RyCwO', 'student', '2025-11-24 14:46:26'),
(22, '12100018', '12100018', '$2y$10$A65N4GhefWQsUT4Vrql6LeP6QilVD5hYN8rdjl/gkqqXxUVtMNm6u', 'student', '2025-11-24 14:46:26'),
(23, '12100019', '12100019', '$2y$10$zRx9mtu1AUQXWe8atPmD9ueLls8kBdJBF5nnQei4APE6VlTj97BM6', 'student', '2025-11-24 14:46:26'),
(24, '12100020', '12100020', '$2y$10$3pD27JmpM6q4A6hg/4McOO/c.PIQfl7/sU9cTIPNw1RU5xdN1zdwO', 'student', '2025-11-24 14:46:26'),
(25, '12100021', '12100021', '$2y$10$C5tW1Bo.wJdvipOcMczxbOAcLoVf4D/RsrcbcgLWKJRTEZxIaib6y', 'student', '2025-11-24 14:46:26'),
(26, '12100022', '12100022', '$2y$10$QViabm2wdzDfj98lcw3kfOYD8m8xKt0oOoRG1BlJBBmDQfWkIckB2', 'student', '2025-11-24 14:46:26'),
(27, '12100023', '12100023', '$2y$10$g6sSGxgbw0FhjZVlIw1RSefnRivMBFbHdluEilOjxduzdBGsmarXa', 'student', '2025-11-24 14:46:26'),
(28, '12100024', '12100024', '$2y$10$VkUlChLqJfVy3OacuEbMReibZ1g8C/3qavkNvr8TEI68AtvuDFAy2', 'student', '2025-11-24 14:46:26'),
(29, '12100025', '12100025', '$2y$10$EMlA4qeBYs/ZWF//W/aiqe19tI2ntl/vRSa.MwE7flM4QGRfjRXMC', 'student', '2025-11-24 14:46:26'),
(30, '12100026', '12100026', '$2y$10$DzoBsMQ/9Qxfu9C7a/1W8e.cRu/R7DhtXoei/jlv0aWL8RFikr6Fy', 'student', '2025-11-24 14:46:26'),
(31, '12100027', '12100027', '$2y$10$ERp5RERooPnVr2Qjc9Mbo.I8xzV/CnDikZvs3oV1tl14ay3dvRMQG', 'student', '2025-11-24 14:46:26'),
(32, '12100028', '12100028', '$2y$10$Sf4Soj/ts7RO.HFi6uOUNugG9ZQiHYqzaUn5RlPLxGpOPgaKBIihy', 'student', '2025-11-24 14:46:26'),
(33, '12100029', '12100029', '$2y$10$.KfuLbqYlmbenXBaYL6kpOIaxhFw2NRqLEZZ9amqZnOQF.WxRMQRi', 'student', '2025-11-24 14:46:27'),
(34, '12100030', '12100030', '$2y$10$YyO4D7Lj0/gGbG5brJ68Cet0F3kguJl7rwjySSJDlOzPuwD92347y', 'student', '2025-11-24 14:46:27'),
(35, '20010001', '20010001', '$2y$10$7ZFIe19nBp1drTPB2a1CreS5JeIFnVMwqkkFhbeYuzsqNYGz.hjAy', 'professor', '2025-11-24 14:46:34'),
(36, '20010002', '20010002', '$2y$10$7MhpUGbvKKOCdc5NAYqBD.4HRY0UQ/v.OC.xnBcAW2aZiXBnlmPRC', 'professor', '2025-11-24 14:46:34'),
(37, '20010003', '20010003', '$2y$10$9Qe2i7gyBRgGcran/5GIr.k9vfyXmnXl/.VZEbpfmhKURJRtBVoAq', 'professor', '2025-11-24 14:46:34'),
(38, '20010004', '20010004', '$2y$10$YOZyVUFn7djtlrPzBIvfpO050qBxn17ZKlPotBGw8xDfpZu5/pSMu', 'professor', '2025-11-24 14:46:34'),
(39, '20010005', '20010005', '$2y$10$gAXMN.ar0hRmi3mKCP8fXePhyAG0ZGx6TDeu/GHcnapfKQtpc1rqm', 'professor', '2025-11-24 14:46:34'),
(40, '20010006', '20010006', '$2y$10$FqISGrLWGE5co.jlZYmhfe2Z5igr.JHkJWvqxcsU8EXxQYGG5FALi', 'professor', '2025-11-24 14:46:34'),
(41, '20010007', '20010007', '$2y$10$3I/vopQjxcx5tuqRANKnhOQwFvvTUGsh2lGmbAyPFzjFyTbn7Rm4K', 'professor', '2025-11-24 14:46:35'),
(42, '20010008', '20010008', '$2y$10$gKr7hO4vwa1CgqV/PfP68.0sulimjHwmPMlbIFpTScoZiJBawJbfS', 'professor', '2025-11-24 14:46:35'),
(43, '20010009', '20010009', '$2y$10$F2Tq/m1xHgfQdbxZvlKw4Obn1m4575Odh61rjXQ6a3RtB01e/nay2', 'professor', '2025-11-24 14:46:35'),
(44, '20010010', '20010010', '$2y$10$7uEk0G4KiN4goJD8Pp4nJOLi9DwHrooTsxeykHiOIAMmMbqESs9fW', 'professor', '2025-11-24 14:46:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `advising_deadlines`
--
ALTER TABLE `advising_deadlines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Indexes for table `advising_schedules`
--
ALTER TABLE `advising_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `booked_by` (`booked_by`);

--
-- Indexes for table `booklet_edit_requests`
--
ALTER TABLE `booklet_edit_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booklet_record_id` (`booklet_record_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `bulk_upload_history`
--
ALTER TABLE `bulk_upload_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `course_catalog`
--
ALTER TABLE `course_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `idx_program` (`program`),
  ADD KEY `idx_term` (`term`);

--
-- Indexes for table `course_prerequisites`
--
ALTER TABLE `course_prerequisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `current_subjects`
--
ALTER TABLE `current_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `study_plan_id` (`study_plan_id`);

--
-- Indexes for table `current_subject_prerequisites`
--
ALTER TABLE `current_subject_prerequisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `current_subject_id` (`current_subject_id`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_professor_id` (`from_professor_id`),
  ADD KEY `to_student_id` (`to_student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_scheduled` (`scheduled_send_time`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Indexes for table `planned_subjects`
--
ALTER TABLE `planned_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `study_plan_id` (`study_plan_id`);

--
-- Indexes for table `planned_subject_prerequisites`
--
ALTER TABLE `planned_subject_prerequisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `planned_subject_id` (`planned_subject_id`);

--
-- Indexes for table `professors`
--
ALTER TABLE `professors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`);

--
-- Indexes for table `program_profiles`
--
ALTER TABLE `program_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_name` (`program_name`),
  ADD UNIQUE KEY `program_code` (`program_code`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD KEY `advisor_id` (`advisor_id`);

--
-- Indexes for table `student_advising_booklet`
--
ALTER TABLE `student_advising_booklet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_year` (`student_id`,`academic_year`,`term`),
  ADD KEY `idx_booklet_student_year_term` (`student_id`,`academic_year`,`term`),
  ADD KEY `idx_booklet_approval` (`approval_status`,`modified_by`);

--
-- Indexes for table `student_concerns`
--
ALTER TABLE `student_concerns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `study_plan_id` (`study_plan_id`);

--
-- Indexes for table `study_plans`
--
ALTER TABLE `study_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `term_gpa_summary`
--
ALTER TABLE `term_gpa_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_term` (`student_id`,`academic_year`,`term`);

--
-- Indexes for table `user_login_info`
--
ALTER TABLE `user_login_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `advising_deadlines`
--
ALTER TABLE `advising_deadlines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advising_schedules`
--
ALTER TABLE `advising_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booklet_edit_requests`
--
ALTER TABLE `booklet_edit_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bulk_upload_history`
--
ALTER TABLE `bulk_upload_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `course_catalog`
--
ALTER TABLE `course_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `course_prerequisites`
--
ALTER TABLE `course_prerequisites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `current_subjects`
--
ALTER TABLE `current_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `current_subject_prerequisites`
--
ALTER TABLE `current_subject_prerequisites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `planned_subjects`
--
ALTER TABLE `planned_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `planned_subject_prerequisites`
--
ALTER TABLE `planned_subject_prerequisites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_profiles`
--
ALTER TABLE `program_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_advising_booklet`
--
ALTER TABLE `student_advising_booklet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_concerns`
--
ALTER TABLE `student_concerns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `study_plans`
--
ALTER TABLE `study_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `term_gpa_summary`
--
ALTER TABLE `term_gpa_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_login_info`
--
ALTER TABLE `user_login_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`id`) REFERENCES `user_login_info` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `advising_deadlines`
--
ALTER TABLE `advising_deadlines`
  ADD CONSTRAINT `advising_deadlines_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `advising_schedules`
--
ALTER TABLE `advising_schedules`
  ADD CONSTRAINT `advising_schedules_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `advising_schedules_ibfk_2` FOREIGN KEY (`booked_by`) REFERENCES `students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booklet_edit_requests`
--
ALTER TABLE `booklet_edit_requests`
  ADD CONSTRAINT `booklet_edit_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booklet_edit_requests_ibfk_2` FOREIGN KEY (`booklet_record_id`) REFERENCES `student_advising_booklet` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booklet_edit_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `professors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bulk_upload_history`
--
ALTER TABLE `bulk_upload_history`
  ADD CONSTRAINT `bulk_upload_history_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `admin` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_prerequisites`
--
ALTER TABLE `course_prerequisites`
  ADD CONSTRAINT `course_prerequisites_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `course_catalog` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `current_subjects`
--
ALTER TABLE `current_subjects`
  ADD CONSTRAINT `current_subjects_ibfk_1` FOREIGN KEY (`study_plan_id`) REFERENCES `study_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `current_subject_prerequisites`
--
ALTER TABLE `current_subject_prerequisites`
  ADD CONSTRAINT `current_subject_prerequisites_ibfk_1` FOREIGN KEY (`current_subject_id`) REFERENCES `current_subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD CONSTRAINT `email_queue_ibfk_1` FOREIGN KEY (`from_professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `email_queue_ibfk_2` FOREIGN KEY (`to_student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD CONSTRAINT `email_templates_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `planned_subjects`
--
ALTER TABLE `planned_subjects`
  ADD CONSTRAINT `planned_subjects_ibfk_1` FOREIGN KEY (`study_plan_id`) REFERENCES `study_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `planned_subject_prerequisites`
--
ALTER TABLE `planned_subject_prerequisites`
  ADD CONSTRAINT `planned_subject_prerequisites_ibfk_1` FOREIGN KEY (`planned_subject_id`) REFERENCES `planned_subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `professors`
--
ALTER TABLE `professors`
  ADD CONSTRAINT `professors_ibfk_1` FOREIGN KEY (`id`) REFERENCES `user_login_info` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`id`) REFERENCES `user_login_info` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`advisor_id`) REFERENCES `professors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_advising_booklet`
--
ALTER TABLE `student_advising_booklet`
  ADD CONSTRAINT `student_advising_booklet_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_concerns`
--
ALTER TABLE `student_concerns`
  ADD CONSTRAINT `student_concerns_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_concerns_ibfk_2` FOREIGN KEY (`study_plan_id`) REFERENCES `study_plans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `study_plans`
--
ALTER TABLE `study_plans`
  ADD CONSTRAINT `study_plans_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `term_gpa_summary`
--
ALTER TABLE `term_gpa_summary`
  ADD CONSTRAINT `term_gpa_summary_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
