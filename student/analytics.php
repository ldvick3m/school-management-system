<?php
/**
 * کارنامه تحصیلی و تحلیل نمرات دانش‌آموز ( Academic Analytics )
 */

require_once '../includes/header.php';
check_auth(['student', 'admin']);

/**
 * تعیین وضعیت کیفی و رنگ بر اساس بازه نمره (سبز - عالی، آبی - خوب، نارنجی - نیاز به تلاش بیشتر، قرمز - ضعیف)
 */
function get_grade_info($score) {
    $score = (float)$score;
    if ($score >= 17) {
        return [
            'color' => '#065F46', // سبز تیره خوانا
            'bg_color' => '#D1FAE5', // پس‌زمینه سبز ملایم
            'border_color' => '#10B981',
            'status' => 'عالی'
        ];
    } elseif ($score >= 14) {
        return [
            'color' => '#1E40AF', // آبی تیره خوانا
            'bg_color' => '#DBEAFE', // پس‌زمینه آبی ملایم
            'border_color' => '#3B82F6',
            'status' => 'خوب'
        ];
    } elseif ($score >= 10) {
        return [
            'color' => '#92400E', // نارنجی تیره/قهوه‌ای فوق‌العاده خوانا
            'bg_color' => '#FEF3C7', // پس‌زمینه زرد/نارنجی بسیار ملایم
            'border_color' => '#F59E0B',
            'status' => 'نیاز به تلاش بیشتر'
        ];
    } else {
        return [
            'color' => '#991B1B', // قرمز تیره خوانا
            'bg_color' => '#FEE2E2', // پس‌زمینه قرمز ملایم
            'border_color' => '#EF4444',
            'status' => 'ضعیف'
        ];
    }
}

