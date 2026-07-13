<?php
/**
 * دشبورد پنل دانش‌آموز به همراه ردیاب زنده کلاس آنلاین
 */

require_once '../includes/header.php';
check_auth(['student', 'admin']);

try {
    // ۱. پیدا کردن شناسه دانش‌آموز و کلاس او
    $stmtS = $pdo->prepare("SELECT s.id, s.grade_level, cs.class_id, c.class_name 
        FROM students s 
        LEFT JOIN class_student cs ON s.id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.id
        WHERE s.user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $student = $stmtS->fetch();

    if (!$student) {
        die("شما به عنوان دانش‌آموز در سیستم تعریف نشده‌اید.");
    }
    
    $studentId = $student['id'];
    $classId = $student['class_id'];
    $gradeLevel = $student['grade_level'];

    // ۲. تعداد کل دروس پایه تحصیلی
    $stmtCoursesCount = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE grade_level = ?");
    $stmtCoursesCount->execute([$gradeLevel]);
    $coursesCount = $stmtCoursesCount->fetchColumn();

    // ۳. تعداد کل مدال‌ها و نشان‌های کسب‌شده
    $stmtBadgesCount = $pdo->prepare("SELECT COUNT(*) FROM student_badges WHERE student_id = ?");
    $stmtBadgesCount->execute([$studentId]);
    $badgesCount = $stmtBadgesCount->fetchColumn();

    // واکشی مدال‌ها و نشان‌های کسب‌شده جهت نمایش مستقیم در صفحه اصلی
    $stmtMyBadges = $pdo->prepare("SELECT b.*, sb.awarded_at 
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        WHERE sb.student_id = ?
        ORDER BY sb.awarded_at DESC LIMIT 6");
    $stmtMyBadges->execute([$studentId]);
    $myBadges = $stmtMyBadges->fetchAll();

    // ۴. معدل کل دانش‌آموز (حساب کردن از تکالیف و آزمون‌های ثبت نمره شده)
    // نمرات تکالیف
    $stmtHwGrades = $pdo->prepare("SELECT grade FROM homework_submissions WHERE student_id = ? AND status = 'graded'");
    $stmtHwGrades->execute([$studentId]);
    $hwGrades = $stmtHwGrades->fetchAll(PDO::FETCH_COLUMN);

    // نمرات آزمون‌ها
    $stmtQuizGrades = $pdo->prepare("SELECT score FROM student_quizzes WHERE student_id = ? AND score IS NOT NULL");
    $stmtQuizGrades->execute([$studentId]);
    $quizGrades = $stmtQuizGrades->fetchAll(PDO::FETCH_COLUMN);

    $allGrades = array_merge($hwGrades, $quizGrades);
    $gpa = 0;
    if (count($allGrades) > 0) {
        $gpa = array_sum($allGrades) / count($allGrades);
    }

    // ۵. واکشی اعلانات هدف‌گذاری شده برای این دانش‌آموز (all, student, grade_X)
    $stmtAnn = $pdo->prepare("SELECT * FROM announcements 
        WHERE target_role = 'all' OR target_role = 'student' OR target_role = ?
        ORDER BY created_at DESC LIMIT 5");
    $stmtAnn->execute(['grade_' . $gradeLevel]);
    $announcements = $stmtAnn->fetchAll();

    // ۶. آرشیو کلاس‌های ضبط شده گذشته (آرشیو لایوها با فیلتر تاریخ)
    // در سیستم ما کلاس‌هایی که تاریخ برگزاری آن‌ها گذشته است به عنوان آرشیو لایوها استفاده می‌شوند
    $stmtArchive = $pdo->prepare("SELECT lc.*, co.course_name, u.full_name as teacher_name 
        FROM live_classes lc
        JOIN courses co ON lc.course_id = co.id
        JOIN teachers t ON lc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE lc.class_id = ? AND (lc.date < CURRENT_DATE() OR (lc.date = CURRENT_DATE() AND lc.end_time < CURRENT_TIME()))
        ORDER BY lc.date DESC, lc.start_time DESC LIMIT 5");
    $stmtArchive->execute([$classId]);
    $liveArchive = $stmtArchive->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات دشبورد دانش‌آموز: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- بخش بنر پویای کلاس لایو (AJAX) -->
    <div id="liveClassBannerContainer"></div>

    <div class="row g-4 mb-4">
        <!-- کارت ۱: کلاس/پایه تحصیلی -->
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کلاس و پایه من</h6>
                    <h4 class="fw-bold mb-0 text-primary"><?= $student['class_name'] ?: 'فاقد تخصیص کلاس' ?></h4>
                    <small class="text-secondary">پایه تحصیلی <?= $gradeLevel ?></small>
                </div>
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                    <i class="bi bi-house-door-fill fs-3"></i>
                </div>
            </div>
        </div>

        <!-- کارت ۲: معدل کل تحصیلی -->
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">معدل کل تحصیلی</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($gpa, 2) ?> <span class="fs-6 text-secondary">/ ۲۰</span></h3>
                    <small class="text-secondary">از مجموع <?= count($allGrades) ?> نمره ثبت شده</small>
                </div>
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                    <i class="bi bi-award-fill fs-3"></i>
                </div>
            </div>
        </div>

        <!-- کارت ۳: مدال‌ها و دستاوردها -->
        <div class="col-md-4">
            <a href="badges.php" class="text-decoration-none text-dark">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-secondary mb-1">مدال‌ها و نشان‌های من</h6>
                        <h3 class="fw-bold mb-0"><?= $badgesCount ?> نشان</h3>
                        <small class="text-secondary">کسب‌شده از معلمان</small>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                        <i class="bi bi-trophy-fill fs-3"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- بخش نشان‌ها و مدال‌های مهارتی من -->
    <?php if (!empty($myBadges)): ?>
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-trophy-fill text-warning me-1"></i> نشان‌ها و دستاوردهای مهارتی من
                    </h6>
                    <a href="badges.php" class="text-xs fw-bold text-primary text-decoration-none">مشاهده صندوق نشان‌ها <i class="bi bi-arrow-left"></i></a>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($myBadges as $mb): ?>
                        <div class="d-flex align-items-center gap-2 p-2 px-3 bg-light rounded-pill border" title="<?= htmlspecialchars($mb['description']) ?>" data-bs-toggle="tooltip">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-white border" style="width: 32px; height: 32px; overflow: hidden; flex-shrink: 0;">
                                <?php if (strpos($mb['icon'], 'uploads/') === 0): ?>
                                    <img src="<?= '../' . htmlspecialchars($mb['icon']) ?>" alt="<?= htmlspecialchars($mb['name']) ?>" style="width: 24px; height: 24px; object-fit: contain;">
                                <?php else: ?>
                                    <i class="bi bi-<?= htmlspecialchars($mb['icon']) ?> text-warning fs-5"></i>
                                <?php endif; ?>
                            </div>
                            <span class="small fw-bold text-dark"><?= htmlspecialchars($mb['name']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- اطلاعیه‌های مدرسه -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill text-primary me-2"></i>تابلو اعلانات مدرسه</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-megaphone fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">اطلاعیه جدیدی وجود ندارد.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($announcements as $ann): ?>
                                <div class="list-group-item px-0 py-3 border-bottom">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($ann['title']) ?></h6>
                                        <small class="text-secondary"><?= to_shamsi($ann['created_at']) ?></small>
                                    </div>
                                    <p class="mb-0 text-secondary small" style="line-height: 1.5;">
                                        <?= htmlspecialchars($ann['content']) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- آرشیو کلاس‌های ضبط شده گذشته -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-archive-fill text-success me-2"></i>آرشیو لایوها و ضبط‌شده‌های قبلی</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($liveArchive)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-camera-video-off fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">کلاس برگزار شده‌ای در آرشیو یافت نشد.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($liveArchive as $la): ?>
                                <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($la['title']) ?></h6>
                                        <small class="text-secondary">
                                            درس: <?= htmlspecialchars($la['course_name']) ?> | دبیر: <?= htmlspecialchars($la['teacher_name']) ?> | تاریخ: <?= to_shamsi($la['date']) ?>
                                        </small>
                                    </div>
                                    <a href="<?= htmlspecialchars($la['join_link']) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-play-circle-fill me-1"></i> پخش ویدیو
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- بخش سه دانش‌آموز برتر کلاس ما -->
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-0 border-start border-4 border-warning">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-trophy-fill text-warning fs-5"></i>
                            <span style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 850;">
                                ستارگان و برندگان سکوی افتخار کلاس
                            </span>
                            <span class="badge bg-warning-subtle border border-warning-subtle fs-8 px-3 py-1 rounded-pill" style="color: #92400E; font-weight: 700;">
                                <?= htmlspecialchars($student['class_name'] ?? '') ?>
                            </span>
                        </h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 justify-content-center">
                        <?php 
                        if (!empty($classId)):
                            // واکشی سه دانش‌آموز برتر کلاس از دیتابیس
                            $stmtTop = $pdo->prepare("SELECT u.full_name, u.avatar_path,
                                COALESCE(
                                    (
                                        COALESCE((SELECT SUM(grade) FROM homework_submissions hs WHERE hs.student_id = s.id AND hs.status = 'graded'), 0) +
                                        COALESCE((SELECT SUM(score) FROM student_quizzes sq WHERE sq.student_id = s.id AND sq.score IS NOT NULL), 0)
                                    ) / 
                                    NULLIF(
                                        (SELECT COUNT(*) FROM homework_submissions hs WHERE hs.student_id = s.id AND hs.status = 'graded') +
                                        (SELECT COUNT(*) FROM student_quizzes sq WHERE sq.student_id = s.id AND sq.score IS NOT NULL),
                                        0
                                    ),
                                    0
                                ) as total_avg
                                FROM students s
                                JOIN users u ON s.user_id = u.id
                                JOIN class_student cs ON s.id = cs.student_id
                                WHERE cs.class_id = ?
                                ORDER BY total_avg DESC
                                LIMIT 3");
                            $stmtTop->execute([$classId]);
                            $topStudents = $stmtTop->fetchAll();
                            
                            $rank = 1;
                            foreach ($topStudents as $ts): 
                                $avatar = !empty($ts['avatar_path']) ? '../' . $ts['avatar_path'] : '../assets/images/default-avatar.png';
                                $medalIcon = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉');
                            ?>
                                <div class="col-md-4 text-center">
                                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column align-items-center justify-content-center">
                                        <div class="position-relative mb-2">
                                            <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($ts['full_name']) ?>" class="rounded-circle border border-2 border-warning" style="width: 64px; height: 64px; object-fit: cover;">
                                            <span class="position-absolute top-0 end-0 translate-middle-y fs-4"><?= $medalIcon ?></span>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($ts['full_name']) ?></h6>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 mt-1">
                                            معدل: <?= number_format($ts['total_avg'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php 
                                $rank++;
                            endforeach; 
                        endif;
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // فعال‌سازی ردیاب کلاس آنلاین زنده در پس‌زمینه و تولتیپ‌ها
    document.addEventListener('DOMContentLoaded', function() {
        initLiveClassChecker(<?= $studentId ?>);
        
        // فعال‌سازی تولتیپ‌های توضیحات نشان‌ها
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>
