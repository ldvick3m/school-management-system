<?php
/**
 * اسکریپت درج داده‌های تستی واقعی و پیشرفته برای سامانه مدیریت مدرسه
 */

require_once __DIR__ . '/../config/db.php';

try {
    // غیرفعال کردن موقت چک کردن کلیدهای خارجی
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // ۱. پاک کردن داده‌های قبلی جداول مربوطه برای جلوگیری از تداخل
    $tablesToTruncate = [
        'student_badges', 'student_quiz_answers', 'student_quizzes', 
        'homework_submissions', 'homeworks', 'resources', 'topics', 
        'class_teacher_course', 'class_student', 'parent_student', 
        'parents', 'students', 'teachers', 'courses', 'classes', 'users'
    ];
    foreach ($tablesToTruncate as $tbl) {
        $pdo->exec("TRUNCATE TABLE `$tbl`");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "جداول با موفقیت تخلیه شدند.\n";

    // شروع تراکنش برای درج داده‌های جدید
    $pdo->beginTransaction();

    // ۲. ایجاد کاربران پایه (مدیر کل و اپراتور)
    $stmtUser = $pdo->prepare("INSERT INTO `users` (`national_code`, `username`, `password`, `role`, `full_name`, `email`, `phone`, `status`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $operatorPass = password_hash('operator123', PASSWORD_DEFAULT);

    $stmtUser->execute(['0012345678', 'admin', $adminPass, 'admin', 'علیرضا کریمی (مدیر کل)', 'admin@school.com', '09121111111']);
    $stmtUser->execute(['0022345678', 'operator', $operatorPass, 'operator', 'سارا حسینی (اپراتور)', 'operator@school.com', '09122222222']);

    // ۳. ایجاد معلمان (۴ معلم با پروفایل واقعی)
    $teacherData = [
        ['username' => 'teacher_ahmadi', 'name' => 'استاد رضا احمدی', 'bio' => 'مدرس ریاضیات دوره متوسطه با ۱۰ سال سابقه تدریس.', 'avatar' => 'uploads/avatars/teacher_man.png'],
        ['username' => 'teacher_alavi', 'name' => 'استاد سارا علوی', 'bio' => 'مدرس علوم تجربی دوره متوسطه دوم و المپیاد.', 'avatar' => 'uploads/avatars/teacher_woman.png'],
        ['username' => 'teacher_karimi', 'name' => 'استاد علی کریمی', 'bio' => 'مدرس ادبیات فارسی و دستور زبان متوسطه.', 'avatar' => 'uploads/avatars/teacher_man.png'],
        ['username' => 'teacher_hoseini', 'name' => 'استاد مریم حسینی', 'bio' => 'مدرس زبان انگلیسی و دوره‌های آیلتس.', 'avatar' => 'uploads/avatars/teacher_woman.png'],
    ];

    $teacherPass = password_hash('teacher123', PASSWORD_DEFAULT);
    $teacherIds = [];

    foreach ($teacherData as $idx => $t) {
        $nationalCode = sprintf('003%07d', $idx);
        $email = $t['username'] . '@school.com';
        $phone = sprintf('091230000%02d', $idx);

        // درج کاربر
        $stmtUser = $pdo->prepare("INSERT INTO `users` (`national_code`, `username`, `password`, `role`, `full_name`, `email`, `phone`, `avatar_path`, `status`) 
            VALUES (?, ?, ?, 'teacher', ?, ?, ?, ?, 1)");
        $stmtUser->execute([$nationalCode, $t['username'], $teacherPass, $t['name'], $email, $phone, $t['avatar']]);
        $userId = $pdo->lastInsertId();

        // درج معلم
        $stmtT = $pdo->prepare("INSERT INTO `teachers` (`user_id`, `bio`) VALUES (?, ?)");
        $stmtT->execute([$userId, $t['bio']]);
        $teacherIds[$t['username']] = $pdo->lastInsertId();
    }

    echo "کاربران معلم با موفقیت ثبت شدند.\n";

    // ۴. ایجاد کلاس‌ها (۴ کلاس)
    $classesData = [
        ['name' => 'کلاس هفتم الف', 'grade' => 7],
        ['name' => 'کلاس هفتم ب', 'grade' => 7],
        ['name' => 'کلاس هشتم الف', 'grade' => 8],
        ['name' => 'کلاس هشتم ب', 'grade' => 8]
    ];
    $classIds = [];
    foreach ($classesData as $c) {
        $stmtC = $pdo->prepare("INSERT INTO `classes` (`class_name`, `grade_level`) VALUES (?, ?)");
        $stmtC->execute([$c['name'], $c['grade']]);
        $classIds[] = $pdo->lastInsertId();
    }

    // ۵. ایجاد دروس
    $coursesData = [
        ['name' => 'ریاضی هفتم', 'grade' => 7],
        ['name' => 'علوم هفتم', 'grade' => 7],
        ['name' => 'ریاضی هشتم', 'grade' => 8],
        ['name' => 'علوم هشتم', 'grade' => 8],
        ['name' => 'ادبیات فارسی', 'grade' => 7],
        ['name' => 'زبان انگلیسی', 'grade' => 8]
    ];
    $courseIds = [];
    foreach ($coursesData as $co) {
        $stmtCo = $pdo->prepare("INSERT INTO `courses` (`course_name`, `grade_level`) VALUES (?, ?)");
        $stmtCo->execute([$co['name'], $co['grade']]);
        $courseIds[$co['name']] = $pdo->lastInsertId();
    }

    echo "کلاس‌ها و دروس ثبت شدند.\n";

    // ۶. تخصیص دروس و دبیران به کلاس‌ها
    // کلاس ۱ (هفتم الف) -> ریاضی (احمدی)، علوم (علوی)، ادبیات (کریمی)
    // کلاس ۲ (هفتم ب) -> ریاضی (احمدی)، علوم (علوی)، ادبیات (کریمی)
    // کلاس ۳ (هشتم الف) -> ریاضی (احمدی)، علوم (علوی)، زبان (حسینی)
    // کلاس ۴ (هشتم ب) -> ریاضی (احمدی)، علوم (علوی)، زبان (حسینی)
    $allocations = [
        // کلاس هفتم الف
        ['class_id' => $classIds[0], 'teacher_id' => $teacherIds['teacher_ahmadi'], 'course_id' => $courseIds['ریاضی هفتم']],
        ['class_id' => $classIds[0], 'teacher_id' => $teacherIds['teacher_alavi'], 'course_id' => $courseIds['علوم هفتم']],
        ['class_id' => $classIds[0], 'teacher_id' => $teacherIds['teacher_karimi'], 'course_id' => $courseIds['ادبیات فارسی']],

        // کلاس هفتم ب
        ['class_id' => $classIds[1], 'teacher_id' => $teacherIds['teacher_ahmadi'], 'course_id' => $courseIds['ریاضی هفتم']],
        ['class_id' => $classIds[1], 'teacher_id' => $teacherIds['teacher_alavi'], 'course_id' => $courseIds['علوم هفتم']],
        ['class_id' => $classIds[1], 'teacher_id' => $teacherIds['teacher_karimi'], 'course_id' => $courseIds['ادبیات فارسی']],

        // کلاس هشتم الف
        ['class_id' => $classIds[2], 'teacher_id' => $teacherIds['teacher_ahmadi'], 'course_id' => $courseIds['ریاضی هشتم']],
        ['class_id' => $classIds[2], 'teacher_id' => $teacherIds['teacher_alavi'], 'course_id' => $courseIds['علوم هشتم']],
        ['class_id' => $classIds[2], 'teacher_id' => $teacherIds['teacher_hoseini'], 'course_id' => $courseIds['زبان انگلیسی']],

        // کلاس هشتم ب
        ['class_id' => $classIds[3], 'teacher_id' => $teacherIds['teacher_ahmadi'], 'course_id' => $courseIds['ریاضی هشتم']],
        ['class_id' => $classIds[3], 'teacher_id' => $teacherIds['teacher_alavi'], 'course_id' => $courseIds['علوم هشتم']],
        ['class_id' => $classIds[3], 'teacher_id' => $teacherIds['teacher_hoseini'], 'course_id' => $courseIds['زبان انگلیسی']],
    ];

    $allocIds = [];
    $stmtAlloc = $pdo->prepare("INSERT INTO `class_teacher_course` (`class_id`, `teacher_id`, `course_id`) VALUES (?, ?, ?)");
    foreach ($allocations as $al) {
        $stmtAlloc->execute([$al['class_id'], $al['teacher_id'], $al['course_id']]);
        $allocIds[] = $pdo->lastInsertId();
    }

    echo "تخصیص دروس و دبیران ثبت شد.\n";

    // ۷. ساخت والدین چند فرزندی
    $parentPass = password_hash('parent123', PASSWORD_DEFAULT);
    
    $stmtParentUser = $pdo->prepare("INSERT INTO `users` (`national_code`, `username`, `password`, `role`, `full_name`, `email`, `phone`, `status`) 
        VALUES (?, ?, ?, 'parent', ?, ?, ?, 1)");
    $stmtParentUser->execute(['0051111111', 'parent_multi', $parentPass, 'محمد کریمی (ولی دانش‌آموزان کریمی)', 'parent@school.com', '09125555555']);
    $parentUserId = $pdo->lastInsertId();
    
    $stmtP = $pdo->prepare("INSERT INTO `parents` (`user_id`) VALUES (?)");
    $stmtP->execute([$parentUserId]);
    $parentId = $pdo->lastInsertId();

    // ۸. ایجاد ۴۰ دانش‌آموز نمونه
    $studentPass = password_hash('student123', PASSWORD_DEFAULT);
    $studentNames = [
        // پسران
        'امیر کریمی', 'آرش کریمی', 'امین کریمی', 'سینا محمدی', 'پوریا رضایی', 
        'علی حسینی', 'مهدی عباسی', 'عرفان علیزاده', 'محمد اکبری', 'سهراب احمدی',
        'نیما ناصری', 'سامان مرادی', 'امید جعفری', 'ارشیا نظری', 'پارسا یوسفی',
        'آبتین راد', 'شایان فرهادی', 'پیمان بهرامی', 'شروین صالحی', 'آرمین خسروی',
        // دختران
        'نازنین رضایی', 'سارا صادقی', 'زهرا کریمی', 'مریم احمدی', 'هستی نوری',
        'غزل حسینی', 'الناز مرادی', 'آیسا طاهری', 'شکیبا یوسفی', 'بهار رستمی',
        'رها محمودی', 'رویا شریفی', 'فاطمه موسوی', 'عسل کرم‌پور', 'پریا راد',
        'هانیه فرهادی', 'نگین فلاحی', 'صبا باقری', 'سحر حیدری', 'تارا صبوری'
    ];

    $studentsList = [];
    $multiChildrenStudentIds = [];

    // آماده‌سازی کدهای درج اختصاصی دانش‌آموز خارج از حلقه برای سرعت بیشتر
    $stmtStudentUser = $pdo->prepare("INSERT INTO `users` (`national_code`, `username`, `password`, `role`, `full_name`, `email`, `phone`, `avatar_path`, `status`) 
        VALUES (?, ?, ?, 'student', ?, ?, ?, ?, 1)");

    $stmtS = $pdo->prepare("INSERT INTO `students` (`user_id`, `grade_level`) VALUES (?, ?)");
    $stmtEnroll = $pdo->prepare("INSERT INTO `class_student` (`class_id`, `student_id`) VALUES (?, ?)");
    $stmtPS = $pdo->prepare("INSERT INTO `parent_student` (`parent_id`, `student_id`) VALUES (?, ?)");
    $stmtP2 = $pdo->prepare("INSERT INTO `parents` (`user_id`) VALUES (?)");

    foreach ($studentNames as $idx => $name) {
        $username = 'student_' . ($idx + 1);
        if ($idx === 0) $username = 'student'; // کاربر دانش‌آموز اصلی
        
        $nationalCode = sprintf('004%07d', $idx);
        $email = $username . '@school.com';
        $phone = sprintf('091240000%02d', $idx);
        $avatar = ($idx < 20) ? 'uploads/avatars/student_boy.png' : 'uploads/avatars/student_girl.png';

        // درج کاربر دانش‌آموز
        $stmtStudentUser->execute([$nationalCode, $username, $studentPass, $name, $email, $phone, $avatar]);
        $userId = $pdo->lastInsertId();

        $gradeLevel = ($idx < 20) ? 7 : 8;

        // درج دانش‌آموز
        $stmtS->execute([$userId, $gradeLevel]);
        $studentId = $pdo->lastInsertId();

        // ثبت‌نام در کلاس‌ها
        $classIdx = floor($idx / 10);
        $classId = $classIds[$classIdx];

        $stmtEnroll->execute([$classId, $studentId]);

        $studentsList[] = [
            'student_id' => $studentId,
            'class_id' => $classId,
            'index' => $idx,
            'name' => $name
        ];

        // پیوند دادن سه فرزند اول به والد چند فرزندی
        if ($idx < 3) {
            $stmtPS->execute([$parentId, $studentId]);
            $multiChildrenStudentIds[] = $studentId;
        } else {
            // ایجاد یک والد اختصاصی برای بقیه
            $parentUsername = 'parent_' . ($idx + 1);
            $parentNational = sprintf('005%07d', $idx);
            $parentPhone = sprintf('091250000%02d', $idx);
            
            $stmtParentUser->execute([$parentNational, $parentUsername, $parentPass, 'ولی ' . $name, $parentUsername . '@school.com', $parentPhone]);
            $pUserId = $pdo->lastInsertId();

            $stmtP2 = $pdo->prepare("INSERT INTO `parents` (`user_id`) VALUES (?)");
            $stmtP2->execute([$pUserId]);
            $pId = $pdo->lastInsertId();

            $stmtPS2 = $pdo->prepare("INSERT INTO `parent_student` (`parent_id`, `student_id`) VALUES (?, ?)");
            $stmtPS2->execute([$pId, $studentId]);
        }
    }

    echo "دانش‌آموزان و والدین با موفقیت پخش و متصل شدند.\n";

    // ۹. ساخت مباحث، تکالیف و آزمون‌ها برای هر تخصیص
    // برای هر تخصیص (درس در کلاس)، یک مبحث درسی، یک تکلیف و یک آزمون ایجاد می‌کنیم
    $stmtTopic = $pdo->prepare("INSERT INTO `topics` (`class_teacher_course_id`, `topic_title`, `topic_description`) VALUES (?, ?, ?)");
    $stmtHw = $pdo->prepare("INSERT INTO `homeworks` (`topic_id`, `homework_title`, `homework_description`, `deadline`) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))");
    $stmtQuiz = $pdo->prepare("INSERT INTO `quizzes` (`topic_id`, `quiz_title`, `quiz_type`, `duration_minutes`) VALUES (?, ?, 'multiple_choice', 20)");

    $topicIds = [];
    $homeworkIds = [];
    $quizIds = [];

    // برای تخصیص‌ها
    foreach ($allocIds as $allocId) {
        // واکشی مشخصات تخصیص جهت نوشتن عنوان مرتبط
        $stmtGetDetail = $pdo->prepare("SELECT c.class_name, co.course_name 
            FROM class_teacher_course ctc
            JOIN classes c ON ctc.class_id = c.id
            JOIN courses co ON ctc.course_id = co.id
            WHERE ctc.id = ?");
        $stmtGetDetail->execute([$allocId]);
        $det = $stmtGetDetail->fetch();

        // ایجاد مبحث
        $topicTitle = "آموزش مبحث اول درس " . $det['course_name'];
        $topicDesc = "توضیحات و مفاهیم اولیه درس " . $det['course_name'] . " در کلاس " . $det['class_name'];
        $stmtTopic->execute([$allocId, $topicTitle, $topicDesc]);
        $topicId = $pdo->lastInsertId();
        $topicIds[$allocId] = $topicId;

        // ایجاد تکلیف
        $hwTitle = "تکلیف شماره ۱ " . $det['course_name'];
        $hwDesc = "لطفاً تمرین‌های صفحه ۱۰ الی ۱۵ کتاب درسی را حل کرده و فایل آن را ارسال کنید.";
        $stmtHw->execute([$topicId, $hwTitle, $hwDesc]);
        $homeworkIds[$topicId] = $pdo->lastInsertId();

        // ایجاد آزمون
        $quizTitle = "آزمون مستمر کلاسی " . $det['course_name'];
        $stmtQuiz->execute([$topicId, $quizTitle]);
        $quizIds[$topicId] = $pdo->lastInsertId();
    }

    echo "مباحث، تکالیف و آزمون‌ها ثبت شدند.\n";

    // ۱۰. ثبت نمرات و تکالیف دمو برای تک‌تک دانش‌آموزان
    // جهت رعایت رقابت و ایجاد رتبه‌بندی مشخص:
    // ۳ دانش‌آموز اول هر کلاس نمرات بسیار بالا (عالی: ۱۹ و ۲۰) می‌گیرند.
    // ۴ دانش‌آموز بعدی نمرات متوسط (۱۴ تا ۱۶) می‌گیرند.
    // ۳ دانش‌آموز آخر نمرات پایین‌تر (۹ تا ۱۳) می‌گیرند.
    // این کار رتبه‌بندی را دقیقاً مشخص و متمایز می‌کند!
    $stmtSubmitHw = $pdo->prepare("INSERT INTO `homework_submissions` (`homework_id`, `student_id`, `file_path`, `grade`, `feedback`, `status`, `submitted_at`, `graded_at`) 
        VALUES (?, ?, 'uploads/homeworks/sample.pdf', ?, ?, 'graded', DATE_SUB(NOW(), INTERVAL 2 DAY), NOW())");
    $stmtSubmitQuiz = $pdo->prepare("INSERT INTO `student_quizzes` (`student_id`, `quiz_id`, `score`, `status`, `start_time`, `submit_time`) 
        VALUES (?, ?, ?, 'completed', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW())");

    foreach ($studentsList as $st) {
        $studentId = $st['student_id'];
        $classId = $st['class_id'];
        
        // رتبه دانش‌آموز در داخل کلاس بر اساس اندیسش در گروه ۱۰ نفره
        $rankInClass = $st['index'] % 10;

        // تعیین نمره به صورت پویا برای ایجاد رتبه‌بندی مشخص
        if ($rankInClass < 3) {
            // شاگرد اول‌ها (۱۹ و ۲۰)
            $gradesList = [20, 19.5, 19];
            $grade = $gradesList[$rankInClass];
            $feedback = "بسیار عالی و دقیق! پاسخ‌های کاملاً صحیح.";
        } elseif ($rankInClass < 7) {
            // دانش‌آموزان متوسط (۱۴ تا ۱۶)
            $gradesList = [16, 15, 14.5, 14];
            $grade = $gradesList[$rankInClass - 3];
            $feedback = "خوب بود، اما تلاش بیشتری نیاز است تا به نمره بیست برسید.";
        } else {
            // دانش‌آموزان نیازمند تلاش (۹ تا ۱۳)
            $gradesList = [12.5, 11, 9.5];
            $grade = $gradesList[$rankInClass - 7];
            $feedback = "متاسفانه نمره شما پایین است. لطفاً مبحث را مجدداً مطالعه کنید.";
        }

        // درج نمرات برای تمام تکالیف و آزمون‌های این کلاس
        // پیدا کردن تمام مباحث/تکالیف/آزمون‌های مربوط به این کلاس
        foreach ($allocIds as $allocId) {
            // پیدا کردن کلاس این تخصیص
            $stmtCheckClass = $pdo->prepare("SELECT class_id FROM class_teacher_course WHERE id = ?");
            $stmtCheckClass->execute([$allocId]);
            $cId = $stmtCheckClass->fetchColumn();

            if ($cId == $classId) {
                $topicId = $topicIds[$allocId];
                $hwId = $homeworkIds[$topicId];
                $qId = $quizIds[$topicId];

                // ثبت تکلیف تصحیح شده
                $stmtSubmitHw->execute([$hwId, $studentId, $grade, $feedback]);
                
                // ثبت آزمون
                // نمره آزمون می‌تواند کمی متفاوت باشد
                $quizScore = $grade;
                $stmtSubmitQuiz->execute([$studentId, $qId, $quizScore]);
            }
        }
    }

    echo "نمرات و تکالیف دانش‌آموزان با موفقیت ثبت شدند.\n";

    // اهدای یک نشان مهارتی نمونه برای تست
    $pdo->exec("INSERT IGNORE INTO `student_badges` (`student_id`, `badge_id`, `awarded_by`, `awarded_at`) VALUES (1, 4, 1, NOW())");

    $pdo->commit();
    echo "تمام تغییرات با موفقیت در پایگاه داده ذخیره شدند!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("خطا در اجرای اسکریپت درج داده‌ها: " . $e->getMessage() . "\n");
}
