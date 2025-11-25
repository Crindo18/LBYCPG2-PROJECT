-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2025 at 10:20 PM
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
-- Table structure for table `academic_advising_forms`
--

CREATE TABLE `academic_advising_forms` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `form_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Stores all form fields in JSON format' CHECK (json_valid(`form_data`)),
  `grades_screenshot` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded grades image',
  `booklet_file` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded academic booklet',
  `status` enum('pending','approved','rejected','revision_requested') DEFAULT 'pending',
  `adviser_comments` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_advising_forms`
--

INSERT INTO `academic_advising_forms` (`id`, `student_id`, `adviser_id`, `form_data`, `grades_screenshot`, `booklet_file`, `status`, `adviser_comments`, `submitted_at`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 8, NULL, '{\"academic_year\":\"2024-2025\",\"term\":\"Term 1\",\"current_year_failed_units\":\"3\",\"overall_failed_units\":\"3\",\"previous_term_gpa\":\"3\",\"cumulative_gpa\":\"3\",\"max_course_load_units\":\"21\",\"total_enrolled_units\":\"21\",\"additional_notes\":\"\",\"certify_prerequisites\":\"1\",\"certify_accuracy\":\"1\",\"request_meeting\":\"0\"}', 'uploads/advising_forms/8_1764010812_grade.pdf', 'uploads/advising_forms/8_1764010812_booklet.pdf', 'pending', NULL, '2025-11-24 19:00:12', NULL, '2025-11-24 19:00:12', '2025-11-24 19:00:12'),
(2, 5, NULL, '{\"academic_year\":\"2024-2025\",\"term\":\"Term 1\",\"current_year_failed_units\":\"3\",\"overall_failed_units\":\"3\",\"previous_term_gpa\":\"3\",\"cumulative_gpa\":\"3\",\"max_course_load_units\":\"21\",\"total_enrolled_units\":\"21\",\"additional_notes\":\"\",\"certify_prerequisites\":\"1\",\"certify_accuracy\":\"1\",\"request_meeting\":\"0\"}', 'uploads/advising_forms/5_1764011940_grade.pdf', 'uploads/advising_forms/5_1764011940_booklet.pdf', 'pending', NULL, '2025-11-24 19:19:00', NULL, '2025-11-24 19:19:00', '2025-11-24 19:19:00');

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
(1, 'admin', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'admin@dlsu.edu.ph', '2025-11-24 09:37:00');

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
-- Table structure for table `advising_form_courses`
--

CREATE TABLE `advising_form_courses` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `course_type` enum('current','next') NOT NULL COMMENT 'current=enrolled, next=planned',
  `course_code` varchar(20) DEFAULT NULL,
  `prerequisites` text DEFAULT NULL,
  `units` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `advising_form_courses`
--

