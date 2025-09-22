-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 14, 2025 at 05:51 PM
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
-- Database: `learning_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `achievement_type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'trophy',
  `color` varchar(20) DEFAULT 'gold',
  `points_awarded` int(11) DEFAULT 0,
  `earned_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `achievements`
--

INSERT INTO `achievements` (`id`, `student_id`, `achievement_type`, `title`, `description`, `icon`, `color`, `points_awarded`, `earned_date`) VALUES
(1, 10, 'first_login', 'Welcome Aboard!', 'Completed your first login to the learning platform.', 'rocket', 'primary', 10, '2025-09-14'),
(2, 10, 'quiz_streak', 'Quiz Master', 'Scored above 80% on multiple quizzes.', 'brain', 'warning', 50, '2025-09-14'),
(3, 10, 'social_learner', 'Social Learner', 'Actively participated in chat discussions.', 'comments', 'info', 25, '2025-09-14');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `subject_id` int(11) NOT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `due_date` datetime NOT NULL,
  `max_marks` int(11) DEFAULT 100,
  `instructions` text DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `status` enum('draft','published','closed') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `title`, `description`, `subject_id`, `lesson_id`, `due_date`, `max_marks`, `instructions`, `attachment_path`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Introduction to Programming Logic and Variables', 'Create simple programs to demonstrate understanding of variables, data types, and basic operations.', 20, NULL, '2025-10-06 20:00:00', 100, 'Write programs in Python to solve the following problems: 1) Calculate the area of different geometric shapes using variables and user input. 2) Create a temperature conversion program that converts between Celsius, Fahrenheit, and Kelvin. 3) Build a simple calculator that performs basic arithmetic operations. 4) Write a program to determine if a number is even or odd. 5) Create a program that calculates the compound interest for a given principal, rate, and time period.\r\nRequirements: Use proper variable naming conventions, include comments explaining your code, handle user input validation, and provide clear output messages.\r\nSubmission Format: Submit .py files with proper documentation and test cases.\r\nDue Date: 2 weeks from assignment date', 'uploads/assignments/68c6c5b4dd756_1757857204.pdf', 'published', 1, '2025-09-14 13:40:04', '2025-09-14 13:40:04'),
(3, 'Introduction to Programming Logic and Variables', 'Create simple programs to demonstrate understanding of variables, data types, and basic operations.', 20, NULL, '2025-10-06 20:00:00', 100, 'Write programs in Python to solve the following problems: 1) Calculate the area of different geometric shapes using variables and user input. 2) Create a temperature conversion program that converts between Celsius, Fahrenheit, and Kelvin. 3) Build a simple calculator that performs basic arithmetic operations. 4) Write a program to determine if a number is even or odd. 5) Create a program that calculates the compound interest for a given principal, rate, and time period.\r\nRequirements: Use proper variable naming conventions, include comments explaining your code, handle user input validation, and provide clear output messages.\r\nSubmission Format: Submit .py files with proper documentation and test cases.\r\nDue Date: 2 weeks from assignment date', 'uploads/assignments/68c6c5e772475_1757857255.pdf', 'published', 1, '2025-09-14 13:40:55', '2025-09-14 13:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `marks_obtained` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `status` enum('submitted','graded','late') DEFAULT 'submitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment_submissions`
--

INSERT INTO `assignment_submissions` (`id`, `assignment_id`, `student_id`, `submission_text`, `file_path`, `original_filename`, `submitted_at`, `marks_obtained`, `feedback`, `graded_by`, `graded_at`, `status`) VALUES
(1, 1, 10, 'fggg', 'uploads/submissions/10_1_1757843687.pdf', 'State the four core values of the Agile Manifesto 32232.pdf', '2025-09-14 09:54:47', NULL, NULL, NULL, NULL, 'submitted');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `certificate_type` enum('course_completion','quiz_mastery','participation','achievement') DEFAULT 'course_completion',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `criteria_met` text DEFAULT NULL,
  `issued_date` date DEFAULT curdate(),
  `certificate_code` varchar(50) DEFAULT NULL,
  `status` enum('earned','revoked') DEFAULT 'earned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `student_id`, `certificate_type`, `title`, `description`, `subject_id`, `criteria_met`, `issued_date`, `certificate_code`, `status`, `created_at`) VALUES
