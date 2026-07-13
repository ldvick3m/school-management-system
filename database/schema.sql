-- ساخت دیتابیس در صورتی که وجود نداشته باشد
CREATE DATABASE IF NOT EXISTS `school-management-system` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;
USE `school-management-system`;

-- جدول کاربران سیستم (ورود یکپارچه)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `national_code` VARCHAR(10) NOT NULL UNIQUE,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'teacher', 'parent', 'operator', 'admin') NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NULL,
    `phone` VARCHAR(11) NULL,
    `status` TINYINT DEFAULT 1, -- 1: فعال، 0: غیرفعال
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول اطلاعات اختصاصی دانش‌آموزان
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `grade_level` INT NOT NULL, -- پایه تحصیلی (مثلاً 7، 8، 9، 10، 11، 12)
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول اطلاعات اختصاصی معلمان
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `bio` TEXT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول اطلاعات اختصاصی والدین
CREATE TABLE IF NOT EXISTS `parents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول واسط پیوند والدین به دانش‌آموزان (یک والد می‌تواند چند فرزند داشته باشد و برعکس)
CREATE TABLE IF NOT EXISTS `parent_student` (
    `parent_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    PRIMARY KEY (`parent_id`, `student_id`),
    FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول دروس مدرسه (مثلاً ریاضی، فیزیک، شیمی)
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_name` VARCHAR(100) NOT NULL,
    `grade_level` INT NOT NULL -- درسی برای چه پایه‌ای است
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول کلاس‌های مدرسه (مثلاً کلاس دهم ریاضی الف، کلاس یازدهم تجربی ب)
CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(100) NOT NULL,
    `grade_level` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول ثبت‌نام دانش‌آموزان در کلاس‌ها
CREATE TABLE IF NOT EXISTS `class_student` (
    `class_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    PRIMARY KEY (`class_id`, `student_id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول واسط تخصیص معلمان به دروس در کلاس‌های مختلف
CREATE TABLE IF NOT EXISTS `class_teacher_course` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_allocation` (`class_id`, `teacher_id`, `course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول مباحث درسی (موضوعاتی که معلم برای هر کلاس/درس آپلود می‌کند)
CREATE TABLE IF NOT EXISTS `topics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_teacher_course_id` INT NULL,
    `topic_title` VARCHAR(255) NOT NULL,
    `topic_description` TEXT NULL,
    `video_path` VARCHAR(255) NULL, -- ویدیو اصلی مبحث
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_teacher_course_id`) REFERENCES `class_teacher_course`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول منابع آموزشی ضمیمه مباحث (فایل‌ها و لینک‌های فرعی)
CREATE TABLE IF NOT EXISTS `resources` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `topic_id` INT NOT NULL,
    `resource_name` VARCHAR(255) NOT NULL,
    `resource_type` ENUM('link', 'pdf', 'image', 'video') NOT NULL,
    `resource_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تالار گفتگوی مباحث درسی (Lesson Forum)
CREATE TABLE IF NOT EXISTS `forum_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `topic_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `message_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تکالیف مربوط به مباحث
CREATE TABLE IF NOT EXISTS `homeworks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `topic_id` INT NOT NULL,
    `homework_title` VARCHAR(255) NOT NULL,
    `homework_description` TEXT NULL,
    `deadline` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول پاسخ‌های تکالیف دانش‌آموزان
CREATE TABLE IF NOT EXISTS `homework_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `homework_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'graded') DEFAULT 'pending',
    `grade` DECIMAL(4,2) NULL,
    `feedback` TEXT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `graded_at` DATETIME NULL,
    FOREIGN KEY (`homework_id`) REFERENCES `homeworks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_homework` (`homework_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول آزمون‌ها (Quizzes)
CREATE TABLE IF NOT EXISTS `quizzes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `topic_id` INT NOT NULL,
    `quiz_title` VARCHAR(255) NOT NULL,
    `quiz_type` ENUM('multiple_choice', 'essay') NOT NULL,
    `duration_minutes` INT NULL, -- برای آزمون‌های تستی
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول سوالات آزمون‌ها
CREATE TABLE IF NOT EXISTS `quiz_questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `quiz_id` INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('multiple_choice', 'essay') NOT NULL,
    -- گزینه‌ها برای آزمون‌های تستی
    `option_1` TEXT NULL,
    `option_2` TEXT NULL,
    `option_3` TEXT NULL,
    `option_4` TEXT NULL,
    `correct_option` INT NULL, -- مقدار 1، 2، 3 یا 4
    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول پیگیری وضعیت شرکت دانش‌آموزان در آزمون‌ها
CREATE TABLE IF NOT EXISTS `student_quizzes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `quiz_id` INT NOT NULL,
    `start_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `submit_time` DATETIME NULL,
    `score` DECIMAL(4,2) NULL,
    `feedback` TEXT NULL,
    `status` ENUM('started', 'completed', 'failed_deadline') DEFAULT 'started',
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_quiz` (`student_id`, `quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول جواب‌های ثبت‌شده برای سوالات آزمون
CREATE TABLE IF NOT EXISTS `student_quiz_answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_quiz_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `selected_option` INT NULL, -- برای آزمون‌های تستی
    `essay_answer_file_path` VARCHAR(255) NULL, -- برای پاسخبرگ آزمون تشریحی
    `score` DECIMAL(4,2) NULL, -- نمره اختصاص داده شده به سوال تشریحی
    FOREIGN KEY (`student_quiz_id`) REFERENCES `student_quizzes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_quiz_question_reply` (`student_quiz_id`, `question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول حضور و غیاب دانش‌آموزان
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `status` ENUM('present', 'absent') NOT NULL,
    `registered_by` INT NOT NULL, -- شناسه معلم
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`registered_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_daily_attendance` (`class_id`, `student_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول دفتر رادار انضباطی
CREATE TABLE IF NOT EXISTS `discipline_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `status` ENUM('excellent', 'good', 'average', 'poor') NOT NULL,
    `reason` TEXT NULL, -- در صورتی که عالی نباشد اجباری است
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول تیکت‌های پشتیبانی اداری/مالی
CREATE TABLE IF NOT EXISTS `tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `creator_id` INT NOT NULL, -- والد یا دانش‌آموز
    `category` ENUM('admin', 'financial') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `status` ENUM('open', 'in_progress', 'closed') DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول پیام‌های تیکت‌ها
CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `message_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول پیام‌های چت‌روم‌ها (بین معلم-والدین و معلم-دانش‌آموز)
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message_text` TEXT NOT NULL,
    `is_read` TINYINT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول کلاس‌های آنلاین/لایو
CREATE TABLE IF NOT EXISTS `live_classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `join_link` VARCHAR(500) NOT NULL,
    `sms_sent` TINYINT DEFAULT 0, -- شبیه‌ساز ارسال پیامک
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول اطلاعیه‌های عمومی مدرسه
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `target_role` VARCHAR(50) DEFAULT 'all', -- all, teacher, parent, student, grade_X
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول دفترچه یادداشت دیجیتال (کاملاً محرمانه)
CREATE TABLE IF NOT EXISTS `notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `note_type` ENUM('general', 'course') NOT NULL,
    `course_id` INT NULL,
    `topic_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول نشان‌ها و دستاوردهای تعریف شده
CREATE TABLE IF NOT EXISTS `badges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(50) NOT NULL, -- نام آیکون بوت‌استرپ
    `type` ENUM('educational', 'behavioral') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- جدول مدال‌های اهدا شده به دانش‌آموزان
CREATE TABLE IF NOT EXISTS `student_badges` (
    `student_id` INT NOT NULL,
    `badge_id` INT NOT NULL,
    `awarded_by` INT NOT NULL, -- شناسه معلم صادر کننده
    `awarded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`student_id`, `badge_id`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`awarded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- داده‌های تستی اولیه برای نشان‌ها (Badges)
INSERT INTO `badges` (`id`, `name`, `description`, `icon`, `type`) VALUES
(1, 'شاگرد اول ماه', 'کسب بالاترین معدل تحصیلی کلاس در طول ماه گذشته', 'trophy-fill', 'educational'),
(2, 'پژوهشگر برتر', 'تحقیق و پژوهش علمی فوق برنامه و ارائه به دبیر مربوطه', 'search', 'educational'),
(3, 'تلاشگر هفته', 'پیشرفت چشمگیر در نمرات و عملکرد درسی هفتگی', 'graph-up-arrow', 'educational'),
(4, 'کتابخوان برتر', 'بیشترین میزان امانت کتاب و مشارکت در فعالیت کتابخانه', 'book-half', 'behavioral'),
(5, 'همیار معلم', 'مشارکت فعال در برگزاری و رفع اشکال سایر همکلاسی‌ها', 'people-fill', 'behavioral'),
(6, 'منضبط‌ترین دانش‌آموز', 'رعایت حداکثری قوانین انضباطی و حضور به‌موقع در کلاس‌ها', 'shield-check', 'behavioral')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
