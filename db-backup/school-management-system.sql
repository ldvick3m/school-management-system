-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 13, 2026 at 09:45 AM
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
-- Database: `school-management-system`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `target_role` varchar(50) DEFAULT 'all',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `target_role`, `created_at`) VALUES
(1, 'تعطیلی مدارس از 13 الی 16 تیرماه', 'مدرسه هفته آینده از 13 ام الی 16 ام تعطیل میباشد.', 'all', '2026-07-01 08:43:36');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent') NOT NULL,
  `registered_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `class_id`, `student_id`, `date`, `status`, `registered_by`, `created_at`) VALUES
(1, 1, 1, '9989-03-29', 'absent', 3, '2026-07-01 09:40:52'),
(2, 1, 1, '9989-03-31', 'present', 3, '2026-07-01 09:40:43'),
(3, 1, 2, '9989-03-31', 'absent', 3, '2026-07-01 09:40:43'),
(5, 1, 2, '9989-03-29', 'absent', 3, '2026-07-01 09:40:52');

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `icon` varchar(255) NOT NULL,
  `type` enum('educational','behavioral') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `name`, `description`, `icon`, `type`) VALUES
(1, 'شاگرد اول ماه', 'کسب بالاترین معدل تحصیلی کلاس در طول ماه گذشته', 'trophy-fill', 'educational'),
(2, 'پژوهشگر برتر', 'تحقیق و پژوهش علمی فوق برنامه و ارائه به دبیر مربوطه', 'search', 'educational'),
(3, 'تلاشگر هفته', 'پیشرفت چشمگیر در نمرات و عملکرد درسی هفتگی', 'graph-up-arrow', 'educational'),
(5, 'همیار معلم', 'مشارکت فعال در برگزاری و رفع اشکال سایر همکلاسی‌ها', 'people-fill', 'behavioral'),
(6, 'منضبط‌ترین دانش‌آموز', 'رعایت حداکثری قوانین انضباطی و حضور به‌موقع در کلاس‌ها', 'shield-check', 'behavioral');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_id`, `message_text`, `created_at`, `is_read`) VALUES
(1, 5, 3, 'سلام، خسته نباشید', '2026-07-01 09:13:44', 1),
(2, 5, 3, 'هفته آینده کلاس فوق العاده داریم؟', '2026-07-01 09:13:58', 1),
(3, 3, 5, 'سلام عرض شد. در صورت تعطیلی رسمی استان یا کشور، خیر', '2026-07-01 09:15:07', 1),
(4, 5, 3, 'متشکر', '2026-07-01 09:43:21', 1),
(5, 3, 30, 'سلام', '2026-07-13 07:10:11', 0);

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `grade_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `grade_level`) VALUES
(1, 'کلاس هفتم الف', 7),
(2, 'کلاس هفتم ب', 7),
(3, 'کلاس هشتم الف', 8),
(4, 'کلاس هشتم ب', 8);

-- --------------------------------------------------------

--
-- Table structure for table `class_student`
--

CREATE TABLE `class_student` (
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `class_student`
--

INSERT INTO `class_student` (`class_id`, `student_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(3, 21),
(3, 22),
(3, 23),
(3, 24),
(3, 25),
(3, 26),
(3, 27),
(3, 28),
(3, 29),
(3, 30),
(4, 31),
(4, 32),
(4, 33),
(4, 34),
(4, 35),
(4, 36),
(4, 37),
(4, 38),
(4, 39),
(4, 40);

-- --------------------------------------------------------

--
-- Table structure for table `class_teacher_course`
--

CREATE TABLE `class_teacher_course` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `class_teacher_course`
--

INSERT INTO `class_teacher_course` (`id`, `class_id`, `teacher_id`, `course_id`) VALUES
(1, 1, 1, 1),
(2, 1, 2, 2),
(3, 1, 3, 5),
(4, 2, 1, 1),
(5, 2, 2, 2),
(6, 2, 3, 5),
(7, 3, 1, 3),
(8, 3, 2, 4),
(9, 3, 4, 6),
(10, 4, 1, 3),
(11, 4, 2, 4),
(12, 4, 4, 6);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `grade_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `grade_level`) VALUES
(1, 'ریاضی هفتم', 7),
(2, 'علوم هفتم', 7),
(3, 'ریاضی هشتم', 8),
(4, 'علوم هشتم', 8),
(5, 'ادبیات فارسی', 7),
(6, 'زبان انگلیسی', 8);

-- --------------------------------------------------------

--
-- Table structure for table `discipline_records`
--

CREATE TABLE `discipline_records` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `status` enum('excellent','good','average','poor') NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_messages`
--

CREATE TABLE `forum_messages` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `forum_messages`
--