(1, 10, 'participation', 'Active Learner Certificate', 'Awarded for consistent participation in learning activities and discussions.', NULL, 'Participated in chat discussions and submitted assignments regularly', '2025-09-14', 'CERT-68C6600369848', 'earned', '2025-09-14 06:26:11'),
(2, 10, 'course_completion', 'Mathematics Fundamentals', 'Successfully completed all mathematics lessons and passed related assessments.', NULL, 'Completed all lessons and achieved 80% average on quizzes', '2025-09-14', 'CERT-68C660036A322', 'earned', '2025-09-14 06:26:11');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `user_id`, `message`, `created_at`) VALUES
(1, 10, 'hi', '2025-09-14 06:11:42'),
(2, 1, '[ADMIN ANNOUNCEMENT] why', '2025-09-14 06:23:13'),
(3, 10, 'hii sasa', '2025-09-14 08:31:16'),
(4, 10, 'loll', '2025-09-14 08:31:30'),
(5, 10, 'ko', '2025-09-14 13:56:32');

-- --------------------------------------------------------

--
-- Table structure for table `daily_leaderboard`
--

CREATE TABLE `daily_leaderboard` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `points` int(11) DEFAULT 0,
  `quiz_completed` int(11) DEFAULT 0,
  `lesson_completed` int(11) DEFAULT 0,
  `rank_position` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `lesson_order` int(11) DEFAULT 1,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text NOT NULL,
  `presentation` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `google_form_url` varchar(500) DEFAULT NULL,
  `google_form_embed_code` text DEFAULT NULL,
  `created_by` int(11) DEFAULT 1,
  `status` enum('active','inactive','pending','rejected') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duration` int(11) DEFAULT 15
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `subject_id`, `lesson_order`, `title`, `description`, `content`, `presentation`, `category`, `difficulty`, `google_form_url`, `google_form_embed_code`, `created_by`, `status`, `created_at`, `updated_at`, `duration`) VALUES
(13, 20, 1, 'Introduction to Cloud Computing', 'Learn the fundamental concepts of cloud computing, its evolution, and why it\'s revolutionizing modern business technology', '<p><b>Cloud computing is a&nbsp;technology that uses&nbsp;remote servers hosted on the internet to store,&nbsp;manage, and process data rather&nbsp;than using local servers or personal computers. It provides&nbsp;on-demand access to computing resources including&nbsp;servers, storage, databases, networking, software, analytics, and intelligence over&nbsp;the internet.&nbsp;The concept evolved from traditional computing models&nbsp;where businesses had to invest heavily&nbsp;in physical infrastructure.&nbsp;Today, cloud computing enables organizations&nbsp;to access technology&nbsp;services without&nbsp;the need for upfront capital investment in&nbsp;hardware and software. Key benefits&nbsp;include cost&nbsp;savings, scalability, flexibility, automatic&nbsp;updates, and improved collaboration.&nbsp;Cloud computing has transformed how businesses operate by&nbsp;providing instant&nbsp;access to resources, enabling remote&nbsp;work, and supporting&nbsp;digital transformation initiatives across&nbsp;all industries</b></p>', 'uploads/presentations/68c6c30d3b4f5_1757856525.mp4', NULL, 'beginner', NULL, NULL, 1, '', '2025-09-14 13:28:45', '2025-09-14 13:28:45', 15);

-- --------------------------------------------------------

--
-- Table structure for table `lesson_completions`
--

CREATE TABLE `lesson_completions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_spent` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_files`
--

CREATE TABLE `lesson_files` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` enum('presentation','video','audio','document','image') NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_files`
--