try {
    // ۱. پیدا کردن اطلاعات دانش‌آموز
    $stmtS = $pdo->prepare("SELECT s.id, s.grade_level, cs.class_id, c.class_name 
        FROM students s 
        LEFT JOIN class_student cs ON s.id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.id
        WHERE s.user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $student = $stmtS->fetch();

    if (!$student || !$student['class_id']) {
        die("شما عضو هیچ کلاسی نیستید.");
    }

    $studentId = $student['id'];
    $classId = $student['class_id'];
    $gradeLevel = $student['grade_level'];

    // ۲. فرمول ارزیابی وضعیت تکالیف (نسبت تکالیف تحویل داده شده به کل تکالیف کلاس)
    $stmtTotalHws = $pdo->prepare("SELECT COUNT(*) FROM homeworks hw 
        JOIN topics t ON hw.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        WHERE ctc.class_id = ?");
    $stmtTotalHws->execute([$classId]);
    $totalHomeworksCount = $stmtTotalHws->fetchColumn();

    $stmtMySubmissions = $pdo->prepare("SELECT COUNT(*) FROM homework_submissions WHERE student_id = ?");
    $stmtMySubmissions->execute([$studentId]);
    $submittedHomeworksCount = $stmtMySubmissions->fetchColumn();

    $homeworkSubmissionRate = 0;
    if ($totalHomeworksCount > 0) {
        $homeworkSubmissionRate = ($submittedHomeworksCount / $totalHomeworksCount) * 100;
    }

    $homeworkStatusQualitative = calculate_homework_status($homeworkSubmissionRate);

    // ۳. واکشی تمام دروس دانش‌آموز جهت تحلیل جزء به جزء نمرات
    $stmtCourses = $pdo->prepare("SELECT co.id as course_id, co.course_name, ctc.id as allocation_id 
        FROM class_teacher_course ctc
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.class_id = ?");
    $stmtCourses->execute([$classId]);
    $coursesList = $stmtCourses->fetchAll();

    $analyticsData = [];
    $gpaSum = 0;
    $gradedCoursesCount = 0;

    foreach ($coursesList as $course) {
        $courseId = $course['course_id'];
        $allocId = $course['allocation_id'];

        // الف) نمرات تکالیف این درس
        $stmtHwGrades = $pdo->prepare("SELECT hs.grade, h.homework_title, hs.submitted_at 
            FROM homework_submissions hs
            JOIN homeworks h ON hs.homework_id = h.id
            JOIN topics t ON h.topic_id = t.id
            WHERE hs.student_id = ? AND t.class_teacher_course_id = ? AND hs.status = 'graded'");
        $stmtHwGrades->execute([$studentId, $allocId]);
        $hwGrades = $stmtHwGrades->fetchAll();

        // ب) نمرات آزمون‌های این درس
        $stmtQuizGrades = $pdo->prepare("SELECT sq.score, q.id as quiz_id, q.quiz_title, q.quiz_type, sq.submit_time 
            FROM student_quizzes sq
            JOIN quizzes q ON sq.quiz_id = q.id
            JOIN topics t ON q.topic_id = t.id
            WHERE sq.student_id = ? AND t.class_teacher_course_id = ? AND sq.score IS NOT NULL");
        $stmtQuizGrades->execute([$studentId, $allocId]);
        $quizGrades = $stmtQuizGrades->fetchAll();

        // محاسبه میانگین حسابی داینامیک این درس در PHP
        $gradesList = array_merge(
            array_column($hwGrades, 'grade'),
            array_column($quizGrades, 'score')
        );

        $courseAverage = 0;
        if (count($gradesList) > 0) {
            $courseAverage = array_sum($gradesList) / count($gradesList);
            $gpaSum += $courseAverage;
            $gradedCoursesCount++;
        }

        $analyticsData[] = [
            'course_name' => $course['course_name'],
            'homeworks' => $hwGrades,
            'quizzes' => $quizGrades,
            'average' => $courseAverage,
            'grades_count' => count($gradesList)
        ];
    }

    $overallGpa = $gradedCoursesCount > 0 ? ($gpaSum / $gradedCoursesCount) : 0;

} catch (PDOException $e) {
    die("خطا در بارگذاری کارنامه تحلیلی: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row g-4">
        <!-- ستون کارت خلاصه کارنامه (سمت راست) -->
        <div class="col-lg-4">
            <!-- خلاصه کارنامه ماهانه -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 text-center border-0">
                    <h5 class="fw-bold mb-0">خلاصه کارنامه تحصیلی</h5>
                </div>
                <div class="card-body text-center">
                    <div class="profile-avatar-lg bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center text-primary fs-1 mb-3">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($_SESSION['full_name']) ?></h5>
                    <p class="text-secondary small mb-3">کلاس: <?= htmlspecialchars($student['class_name']) ?></p>
                    
                    <!-- معدل کل -->
                    <?php $gpaInfo = get_grade_info($overallGpa); ?>
                    <div class="border rounded p-3 bg-light mb-3">
                        <small class="text-secondary d-block mb-1">معدل کل نمرات</small>
                        <h2 class="fw-bold mb-1" style="color: <?= $gpaInfo['color'] ?>;"><?= number_format($overallGpa, 2) ?></h2>
                        <span class="badge" style="color: <?= $gpaInfo['color'] ?>; background-color: <?= $gpaInfo['bg_color'] ?>; border: 1px solid <?= $gpaInfo['border_color'] ?>; font-weight: bold; font-size: 0.8rem;"><?= $gpaInfo['status'] ?></span>
                    </div>

                    <!-- ردیاب تکالیف -->
                    <div class="border rounded p-3 bg-light">
                        <small class="text-secondary d-block mb-1">وضعیت کیفی تکالیف ارسالی</small>
                        <h4 class="fw-bold mb-0 text-<?= get_status_class($homeworkStatusQualitative) ?>">
                            <?= $homeworkStatusQualitative ?>
                        </h4>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-<?= get_status_class($homeworkStatusQualitative) ?>" role="progressbar" style="width: <?= $homeworkSubmissionRate ?>%;" aria-valuenow="<?= $homeworkSubmissionRate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-secondary mt-1 d-block text-xs">ارسال <?= $submittedHomeworksCount ?> از <?= $totalHomeworksCount ?> تکلیف کلاس</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ستون ریز نمرات و میانگین دروس (سمت چپ) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">تحلیل جزء به جزء نمرات دروس</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($analyticsData)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-bar-chart-line fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">درسی یافت نشد.</p>
                        </div>
                    <?php else: foreach ($analyticsData as $data): ?>
                        <div class="border rounded-3 p-4 mb-4 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($data['course_name']) ?></h5>
                                <div class="text-end">
                                    <?php if ($data['grades_count'] > 0): 
                                        $avgInfo = get_grade_info($data['average']);
                                    ?>
                                        <span class="badge fs-7 p-2" style="color: <?= $avgInfo['color'] ?>; background-color: <?= $avgInfo['bg_color'] ?>; border: 1px solid <?= $avgInfo['border_color'] ?>; font-weight: bold;">
                                            میانگین درس: <?= number_format($data['average'], 2) ?> — <?= $avgInfo['status'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-7 p-2">
                                            میانگین درس: ثبت‌نشده
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- لیست نمرات تکالیف این درس -->
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-file-earmark-text me-1"></i> نمرات تکالیف</h6>
                                    <?php if (empty($data['homeworks'])): ?>
                                        <small class="text-secondary d-block p-2 bg-white rounded border border-dashed">نمره‌ای ثبت نشده است.</small>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($data['homeworks'] as $hw): 
                                                $hwInfo = get_grade_info($hw['grade']);
                                            ?>
                                                <div class="list-group-item bg-white d-flex justify-content-between align-items-center py-2 px-3 small border">
                                                    <span class="text-truncate" style="max-width: 140px;"><?= htmlspecialchars($hw['homework_title']) ?></span>
                                                    <span class="d-flex align-items-center gap-2">
                                                        <small class="text-xs fw-bold" style="color: <?= $hwInfo['color'] ?>;"><?= $hwInfo['status'] ?></small>
                                                        <span class="badge" style="color: <?= $hwInfo['color'] ?>; background-color: <?= $hwInfo['bg_color'] ?>; border: 1px solid <?= $hwInfo['border_color'] ?>; font-weight: bold;"><?= $hw['grade'] ?></span>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- لیست نمرات آزمون‌های این درس -->
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-question-square me-1"></i> نمرات آزمون‌ها</h6>
                                    <?php if (empty($data['quizzes'])): ?>
                                        <small class="text-secondary d-block p-2 bg-white rounded border border-dashed">نمره‌ای ثبت نشده است.</small>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($data['quizzes'] as $qz): 
                                                $qzInfo = get_grade_info($qz['score']);
                                            ?>
                                                <div class="list-group-item bg-white d-flex justify-content-between align-items-center py-2 px-3 small border">
                                                    <span class="text-truncate" style="max-width: 140px;"><?= htmlspecialchars($qz['quiz_title']) ?></span>
                                                    <span class="d-flex align-items-center gap-2">
                                                        <small class="text-xs fw-bold" style="color: <?= $qzInfo['color'] ?>;"><?= $qzInfo['status'] ?></small>
                                                        <span class="badge" style="color: <?= $qzInfo['color'] ?>; background-color: <?= $qzInfo['bg_color'] ?>; border: 1px solid <?= $qzInfo['border_color'] ?>; font-weight: bold;"><?= $qz['score'] ?></span>
                                                        <?php if ($qz['quiz_type'] === 'multiple_choice'): ?>
                                                            <a href="quiz.php?quiz_id=<?= $qz['quiz_id'] ?>&show_answers=1" class="btn btn-outline-primary btn-xs py-0 px-2 fw-bold" style="font-size: 0.75rem; border-radius: 4px;">جزئیات</a>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