INSERT INTO `advising_form_courses` (`id`, `form_id`, `course_type`, `course_code`, `prerequisites`, `units`) VALUES
(1, 1, 'current', 'CALENG1', NULL, 3),
(2, 2, 'current', 'CALENG1', NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `advising_form_prerequisites`
--

CREATE TABLE `advising_form_prerequisites` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `prerequisite_code` varchar(20) NOT NULL,
  `prerequisite_type` enum('H','S','C') NOT NULL COMMENT 'H=Hard, S=Soft, C=Co-requisite',
  `grade_received` varchar(10) DEFAULT NULL COMMENT 'Grade received for prerequisite or N/A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `advising_form_prerequisites`
--

INSERT INTO `advising_form_prerequisites` (`id`, `course_id`, `prerequisite_code`, `prerequisite_type`, `grade_received`) VALUES
(1, 1, 'FNDMATH', 'H', 'N/A'),
(2, 2, 'FNDMATH', 'H', 'N/A');

-- --------------------------------------------------------

--
-- Table structure for table `advising_schedules`
--

CREATE TABLE `advising_schedules` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `max_slots` int(11) DEFAULT 1,
  `booked_count` int(11) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_appointments`
--

CREATE TABLE `student_appointments` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
(1, 1, 'courses', 'Final_Combined_CPE_Curriculum.csv', 103, 103, 0, '', '2025-11-24 12:54:36'),
(2, 1, 'students', 'students_bulk_upload.csv', 50, 49, 1, 'Row 1 (ID: 12012345): Duplicate entry \'12012345\' for key \'id_number\'', '2025-11-24 13:29:23'),
(3, 1, 'professors', 'professors_bulk_upload.csv', 50, 49, 1, 'Row 1 (ID: 10012345): Duplicate entry \'10012345\' for key \'id_number\'', '2025-11-24 13:30:08');

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
(1, 'FNDMATH', 'Foundation in Math (FOUN)', 5, '0', 'Term 1', 'major', '', 1, '2025-11-24 09:37:01', '2025-11-24 12:54:36'),
(2, 'PROLOGI', 'Programming Logic and Design Lecture (1E)', 2, '0', 'Term 2', 'major', '', 1, '2025-11-24 09:37:01', '2025-11-24 12:54:36'),
(3, 'LBYCPA1', 'Programming Logic and Design Laboratory (1E)', 2, '0', 'Term 2', 'major', 'PROLOGI (C)', 1, '2025-11-24 09:37:01', '2025-11-24 12:54:36'),
(4, 'CALENG1', 'Differential Calculus (1A)', 3, '0', 'Term 2', 'major', 'FNDMATH (H)', 1, '2025-11-24 09:37:01', '2025-11-24 12:54:36'),
(5, 'CSSWENG', 'Software Engineering', 3, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 09:37:01', '2025-11-24 09:37:01'),
(6, 'CSALGCM', 'Design and Analysis of Algorithms', 3, 'BS Computer Engineering', 'Term 2', 'major', 'PROLOGI(H)', 1, '2025-11-24 09:37:01', '2025-11-24 09:37:01'),
(7, 'CSMCPRO', 'Microprocessors', 3, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 09:37:01', '2025-11-24 09:37:01'),
(8, 'CSNETWK', 'Computer Networks', 3, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 09:37:01', '2025-11-24 09:37:01'),
(9, 'CSARCH2', 'Computer Architecture 2', 3, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 09:37:01', '2025-11-24 09:37:01'),
(10, 'REMETHS', 'Methods of Research for CpE (1E)', 3, '0', 'Term 8', 'major', 'ENGDATA/ GEPCOMM/ LOGDSGN (H/H/H)', 1, '2025-11-24 09:37:01', '2025-11-24 12:54:36'),
(11, 'DSIGPRO', 'Digital Signal Processing Lecture (1E)', 3, '0', 'Term 9', 'major', 'FDCNSYS/ EMBDSYS (H/S)', 1, '2025-11-24 09:37:01', '2025-11-24 12:54:36'),
(12, 'CSINPRO', 'Internship Program', 3, 'BS Computer Engineering', 'Term 12', 'major', '', 1, '2025-11-24 09:37:01', '2025-11-24 09:37:01'),
(13, 'LCC..01', 'Lasallian Core Curriculum (Placeholder 01)', 3, 'BS Computer Engineering', 'Term 1', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(14, 'NSTP101', 'National Service Training Program-General Orientation', 0, 'BS Computer Engineering', 'Term 1', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(17, 'BASPHYS', 'Basic Physics', 3, 'BS Computer Engineering', 'Term 1', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(18, 'FNDSTAT', 'Foundation in Statistics (FOUN)', 3, 'BS Computer Engineering', 'Term 1', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(21, 'COEDISC', 'Computer Engineering as a Discipline (1E)', 1, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(23, 'LCC..02', 'Lasallian Core Curriculum (Placeholder 02)', 3, 'BS Computer Engineering', 'Term 2', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(24, 'NSTPCW1', 'National Service Training Program 1 (2D)', -3, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(25, 'LBYEC2A', 'Computer Fundamentals and Programming 1', 1, 'BS Computer Engineering', 'Term 2', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(26, 'LCC.. 04', 'Lasallian Core Curriculum (Placeholder 04)', 3, 'BS Computer Engineering', 'Term 2', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(27, 'LCC..03', 'Lasallian Core Curriculum (Placeholder 03)', 3, 'BS Computer Engineering', 'Term 2', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(28, 'LCC..06', 'Lasallian Core Curriculum (Placeholder 06)', 3, 'BS Computer Engineering', 'Term 3', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(29, 'LBYCPEI', 'Object Oriented Programming Laboratory (1E)', 2, 'BS Computer Engineering', 'Term 3', 'major', 'LBYCPA1 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(30, 'LCC..05', 'Lasallian Core Curriculum (Placeholder 05)', 3, 'BS Computer Engineering', 'Term 3', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(31, 'LCC..07', 'Lasallian Core Curriculum (Placeholder 07)', 3, 'BS Computer Engineering', 'Term 3', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(32, 'LBYEC2B', 'Computer Fundamentals and Programming 2', 1, 'BS Computer Engineering', 'Term 3', 'major', 'LBYEC2A (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(33, 'LBYPH1A', 'Physics for Engineers Laboratory (1B)', 1, 'BS Computer Engineering', 'Term 3', 'major', 'ENGPHYS (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(34, 'CALENG2', 'Integral Calculus (1A)', 3, 'BS Computer Engineering', 'Term 3', 'major', 'CALENG1 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(35, 'LASARE1', 'Lasallian Recollection 1', 0, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(36, 'SAS1000', 'Students Affairs Service 1000 (LS)', 0, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(37, 'LCLSONE', 'Lasallian Studies 1', -1, 'BS Computer Engineering', 'Term 3', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(38, 'NSTPCW2', 'National Service Training Program 2 (2D)', -3, 'BS Computer Engineering', 'Term 3', 'major', 'NSTPCW1 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(39, 'ENGPHYS', 'Physics for Engineers (1B)', 3, 'BS Computer Engineering', 'Term 3', 'major', 'CALENG1 / BASPHYS (S / H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(40, 'PE1CRDO', 'Cardio Fitness', 2, 'BS Computer Engineering', 'Term 4', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(41, 'LBYCH1A', 'Chemistry for Engineers Laboratory (1B)', 1, 'BS Computer Engineering', 'Term 4', 'major', 'ENGCHEM (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(42, 'ENGCHEM', 'Chemistry for Engineers (1B)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'BASCHEM (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(43, 'FUNDCKT', 'Fundamentals of Electrical Circuits Lecture (1D)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'ENGPHYS (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(44, 'DISCRMT', 'Discrete Mathematics (1E)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'CALENG1 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(45, 'LBYEC2M', 'Fundamentals of Electrical Circuits Lab (1D)', 1, 'BS Computer Engineering', 'Term 4', 'major', 'FUNDCKT (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(46, 'DATSRAL', 'Data Structures and Algorithms Lecture (1E)', 1, 'BS Computer Engineering', 'Term 4', 'major', 'LBYCPEI (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(47, 'CALENG3', 'Differential Equations (1A)', 3, 'BS Computer Engineering', 'Term 4', 'major', 'CALENG2 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(48, 'LBYCPA2', 'Data Structures and Algorithms Laboratory (1E)', 2, 'BS Computer Engineering', 'Term 4', 'major', 'DATSRAL (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(49, 'SAS2000', 'Student Affairs Series 2', 0, 'BS Computer Engineering', 'Term 5', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(50, 'ENGDATA', 'Engineering Data Analysis (1A)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'CALENG2/ FNDSTAT (S / H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(51, 'NUMMETS', 'Numerical Methods (1E)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'CALENG3 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(52, 'FUNDLEC', 'Fundamentals of Electronic Circuits Lecture (1D)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'FUNDCKT (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(53, 'LBYCPC2', 'Fundamentals of Electronic Circuits Laboratory (1D)', 1, 'BS Computer Engineering', 'Term 5', 'major', 'FUNDLEC (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(54, 'SOFDESG', 'Software Design Lecture (1E)', 3, 'BS Computer Engineering', 'Term 5', 'major', 'LBYCPA2 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(55, 'LBYCPD2', 'Software Design Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 5', 'major', 'SOFDESG (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(56, 'ENGENVI', 'Environmental Science and Engineering', 3, 'BS Computer Engineering', 'Term 5', 'major', 'ENGCHEM (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(57, 'PE2FTEX', 'Functional Exercise', 2, 'BS Computer Engineering', 'Term 5', 'general_education', 'PE1CRDO (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(58, 'PETHREE', 'Generic Code', 2, 'BS Computer Engineering', 'Term 6', 'general_education', 'PE1 / PE2 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(59, 'LCC..08', 'Lasallian Core Curriculum (Placeholder 08)', 3, 'BS Computer Engineering', 'Term 6', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(60, 'LBYME1C', 'Computer-Aided Drafting (CAD) for ECE and CpE (1C)', 1, 'BS Computer Engineering', 'Term 6', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(61, 'LBYCPC3', 'Feedback and Control System Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 6', 'major', 'FDCNSYS (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(62, 'FDCNSYS', 'Feedback and Control Systems (1E)', 3, 'BS Computer Engineering', 'Term 6', 'major', 'NUMMETS/FUNDCKT (H/H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(63, 'LBYCPG4', 'Logic Circuits and Design Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 6', 'major', 'LOGDSGN (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(64, 'LOGDSGN', 'Logic Circuits and Design Lecture (1E)', 3, 'BS Computer Engineering', 'Term 6', 'major', 'FUNDLEC (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(65, 'MXSIGFN', 'Fundamentals of Mixed Signals and Sensors (1E)', 3, 'BS Computer Engineering', 'Term 6', 'major', 'FUNDLEC (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(66, 'LASARE2', 'Lasallian Recollection 2', 0, 'BS Computer Engineering', 'Term 6', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(67, 'LCLSTWO', 'Lasallian Studies 2', -1, 'BS Computer Engineering', 'Term 6', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(68, 'LBYCPG2', 'Basic Computer Systems Administration', 1, 'BS Computer Engineering', 'Term 7', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(69, 'PEDFOUR', 'Generic Code', 2, 'BS Computer Engineering', 'Term 7', 'general_education', 'PE1/PE2/PE3 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(70, 'DIGDACM', 'Data and Digital Communications (1E)', 3, 'BS Computer Engineering', 'Term 7', 'major', 'FUNDLEC (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(71, 'LBYCPF2', 'Introduction to HDL Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 7', 'major', 'LBYCPA1/FUNDLEC (H/H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(72, 'LBYEC3B', 'Intelligent Systems for Engineering', 1, 'BS Computer Engineering', 'Term 7', 'major', 'LBYEC2A/ ENGDATA (H/H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(73, 'LBYCPB3', 'Computer Engineering Drafting and Design Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 7', 'major', 'LOGDSGN (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(74, 'LCC..09', 'Lasallian Core Curriculum (Placeholder 09)', 3, 'BS Computer Engineering', 'Term 7', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(75, 'MICPROS', 'Microprocessors Lecture (1E)', 3, 'BS Computer Engineering', 'Term 7', 'major', 'LOGDSGN (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(76, 'LBYCPA3', 'Microprocessors Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 7', 'major', 'MICPROS (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(77, 'LBYCPG3', 'Online Technologies Laboratory', 1, 'BS Computer Engineering', 'Term 8', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(78, 'LCC..10', 'Lasallian Core Curriculum (Placeholder 10)', 3, 'BS Computer Engineering', 'Term 8', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(80, 'OPESSYS', 'Operating Systems Lec (1E)', 3, 'BS Computer Engineering', 'Term 8', 'major', 'LBYCPA2 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(81, 'LBYCPM3', 'Embedded Systems Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 8', 'major', 'EMBDSYS (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(82, 'LBYCPO1', 'Operating Systems Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 8', 'major', 'OPESSYS (c)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(83, 'LBYCPD3', 'Computer Architecture and Organization Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 8', 'major', 'CSYSARC (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(84, 'CSYSARC', 'Computer Architecture and Organization Lecture (1E)', 3, 'BS Computer Engineering', 'Term 8', 'major', 'MICPROS (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(85, 'EMBDSYS', 'Embedded Systems Lecture (1E)', 3, 'BS Computer Engineering', 'Term 8', 'major', 'MICPROS (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(86, 'LBYCPF3', 'CpE Elective 1 Laboratory (1F)', 1, 'BS Computer Engineering', 'Term 9', 'major', 'CPECOG1 (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(87, 'CPECOG1', 'CpE Elective 1 Lecture (1F)', 2, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS/THSCP4A (H/C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(88, 'CPEPRAC', 'CpE Laws and Professional Practice (1E)', 2, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(89, 'THSCP4A', 'CpE Practice and Design 1 (1E)', 1, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS/ REMETHS (H/H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(90, 'OCHESAF', 'Basic Occupational Health and Safety (1E)', 3, 'BS Computer Engineering', 'Term 9', 'major', 'EMBDSYS (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(91, 'LBYCPA4', 'Digital Signal Processing Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 9', 'major', 'DSIGPRO (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(92, 'LASARE3', 'Lasallian Recollection 3', 0, 'BS Computer Engineering', 'Term 9', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(93, 'LCC..11', 'Lasallian Core Curriculum (Placeholder 11)', 3, 'BS Computer Engineering', 'Term 9', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(94, 'LCLSTRI', 'Lasallian Studies 3', -1, 'BS Computer Engineering', 'Term 9', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(96, 'SAS3000', 'Student Affairs Series 3', 0, 'BS Computer Engineering', 'Term 10', 'major', 'SAS2000 (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(97, 'LBYCPH3', 'CpE Elective 2 Laboratory (1F)', 1, 'BS Computer Engineering', 'Term 10', 'major', 'CPECOG2 (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(98, 'CPECOG2', 'CpE Elective 2 Lecture (1F)', 2, 'BS Computer Engineering', 'Term 10', 'major', 'THSCP4A (S)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(99, 'CPECAPS', 'Operational Technologies', 1, 'BS Computer Engineering', 'Term 10', 'major', 'LBYCPH3/ LBYCPB4 (C/C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(100, 'LBYCPB4', 'Computer Networks and Security Laboratory (1E)', 1, 'BS Computer Engineering', 'Term 10', 'major', 'CONETSC (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(101, 'ENGTREP', 'Technopreneurship 101 (1C)', 3, 'BS Computer Engineering', 'Term 10', 'major', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(102, 'CONETSC', 'Computer Networks and Security Lecture (1E)', 3, 'BS Computer Engineering', 'Term 10', 'major', 'DIGDACM (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(103, 'EMERTEC', 'Emerging Technologies in CpE (1E)', 3, 'BS Computer Engineering', 'Term 10', 'major', 'EMBDSYS (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(104, 'LCC..12', 'Lasallian Core Curriculum (Placeholder 12)', 3, 'BS Computer Engineering', 'Term 10', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(105, 'THSCP4B', 'CpE Practice and Design 2 (1E)', 1, 'BS Computer Engineering', 'Term 10', 'major', 'THSCP4A (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(106, 'PRCGECP', 'Practicum for CpE (1E)', 3, 'BS Computer Engineering', 'Term 11', 'major', 'REMETHS (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(107, 'ENGMANA', 'Engineering Management', 2, 'BS Computer Engineering', 'Term 12', 'major', 'CALENG1 (S)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(108, 'LCC..15', 'Lasallian Core Curriculum (Placeholder 15)', 3, 'BS Computer Engineering', 'Term 12', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(109, 'ECNOMIC', 'Engineering Economics for CpE (1C)', 3, 'BS Computer Engineering', 'Term 12', 'major', 'CALENG1 (S)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(110, 'LBYCPC4', 'CPE Elective 3 Laboratory (1F)', 1, 'BS Computer Engineering', 'Term 12', 'major', 'CPECOG3 (C)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(111, 'CPECOG3', 'CpE Elective 3 Lecture (1F)', 2, 'BS Computer Engineering', 'Term 12', 'major', 'THSCP4A (S)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(112, 'THSCP4C', 'CpE Practice and Design 3 (1E)', 1, 'BS Computer Engineering', 'Term 12', 'major', 'THSCP4B (H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(113, 'LCC..14', 'Lasallian Core Curriculum (Placeholder 14)', 3, 'BS Computer Engineering', 'Term 12', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(114, 'LCC..13', 'Lasallian Core Curriculum (Placeholder 13)', 3, 'BS Computer Engineering', 'Term 12', 'general_education', '', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36'),
(115, 'CPETRIP', 'Seminars and Field Trips for CpE (1E)', 1, 'BS Computer Engineering', 'Term 12', 'major', 'EMBDSYS/CPECAPS (H/H)', 1, '2025-11-24 12:54:36', '2025-11-24 12:54:36');

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
(1, 1, 'FNDMATH', 'Fundamental Mathematics', 3),
(2, 1, 'REMETHS', 'Methods of Research for CpE', 3),
(3, 1, 'CSMCPRO', 'Microprocessors', 3);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `professors`
--

INSERT INTO `professors` (`id`, `id_number`, `first_name`, `middle_name`, `last_name`, `department`, `email`, `created_at`) VALUES
(3, 10012345, 'Bianca', 'Santos', 'Garcia', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'maria.garcia@dlsu.edu.ph', '2025-11-24 09:37:01'),
(55, 10012346, 'Roberto', 'Cruz', 'Fernandez', 'GCOE-ECEE', 'roberto.fernandez@dlsu.edu.ph', '2025-11-24 13:30:00'),
(56, 10012347, 'Carmen', 'Lopez', 'Martinez', 'GCOE-ECEE', 'carmen.martinez@dlsu.edu.ph', '2025-11-24 13:30:00'),
(57, 10012348, 'Pedro', 'Miguel', 'Rodriguez', 'GCOE-ECEE', 'pedro.rodriguez@dlsu.edu.ph', '2025-11-24 13:30:01'),
(58, 10012349, 'Linda', 'Angeles', 'Gonzalez', 'GCOE-ECEE', 'linda.gonzalez@dlsu.edu.ph', '2025-11-24 13:30:01'),
(59, 10012350, 'Fernando', 'Jose', 'Perez', 'GCOE-ECEE', 'fernando.perez@dlsu.edu.ph', '2025-11-24 13:30:01'),
(60, 10012351, 'Teresa', 'Grace', 'Sanchez', 'GCOE-ECEE', 'teresa.sanchez@dlsu.edu.ph', '2025-11-24 13:30:01'),
(61, 10012352, 'Ramon', 'Antonio', 'Rivera', 'GCOE-ECEE', 'ramon.rivera@dlsu.edu.ph', '2025-11-24 13:30:01'),
(62, 10012353, 'Angela', 'Marie', 'Torres', 'GCOE-ECEE', 'angela.torres@dlsu.edu.ph', '2025-11-24 13:30:01'),
(63, 10012354, 'Manuel', 'Luis', 'Flores', 'GCOE-ECEE', 'manuel.flores@dlsu.edu.ph', '2025-11-24 13:30:02'),
(64, 10012355, 'Elena', 'Rose', 'Ramirez', 'GCOE-ECEE', 'elena.ramirez@dlsu.edu.ph', '2025-11-24 13:30:02'),
(65, 10012356, 'Ricardo', 'David', 'Morales', 'GCOE-ECEE', 'ricardo.morales@dlsu.edu.ph', '2025-11-24 13:30:02'),
(66, 10012357, 'Patricia', 'Faith', 'Cruz', 'GCOE-ECEE', 'patricia.cruz@dlsu.edu.ph', '2025-11-24 13:30:02'),
(67, 10012358, 'Jorge', 'Gabriel', 'Castillo', 'GCOE-ECEE', 'jorge.castillo@dlsu.edu.ph', '2025-11-24 13:30:02'),
(68, 10012359, 'Diana', 'Joy', 'Reyes', 'GCOE-ECEE', 'diana.reyes@dlsu.edu.ph', '2025-11-24 13:30:02'),
(69, 10012360, 'Alberto', 'Marco', 'Mendoza', 'GCOE-ECEE', 'alberto.mendoza@dlsu.edu.ph', '2025-11-24 13:30:03'),
(70, 10012361, 'Gloria', 'Pearl', 'Navarro', 'GCOE-ECEE', 'gloria.navarro@dlsu.edu.ph', '2025-11-24 13:30:03'),
(71, 10012362, 'Ernesto', 'Andre', 'Santiago', 'GCOE-ECEE', 'ernesto.santiago@dlsu.edu.ph', '2025-11-24 13:30:03'),
(72, 10012363, 'Rosario', 'Dawn', 'Gutierrez', 'GCOE-ECEE', 'rosario.gutierrez@dlsu.edu.ph', '2025-11-24 13:30:03'),
(73, 10012364, 'Francisco', 'Paulo', 'Ortega', 'GCOE-ECEE', 'francisco.ortega@dlsu.edu.ph', '2025-11-24 13:30:03'),
(74, 10012365, 'Beatriz', 'Claire', 'Vargas', 'GCOE-ECEE', 'beatriz.vargas@dlsu.edu.ph', '2025-11-24 13:30:03'),
(75, 10012366, 'Alfredo', 'James', 'Jimenez', 'GCOE-ECEE', 'alfredo.jimenez@dlsu.edu.ph', '2025-11-24 13:30:03'),
(76, 10012367, 'Margarita', 'Grace', 'Castro', 'GCOE-ECEE', 'margarita.castro@dlsu.edu.ph', '2025-11-24 13:30:04'),
(77, 10012368, 'Guillermo', 'Christian', 'Medina', 'GCOE-ECEE', 'guillermo.medina@dlsu.edu.ph', '2025-11-24 13:30:04'),
(78, 10012369, 'Luz', 'Louise', 'Romero', 'GCOE-ECEE', 'luz.romero@dlsu.edu.ph', '2025-11-24 13:30:04'),
(79, 10012370, 'Rodrigo', 'Rafael', 'Aguilar', 'GCOE-ECEE', 'rodrigo.aguilar@dlsu.edu.ph', '2025-11-24 13:30:04'),
(80, 10012371, 'Cecilia', 'Anne', 'Delgado', 'GCOE-ECEE', 'cecilia.delgado@dlsu.edu.ph', '2025-11-24 13:30:04'),
(81, 10012372, 'Victor', 'Isaac', 'Rojas', 'GCOE-ECEE', 'victor.rojas@dlsu.edu.ph', '2025-11-24 13:30:04'),
(82, 10012373, 'Pilar', 'Nicole', 'Velasco', 'GCOE-ECEE', 'pilar.velasco@dlsu.edu.ph', '2025-11-24 13:30:05'),
(83, 10012374, 'Enrique', 'John', 'Chavez', 'GCOE-ECEE', 'enrique.chavez@dlsu.edu.ph', '2025-11-24 13:30:05'),
(84, 10012375, 'Dolores', 'May', 'Salazar', 'GCOE-ECEE', 'dolores.salazar@dlsu.edu.ph', '2025-11-24 13:30:05'),
(85, 10012376, 'Hector', 'Paul', 'Pena', 'GCOE-ECEE', 'hector.pena@dlsu.edu.ph', '2025-11-24 13:30:05'),
(86, 10012377, 'Esperanza', 'Faith', 'Vasquez', 'GCOE-ECEE', 'esperanza.vasquez@dlsu.edu.ph', '2025-11-24 13:30:05'),
(87, 10012378, 'Arturo', 'Matthew', 'Ruiz', 'GCOE-ECEE', 'arturo.ruiz@dlsu.edu.ph', '2025-11-24 13:30:05'),
(88, 10012379, 'Veronica', 'Sophia', 'Herrera', 'GCOE-ECEE', 'veronica.herrera@dlsu.edu.ph', '2025-11-24 13:30:06'),
(89, 10012380, 'Javier', 'Nathan', 'Espinoza', 'GCOE-ECEE', 'javier.espinoza@dlsu.edu.ph', '2025-11-24 13:30:06'),
(90, 10012381, 'Cristina', 'Elizabeth', 'Dominguez', 'GCOE-ECEE', 'cristina.dominguez@dlsu.edu.ph', '2025-11-24 13:30:06'),
(91, 10012382, 'Armando', 'Thomas', 'Ibarra', 'GCOE-ECEE', 'armando.ibarra@dlsu.edu.ph', '2025-11-24 13:30:06'),
(92, 10012383, 'Silvia', 'Catherine', 'Cortez', 'GCOE-ECEE', 'silvia.cortez@dlsu.edu.ph', '2025-11-24 13:30:06'),
(93, 10012384, 'Felipe', 'Daniel', 'Molina', 'GCOE-ECEE', 'felipe.molina@dlsu.edu.ph', '2025-11-24 13:30:06'),
(94, 10012385, 'Yolanda', 'Ruby', 'Valdez', 'GCOE-ECEE', 'yolanda.valdez@dlsu.edu.ph', '2025-11-24 13:30:07'),
(95, 10012386, 'Marcos', 'Joseph', 'Soto', 'GCOE-ECEE', 'marcos.soto@dlsu.edu.ph', '2025-11-24 13:30:07'),
(96, 10012387, 'Adriana', 'Jane', 'Campos', 'GCOE-ECEE', 'adriana.campos@dlsu.edu.ph', '2025-11-24 13:30:07'),
(97, 10012388, 'Sergio', 'Alexander', 'Alvarez', 'GCOE-ECEE', 'sergio.alvarez@dlsu.edu.ph', '2025-11-24 13:30:07'),
(98, 10012389, 'Luisa', 'Michelle', 'Fuentes', 'GCOE-ECEE', 'luisa.fuentes@dlsu.edu.ph', '2025-11-24 13:30:07'),
(99, 10012390, 'Gustavo', 'Ryan', 'Sandoval', 'GCOE-ECEE', 'gustavo.sandoval@dlsu.edu.ph', '2025-11-24 13:30:07'),
(100, 10012391, 'Monica', 'Andrea', 'Vega', 'GCOE-ECEE', 'monica.vega@dlsu.edu.ph', '2025-11-24 13:30:07'),
(101, 10012392, 'Pablo', 'Christopher', 'Paredes', 'GCOE-ECEE', 'pablo.paredes@dlsu.edu.ph', '2025-11-24 13:30:08'),
(102, 10012393, 'Julia', 'Maria', 'Acosta', 'GCOE-ECEE', 'julia.acosta@dlsu.edu.ph', '2025-11-24 13:30:08'),
(103, 10012394, 'Raul', 'Miguel', 'Nunez', 'GCOE-ECEE', 'raul.nunez@dlsu.edu.ph', '2025-11-24 13:30:08');

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
(1, 'BS Computer Engineering', 'BSCpE', 180, 'Bachelor of Science in Computer Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 30, '2025-11-24 09:37:00', '2025-11-24 09:37:00'),
(2, 'BS Electronics and Communications Engineering', 'BSECE', 180, 'Bachelor of Science in Electronics and Communications Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 30, '2025-11-24 09:37:00', '2025-11-24 09:37:00'),
(3, 'BS Electrical Engineering', 'BSEE', 180, 'Bachelor of Science in Electrical Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 30, '2025-11-24 09:37:00', '2025-11-24 09:37:00');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `id_number`, `first_name`, `middle_name`, `last_name`, `college`, `department`, `program`, `specialization`, `phone_number`, `email`, `parent_guardian_name`, `parent_guardian_number`, `advisor_id`, `advising_cleared`, `accumulated_failed_units`, `created_at`) VALUES
(2, 12012345, 'Juan', 'Santos', 'Dela Cruz', 'Gokongwei College of Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'BS Computer Engineering', 'N/A', '+63 917 123 4567', 'juan_delacruz@dlsu.edu.ph', 'Maria Bianca Cruz', '+63 918 765 4321', 3, 1, 0, '2025-11-24 09:37:00'),
(5, 12012346, 'Maria', 'Angeles', 'Santos', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 234 5678', 'maria.santos@dlsu.edu.ph', 'Roberto Santos', '+63 918 876 5432', 55, 1, 3, '2025-11-24 13:29:16'),
(6, 12012347, 'Jose', 'Miguel', 'Reyes', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 345 6789', 'jose.reyes@dlsu.edu.ph', 'Carmen Reyes', '+63 918 987 6543', NULL, 0, 0, '2025-11-24 13:29:16'),
(7, 12012348, 'Ana', 'Grace', 'Garcia', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 456 7890', 'ana.garcia@dlsu.edu.ph', 'Pedro Garcia', '+63 918 098 7654', NULL, 0, 0, '2025-11-24 13:29:16'),
(8, 12012349, 'Carlos', 'Jose', 'Martinez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 567 8901', 'carlos.martinez@dlsu.edu.ph', 'Linda Martinez', '+63 918 109 8765', NULL, 0, 3, '2025-11-24 13:29:16'),
(9, 12012350, 'Sofia', 'Marie', 'Lopez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 678 9012', 'sofia.lopez@dlsu.edu.ph', 'Fernando Lopez', '+63 918 210 9876', NULL, 0, 0, '2025-11-24 13:29:17'),
(10, 12012351, 'Miguel', 'Antonio', 'Gonzales', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 789 0123', 'miguel.gonzales@dlsu.edu.ph', 'Teresa Gonzales', '+63 918 321 0987', NULL, 0, 0, '2025-11-24 13:29:17'),
(11, 12012352, 'Isabella', 'Rose', 'Hernandez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 890 1234', 'isabella.hernandez@dlsu.edu.ph', 'Ramon Hernandez', '+63 918 432 1098', NULL, 0, 0, '2025-11-24 13:29:17'),
(12, 12012353, 'Diego', 'Luis', 'Flores', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 901 2345', 'diego.flores@dlsu.edu.ph', 'Angela Flores', '+63 918 543 2109', NULL, 0, 0, '2025-11-24 13:29:17'),
(13, 12012354, 'Gabriela', 'Faith', 'Torres', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 012 3456', 'gabriela.torres@dlsu.edu.ph', 'Manuel Torres', '+63 918 654 3210', NULL, 0, 0, '2025-11-24 13:29:17'),
(14, 12012355, 'Rafael', 'David', 'Morales', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 123 4568', 'rafael.morales@dlsu.edu.ph', 'Elena Morales', '+63 918 765 4322', NULL, 0, 0, '2025-11-24 13:29:17'),
(15, 12012356, 'Valentina', 'Joy', 'Ramos', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 234 5679', 'valentina.ramos@dlsu.edu.ph', 'Ricardo Ramos', '+63 918 876 5433', NULL, 0, 0, '2025-11-24 13:29:18'),
(16, 12012357, 'Lucas', 'Gabriel', 'Cruz', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 345 6780', 'lucas.cruz@dlsu.edu.ph', 'Patricia Cruz', '+63 918 987 6544', NULL, 0, 0, '2025-11-24 13:29:18'),
(17, 12012358, 'Camila', 'Hope', 'Castillo', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 456 7891', 'camila.castillo@dlsu.edu.ph', 'Jorge Castillo', '+63 918 098 7655', NULL, 0, 0, '2025-11-24 13:29:18'),
(18, 12012359, 'Sebastian', 'Marco', 'Ramirez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 567 8902', 'sebastian.ramirez@dlsu.edu.ph', 'Diana Ramirez', '+63 918 109 8766', NULL, 0, 0, '2025-11-24 13:29:18'),
(19, 12012360, 'Lucia', 'Pearl', 'Mendoza', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 678 9013', 'lucia.mendoza@dlsu.edu.ph', 'Alberto Mendoza', '+63 918 210 9877', NULL, 0, 0, '2025-11-24 13:29:18'),
(20, 12012361, 'Mateo', 'Andre', 'Navarro', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 789 0124', 'mateo.navarro@dlsu.edu.ph', 'Gloria Navarro', '+63 918 321 0988', NULL, 0, 0, '2025-11-24 13:29:18'),
(21, 12012362, 'Victoria', 'Dawn', 'Santiago', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 890 1235', 'victoria.santiago@dlsu.edu.ph', 'Ernesto Santiago', '+63 918 432 1099', NULL, 0, 0, '2025-11-24 13:29:18'),
(22, 12012363, 'Adrian', 'Paulo', 'Rivera', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 901 2346', 'adrian.rivera@dlsu.edu.ph', 'Rosario Rivera', '+63 918 543 2100', NULL, 0, 0, '2025-11-24 13:29:19'),
(23, 12012364, 'Natalia', 'Claire', 'Gutierrez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 012 3457', 'natalia.gutierrez@dlsu.edu.ph', 'Francisco Gutierrez', '+63 918 654 3211', NULL, 0, 0, '2025-11-24 13:29:19'),
(24, 12012365, 'Leonardo', 'James', 'Fernandez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 123 4569', 'leonardo.fernandez@dlsu.edu.ph', 'Beatriz Fernandez', '+63 918 765 4323', NULL, 0, 0, '2025-11-24 13:29:19'),
(25, 12012366, 'Emilia', 'Grace', 'Jimenez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 234 5670', 'emilia.jimenez@dlsu.edu.ph', 'Alfredo Jimenez', '+63 918 876 5434', NULL, 0, 0, '2025-11-24 13:29:19'),
(26, 12012367, 'Daniel', 'Christian', 'Vargas', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 345 6781', 'daniel.vargas@dlsu.edu.ph', 'Margarita Vargas', '+63 918 987 6545', NULL, 0, 0, '2025-11-24 13:29:19'),
(27, 12012368, 'Emma', 'Louise', 'Castro', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 456 7892', 'emma.castro@dlsu.edu.ph', 'Guillermo Castro', '+63 918 098 7656', NULL, 0, 0, '2025-11-24 13:29:19'),
(28, 12012369, 'Samuel', 'Rafael', 'Ortega', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 567 8903', 'samuel.ortega@dlsu.edu.ph', 'Luz Ortega', '+63 918 109 8767', NULL, 0, 0, '2025-11-24 13:29:20'),
(29, 12012370, 'Olivia', 'Anne', 'Medina', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 678 9014', 'olivia.medina@dlsu.edu.ph', 'Rodrigo Medina', '+63 918 210 9878', NULL, 0, 0, '2025-11-24 13:29:20'),
(30, 12012371, 'Benjamin', 'Isaac', 'Romero', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 789 0125', 'benjamin.romero@dlsu.edu.ph', 'Cecilia Romero', '+63 918 321 0989', NULL, 0, 0, '2025-11-24 13:29:20'),
(31, 12012372, 'Mia', 'Nicole', 'Aguilar', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 890 1236', 'mia.aguilar@dlsu.edu.ph', 'Victor Aguilar', '+63 918 432 1090', NULL, 0, 0, '2025-11-24 13:29:20'),
(32, 12012373, 'Alexander', 'John', 'Delgado', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 901 2347', 'alexander.delgado@dlsu.edu.ph', 'Pilar Delgado', '+63 918 543 2101', NULL, 0, 0, '2025-11-24 13:29:20'),
(33, 12012374, 'Charlotte', 'May', 'Rojas', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 012 3458', 'charlotte.rojas@dlsu.edu.ph', 'Enrique Rojas', '+63 918 654 3212', NULL, 0, 0, '2025-11-24 13:29:20'),
(34, 12012375, 'Nicholas', 'Paul', 'Velasco', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 123 4560', 'nicholas.velasco@dlsu.edu.ph', 'Dolores Velasco', '+63 918 765 4324', NULL, 0, 0, '2025-11-24 13:29:20'),
(35, 12012376, 'Amelia', 'Faith', 'Chavez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 234 5671', 'amelia.chavez@dlsu.edu.ph', 'Hector Chavez', '+63 918 876 5435', NULL, 0, 0, '2025-11-24 13:29:21'),
(36, 12012377, 'Ethan', 'Matthew', 'Jimenez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 345 6782', 'ethan.jimenez2@dlsu.edu.ph', 'Esperanza Jimenez', '+63 918 987 6546', NULL, 0, 0, '2025-11-24 13:29:21'),
(37, 12012378, 'Ava', 'Sophia', 'Salazar', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 456 7893', 'ava.salazar@dlsu.edu.ph', 'Arturo Salazar', '+63 918 098 7657', NULL, 0, 0, '2025-11-24 13:29:21'),
(38, 12012379, 'Joshua', 'Nathan', 'Pena', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 567 8904', 'joshua.pena@dlsu.edu.ph', 'Veronica Pena', '+63 918 109 8768', NULL, 0, 0, '2025-11-24 13:29:21'),
(39, 12012380, 'Harper', 'Elizabeth', 'Vasquez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 678 9015', 'harper.vasquez@dlsu.edu.ph', 'Javier Vasquez', '+63 918 210 9879', NULL, 0, 0, '2025-11-24 13:29:21'),
(40, 12012381, 'Ryan', 'Thomas', 'Ruiz', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 789 0126', 'ryan.ruiz@dlsu.edu.ph', 'Cristina Ruiz', '+63 918 321 0980', NULL, 0, 0, '2025-11-24 13:29:21'),
(41, 12012382, 'Ella', 'Catherine', 'Herrera', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 890 1237', 'ella.herrera@dlsu.edu.ph', 'Armando Herrera', '+63 918 432 1091', NULL, 0, 0, '2025-11-24 13:29:22'),
(42, 12012383, 'Christopher', 'Daniel', 'Espinoza', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 901 2348', 'christopher.espinoza@dlsu.edu.ph', 'Silvia Espinoza', '+63 918 543 2102', NULL, 0, 0, '2025-11-24 13:29:22'),
(43, 12012384, 'Scarlett', 'Ruby', 'Dominguez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 012 3459', 'scarlett.dominguez@dlsu.edu.ph', 'Felipe Dominguez', '+63 918 654 3213', NULL, 0, 0, '2025-11-24 13:29:22'),
(44, 12012385, 'Andrew', 'Joseph', 'Ibarra', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 123 4561', 'andrew.ibarra@dlsu.edu.ph', 'Yolanda Ibarra', '+63 918 765 4325', NULL, 0, 0, '2025-11-24 13:29:22'),
(45, 12012386, 'Lily', 'Jane', 'Cortez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 234 5672', 'lily.cortez@dlsu.edu.ph', 'Marcos Cortez', '+63 918 876 5436', NULL, 0, 0, '2025-11-24 13:29:22'),
(46, 12012387, 'Matthew', 'Alexander', 'Molina', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 345 6783', 'matthew.molina@dlsu.edu.ph', 'Adriana Molina', '+63 918 987 6547', NULL, 0, 0, '2025-11-24 13:29:22'),
(47, 12012388, 'Zoe', 'Marie', 'Valdez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 456 7894', 'zoe.valdez@dlsu.edu.ph', 'Sergio Valdez', '+63 918 098 7658', NULL, 0, 0, '2025-11-24 13:29:23'),
(48, 12012389, 'Anthony', 'Michael', 'Soto', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 567 8905', 'anthony.soto@dlsu.edu.ph', 'Luisa Soto', '+63 918 109 8769', NULL, 0, 0, '2025-11-24 13:29:23'),
(49, 12012390, 'Hannah', 'Grace', 'Campos', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 678 9016', 'hannah.campos@dlsu.edu.ph', 'Gustavo Campos', '+63 918 210 9870', NULL, 0, 0, '2025-11-24 13:29:23'),
(50, 12012391, 'Dylan', 'Christopher', 'Alvarez', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 789 0127', 'dylan.alvarez@dlsu.edu.ph', 'Monica Alvarez', '+63 918 321 0981', NULL, 0, 0, '2025-11-24 13:29:23'),
(51, 12012392, 'Grace', 'Michelle', 'Fuentes', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 890 1238', 'grace.fuentes@dlsu.edu.ph', 'Pablo Fuentes', '+63 918 432 1092', NULL, 0, 0, '2025-11-24 13:29:23'),
(52, 12012393, 'Nathan', 'Ryan', 'Sandoval', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 901 2349', 'nathan.sandoval@dlsu.edu.ph', 'Julia Sandoval', '+63 918 543 2103', NULL, 0, 0, '2025-11-24 13:29:23'),
(53, 12012394, 'Chloe', 'Andrea', 'Vega', 'Gokongwei College of Engineering', 'GCOE-ECEE', 'BS Computer Engineering', 'N/A', '+63 917 012 3450', 'chloe.vega@dlsu.edu.ph', 'Raul Vega', '+63 918 654 3214', NULL, 0, 0, '2025-11-24 13:29:23');

-- --------------------------------------------------------

--
-- Table structure for table `student_academic_performance`
--

CREATE TABLE `student_academic_performance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `term` varchar(20) NOT NULL COMMENT 'e.g., Fall, Spring, Summer',
  `academic_year` varchar(9) NOT NULL COMMENT 'e.g., 2024-2025',
  `term_gpa` decimal(3,2) DEFAULT NULL,
  `cumulative_gpa` decimal(3,2) DEFAULT NULL,
  `total_units_taken` int(11) DEFAULT 0,
  `total_units_passed` int(11) DEFAULT 0,
  `total_units_failed` int(11) DEFAULT 0,
  `courses_enrolled` int(11) DEFAULT 0,
  `courses_passed` int(11) DEFAULT 0,
  `courses_failed` int(11) DEFAULT 0,
  `academic_standing` varchar(50) DEFAULT NULL COMMENT 'e.g., Good Standing, Probation, etc.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
-- Table structure for table `student_gpa`
--

CREATE TABLE `student_gpa` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `form_id` int(11) DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL COMMENT 'Calculated GPA (0.00 to 4.00)',
  `total_units` int(11) DEFAULT 0,
  `courses_taken` int(11) DEFAULT 0,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `reupload_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `study_plans`
--

INSERT INTO `study_plans` (`id`, `student_id`, `term`, `academic_year`, `grade_screenshot`, `submission_date`, `certified`, `wants_meeting`, `selected_schedule_id`, `cleared`, `adviser_feedback`, `screenshot_reupload_requested`, `reupload_reason`) VALUES
(1, 2, 'Term 2', '2024-2025', NULL, '2025-11-24 10:34:27', 1, 1, NULL, 1, '', 0, NULL);

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
(1, NULL, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-11-24 09:37:00'),
(2, '12012345', '12012345', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2025-11-24 09:37:00'),
(3, '10012345', '10012345', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'professor', '2025-11-24 09:37:01'),
(5, '12012346', '12012346', '$2y$10$IGVDNBi5AUmYMm79LcYJxeT/z0duc7Us3NpNQmshj7l6R9QxKKn3q', 'student', '2025-11-24 13:29:16'),
(6, '12012347', '12012347', '$2y$10$z6z4xaKVQmhEH99pJbj98uczftdGdLL8aIqDjDRzkA5bu7XkywAK6', 'student', '2025-11-24 13:29:16'),
(7, '12012348', '12012348', '$2y$10$0bN.iBEJF98HhYMXXvcmOOgO50t/wehiVOm0y8.zT9hkQjnXBZZ.C', 'student', '2025-11-24 13:29:16'),
(8, '12012349', '12012349', '$2y$10$F/Vb7VwqUBfic/x0rS0QQeRejL/.doE80wezRYvLOChBW5Bve7I9i', 'student', '2025-11-24 13:29:16'),
(9, '12012350', '12012350', '$2y$10$R5O3icJD2HDns4UlJUFDDeZ/bzxvVquyGks0X3.8coR8d2ElS03yS', 'student', '2025-11-24 13:29:17'),
(10, '12012351', '12012351', '$2y$10$CQPMUCUuvrFf3ikd2mgBNOa5VuPelVWAbTJt7ztr4xVqvhUW8vq7C', 'student', '2025-11-24 13:29:17'),
(11, '12012352', '12012352', '$2y$10$d4OMZAfKRh916OIKQBj0getu9d/DJOZ4KAdxF7alRHwZxFAufBInq', 'student', '2025-11-24 13:29:17'),
(12, '12012353', '12012353', '$2y$10$r3iJcBJKyavjuH8KomoZ0.EhMbIpeYsNCPlPxU/wjZWNjjNaRdl/a', 'student', '2025-11-24 13:29:17'),
(13, '12012354', '12012354', '$2y$10$RWWWbn0O3R8MTdeW7ova..UAuS6s4axa3rL9v9mgUYMi8WUcu3D1u', 'student', '2025-11-24 13:29:17'),
(14, '12012355', '12012355', '$2y$10$yMiuvLmCoAyXeAQVNufyAulML3mhBsFO4TU9fgWxlkvdYuyjHVYFu', 'student', '2025-11-24 13:29:17'),
(15, '12012356', '12012356', '$2y$10$OcE3EgL2LBM7gghMbXexf.DxyoVqagyjCpNPpUfzdtAUaNyOQ3OmC', 'student', '2025-11-24 13:29:18'),
(16, '12012357', '12012357', '$2y$10$FVNIwdEdgK8OE/jXle9IuOp6wWuX7STIh1ppVlDFOTh28cGvRWBfC', 'student', '2025-11-24 13:29:18'),
(17, '12012358', '12012358', '$2y$10$6bzMmAloqRWjd3WrTrRv6.sRklarenkPml1Ae0DmSINRNQgZaS05G', 'student', '2025-11-24 13:29:18'),
(18, '12012359', '12012359', '$2y$10$XV0T6HCP3L6WtHjDkDz./ePk.0d/KxwavOPUedjVRIjqiY2L1N7eS', 'student', '2025-11-24 13:29:18'),
(19, '12012360', '12012360', '$2y$10$R8w8LmjhQOglknakYnauTuZZLUvy2Ry.Pp7.aj/B3UE6EzaK8dqxK', 'student', '2025-11-24 13:29:18'),
(20, '12012361', '12012361', '$2y$10$n.mCY8O/DYUtZ4VEzU3wCenWN6pRug7wxJs2B3UIvk3/TMFS38H8y', 'student', '2025-11-24 13:29:18'),
(21, '12012362', '12012362', '$2y$10$RauKjGd.rN3GRciTNz6UUO6jWwBz97W1Jq61g1.eGzYRhv8O2wCpy', 'student', '2025-11-24 13:29:18'),
(22, '12012363', '12012363', '$2y$10$7.qiMQqeL51UGnJVehHkduV2IS6dPSR9lXttzfmfSi6uk2np9bWUm', 'student', '2025-11-24 13:29:19'),
(23, '12012364', '12012364', '$2y$10$CqOkj9s4JmHGBPEYHlTJB.NPs6P3cSVudDUqPUmzKkiJPuFwrjZnW', 'student', '2025-11-24 13:29:19'),
(24, '12012365', '12012365', '$2y$10$m8jsIfmy/dT28qJvhrvV6uf3Msxpz2N0jB1QzOt5YfIrHsM29NU.S', 'student', '2025-11-24 13:29:19'),
(25, '12012366', '12012366', '$2y$10$1ZWsLgUH29LntO84uqufuu4bG.yssJc00FrDCZ9vHfICxFy8uWyGC', 'student', '2025-11-24 13:29:19'),
(26, '12012367', '12012367', '$2y$10$N3aHZAbzjRnqYvlU1b7Xo.BDs3Xvs4qyn3vTPCzCNbsbP0ytzD.ji', 'student', '2025-11-24 13:29:19'),
(27, '12012368', '12012368', '$2y$10$j1oLbeN8zMbTPkkYgtQESOFXTGHYfsB1m4J/paLWdgGRsnQ8OClkO', 'student', '2025-11-24 13:29:19'),
(28, '12012369', '12012369', '$2y$10$JFo5P9fmXnj5N2BElWgn6uG/GbqRZaocnIyQnJpJI2NK5l1hPqNRS', 'student', '2025-11-24 13:29:20'),
(29, '12012370', '12012370', '$2y$10$ijvgWgoMbGo6RTO8VJgQYetoRN7UmgUrARCbnYl/Ytm7tyskdHf/a', 'student', '2025-11-24 13:29:20'),
(30, '12012371', '12012371', '$2y$10$60XyTx1U39wjz/CJNVz0x.y8rsXCycjD2DaVDTOWlfxAHoPCELuFm', 'student', '2025-11-24 13:29:20'),
(31, '12012372', '12012372', '$2y$10$.aaGTJcSJ0UzeYjq4gKwE.eMBJGeBwhzSX1n2nKth4v7KcL1/MlMy', 'student', '2025-11-24 13:29:20'),
(32, '12012373', '12012373', '$2y$10$OS4gHYMm0Rhiyk28Daq8W.Gp.Hlx2TWzJpJE3YU9Lfmfb1GKqOpB6', 'student', '2025-11-24 13:29:20'),
(33, '12012374', '12012374', '$2y$10$EgU1fk54311t1V4AuYCZUuF1gi7L6UiqhfsWrj06XU4fEozBorWT2', 'student', '2025-11-24 13:29:20'),
(34, '12012375', '12012375', '$2y$10$.38EBBtp52/x0cacl/XbJet9Of18AU35ATyDQxRSSWTh4CLLZNmFa', 'student', '2025-11-24 13:29:20'),
(35, '12012376', '12012376', '$2y$10$n31.ALRGkQQ3Ym6uaIr8s.td6FtlZ3GWbYFDa.sW2ex8BOPih7GKC', 'student', '2025-11-24 13:29:21'),
(36, '12012377', '12012377', '$2y$10$FZllUsk0rhKfeslVu96mPeb5MUpZ5VgrsWVsZTZH0AQlh4/N3X/m2', 'student', '2025-11-24 13:29:21'),
(37, '12012378', '12012378', '$2y$10$CjaHlnVQBCTC9M.kf78wGO9UF2kfkgQ.uRmwIRnwQqpDOAEfSuFEq', 'student', '2025-11-24 13:29:21'),
(38, '12012379', '12012379', '$2y$10$8cAuC/CjRTSvu.rHQ..v4e7cL1Nx6kgo4N05EuwVODzbCjjRa6hZK', 'student', '2025-11-24 13:29:21'),
(39, '12012380', '12012380', '$2y$10$3wZPKlafLifYIQvIR3ly0.HmFlcSu9fgN9ThqWo3arUuR8MtyGouy', 'student', '2025-11-24 13:29:21'),
(40, '12012381', '12012381', '$2y$10$vwKJFRTgLmvPoqrLOBaQwevRXY6KhYbcfrlL524u.OxguDdHeVOc6', 'student', '2025-11-24 13:29:21'),
(41, '12012382', '12012382', '$2y$10$CtoB93DDSxrazU8TsQtple3C.nlsOSri1rbaq2aIDIGnMDa727xJC', 'student', '2025-11-24 13:29:22'),
(42, '12012383', '12012383', '$2y$10$zIez52MQwh5RCcpXxr/houopiY7QmDGz8C7O.D5Iu7YBJFMiDYV7i', 'student', '2025-11-24 13:29:22'),
(43, '12012384', '12012384', '$2y$10$S2ZEIL9jdaQ7/P1ligA9xOj4jigDN.8edgICJBW4yF8jwFzQ4gSKa', 'student', '2025-11-24 13:29:22'),
(44, '12012385', '12012385', '$2y$10$H8TKGrsAITXM06GDkYsFI.VGTLHHeDWnSpIiY14Ql42K2UCIoGNka', 'student', '2025-11-24 13:29:22'),
(45, '12012386', '12012386', '$2y$10$yfII8a3SZhWIrhGE9G69YOPcBUnSSyg824rOJV5jtk//04ObmuSw2', 'student', '2025-11-24 13:29:22'),
(46, '12012387', '12012387', '$2y$10$yiPxsihSfaU/rNgybE2yUublAHBIBE1aRmAFTdjEcQ03A2tLu6ALm', 'student', '2025-11-24 13:29:22'),
(47, '12012388', '12012388', '$2y$10$rWtnzqP/fp1owvhfEvtGNuM3GSRmPDID1wh.X64My97WnQSNISReS', 'student', '2025-11-24 13:29:23'),
(48, '12012389', '12012389', '$2y$10$W/NBKPAN2aIPYhx8E5VUueoy0Ewv3.//AkuE3cPdoO7hoHIHwOrZu', 'student', '2025-11-24 13:29:23'),
(49, '12012390', '12012390', '$2y$10$VKvXynxXAOoQYqFBkwYkGOu1hFkO56.U2dZMhmgb.UzNfMDh2.GzC', 'student', '2025-11-24 13:29:23'),
(50, '12012391', '12012391', '$2y$10$aQhNT4vxGBDqXJwtHR52LeH7KdgQf1a6YKhEcWgqByNybJ1KW9PgS', 'student', '2025-11-24 13:29:23'),
(51, '12012392', '12012392', '$2y$10$2F8apmhjQ1pkGpkXpeqZqOXo8LToVtmHsAA1Z.uEXJiTaS4plJpaG', 'student', '2025-11-24 13:29:23'),
(52, '12012393', '12012393', '$2y$10$Ni.KwSu0QOmwv4O/V3K1.OyzyXYJIQYXOYUhpfmLmXVKu04dT3Td2', 'student', '2025-11-24 13:29:23'),
(53, '12012394', '12012394', '$2y$10$tq7uY7KdUXYi96UYQD7.nOkJjyjxAp7YJ6KKFyKU.jQf2WV/nHGC.', 'student', '2025-11-24 13:29:23'),
(55, '10012346', '10012346', '$2y$10$i.UogS7f5czuo7.LJRrgcODchVWwI4Ade1IAI/.k/ee2BXPmOi9CW', 'professor', '2025-11-24 13:30:00'),
(56, '10012347', '10012347', '$2y$10$6zPLC.Wi9t3rICq/Vp2OG..TUKTVSwFBeKJW3ygN7n3zViRqTbv6C', 'professor', '2025-11-24 13:30:00'),
(57, '10012348', '10012348', '$2y$10$zyBKYw9pR8ECmT5OxrVUR.uWZrwF8X53ROOhND2R1ImefTvOcASum', 'professor', '2025-11-24 13:30:01'),
(58, '10012349', '10012349', '$2y$10$y8hvFX1wbqWELjGIxzqLLOAjAmOTwYV85NRm8Lw90fcVbGSEKtsmq', 'professor', '2025-11-24 13:30:01'),
(59, '10012350', '10012350', '$2y$10$8cMXJT0U3FOObMF7XhbIp.YRVz5Q7OeLTDRG/WqP5WnNfKE4F8qPi', 'professor', '2025-11-24 13:30:01'),
(60, '10012351', '10012351', '$2y$10$XQSoR.83YEc6epUzNrQInOCkKlwvJVczLD3iKxEvoXwM2UB9oQd9y', 'professor', '2025-11-24 13:30:01'),
(61, '10012352', '10012352', '$2y$10$wQZE8.IPIfrCzck.PZ7aD.AAKzTgT0pf3jCYfWbEwCO.G45MEouze', 'professor', '2025-11-24 13:30:01'),
(62, '10012353', '10012353', '$2y$10$NcFgIouHIro7A3TBwwPRH.JhUj0hT8XXN6LarFIh2Jv6HMSPjpaoy', 'professor', '2025-11-24 13:30:01'),
(63, '10012354', '10012354', '$2y$10$a4WecZT3A7bREC8ywG7Z2.2FRVdWSt0Sf1PNxSWIE4GbsPQp5sqUu', 'professor', '2025-11-24 13:30:02'),
(64, '10012355', '10012355', '$2y$10$9zQ5aEllV19OQzaS/m9M2.OQrFcde7pwQDgjisQmG/.dqDJlLrTF6', 'professor', '2025-11-24 13:30:02'),
(65, '10012356', '10012356', '$2y$10$lz2tP/9QNtEbj2knHQKtDu6SHiVlFc2NXJOsik7IO7CWzg0AgFVEy', 'professor', '2025-11-24 13:30:02'),
(66, '10012357', '10012357', '$2y$10$JMN2XkAKPw7Ft6xcQ6cJZOy.VLCIB0/S/5nonMDXWpHGjwZ8JmFBu', 'professor', '2025-11-24 13:30:02'),
(67, '10012358', '10012358', '$2y$10$j7NpeMWWm6O9oX8S8EBzbulIzuX/KTzWXqL5996ufxnBWgo81yvqW', 'professor', '2025-11-24 13:30:02'),
(68, '10012359', '10012359', '$2y$10$vDLYS4I905QTsQ87uMQ29uh2qVU/ICX7clrhPanficPiDDeSm60Ii', 'professor', '2025-11-24 13:30:02'),
(69, '10012360', '10012360', '$2y$10$yGTeyY4uxUmHLJNyydeS5OaSbhUZmNXNhXSlWQAb3LiL3dEybOEyC', 'professor', '2025-11-24 13:30:03'),
(70, '10012361', '10012361', '$2y$10$fPELUlff8GTJJMusLtkyc.psIrpJ6144QWvNLbcMEairwDIsnie0G', 'professor', '2025-11-24 13:30:03'),
(71, '10012362', '10012362', '$2y$10$Z6ljxZCBsIV7BKMNyrDgD.ahnpEbZzOyGPmMY4MUXKrddctQiqPDi', 'professor', '2025-11-24 13:30:03'),
(72, '10012363', '10012363', '$2y$10$zGQSuzFnUl3WmQ5.8ZNIZ.WXMGn70U29Yl05HF/o1iriO5yu4poly', 'professor', '2025-11-24 13:30:03'),
(73, '10012364', '10012364', '$2y$10$p4FqLhPvnfKEvcSbIghh3uSv88hw4zuzMwngzBPBA7AdDR5MOZS0C', 'professor', '2025-11-24 13:30:03'),
(74, '10012365', '10012365', '$2y$10$ROu/Qjd1HmS8SW9hSw/NEOZ/wwIrTiYDmRDGro7IXoeV.0dfrtvxC', 'professor', '2025-11-24 13:30:03'),
(75, '10012366', '10012366', '$2y$10$j/Si/QnJsdyZ1.KP.KSVFuU7StlHf5.BtpSWAnvBRYayTs2G5meoa', 'professor', '2025-11-24 13:30:03'),
(76, '10012367', '10012367', '$2y$10$Snzz8hN0dLWi6M2UX/hoHOpaj5IetxLGOxSaIlx9AllIZiF5KwIMq', 'professor', '2025-11-24 13:30:04'),
(77, '10012368', '10012368', '$2y$10$6PkjlYNbK6KnFY3DGg4pqezio5EdKmnjJ.cJ51OmpFBiHSDi0qUDW', 'professor', '2025-11-24 13:30:04'),
(78, '10012369', '10012369', '$2y$10$WrBcAFJcNSw98gBXNaH/YOzY5IGk.s5ijK3uXpXGoPFGJ8SnuwpZe', 'professor', '2025-11-24 13:30:04'),
(79, '10012370', '10012370', '$2y$10$6QzFmUlTToYQtWHuy3aQOu5qv4DyckidGLclQQiimomUeomAfvYqy', 'professor', '2025-11-24 13:30:04'),
(80, '10012371', '10012371', '$2y$10$9MbWVyg4vOWyUNliAG.Yn.8G5Z3OsNEiWujbOJEKQ2GUl1YHkKei.', 'professor', '2025-11-24 13:30:04'),
(81, '10012372', '10012372', '$2y$10$4z8auZHFR2RHdIdCyHGP1.YHKPVnXGbO50Fa86VupEUBipYXIHQcS', 'professor', '2025-11-24 13:30:04'),
(82, '10012373', '10012373', '$2y$10$cDslj0B.uBFva7mUEbnbVODihsEroSzfv2wEw/x45lnXLXTMYLb7C', 'professor', '2025-11-24 13:30:05'),
(83, '10012374', '10012374', '$2y$10$gOpbLGfQ0GgR4mGAvn8sMOhabnulzmmP/x3DdJJEqNVFuthfrjTFq', 'professor', '2025-11-24 13:30:05'),
(84, '10012375', '10012375', '$2y$10$tNsSUPvDAaOWazGRkBeSQ.8RXpxi8u7zEiOfZ9iEBIwbIlRnjIc9i', 'professor', '2025-11-24 13:30:05'),
(85, '10012376', '10012376', '$2y$10$ohceViCM.aqV/92aRJyHduMO0QO.yEFLQ5mdJW/H5bp7TFB.MIef.', 'professor', '2025-11-24 13:30:05'),
(86, '10012377', '10012377', '$2y$10$GSYgGXMWMVS0pMF/E.hwVumBdhygy8yjRSH8EnwC/GxNdcVWYAclS', 'professor', '2025-11-24 13:30:05'),
(87, '10012378', '10012378', '$2y$10$IBm59vqGp2cQWnTIs2vHiOpJlSQyr10yYeOx5OTuRRN76GjdbeM1K', 'professor', '2025-11-24 13:30:05'),
(88, '10012379', '10012379', '$2y$10$ioLCF80PDJ4XDbhnmXMs8ePC7dvr49H6iuWQ6VjhNyWZs7YzUoZU.', 'professor', '2025-11-24 13:30:06'),
(89, '10012380', '10012380', '$2y$10$SVldsFyGlCkOH6oPXWevbOXHHXWUTi4QbAcl2R./AE.KorEHABFU2', 'professor', '2025-11-24 13:30:06'),
(90, '10012381', '10012381', '$2y$10$t9p4i/ASuKkGHO6OkB3.gur2OBnUHL4jSO0FxM/xu/dDNoySyTN1y', 'professor', '2025-11-24 13:30:06'),
(91, '10012382', '10012382', '$2y$10$cnhub4tEl8ZBde3Yf1FkFu/2PVFJPexYhU7OYBKKUjIC4.lEnZQ5C', 'professor', '2025-11-24 13:30:06'),
(92, '10012383', '10012383', '$2y$10$I5a.Q5f3YnDz7xanGGkGz.GWMu.t5FQ272T/L.i8DBAGJCAF5uSqW', 'professor', '2025-11-24 13:30:06'),
(93, '10012384', '10012384', '$2y$10$RDij2YlJlmJ/3YAzV/WF/uecwBphGk9XDY7O4JaJ8W3qFXIvL8n7C', 'professor', '2025-11-24 13:30:06'),
(94, '10012385', '10012385', '$2y$10$ZNpftFOa1SIO4C0KCwIRyO5SDiN9jgmLOMFeFEN30pxGoeCkAgIhW', 'professor', '2025-11-24 13:30:06'),
(95, '10012386', '10012386', '$2y$10$I8oLM1xoC2CaH305X5bCFu.Vd0oR5WAozH8eJVF26rkwdiA8xfW9K', 'professor', '2025-11-24 13:30:07'),
(96, '10012387', '10012387', '$2y$10$WJf/EHaN2T08Brn7bRuoqeAH01rXizuVA99M5J5hA8u9nNWjP/hYy', 'professor', '2025-11-24 13:30:07'),
(97, '10012388', '10012388', '$2y$10$6S9V8JppVRKf/BxYP1/KdOuL8dTRHeOfBNDwVH8YNC/qIEAk7gsie', 'professor', '2025-11-24 13:30:07'),
(98, '10012389', '10012389', '$2y$10$7IGA/IQvFbKnmqOG8oJS.OZs2p5OevpmZ5Q/zWLG.iUlcoqI8wpIu', 'professor', '2025-11-24 13:30:07'),
(99, '10012390', '10012390', '$2y$10$AqkOFYBozaVQg1g5Yo.FquSL0d1NbkF.Yfanxm2Njeek/842oF.4O', 'professor', '2025-11-24 13:30:07'),
(100, '10012391', '10012391', '$2y$10$UObGBlR9SJyqxQEijOqfjeZ0Jm83GtR9Bv2rUfjm2iZ5CjpXUcG1C', 'professor', '2025-11-24 13:30:07'),
(101, '10012392', '10012392', '$2y$10$JCwKKPksTi8gWYTT6Sgo5eOimVp.HtowItgyTzzue4tm7hct2IjIi', 'professor', '2025-11-24 13:30:08'),
(102, '10012393', '10012393', '$2y$10$YjNdUCGTtozdqGGZ/nRG9ept395aDdl4TuljrQNf4hngfCpf5FW1W', 'professor', '2025-11-24 13:30:08'),
(103, '10012394', '10012394', '$2y$10$ubkTcx6WIAJAQdjs0k9O0.F30KxIDZRVvKuPOh6ZSeKfy/mR0fF4O', 'professor', '2025-11-24 13:30:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_advising_forms`
--
ALTER TABLE `academic_advising_forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_adviser_id` (`adviser_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted_at` (`submitted_at`);

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
-- Indexes for table `advising_form_courses`
--
ALTER TABLE `advising_form_courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_form_id` (`form_id`),
  ADD KEY `idx_course_type` (`course_type`);

--
-- Indexes for table `advising_form_prerequisites`
--
ALTER TABLE `advising_form_prerequisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course` (`course_id`);

--
-- Indexes for table `advising_schedules`
--
ALTER TABLE `advising_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Indexes for table `student_appointments`
--
ALTER TABLE `student_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `student_id` (`student_id`);

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
-- Indexes for table `student_academic_performance`
--
ALTER TABLE `student_academic_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_term` (`student_id`,`term`,`academic_year`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `student_advising_booklet`
--
ALTER TABLE `student_advising_booklet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_year` (`student_id`,`academic_year`,`term`);

--
-- Indexes for table `student_concerns`
--
ALTER TABLE `student_concerns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `study_plan_id` (`study_plan_id`);

--
-- Indexes for table `student_gpa`
--
ALTER TABLE `student_gpa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_form_id` (`form_id`);

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
-- AUTO_INCREMENT for table `academic_advising_forms`
--
ALTER TABLE `academic_advising_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `advising_deadlines`
--
ALTER TABLE `advising_deadlines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advising_form_courses`
--
ALTER TABLE `advising_form_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `advising_form_prerequisites`
--
ALTER TABLE `advising_form_prerequisites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `advising_schedules`
--
ALTER TABLE `advising_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_appointments`
--
ALTER TABLE `student_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bulk_upload_history`
--
ALTER TABLE `bulk_upload_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `student_academic_performance`
--
ALTER TABLE `student_academic_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `student_gpa`
--
ALTER TABLE `student_gpa`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_advising_forms`
--
ALTER TABLE `academic_advising_forms`
  ADD CONSTRAINT `academic_advising_forms_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `academic_advising_forms_ibfk_2` FOREIGN KEY (`adviser_id`) REFERENCES `professors` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `advising_form_courses`
--
ALTER TABLE `advising_form_courses`
  ADD CONSTRAINT `advising_form_courses_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `academic_advising_forms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `advising_form_prerequisites`
--
ALTER TABLE `advising_form_prerequisites`
  ADD CONSTRAINT `advising_form_prerequisites_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `advising_form_courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `advising_schedules`
--
ALTER TABLE `advising_schedules`
  ADD CONSTRAINT `advising_schedules_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_appointments`
--
ALTER TABLE `student_appointments`
  ADD CONSTRAINT `student_appointments_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `advising_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_appointments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `student_academic_performance`
--
ALTER TABLE `student_academic_performance`
  ADD CONSTRAINT `student_academic_performance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `student_gpa`
--
ALTER TABLE `student_gpa`
  ADD CONSTRAINT `student_gpa_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_gpa_ibfk_2` FOREIGN KEY (`form_id`) REFERENCES `academic_advising_forms` (`id`) ON DELETE CASCADE;

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