INSERT INTO `lesson_files` (`id`, `lesson_id`, `file_name`, `original_name`, `file_type`, `file_size`, `file_path`, `mime_type`, `uploaded_at`) VALUES
(1, 11, 'sample_presentation.pdf', 'Introduction to Subject - Presentation.pdf', 'presentation', 2048000, '../uploads/lessons/sample_presentation.pdf', 'application/pdf', '2025-09-14 07:14:49'),
(2, 11, 'sample_video.mp4', 'Lesson Tutorial Video.mp4', 'video', 15728640, '../uploads/lessons/sample_video.mp4', 'video/mp4', '2025-09-14 07:14:49'),
(3, 11, 'sample_audio.mp3', 'Audio Lecture Notes.mp3', 'audio', 5242880, '../uploads/lessons/sample_audio.mp3', 'audio/mpeg', '2025-09-14 07:14:49'),
(4, 11, 'sample_document.pdf', 'Additional Reading Material.pdf', 'document', 1024000, '../uploads/lessons/sample_document.pdf', 'application/pdf', '2025-09-14 07:14:49');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_progress`
--

CREATE TABLE `lesson_progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `progress_percentage` int(11) DEFAULT 0,
  `time_spent` int(11) DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_progress`
--

INSERT INTO `lesson_progress` (`id`, `student_id`, `lesson_id`, `status`, `progress_percentage`, `time_spent`, `started_at`, `completed_at`) VALUES
(1, 10, 11, 'in_progress', 75, 0, '2025-09-14 06:48:47', NULL),
(2, 10, 5, 'in_progress', 75, 0, '2025-09-14 07:06:28', NULL),
(3, 10, 10, 'in_progress', 25, 0, '2025-09-14 07:12:36', NULL),
(4, 10, 9, 'in_progress', 25, 0, '2025-09-14 07:12:41', NULL),
(5, 10, 6, 'in_progress', 25, 0, '2025-09-14 07:12:47', NULL),
(6, 10, 12, 'in_progress', 50, 0, '2025-09-14 07:54:30', NULL),
(7, 10, 13, 'in_progress', 50, 0, '2025-09-14 13:54:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lesson_suggestions`
--

CREATE TABLE `lesson_suggestions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `meeting_date` datetime NOT NULL,
  `duration` int(11) DEFAULT 60,
  `meeting_link` varchar(500) DEFAULT NULL,
  `meeting_password` varchar(100) DEFAULT NULL,
  `max_participants` int(11) DEFAULT 50,
  `status` enum('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `title`, `description`, `subject_id`, `lesson_id`, `meeting_date`, `duration`, `meeting_link`, `meeting_password`, `max_participants`, `status`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(9, 'colud Computing Introduction', 'DevOps combines development and operations practices to shorten development lifecycles and provide continuous delivery with high quality. Cloud computing provides the ideal platform for DevOps by offering programmable infrastructure, automated provisioning, and scalable resources. Infrastructure as Code (IaC) uses code to provision and manage cloud resources, ensuring consistent and repeatable deployments. Tools like Terraform, AWS CloudFormation, and Azure Resource Manager enable declarative infrastructure management. Continuous Integration (CI) automatically builds and tests code changes, using cloud-based build services for scalability and cost-effectiveness. Continuous Deployment (CD) automates application deployment to various environments, leveraging cloud platforms for rapid scaling and rollback capabilities. Configuration management tools like Ansible, Chef, and Puppet maintain consistent system configurations across cloud environments. Monitoring and logging services provide real-time visibility into application performance, infrastructure health, and user experience. Automated testing includes unit tests, integration tests, and performance tests executed in cloud environments that mirror production systems. Blue-green deployments and canary releases minimize deployment risks by gradually shifting traffic to new versions.', 20, NULL, '2025-09-30 20:00:00', 15, 'https://testnet.humanity.org/dashboard', '', 10, 'approved', 1, 1, '2025-09-14 13:48:35', '2025-09-14 13:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_participants`
--