INSERT INTO `forum_messages` (`id`, `topic_id`, `sender_id`, `message_text`, `created_at`) VALUES
(1, 1, 3, 'سلام به همه دانش‌آموزان عزیز. فیلم تدریس مربوط به مبحث جمع و تفریق آپلود شد. لطفاً حتماً مشاهده کنید.', '2026-07-12 05:13:21'),
(2, 1, 4, 'سلام استاد وقتتون بخیر. ممنون از تدریس خوبتون. بخش مربوط به جمع اعداد علامت‌دار رو خیلی عالی متوجه شدم.', '2026-07-12 15:13:21'),
(3, 1, 3, 'خواهش می‌کنم پسرم. برای بخش تفریق هم تمرین‌های کتاب کار صفحه ۱۲ رو انجام بدید.', '2026-07-12 06:13:21'),
(4, 1, 4, 'استاد، مهلت ارسال پاسخنامه تکالیف تا چه زمانی هست؟', '2026-07-12 06:28:21'),
(5, 1, 3, 'تا پایان روز جمعه فرصت دارید پاسخبرگ‌ها رو از بخش تکالیف پنل ارسال کنید.', '2026-07-12 06:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `homeworks`
--

CREATE TABLE `homeworks` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `homework_title` varchar(255) NOT NULL,
  `homework_description` text DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `homeworks`
--

INSERT INTO `homeworks` (`id`, `topic_id`, `homework_title`, `homework_description`, `deadline`, `created_at`) VALUES
(1, 1, 'تکلیف شماره ۱ ریاضی هفتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(2, 2, 'تکلیف شماره ۱ علوم هفتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(3, 3, 'تکلیف شماره ۱ ادبیات فارسی', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(4, 4, 'تکلیف شماره ۱ ریاضی هفتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(5, 5, 'تکلیف شماره ۱ علوم هفتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(6, 6, 'تکلیف شماره ۱ ادبیات فارسی', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(7, 7, 'تکلیف شماره ۱ ریاضی هشتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(8, 8, 'تکلیف شماره ۱ علوم هشتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(9, 9, 'تکلیف شماره ۱ زبان انگلیسی', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(10, 10, 'تکلیف شماره ۱ ریاضی هشتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(11, 11, 'تکلیف شماره ۱ علوم هشتم', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46'),
(12, 12, 'تکلیف شماره ۱ زبان انگلیسی', 'لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.', '2026-07-19 14:04:46', '2026-07-12 10:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `homework_submissions`
--

CREATE TABLE `homework_submissions` (
  `id` int(11) NOT NULL,
  `homework_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','graded') DEFAULT 'pending',
  `grade` decimal(4,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `graded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `homework_submissions`
--

INSERT INTO `homework_submissions` (`id`, `homework_id`, `student_id`, `file_path`, `status`, `grade`, `feedback`, `submitted_at`, `graded_at`) VALUES
(1, 1, 1, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(2, 2, 1, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(3, 3, 1, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(4, 1, 2, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(5, 2, 2, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(6, 3, 2, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(7, 1, 3, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(8, 2, 3, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(9, 3, 3, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(10, 1, 4, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(11, 2, 4, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(12, 3, 4, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(13, 1, 5, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(14, 2, 5, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(15, 3, 5, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(16, 1, 6, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(17, 2, 6, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(18, 3, 6, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(19, 1, 7, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(20, 2, 7, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(21, 3, 7, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(22, 1, 8, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(23, 2, 8, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(24, 3, 8, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(25, 1, 9, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(26, 2, 9, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(27, 3, 9, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(28, 1, 10, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(29, 2, 10, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(30, 3, 10, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(31, 4, 11, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(32, 5, 11, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(33, 6, 11, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(34, 4, 12, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(35, 5, 12, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(36, 6, 12, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(37, 4, 13, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(38, 5, 13, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(39, 6, 13, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(40, 4, 14, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(41, 5, 14, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(42, 6, 14, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(43, 4, 15, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(44, 5, 15, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(45, 6, 15, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(46, 4, 16, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(47, 5, 16, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(48, 6, 16, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(49, 4, 17, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(50, 5, 17, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(51, 6, 17, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(52, 4, 18, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(53, 5, 18, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(54, 6, 18, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(55, 4, 19, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(56, 5, 19, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(57, 6, 19, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(58, 4, 20, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(59, 5, 20, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(60, 6, 20, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(61, 7, 21, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(62, 8, 21, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(63, 9, 21, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(64, 7, 22, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(65, 8, 22, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(66, 9, 22, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(67, 7, 23, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(68, 8, 23, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(69, 9, 23, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(70, 7, 24, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(71, 8, 24, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(72, 9, 24, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(73, 7, 25, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(74, 8, 25, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(75, 9, 25, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(76, 7, 26, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(77, 8, 26, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(78, 9, 26, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(79, 7, 27, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(80, 8, 27, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(81, 9, 27, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(82, 7, 28, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(83, 8, 28, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(84, 9, 28, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(85, 7, 29, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(86, 8, 29, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(87, 9, 29, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(88, 7, 30, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(89, 8, 30, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(90, 9, 30, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(91, 10, 31, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(92, 11, 31, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(93, 12, 31, 'uploads/homeworks/sample.pdf', 'graded', 20.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(94, 10, 32, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(95, 11, 32, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(96, 12, 32, 'uploads/homeworks/sample.pdf', 'graded', 19.50, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(97, 10, 33, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(98, 11, 33, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(99, 12, 33, 'uploads/homeworks/sample.pdf', 'graded', 19.00, 'بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(100, 10, 34, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(101, 11, 34, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(102, 12, 34, 'uploads/homeworks/sample.pdf', 'graded', 16.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(103, 10, 35, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(104, 11, 35, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(105, 12, 35, 'uploads/homeworks/sample.pdf', 'graded', 15.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(106, 10, 36, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(107, 11, 36, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(108, 12, 36, 'uploads/homeworks/sample.pdf', 'graded', 14.50, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(109, 10, 37, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(110, 11, 37, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(111, 12, 37, 'uploads/homeworks/sample.pdf', 'graded', 14.00, 'خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(112, 10, 38, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(113, 11, 38, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(114, 12, 38, 'uploads/homeworks/sample.pdf', 'graded', 12.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(115, 10, 39, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(116, 11, 39, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(117, 12, 39, 'uploads/homeworks/sample.pdf', 'graded', 11.00, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(118, 10, 40, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(119, 11, 40, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46'),
(120, 12, 40, 'uploads/homeworks/sample.pdf', 'graded', 9.50, 'متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.', '2026-07-10 10:34:46', '2026-07-12 14:04:46');

-- --------------------------------------------------------

--
-- Table structure for table `live_classes`
--

CREATE TABLE `live_classes` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `join_link` varchar(500) NOT NULL,
  `sms_sent` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `live_classes`
--

INSERT INTO `live_classes` (`id`, `class_id`, `teacher_id`, `course_id`, `title`, `date`, `start_time`, `end_time`, `join_link`, `sms_sent`, `created_at`) VALUES
(1, 1, 1, 1, 'تست کلاس آنلاین', '2026-07-01', '12:39:00', '13:30:00', 'https://meet.google.com/nyj-mobs-fac', 1, '2026-07-01 09:07:21');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `note_type` enum('general','course') NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `topic_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `student_id`, `note_type`, `course_id`, `topic_id`, `title`, `content`, `created_at`) VALUES
(1, 1, 'course', 1, 1, 'آموزش عمل جمع و تفریق', 'تست یاداشت آموزش عمل جمع و تفریق', '2026-07-12 08:20:48');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`id`, `user_id`) VALUES
(1, 7),
(2, 12),
(3, 14),
(4, 16),
(5, 18),
(6, 20),
(7, 22),
(8, 24),
(9, 26),
(10, 28),
(11, 30),
(12, 32),
(13, 34),
(14, 36),
(15, 38),
(16, 40),
(17, 42),
(18, 44),
(19, 46),
(20, 48),
(21, 50),
(22, 52),
(23, 54),
(24, 56),
(25, 58),
(26, 60),
(27, 62),
(28, 64),
(29, 66),
(30, 68),
(31, 70),
(32, 72),
(33, 74),
(34, 76),
(35, 78),
(36, 80),
(37, 82),
(38, 84);

-- --------------------------------------------------------

--
-- Table structure for table `parent_student`
--

CREATE TABLE `parent_student` (
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `parent_student`
--

INSERT INTO `parent_student` (`parent_id`, `student_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 4),
(3, 5),
(4, 6),
(5, 7),
(6, 8),
(7, 9),
(8, 10),
(9, 11),
(10, 12),
(11, 13),
(12, 14),
(13, 15),
(14, 16),
(15, 17),
(16, 18),
(17, 19),
(18, 20),
(19, 21),
(20, 22),
(21, 23),
(22, 24),
(23, 25),
(24, 26),
(25, 27),
(26, 28),
(27, 29),
(28, 30),
(29, 31),
(30, 32),
(31, 33),
(32, 34),
(33, 35),
(34, 36),
(35, 37),
(36, 38),
(37, 39),
(38, 40);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `quiz_title` varchar(255) NOT NULL,
  `quiz_type` enum('multiple_choice','essay') NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `topic_id`, `quiz_title`, `quiz_type`, `duration_minutes`, `created_at`) VALUES
(1, 1, 'کوییز تستی سنجش جمع و تفریق ریاضی هفتم', 'multiple_choice', 15, '2026-07-12 08:43:21'),
(14, 1, 'آزمون مستمر کلاسی ریاضی هفتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(15, 2, 'آزمون مستمر کلاسی علوم هفتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(16, 3, 'آزمون مستمر کلاسی ادبیات فارسی', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(17, 4, 'آزمون مستمر کلاسی ریاضی هفتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(18, 5, 'آزمون مستمر کلاسی علوم هفتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(19, 6, 'آزمون مستمر کلاسی ادبیات فارسی', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(20, 7, 'آزمون مستمر کلاسی ریاضی هشتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(21, 8, 'آزمون مستمر کلاسی علوم هشتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(22, 9, 'آزمون مستمر کلاسی زبان انگلیسی', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(23, 10, 'آزمون مستمر کلاسی ریاضی هشتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(24, 11, 'آزمون مستمر کلاسی علوم هشتم', 'multiple_choice', 20, '2026-07-12 10:34:46'),
(25, 12, 'آزمون مستمر کلاسی زبان انگلیسی', 'multiple_choice', 20, '2026-07-12 10:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','essay') NOT NULL,
  `option_1` text DEFAULT NULL,
  `option_2` text DEFAULT NULL,
  `option_3` text DEFAULT NULL,
  `option_4` text DEFAULT NULL,
  `correct_option` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question_text`, `question_type`, `option_1`, `option_2`, `option_3`, `option_4`, `correct_option`) VALUES
(1, 1, 'حاصل عبارت ۲۵ - (۱۴ - ۳۰) کدام گزینه است؟', 'multiple_choice', '۹', '-۹', '۳۹', '-۳۹', 1),
(2, 1, 'قرینه عدد ۱۵- نسبت به عدد ۵ کدام است؟', 'multiple_choice', '۲۵', '۱۵', '۳۵', '۲۰', 1),
(3, 1, 'دمای اردبیل ۸ درجه زیر صفر و دمای تبریز ۳ درجه از اردبیل گرم‌تر است. دمای تبریز چند درجه است؟', 'multiple_choice', '-۵', '-۱۱', '۵', '۱۱', 1),
(4, 1, 'کوچک‌ترین عدد صحیح منفی دورقمی کدام است؟', 'multiple_choice', '-۹۹', '-۱۰', '-۹۰', '-۱۱', 1);

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `resource_name` varchar(255) NOT NULL,
  `resource_type` enum('link','pdf','image','video') NOT NULL,
  `resource_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `grade_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `grade_level`) VALUES
(1, 8, 7),
(2, 9, 7),
(3, 10, 7),
(4, 11, 7),
(5, 13, 7),
(6, 15, 7),
(7, 17, 7),
(8, 19, 7),
(9, 21, 7),
(10, 23, 7),
(11, 25, 7),
(12, 27, 7),
(13, 29, 7),
(14, 31, 7),
(15, 33, 7),
(16, 35, 7),
(17, 37, 7),
(18, 39, 7),
(19, 41, 7),
(20, 43, 7),
(21, 45, 8),
(22, 47, 8),
(23, 49, 8),
(24, 51, 8),
(25, 53, 8),
(26, 55, 8),
(27, 57, 8),
(28, 59, 8),
(29, 61, 8),
(30, 63, 8),
(31, 65, 8),
(32, 67, 8),
(33, 69, 8),
(34, 71, 8),
(35, 73, 8),
(36, 75, 8),
(37, 77, 8),
(38, 79, 8),
(39, 81, 8),
(40, 83, 8);

-- --------------------------------------------------------

--
-- Table structure for table `student_badges`
--

CREATE TABLE `student_badges` (
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `awarded_by` int(11) NOT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `student_badges`
--

INSERT INTO `student_badges` (`student_id`, `badge_id`, `awarded_by`, `awarded_at`) VALUES
(40, 3, 2, '2026-07-12 11:39:45');

-- --------------------------------------------------------

--
-- Table structure for table `student_quizzes`
--

CREATE TABLE `student_quizzes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `submit_time` datetime DEFAULT NULL,
  `score` decimal(4,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('started','completed','failed_deadline') DEFAULT 'started'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `student_quizzes`
--

INSERT INTO `student_quizzes` (`id`, `student_id`, `quiz_id`, `start_time`, `submit_time`, `score`, `feedback`, `status`) VALUES
(1, 1, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(2, 1, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(3, 1, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(4, 2, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(5, 2, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(6, 2, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(7, 3, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(8, 3, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(9, 3, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(10, 4, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(11, 4, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(12, 4, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(13, 5, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(14, 5, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(15, 5, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(16, 6, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(17, 6, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(18, 6, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(19, 7, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(20, 7, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(21, 7, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(22, 8, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(23, 8, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(24, 8, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(25, 9, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(26, 9, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(27, 9, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(28, 10, 14, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(29, 10, 15, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(30, 10, 16, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(31, 11, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(32, 11, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(33, 11, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(34, 12, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(35, 12, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(36, 12, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(37, 13, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(38, 13, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(39, 13, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(40, 14, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(41, 14, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(42, 14, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(43, 15, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(44, 15, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(45, 15, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(46, 16, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(47, 16, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(48, 16, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(49, 17, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(50, 17, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(51, 17, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(52, 18, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(53, 18, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(54, 18, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(55, 19, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(56, 19, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(57, 19, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(58, 20, 17, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(59, 20, 18, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(60, 20, 19, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(61, 21, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(62, 21, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(63, 21, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(64, 22, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(65, 22, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(66, 22, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(67, 23, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(68, 23, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(69, 23, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(70, 24, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(71, 24, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(72, 24, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(73, 25, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(74, 25, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(75, 25, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(76, 26, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(77, 26, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(78, 26, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(79, 27, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(80, 27, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(81, 27, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(82, 28, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(83, 28, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(84, 28, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(85, 29, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(86, 29, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(87, 29, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(88, 30, 20, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(89, 30, 21, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(90, 30, 22, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(91, 31, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(92, 31, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(93, 31, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 20.00, NULL, 'completed'),
(94, 32, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(95, 32, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(96, 32, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.50, NULL, 'completed'),
(97, 33, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(98, 33, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(99, 33, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 19.00, NULL, 'completed'),
(100, 34, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(101, 34, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(102, 34, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 16.00, NULL, 'completed'),
(103, 35, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(104, 35, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(105, 35, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 15.00, NULL, 'completed'),
(106, 36, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(107, 36, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(108, 36, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.50, NULL, 'completed'),
(109, 37, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(110, 37, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(111, 37, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 14.00, NULL, 'completed'),
(112, 38, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(113, 38, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(114, 38, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 12.50, NULL, 'completed'),
(115, 39, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(116, 39, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(117, 39, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 11.00, NULL, 'completed'),
(118, 40, 23, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(119, 40, 24, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed'),
(120, 40, 25, '2026-07-12 09:34:46', '2026-07-12 14:04:46', 9.50, NULL, 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `student_quiz_answers`
--

CREATE TABLE `student_quiz_answers` (
  `id` int(11) NOT NULL,
  `student_quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` int(11) DEFAULT NULL,
  `essay_answer_file_path` varchar(255) DEFAULT NULL,
  `score` decimal(4,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `task_type` enum('routine','custom') NOT NULL,
  `priority` enum('normal','urgent','critical') DEFAULT 'normal',
  `status` enum('todo','in_progress','done') DEFAULT 'todo',
  `deadline` datetime NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `timer_seconds` int(11) DEFAULT 0,
  `timer_last_started` datetime DEFAULT NULL,
  `is_timer_running` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `template_id`, `title`, `description`, `created_by`, `assigned_to`, `task_type`, `priority`, `status`, `deadline`, `attachment_path`, `timer_seconds`, `timer_last_started`, `is_timer_running`, `created_at`, `updated_at`) VALUES
(1, 1, 'ثبت حضور و غیاب روزانه کلاس‌ها', 'لطفاً لیست حضور و غیاب روزانه دانش‌آموزان کلاس خود را حداکثر تا ساعت ۱۳ در سیستم ثبت نمایید.', 1, 3, 'routine', 'urgent', 'in_progress', '2026-07-12 13:00:00', NULL, 1890, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 06:41:20'),
(2, 2, 'تصحیح و ثبت نمرات تکالیف هفتگی', 'بررسی و ثبت فیدبک تکالیف ارسال شده دانش‌آموزان در طول هفته جاری.', 1, 3, 'routine', 'normal', 'in_progress', '2026-07-16 23:59:59', NULL, 4200, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 06:12:06'),
(3, NULL, 'طراحی سوالات آزمون میان‌ترم دوم علوم تست', 'سوالات آزمون علوم تجربی پایه هشتم طراحی شده و فایل PDF آن ضمیمه شود.', 2, 3, 'custom', 'critical', 'in_progress', '2026-07-10 07:15:00', NULL, 4972, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 07:38:52'),
(4, NULL, 'ارسال گزارش ماهانه وضعیت درسی کلاس ۹ الف', 'ارائه خلاصه تحلیل کیفی از سطح علمی دانش‌آموزان کلاس ۹ الف به اپراتور.', 2, 3, 'custom', 'normal', 'todo', '2026-07-13 19:15:25', NULL, 0, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 06:07:53'),
(5, NULL, 'بررسی و تایید نهایی درخواست‌های مرخصی و برنامه‌های هفتگی معلمان', 'پوشه مرخصی‌ها و برنامه‌های هفتگی بارگذاری شده توسط دبیران را بررسی و نتیجه را به مدیر اعلام کنید.', 1, 2, 'custom', 'urgent', 'in_progress', '2026-07-14 17:00:00', NULL, 7200, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 05:15:25'),
(6, NULL, 'برنامه‌ریزی و چک نهایی تداخل‌های کلاس‌های لایو شنبه تست', 'لیست تداخل‌های کلاس آنلاین شنبه را بررسی و تایید کنید.', 2, 2, 'custom', 'normal', 'in_progress', '2026-07-15 12:00:00', NULL, 3, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 06:07:49'),
(7, 3, 'پیش‌خوانی مبحث جدید درس ریاضی', 'فیلم آموزشی و جزوه پیوست مبحث جدید درس ریاضی را قبل از کلاس روز شنبه مطالعه و پیش‌خوانی کنید.', 3, 8, 'routine', 'normal', 'todo', '2026-07-18 08:00:00', NULL, 0, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 05:57:16'),
(8, NULL, 'حل تمرین‌های دوره فصل ۳ علوم', 'مرور سوالات تشریحی و تست‌های پایان فصل ۳ علوم تجربی.', 8, 8, 'custom', 'normal', 'in_progress', '2026-07-14 07:15:25', NULL, 61, NULL, 0, '2026-07-13 05:15:25', '2026-07-13 06:12:14');

-- --------------------------------------------------------

--
-- Table structure for table `task_comments`
--

CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `task_comments`
--

INSERT INTO `task_comments` (`id`, `task_id`, `user_id`, `comment_text`, `attachment_path`, `created_at`) VALUES
(1, 3, 3, 'سلام. طراحی سوالات تمام شده است، فردا صبح فایل را آپلود می‌کنم. @operator_1', NULL, '2026-07-13 05:15:25'),
(2, 3, 2, 'بسیار عالی. لطفاً در صورت امکان داکیومنت پاسخ تشریحی را هم ضمیمه کنید. تشکر. @teacher_1', NULL, '2026-07-13 05:15:25');

-- --------------------------------------------------------

--
-- Table structure for table `task_notifications`
--

CREATE TABLE `task_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `notification_type` enum('deadline_warning','overdue_warning','mention') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `task_notifications`
--

INSERT INTO `task_notifications` (`id`, `user_id`, `task_id`, `notification_type`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 3, 'mention', 'کاربر «دبیر تستی» شما را در وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» منشن کرد.', 0, '2026-07-13 05:15:25'),
(2, 3, 3, 'mention', 'کاربر «اپراتور تستی» شما را در وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» منشن کرد.', 0, '2026-07-13 05:15:25'),
(3, 3, 3, 'mention', 'هشدار قرمز: مهلت انجام وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» به پایان رسیده و این کار معوقه شده است.', 0, '2026-07-13 05:15:25'),
(4, 3, 4, 'deadline_warning', 'هشدار: کمتر از ۲۴ ساعت به مهلت انجام وظیفه «ارسال گزارش ماهانه وضعیت درسی کلاس ۹ الف» باقی مانده است.', 0, '2026-07-13 05:19:25'),
(5, 2, 4, 'deadline_warning', 'هشدار: کمتر از ۲۴ ساعت به مهلت انجام وظیفه «ارسال گزارش ماهانه وضعیت درسی کلاس ۹ الف» باقی مانده است.', 0, '2026-07-13 05:19:25'),
(6, 8, 8, 'deadline_warning', 'هشدار: کمتر از ۲۴ ساعت به مهلت انجام وظیفه «حل تمرین‌های دوره فصل ۳ علوم» باقی مانده است.', 0, '2026-07-13 05:19:25'),
(7, 3, 3, 'overdue_warning', 'هشدار قرمز: مهلت انجام وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» به پایان رسیده و این کار معوقه شده است.', 0, '2026-07-13 05:19:25'),
(8, 2, 3, 'overdue_warning', 'هشدار قرمز: مهلت انجام وظیفه «طراحی سوالات آزمون میان‌ترم دوم علوم» به پایان رسیده و این کار معوقه شده است.', 0, '2026-07-13 05:19:25'),
(9, 3, 1, 'overdue_warning', 'هشدار قرمز: مهلت انجام وظیفه «ثبت حضور و غیاب روزانه کلاس‌ها» به پایان رسیده و این کار معوقه شده است.', 0, '2026-07-13 06:01:27'),
(10, 1, 1, 'overdue_warning', 'هشدار قرمز: مهلت انجام وظیفه «ثبت حضور و غیاب روزانه کلاس‌ها» به پایان رسیده و این کار معوقه شده است.', 0, '2026-07-13 06:01:27');

-- --------------------------------------------------------

--
-- Table structure for table `task_templates`
--

CREATE TABLE `task_templates` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_type` enum('all_teachers','specific_teachers','all_students','class_students','individual') NOT NULL,
  `assigned_to_role` enum('teacher','student','operator') DEFAULT NULL,
  `assigned_class_id` int(11) DEFAULT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `priority` enum('normal','urgent','critical') DEFAULT 'normal',
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `task_templates`
--

INSERT INTO `task_templates` (`id`, `title`, `description`, `created_by`, `assigned_type`, `assigned_to_role`, `assigned_class_id`, `frequency`, `priority`, `is_active`, `created_at`) VALUES
(1, 'ثبت حضور و غیاب روزانه کلاس‌ها', 'لطفاً لیست حضور و غیاب روزانه دانش‌آموزان کلاس خود را حداکثر تا ساعت ۱۳ در سیستم ثبت نمایید.', 1, 'all_teachers', 'teacher', NULL, 'daily', 'urgent', 1, '2026-07-13 05:15:25'),
(2, 'تصحیح و ثبت نمرات تکالیف هفتگی', 'بررسی و ثبت فیدبک تکالیف ارسال شده دانش‌آموزان در طول هفته جاری.', 1, 'all_teachers', 'teacher', NULL, 'weekly', 'normal', 1, '2026-07-13 05:15:25'),
(3, 'پیش‌خوانی مبحث جدید درس ریاضی', 'فیلم آموزشی و جزوه پیوست مبحث جدید درس ریاضی را قبل از کلاس روز شنبه مطالعه و پیش‌خوانی کنید.', 3, 'all_students', 'student', NULL, 'weekly', 'normal', 1, '2026-07-13 05:15:25');

-- --------------------------------------------------------

--
-- Table structure for table `task_template_assignments`
--

CREATE TABLE `task_template_assignments` (
  `template_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `bio`) VALUES
(1, 3, 'مدرس ریاضیات دوره متوسطه با ۱۰ سال سابقه تدریس.'),
(2, 4, 'مدرس علوم تجربی دوره متوسطه دوم و المپیاد.'),
(3, 5, 'مدرس ادبیات فارسی و دستور زبان متوسطه.'),
(4, 6, 'مدرس زبان انگلیسی و دوره‌های آیلتس.');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `category` enum('admin','financial') NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` enum('open','in_progress','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `creator_id`, `category`, `title`, `status`, `created_at`) VALUES
(1, 5, 'admin', 'ثبت تیکت پشتیبانی جدید', 'in_progress', '2026-07-01 09:19:13'),
(2, 5, 'admin', 'ثبت تیکت پشتیبانی جدید', 'closed', '2026-07-01 09:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_messages`
--

CREATE TABLE `ticket_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `ticket_messages`
--

INSERT INTO `ticket_messages` (`id`, `ticket_id`, `sender_id`, `message_text`, `created_at`) VALUES
(1, 1, 5, 'جلسه اولیا مربیان کی برگذار میشه؟', '2026-07-01 09:19:13'),
(2, 2, 5, 'جلسه اولیا مربیان کی برگذار میشه؟', '2026-07-01 09:21:39'),
(3, 1, 2, '10 تیر', '2026-07-01 09:35:40');

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `id` int(11) NOT NULL,
  `class_teacher_course_id` int(11) DEFAULT NULL,
  `topic_title` varchar(255) NOT NULL,
  `topic_description` text DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `topics`
--

INSERT INTO `topics` (`id`, `class_teacher_course_id`, `topic_title`, `topic_description`, `video_path`, `created_at`) VALUES
(1, 1, 'آموزش مبحث اول درس ریاضی هفتم', 'توضیحات و مفاهیم اولیه درس ریاضی هفتم در کلاس کلاس هفتم الف', NULL, '2026-07-12 10:34:46'),
(2, 2, 'آموزش مبحث اول درس علوم هفتم', 'توضیحات و مفاهیم اولیه درس علوم هفتم در کلاس کلاس هفتم الف', NULL, '2026-07-12 10:34:46'),
(3, 3, 'آموزش مبحث اول درس ادبیات فارسی', 'توضیحات و مفاهیم اولیه درس ادبیات فارسی در کلاس کلاس هفتم الف', NULL, '2026-07-12 10:34:46'),
(4, 4, 'آموزش مبحث اول درس ریاضی هفتم', 'توضیحات و مفاهیم اولیه درس ریاضی هفتم در کلاس کلاس هفتم ب', NULL, '2026-07-12 10:34:46'),
(5, 5, 'آموزش مبحث اول درس علوم هفتم', 'توضیحات و مفاهیم اولیه درس علوم هفتم در کلاس کلاس هفتم ب', NULL, '2026-07-12 10:34:46'),
(6, 6, 'آموزش مبحث اول درس ادبیات فارسی', 'توضیحات و مفاهیم اولیه درس ادبیات فارسی در کلاس کلاس هفتم ب', NULL, '2026-07-12 10:34:46'),
(7, 7, 'آموزش مبحث اول درس ریاضی هشتم', 'توضیحات و مفاهیم اولیه درس ریاضی هشتم در کلاس کلاس هشتم الف', NULL, '2026-07-12 10:34:46'),
(8, 8, 'آموزش مبحث اول درس علوم هشتم', 'توضیحات و مفاهیم اولیه درس علوم هشتم در کلاس کلاس هشتم الف', NULL, '2026-07-12 10:34:46'),
(9, 9, 'آموزش مبحث اول درس زبان انگلیسی', 'توضیحات و مفاهیم اولیه درس زبان انگلیسی در کلاس کلاس هشتم الف', NULL, '2026-07-12 10:34:46'),
(10, 10, 'آموزش مبحث اول درس ریاضی هشتم', 'توضیحات و مفاهیم اولیه درس ریاضی هشتم در کلاس کلاس هشتم ب', NULL, '2026-07-12 10:34:46'),
(11, 11, 'آموزش مبحث اول درس علوم هشتم', 'توضیحات و مفاهیم اولیه درس علوم هشتم در کلاس کلاس هشتم ب', NULL, '2026-07-12 10:34:46'),
(12, 12, 'آموزش مبحث اول درس زبان انگلیسی', 'توضیحات و مفاهیم اولیه درس زبان انگلیسی در کلاس کلاس هشتم ب', NULL, '2026-07-12 10:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `national_code` varchar(10) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher','parent','operator','admin') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `national_code`, `username`, `password`, `role`, `full_name`, `email`, `phone`, `status`, `created_at`, `avatar_path`) VALUES
(1, '0012345678', 'admin', '$2y$10$TU/JVVrqO7j55NnGy34efuUXzPVQSCX0TAeVqSQatDR38bomZTGLK', 'admin', 'علیرضا کریمی (مدیر کل)', 'admin@school.com', '09121111111', 1, '2026-07-12 10:34:46', NULL),
(2, '0022345678', 'operator', '$2y$10$qgZiXMPYJCRL8/QcyKkkm.JD0PlZ39U1yawxK7pl5OAmw0GacgMmG', 'operator', 'سارا حسینی (اپراتور)', 'operator@school.com', '09122222222', 1, '2026-07-12 10:34:46', NULL),
(3, '0030000000', 'teacher_ahmadi', '$2y$10$EpOCEGdM9ukD/71OLdbvoee8p6ih0WkXZRgOuntraC.rOJNvc.IFG', 'teacher', 'استاد رضا احمدی', 'teacher_ahmadi@school.com', '09123000000', 1, '2026-07-12 10:34:46', 'uploads/avatars/teacher_man.png'),
(4, '0030000001', 'teacher_alavi', '$2y$10$EpOCEGdM9ukD/71OLdbvoee8p6ih0WkXZRgOuntraC.rOJNvc.IFG', 'teacher', 'استاد سارا علوی', 'teacher_alavi@school.com', '09123000001', 1, '2026-07-12 10:34:46', 'uploads/avatars/teacher_woman.png'),
(5, '0030000002', 'teacher_karimi', '$2y$10$EpOCEGdM9ukD/71OLdbvoee8p6ih0WkXZRgOuntraC.rOJNvc.IFG', 'teacher', 'استاد علی کریمی', 'teacher_karimi@school.com', '09123000002', 1, '2026-07-12 10:34:46', 'uploads/avatars/teacher_man.png'),
(6, '0030000003', 'teacher_hoseini', '$2y$10$EpOCEGdM9ukD/71OLdbvoee8p6ih0WkXZRgOuntraC.rOJNvc.IFG', 'teacher', 'استاد مریم حسینی', 'teacher_hoseini@school.com', '09123000003', 1, '2026-07-12 10:34:46', 'uploads/avatars/teacher_woman.png'),
(7, '0051111111', 'parent_multi', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'محمد کریمی (ولی دانش‌آموزان کریمی)', 'parent@school.com', '09125555555', 1, '2026-07-12 10:34:46', NULL),
(8, '0040000000', 'student', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'امیر کریمی', 'student@school.com', '09124000000', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(9, '0040000001', 'student_2', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'آرش کریمی', 'student_2@school.com', '09124000001', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(10, '0040000002', 'student_3', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'امین کریمی', 'student_3@school.com', '09124000002', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(11, '0040000003', 'student_4', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'سینا محمدی', 'student_4@school.com', '09124000003', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(12, '0050000003', 'parent_4', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی سینا محمدی', 'parent_4@school.com', '09125000003', 1, '2026-07-12 10:34:46', NULL),
(13, '0040000004', 'student_5', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'پوریا رضایی', 'student_5@school.com', '09124000004', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(14, '0050000004', 'parent_5', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی پوریا رضایی', 'parent_5@school.com', '09125000004', 1, '2026-07-12 10:34:46', NULL),
(15, '0040000005', 'student_6', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'علی حسینی', 'student_6@school.com', '09124000005', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(16, '0050000005', 'parent_6', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی علی حسینی', 'parent_6@school.com', '09125000005', 1, '2026-07-12 10:34:46', NULL),
(17, '0040000006', 'student_7', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'مهدی عباسی', 'student_7@school.com', '09124000006', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(18, '0050000006', 'parent_7', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی مهدی عباسی', 'parent_7@school.com', '09125000006', 1, '2026-07-12 10:34:46', NULL),
(19, '0040000007', 'student_8', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'عرفان علیزاده', 'student_8@school.com', '09124000007', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(20, '0050000007', 'parent_8', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی عرفان علیزاده', 'parent_8@school.com', '09125000007', 1, '2026-07-12 10:34:46', NULL),
(21, '0040000008', 'student_9', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'محمد اکبری', 'student_9@school.com', '09124000008', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(22, '0050000008', 'parent_9', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی محمد اکبری', 'parent_9@school.com', '09125000008', 1, '2026-07-12 10:34:46', NULL),
(23, '0040000009', 'student_10', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'سهراب احمدی', 'student_10@school.com', '09124000009', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(24, '0050000009', 'parent_10', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی سهراب احمدی', 'parent_10@school.com', '09125000009', 1, '2026-07-12 10:34:46', NULL),
(25, '0040000010', 'student_11', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'نیما ناصری', 'student_11@school.com', '09124000010', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(26, '0050000010', 'parent_11', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی نیما ناصری', 'parent_11@school.com', '09125000010', 1, '2026-07-12 10:34:46', NULL),
(27, '0040000011', 'student_12', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'سامان مرادی', 'student_12@school.com', '09124000011', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(28, '0050000011', 'parent_12', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی سامان مرادی', 'parent_12@school.com', '09125000011', 1, '2026-07-12 10:34:46', NULL),
(29, '0040000012', 'student_13', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'امید جعفری', 'student_13@school.com', '09124000012', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(30, '0050000012', 'parent_13', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی امید جعفری', 'parent_13@school.com', '09125000012', 1, '2026-07-12 10:34:46', NULL),
(31, '0040000013', 'student_14', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'ارشیا نظری', 'student_14@school.com', '09124000013', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(32, '0050000013', 'parent_14', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی ارشیا نظری', 'parent_14@school.com', '09125000013', 1, '2026-07-12 10:34:46', NULL),
(33, '0040000014', 'student_15', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'پارسا یوسفی', 'student_15@school.com', '09124000014', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(34, '0050000014', 'parent_15', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی پارسا یوسفی', 'parent_15@school.com', '09125000014', 1, '2026-07-12 10:34:46', NULL),
(35, '0040000015', 'student_16', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'آبتین راد', 'student_16@school.com', '09124000015', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(36, '0050000015', 'parent_16', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی آبتین راد', 'parent_16@school.com', '09125000015', 1, '2026-07-12 10:34:46', NULL),
(37, '0040000016', 'student_17', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'شایان فرهادی', 'student_17@school.com', '09124000016', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(38, '0050000016', 'parent_17', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی شایان فرهادی', 'parent_17@school.com', '09125000016', 1, '2026-07-12 10:34:46', NULL),
(39, '0040000017', 'student_18', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'پیمان بهرامی', 'student_18@school.com', '09124000017', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(40, '0050000017', 'parent_18', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی پیمان بهرامی', 'parent_18@school.com', '09125000017', 1, '2026-07-12 10:34:46', NULL),
(41, '0040000018', 'student_19', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'شروین صالحی', 'student_19@school.com', '09124000018', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(42, '0050000018', 'parent_19', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی شروین صالحی', 'parent_19@school.com', '09125000018', 1, '2026-07-12 10:34:46', NULL),
(43, '0040000019', 'student_20', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'آرمین خسروی', 'student_20@school.com', '09124000019', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_boy.png'),
(44, '0050000019', 'parent_20', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی آرمین خسروی', 'parent_20@school.com', '09125000019', 1, '2026-07-12 10:34:46', NULL),
(45, '0040000020', 'student_21', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'نازنین رضایی', 'student_21@school.com', '09124000020', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(46, '0050000020', 'parent_21', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی نازنین رضایی', 'parent_21@school.com', '09125000020', 1, '2026-07-12 10:34:46', NULL),
(47, '0040000021', 'student_22', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'سارا صادقی', 'student_22@school.com', '09124000021', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(48, '0050000021', 'parent_22', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی سارا صادقی', 'parent_22@school.com', '09125000021', 1, '2026-07-12 10:34:46', NULL),
(49, '0040000022', 'student_23', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'زهرا کریمی', 'student_23@school.com', '09124000022', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(50, '0050000022', 'parent_23', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی زهرا کریمی', 'parent_23@school.com', '09125000022', 1, '2026-07-12 10:34:46', NULL),
(51, '0040000023', 'student_24', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'مریم احمدی', 'student_24@school.com', '09124000023', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(52, '0050000023', 'parent_24', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی مریم احمدی', 'parent_24@school.com', '09125000023', 1, '2026-07-12 10:34:46', NULL),
(53, '0040000024', 'student_25', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'هستی نوری', 'student_25@school.com', '09124000024', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(54, '0050000024', 'parent_25', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی هستی نوری', 'parent_25@school.com', '09125000024', 1, '2026-07-12 10:34:46', NULL),
(55, '0040000025', 'student_26', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'غزل حسینی', 'student_26@school.com', '09124000025', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(56, '0050000025', 'parent_26', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی غزل حسینی', 'parent_26@school.com', '09125000025', 1, '2026-07-12 10:34:46', NULL),
(57, '0040000026', 'student_27', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'الناز مرادی', 'student_27@school.com', '09124000026', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(58, '0050000026', 'parent_27', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی الناز مرادی', 'parent_27@school.com', '09125000026', 1, '2026-07-12 10:34:46', NULL),
(59, '0040000027', 'student_28', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'آیسا طاهری', 'student_28@school.com', '09124000027', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(60, '0050000027', 'parent_28', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی آیسا طاهری', 'parent_28@school.com', '09125000027', 1, '2026-07-12 10:34:46', NULL),
(61, '0040000028', 'student_29', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'شکیبا یوسفی', 'student_29@school.com', '09124000028', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(62, '0050000028', 'parent_29', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی شکیبا یوسفی', 'parent_29@school.com', '09125000028', 1, '2026-07-12 10:34:46', NULL),
(63, '0040000029', 'student_30', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'بهار رستمی', 'student_30@school.com', '09124000029', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(64, '0050000029', 'parent_30', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی بهار رستمی', 'parent_30@school.com', '09125000029', 1, '2026-07-12 10:34:46', NULL),
(65, '0040000030', 'student_31', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'رها محمودی', 'student_31@school.com', '09124000030', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(66, '0050000030', 'parent_31', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی رها محمودی', 'parent_31@school.com', '09125000030', 1, '2026-07-12 10:34:46', NULL),
(67, '0040000031', 'student_32', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'رویا شریفی', 'student_32@school.com', '09124000031', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(68, '0050000031', 'parent_32', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی رویا شریفی', 'parent_32@school.com', '09125000031', 1, '2026-07-12 10:34:46', NULL),
(69, '0040000032', 'student_33', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'فاطمه موسوی', 'student_33@school.com', '09124000032', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(70, '0050000032', 'parent_33', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی فاطمه موسوی', 'parent_33@school.com', '09125000032', 1, '2026-07-12 10:34:46', NULL),
(71, '0040000033', 'student_34', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'عسل کرم‌پور', 'student_34@school.com', '09124000033', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(72, '0050000033', 'parent_34', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی عسل کرم‌پور', 'parent_34@school.com', '09125000033', 1, '2026-07-12 10:34:46', NULL),
(73, '0040000034', 'student_35', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'پریا راد', 'student_35@school.com', '09124000034', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(74, '0050000034', 'parent_35', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی پریا راد', 'parent_35@school.com', '09125000034', 1, '2026-07-12 10:34:46', NULL),
(75, '0040000035', 'student_36', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'هانیه فرهادی', 'student_36@school.com', '09124000035', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(76, '0050000035', 'parent_36', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی هانیه فرهادی', 'parent_36@school.com', '09125000035', 1, '2026-07-12 10:34:46', NULL),
(77, '0040000036', 'student_37', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'نگین فلاحی', 'student_37@school.com', '09124000036', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(78, '0050000036', 'parent_37', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی نگین فلاحی', 'parent_37@school.com', '09125000036', 1, '2026-07-12 10:34:46', NULL),
(79, '0040000037', 'student_38', '$2y$10$t7DVE5yDyrCg.Lk6C5vjw.fuXcuTXguhHtZ7wzKzYbJD4vJlJzHtS', 'student', 'صبا باقری', 'student_38@school.com', '09124000037', 1, '2026-07-12 10:34:46', 'uploads/avatars/avatar_6a537be18512e6.76000532.jpg'),
(80, '0050000037', 'parent_38', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی صبا باقری', 'parent_38@school.com', '09125000037', 1, '2026-07-12 10:34:46', NULL),
(81, '0040000038', 'student_39', '$2y$10$t4EOn7TMf/3A8i76vBF9x.qPCBBIrBLwBZX4RQxvlcqGXKGFSL3Km', 'student', 'سحر حیدری', 'student_39@school.com', '09124000038', 1, '2026-07-12 10:34:46', 'uploads/avatars/student_girl.png'),
(82, '0050000038', 'parent_39', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی سحر حیدری', 'parent_39@school.com', '09125000038', 1, '2026-07-12 10:34:46', NULL),
(83, '0040000039', 'student_40', '$2y$10$k9XSyzXs2ueDdkall44IXO8RPzbvBMn8Pprmmp00TghAtzTjoI.Wi', 'student', 'تارا صبوری', 'student_40@school.com', '09124000039', 1, '2026-07-12 10:34:46', 'uploads/avatars/avatar_6a537b3780a008.65696459.jpg'),
(84, '0050000039', 'parent_40', '$2y$10$Oky56eAXHcDNGzWCnQl/XOWXOd/fd5zka17/o/Tpb8.Ui20lO5bmG', 'parent', 'ولی تارا صبوری', 'parent_40@school.com', '09125000039', 1, '2026-07-12 10:34:46', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_attendance` (`class_id`,`student_id`,`date`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `registered_by` (`registered_by`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_student`
--
ALTER TABLE `class_student`
  ADD PRIMARY KEY (`class_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `class_teacher_course`
--
ALTER TABLE `class_teacher_course`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_allocation` (`class_id`,`teacher_id`,`course_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discipline_records`
--
ALTER TABLE `discipline_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `forum_messages`
--
ALTER TABLE `forum_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `homeworks`
--
ALTER TABLE `homeworks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Indexes for table `homework_submissions`
--
ALTER TABLE `homework_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_homework` (`homework_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `live_classes`
--
ALTER TABLE `live_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD PRIMARY KEY (`parent_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD PRIMARY KEY (`student_id`,`badge_id`),
  ADD KEY `badge_id` (`badge_id`),
  ADD KEY `awarded_by` (`awarded_by`);

--
-- Indexes for table `student_quizzes`
--
ALTER TABLE `student_quizzes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_quiz` (`student_id`,`quiz_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `student_quiz_answers`
--
ALTER TABLE `student_quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_quiz_question_reply` (`student_quiz_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `task_comments`
--
ALTER TABLE `task_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `task_notifications`
--
ALTER TABLE `task_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `task_templates`
--
ALTER TABLE `task_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `task_template_assignments`
--
ALTER TABLE `task_template_assignments`
  ADD PRIMARY KEY (`template_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `creator_id` (`creator_id`);

--
-- Indexes for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_teacher_course_id` (`class_teacher_course_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `national_code` (`national_code`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `class_teacher_course`
--
ALTER TABLE `class_teacher_course`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `discipline_records`
--
ALTER TABLE `discipline_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forum_messages`
--
ALTER TABLE `forum_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `homeworks`
--
ALTER TABLE `homeworks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `homework_submissions`
--
ALTER TABLE `homework_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `live_classes`
--
ALTER TABLE `live_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `student_quizzes`
--
ALTER TABLE `student_quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `student_quiz_answers`
--
ALTER TABLE `student_quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `task_comments`
--
ALTER TABLE `task_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `task_notifications`
--
ALTER TABLE `task_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `task_templates`
--
ALTER TABLE `task_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_student`
--
ALTER TABLE `class_student`
  ADD CONSTRAINT `class_student_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_teacher_course`
--
ALTER TABLE `class_teacher_course`
  ADD CONSTRAINT `class_teacher_course_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_teacher_course_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_teacher_course_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discipline_records`
--
ALTER TABLE `discipline_records`
  ADD CONSTRAINT `discipline_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `discipline_records_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_messages`
--
ALTER TABLE `forum_messages`
  ADD CONSTRAINT `forum_messages_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `homeworks`
--
ALTER TABLE `homeworks`
  ADD CONSTRAINT `homeworks_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `homework_submissions`
--
ALTER TABLE `homework_submissions`
  ADD CONSTRAINT `homework_submissions_ibfk_1` FOREIGN KEY (`homework_id`) REFERENCES `homeworks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `homework_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `live_classes`
--
ALTER TABLE `live_classes`
  ADD CONSTRAINT `live_classes_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `live_classes_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `live_classes_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notes_ibfk_3` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resources`
--
ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_badges`
--
ALTER TABLE `student_badges`
  ADD CONSTRAINT `student_badges_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_badges_ibfk_3` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_quizzes`
--
ALTER TABLE `student_quizzes`
  ADD CONSTRAINT `student_quizzes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_quizzes_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_quiz_answers`
--
ALTER TABLE `student_quiz_answers`
  ADD CONSTRAINT `student_quiz_answers_ibfk_1` FOREIGN KEY (`student_quiz_id`) REFERENCES `student_quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_quiz_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `task_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_comments`
--
ALTER TABLE `task_comments`
  ADD CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_notifications`
--
ALTER TABLE `task_notifications`
  ADD CONSTRAINT `task_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_notifications_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_templates`
--
ALTER TABLE `task_templates`
  ADD CONSTRAINT `task_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_template_assignments`
--
ALTER TABLE `task_template_assignments`
  ADD CONSTRAINT `task_template_assignments_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `task_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_template_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD CONSTRAINT `ticket_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`class_teacher_course_id`) REFERENCES `class_teacher_course` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