CREATE TABLE `meeting_participants` (
  `id` int(11) NOT NULL,
  `meeting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NULL DEFAULT NULL,
  `left_at` timestamp NULL DEFAULT NULL,
  `attendance_status` enum('registered','attended','absent') DEFAULT 'registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meeting_requests`
--

CREATE TABLE `meeting_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `time_limit` int(11) DEFAULT 30,
  `passing_score` int(11) DEFAULT 70,
  `total_questions` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT 1,
  `status` enum('active','inactive','pending','rejected') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `subject_id`, `lesson_id`, `title`, `description`, `time_limit`, `passing_score`, `total_questions`, `created_by`, `status`, `created_at`, `updated_at`) VALUES
(7, 20, 13, 'First easy quiz', 'all student participate', 30, 80, 5, 1, 'active', '2025-09-14 13:31:15', '2025-09-14 13:36:04'),
(8, 20, 13, 'test', 'DevOps combines development and operations practices to shorten development lifecycles and provide continuous delivery with high quality. Cloud computing provides the ideal platform for DevOps by offering programmable infrastructure, automated provisioning, and scalable resources. Infrastructure as Code (IaC) uses code to provision and manage cloud resources, ensuring consistent and repeatable deployments. Tools like Terraform, AWS CloudFormation, and Azure Resource Manager enable declarative infrastructure management.', 5, 70, 5, 1, 'active', '2025-09-14 14:30:36', '2025-09-14 14:31:33');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `total_questions` int(11) DEFAULT 0,
  `correct_answers` int(11) DEFAULT 0,
  `time_taken` int(11) DEFAULT 0,
  `status` enum('in_progress','completed','timeout') DEFAULT 'in_progress',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(500) NOT NULL,
  `option_b` varchar(500) NOT NULL,
  `option_c` varchar(500) NOT NULL,
  `option_d` varchar(500) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `explanation` text DEFAULT NULL,
  `points` int(11) DEFAULT 1,
  `question_order` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `points`, `question_order`, `created_at`) VALUES
(18, 7, 'What is the primary benefit of cloud computing scalability?', 'Reduced internet bandwidth usage', 'Ability to increase or decrease resources based on demand', 'Permanent hardware ownership', 'Software as a Service', 'B', '', 10, 1, '2025-09-14 13:33:02'),
(19, 7, 'Which cloud service model provides the most control over the operating system?', 'Software as a Service (SaaS)', 'Platform as a Service (PaaS)', 'Infrastructure as a Service (IaaS)', 'Function as a Service (FaaS)', 'C', '', 10, 2, '2025-09-14 13:33:56'),
(20, 7, 'What does the \"shared responsibility model\" in cloud security mean?', 'All security is handled by the cloud provider', 'All security is handled by the customer', 'Security responsibilities are split between provider and customer', 'Security is optional in cloud environments', 'C', '', 10, 3, '2025-09-14 13:34:47'),
(21, 7, 'Which deployment model combines public and private cloud environments?', 'Community cloud', 'Hybrid cloud', 'Multi-cloud', 'Distributed cloud', 'B', '', 10, 4, '2025-09-14 13:35:23'),
(22, 7, 'What is the main purpose of a Content Delivery Network (CDN) in cloud computing?', 'Store backup data permanently', 'Increase data processing power', 'Deliver content faster to users globally', 'Encrypt all transmitted data', 'C', '', 10, 5, '2025-09-14 13:36:04'),
(23, 8, 'asa', 'as', 'as', 'as', 'as', 'A', '', 1, 1, '2025-09-14 14:30:48'),
(24, 8, 'sd', 'sd', 'sd', 'sd', 'sd', 'A', '', 1, 2, '2025-09-14 14:31:00'),
(25, 8, 'ss', 'ss', 'ss', 'ss', 'ss', 'A', '', 1, 3, '2025-09-14 14:31:10'),
(26, 8, 'ww', 'ww', 'ww', 'ww', 'ww', 'A', '', 1, 4, '2025-09-14 14:31:21'),
(27, 8, 'qa', 'qa', 'qa', 'qa', 'qa', 'A', '', 1, 5, '2025-09-14 14:31:33');

--
-- Triggers `quiz_questions`
--
DELIMITER $$
CREATE TRIGGER `update_quiz_question_count_delete` AFTER DELETE ON `quiz_questions` FOR EACH ROW BEGIN
    UPDATE quizzes 
    SET total_questions = (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = OLD.quiz_id) 
    WHERE id = OLD.quiz_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_quiz_question_count_insert` AFTER INSERT ON `quiz_questions` FOR EACH ROW BEGIN
    UPDATE quizzes 
    SET total_questions = (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = NEW.quiz_id) 
    WHERE id = NEW.quiz_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_progress`
--

CREATE TABLE `student_progress` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `total_points` int(11) DEFAULT 0,
  `quiz_points` int(11) DEFAULT 0,
  `assignment_points` int(11) DEFAULT 0,
  `participation_points` int(11) DEFAULT 0,
  `level_name` varchar(50) DEFAULT 'Beginner',
  `badges` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_progress`
--

INSERT INTO `student_progress` (`id`, `student_id`, `total_points`, `quiz_points`, `assignment_points`, `participation_points`, `level_name`, `badges`, `last_updated`) VALUES
(1, 2, 286, 156, 156, 33, 'Intermediate', NULL, '2025-09-14 05:45:50'),
(2, 3, 502, 206, 147, 34, 'Advanced', NULL, '2025-09-14 05:45:50'),
(3, 4, 111, 215, 61, 106, 'Novice', NULL, '2025-09-14 05:45:50'),
(4, 5, 547, 56, 144, 51, 'Advanced', NULL, '2025-09-14 05:45:50'),
(5, 7, 551, 194, 230, 58, 'Advanced', NULL, '2025-09-14 05:45:50'),
(6, 9, 595, 238, 198, 113, 'Advanced', NULL, '2025-09-14 05:45:50'),
(7, 10, 187, 96, 179, 79, 'Novice', NULL, '2025-09-14 05:45:50'),
(8, 2, 379, 122, 78, 87, 'Intermediate', NULL, '2025-09-14 12:21:11'),
(9, 3, 447, 169, 232, 100, 'Intermediate', NULL, '2025-09-14 12:21:11'),
(10, 4, 132, 58, 55, 25, 'Novice', NULL, '2025-09-14 12:21:11'),
(11, 5, 563, 175, 122, 118, 'Advanced', NULL, '2025-09-14 12:21:11'),
(12, 7, 394, 76, 231, 38, 'Intermediate', NULL, '2025-09-14 12:21:11'),
(13, 9, 566, 104, 163, 27, 'Advanced', NULL, '2025-09-14 12:21:11'),
(14, 10, 311, 57, 231, 68, 'Intermediate', NULL, '2025-09-14 12:21:11'),
(15, 2, 471, 71, 111, 46, 'Intermediate', NULL, '2025-09-14 12:25:02'),
(16, 3, 184, 87, 134, 79, 'Novice', NULL, '2025-09-14 12:25:02'),
(17, 4, 338, 198, 108, 47, 'Intermediate', NULL, '2025-09-14 12:25:02'),
(18, 5, 214, 147, 198, 49, 'Novice', NULL, '2025-09-14 12:25:02'),
(19, 7, 102, 107, 134, 50, 'Novice', NULL, '2025-09-14 12:25:02'),
(20, 9, 598, 95, 79, 29, 'Advanced', NULL, '2025-09-14 12:25:02'),
(21, 10, 501, 224, 240, 39, 'Advanced', NULL, '2025-09-14 12:25:02'),
(22, 2, 202, 179, 175, 45, 'Novice', NULL, '2025-09-14 12:26:28'),
(23, 3, 162, 53, 193, 78, 'Novice', NULL, '2025-09-14 12:26:28'),
(24, 4, 353, 238, 87, 35, 'Intermediate', NULL, '2025-09-14 12:26:28'),
(25, 5, 593, 171, 64, 78, 'Advanced', NULL, '2025-09-14 12:26:28'),
(26, 7, 332, 193, 90, 110, 'Intermediate', NULL, '2025-09-14 12:26:28'),
(27, 9, 424, 188, 151, 72, 'Intermediate', NULL, '2025-09-14 12:26:28'),
(28, 10, 519, 205, 124, 77, 'Advanced', NULL, '2025-09-14 12:26:28'),
(29, 2, 410, 215, 105, 115, 'Intermediate', NULL, '2025-09-14 12:26:58'),
(30, 3, 452, 210, 231, 36, 'Intermediate', NULL, '2025-09-14 12:26:58'),
(31, 4, 525, 233, 55, 63, 'Advanced', NULL, '2025-09-14 12:26:58'),
(32, 5, 526, 70, 242, 75, 'Advanced', NULL, '2025-09-14 12:26:58'),
(33, 7, 420, 186, 147, 63, 'Intermediate', NULL, '2025-09-14 12:26:58'),
(34, 9, 328, 77, 111, 37, 'Intermediate', NULL, '2025-09-14 12:26:58'),
(35, 10, 459, 91, 226, 105, 'Intermediate', NULL, '2025-09-14 12:26:58'),
(36, 2, 177, 85, 137, 88, 'Novice', NULL, '2025-09-14 12:27:11'),
(37, 3, 539, 147, 209, 77, 'Advanced', NULL, '2025-09-14 12:27:11'),
(38, 4, 209, 155, 245, 56, 'Novice', NULL, '2025-09-14 12:27:11'),
(39, 5, 409, 81, 238, 48, 'Intermediate', NULL, '2025-09-14 12:27:11'),
(40, 7, 283, 73, 146, 31, 'Intermediate', NULL, '2025-09-14 12:27:11'),
(41, 9, 534, 80, 82, 59, 'Advanced', NULL, '2025-09-14 12:27:11'),
(42, 10, 222, 88, 92, 75, 'Novice', NULL, '2025-09-14 12:27:11');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT 1,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `color` varchar(7) DEFAULT '#007bff',
  `icon` varchar(50) DEFAULT 'book'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `description`, `created_by`, `status`, `created_at`, `updated_at`, `color`, `icon`) VALUES
(10, 'Programming Fundamentals', 'Basic programming concepts, logic, and problem-solving techniques', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(11, 'Web Development', 'HTML, CSS, JavaScript, and modern web technologies', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(12, 'Database Management', 'SQL, MySQL, database design, and data management', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(13, 'Data Structures & Algorithms', 'Core computer science concepts for efficient programming', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(14, 'Object-Oriented Programming', 'OOP concepts using Java, C++, or Python', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(15, 'Computer Networks', 'Network protocols, internet technologies, and system administration', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(16, 'Operating Systems', 'Windows, Linux, system administration, and computer architecture', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(17, 'Cybersecurity', 'Information security, ethical hacking, and data protection', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(18, 'Machine Learning', 'AI fundamentals, data science, and predictive modeling', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(19, 'Mobile App Development', 'Android and iOS app development using modern frameworks', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(20, 'Cloud Computing', 'AWS, Azure, Google Cloud, and cloud architecture', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book'),
(21, 'Software Engineering', 'Software development lifecycle, testing, and project management', 1, 'active', '2025-09-14 13:15:35', '2025-09-14 13:15:35', '#007bff', 'book');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student') DEFAULT 'student',
  `status` enum('active','inactive','banned','online') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@learning.com', '$2y$10$SQyTe0AFnULAHFxzrX8z9emj51ttw/IEvMWDVexQN7WR45zCCGdHq', 'admin', '', '2025-09-13 14:01:54', '2025-09-14 05:37:44'),
(2, 'Nadul Laknidu', 'nadullaknidu7@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'banned', '2025-09-13 14:01:55', '2025-09-14 13:45:49'),
(3, 'Mary Learner', 'mary@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'banned', '2025-09-13 14:01:55', '2025-09-14 13:45:46'),
(4, 'David Scholar', 'david@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'banned', '2025-09-13 14:01:55', '2025-09-14 13:45:35'),
(5, 'Sarah Reader', 'sarah@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'banned', '2025-09-13 14:01:55', '2025-09-14 13:45:31'),
(6, 'Admin User', 'admin@example.com', '$2y$10$0/udsTP8vq6pxYlAYwkeYeHHQNhOBLtaif9wgUhRCTd0aGVTtfpUC', 'admin', '', '2025-09-13 17:16:45', '2025-09-13 17:16:53'),
(7, 'Student User', 'student@example.com', '$2y$10$CsGXKPky6yE.R8t0PIzZgu1u7ZDox6BQC0929/0GN6CCfhfoeVlUS', 'student', 'banned', '2025-09-13 17:16:45', '2025-09-14 13:45:52'),
(8, 'Admin', 'admin@admin.com', '$2y$10$VjQn/OVEhKpnC7r.gYl0FulQpkdy28rwJHBY6cXl5jy.HTOU3R7jG', 'admin', 'active', '2025-09-13 17:26:12', '2025-09-13 17:26:12'),
(9, 'Student', 'student@student.com', '$2y$10$w6M2n69YXkFxr6x/hA.nqO7z/5Z84pQHZmXuG9B/qxPx0tGW/0CxK', 'student', 'online', '2025-09-13 17:26:12', '2025-09-14 05:31:33'),
(10, 'asara gamage', 'asaragamage109@gmail.com', '$2y$10$e7qrFoKloalovMkkCYsx1eBJ5Y4H9dJykC1GtWo3GTnOMjZipOjuO', 'student', '', '2025-09-14 05:33:12', '2025-09-14 14:28:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`assignment_id`,`student_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_code` (`certificate_code`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_messages_user` (`user_id`);

--
-- Indexes for table `daily_leaderboard`
--
ALTER TABLE `daily_leaderboard`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`),
  ADD KEY `idx_leaderboard_date` (`date`),
  ADD KEY `idx_leaderboard_user` (`user_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lessons_subject` (`subject_id`),
  ADD KEY `idx_lessons_status` (`status`);

--
-- Indexes for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lesson_completions_user` (`user_id`),
  ADD KEY `idx_lesson_completions_lesson` (`lesson_id`);

--
-- Indexes for table `lesson_files`
--
ALTER TABLE `lesson_files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lesson_progress`
--
ALTER TABLE `lesson_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_lesson` (`student_id`,`lesson_id`);

--
-- Indexes for table `lesson_suggestions`
--
ALTER TABLE `lesson_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participant` (`meeting_id`,`user_id`);

--
-- Indexes for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quizzes_subject` (`subject_id`),
  ADD KEY `idx_quizzes_lesson` (`lesson_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_attempts_user` (`user_id`),
  ADD KEY `idx_quiz_attempts_quiz` (`quiz_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quiz_questions_quiz` (`quiz_id`);

--
-- Indexes for table `student_progress`
--
ALTER TABLE `student_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `daily_leaderboard`
--
ALTER TABLE `daily_leaderboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_files`
--
ALTER TABLE `lesson_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lesson_progress`
--
ALTER TABLE `lesson_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `lesson_suggestions`
--
ALTER TABLE `lesson_suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `student_progress`
--
ALTER TABLE `student_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `achievements`
--
ALTER TABLE `achievements`
  ADD CONSTRAINT `achievements_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `lesson_suggestions`
--
ALTER TABLE `lesson_suggestions`
  ADD CONSTRAINT `lesson_suggestions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lesson_suggestions_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `meeting_requests`
--
ALTER TABLE `meeting_requests`
  ADD CONSTRAINT `meeting_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `meeting_requests_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `student_progress`
--
ALTER TABLE `student_progress`
  ADD CONSTRAINT `student_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
