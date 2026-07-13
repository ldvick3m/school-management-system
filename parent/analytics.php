<?php
/**
 * رصد تحصیلی و گزارش‌های پیشرفته تحلیلی فرزند (Chart.js)
 */

require_once '../includes/header.php';
check_auth(['parent', 'admin']);

$activeChildId = $_SESSION['active_child_id'] ?? 0;
$activeChildName = $_SESSION['active_child_name'] ?? '';

// متغیرهای آماری نمودارها
$chartCoursesNames = [];
$chartChildAverages = [];
$chartClassAverages = [];

$hwStats = ['completed' => 0, 'pending_grade' => 0, 'overdue' => 0];

$radarSkills = ['academic' => 80, 'discipline' => 100, 'teamwork' => 70];

$disciplineRecords = [];

if ($activeChildId > 0) {
    try {
        // پیدا کردن کلاس و یوزر آی‌دی فرزند
        $stmtChildInfo = $pdo->prepare("SELECT s.grade_level, cs.class_id, s.user_id 
            FROM students s 
            JOIN class_student cs ON s.id = cs.student_id 
            WHERE s.id = ?");
        $stmtChildInfo->execute([$activeChildId]);
        $childInfo = $stmtChildInfo->fetch();
        
        if ($childInfo) {
            $classId = $childInfo['class_id'];
            $childUserId = $childInfo['user_id'];

            // ۱. آماده‌سازی داده‌های نمودار خطی (Line Chart) مقایسه نمرات فرزند با میانگین کل کلاس
            // دروس فعال کلاس
            $stmtC = $pdo->prepare("SELECT co.id as course_id, co.course_name, ctc.id as allocation_id 
                FROM class_teacher_course ctc
                JOIN courses co ON ctc.course_id = co.id
                WHERE ctc.class_id = ?");
            $stmtC->execute([$classId]);
            $courses = $stmtC->fetchAll();

            $sumAcademic = 0;
            $coursesGradedCount = 0;

            foreach ($courses as $c) {
                $courseId = $c['course_id'];
                $allocId = $c['allocation_id'];
                $chartCoursesNames[] = $c['course_name'];

                // الف) میانگین فرزند در این درس (تکالیف + آزمون‌ها)
                $stmtChildGradesHw = $pdo->prepare("SELECT grade FROM homework_submissions hs
                    JOIN homeworks h ON hs.homework_id = h.id
                    JOIN topics t ON h.topic_id = t.id
                    WHERE hs.student_id = ? AND t.class_teacher_course_id = ? AND hs.status = 'graded'");
                $stmtChildGradesHw->execute([$activeChildId, $allocId]);
                $hwGrades = $stmtChildGradesHw->fetchAll(PDO::FETCH_COLUMN);

                $stmtChildGradesQz = $pdo->prepare("SELECT score FROM student_quizzes sq
                    JOIN quizzes q ON sq.quiz_id = q.id
                    JOIN topics t ON q.topic_id = t.id
                    WHERE sq.student_id = ? AND t.class_teacher_course_id = ? AND sq.score IS NOT NULL");
                $stmtChildGradesQz->execute([$activeChildId, $allocId]);
                $qzGrades = $stmtChildGradesQz->fetchAll(PDO::FETCH_COLUMN);

                $childAllGrades = array_merge($hwGrades, $qzGrades);
                $childAvg = count($childAllGrades) > 0 ? (array_sum($childAllGrades) / count($childAllGrades)) : 0;
                $chartChildAverages[] = round($childAvg, 2);
                
                if ($childAvg > 0) {
                    $sumAcademic += $childAvg;
                    $coursesGradedCount++;
                }

                // ب) میانگین کل دانش‌آموزان کلاس در این درس
                $stmtClassGradesHw = $pdo->prepare("SELECT hs.grade FROM homework_submissions hs
                    JOIN homeworks h ON hs.homework_id = h.id
                    JOIN topics t ON h.topic_id = t.id
                    JOIN class_student cs ON hs.student_id = cs.student_id
                    WHERE cs.class_id = ? AND t.class_teacher_course_id = ? AND hs.status = 'graded'");
                $stmtClassGradesHw->execute([$classId, $allocId]);
                $classHwGrades = $stmtClassGradesHw->fetchAll(PDO::FETCH_COLUMN);

                $stmtClassGradesQz = $pdo->prepare("SELECT sq.score FROM student_quizzes sq
                    JOIN quizzes q ON sq.quiz_id = q.id
                    JOIN topics t ON q.topic_id = t.id
                    JOIN class_student cs ON sq.student_id = cs.student_id
                    WHERE cs.class_id = ? AND t.class_teacher_course_id = ? AND sq.score IS NOT NULL");
                $stmtClassGradesQz->execute([$classId, $allocId]);
                $classQzGrades = $stmtClassGradesQz->fetchAll(PDO::FETCH_COLUMN);

                $classAllGrades = array_merge($classHwGrades, $classQzGrades);
                $classAvg = count($classAllGrades) > 0 ? (array_sum($classAllGrades) / count($classAllGrades)) : 0;
                $chartClassAverages[] = round($classAvg, 2);
            }

            // ۲. آماده‌سازی داده‌های نمودار دونات (Doughnut Chart) وضعیت تکالیف
            // تعداد کل تکالیف
            $stmtHwTotal = $pdo->prepare("SELECT hw.id, hw.deadline FROM homeworks hw
                JOIN topics t ON hw.topic_id = t.id
                JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
                WHERE ctc.class_id = ?");
            $stmtHwTotal->execute([$classId]);
            $allHws = $stmtHwTotal->fetchAll();

            foreach ($allHws as $hw) {
                // چک کردن پاسخ فرزند
                $stmtSub = $pdo->prepare("SELECT status FROM homework_submissions WHERE homework_id = ? AND student_id = ?");
                $stmtSub->execute([$hw['id'], $activeChildId]);
                $subStatus = $stmtSub->fetchColumn();

                if ($subStatus === 'graded') {
                    $hwStats['completed']++;
                } elseif ($subStatus === 'pending') {
                    $hwStats['pending_grade']++;
                } else {
                    // بررسی معوقه
                    if (strtotime($hw['deadline']) < time()) {
                        $hwStats['overdue']++;
                    } else {
                        $hwStats['pending_grade']++; // در انتظار ارسال
                    }
                }
            }

            // ۳. داده‌های نمودار تعادل مهارت‌ها (Radar Chart)
            // الف) نمره علمی (Academic): معدل نمرات ضرب در ۵ جهت نگاشت به مقیاس ۱۰۰
            $overallGpa = $coursesGradedCount > 0 ? ($sumAcademic / $coursesGradedCount) : 0;
            $radarSkills['academic'] = round($overallGpa * 5, 2);

            // ب) نمره انضباطی (Discipline): از ۱۰-۱۰۰ بر اساس رکوردهای انضباطی
            $stmtDr = $pdo->prepare("SELECT status FROM discipline_records WHERE student_id = ?");
            $stmtDr->execute([$activeChildId]);
            $drStatuses = $stmtDr->fetchAll(PDO::FETCH_COLUMN);

            $drScore = 100;
            if (count($drStatuses) > 0) {
                $sums = 0;
                foreach ($drStatuses as $status) {
                    if ($status === 'excellent') $sums += 100;
                    elseif ($status === 'good') $sums += 80;
                    elseif ($status === 'average') $sums += 60;
                    else $sums += 40;
                }
                $drScore = $sums / count($drStatuses);
            }
            $radarSkills['discipline'] = round($drScore, 2);

            // ج) کار تیمی (Teamwork): شبیه‌سازی بر اساس تعداد پیام‌های تالار گفتگوی دانش‌آموز
            $stmtForumCount = $pdo->prepare("SELECT COUNT(*) FROM forum_messages WHERE sender_id = ?");
            $stmtForumCount->execute([$childUserId]);
            $postsCount = $stmtForumCount->fetchColumn();

            $teamworkScore = 50 + min(50, $postsCount * 5); // حداکثر ۱۰۰
            $radarSkills['teamwork'] = $teamworkScore;

            // ۴. آرشیو تاریخچه انضباطی فرزند
            $stmtDrList = $pdo->prepare("SELECT dr.*, u.full_name as teacher_name 
                FROM discipline_records dr
                JOIN users u ON dr.teacher_id = u.id
                WHERE dr.student_id = ?
                ORDER BY dr.created_at DESC");
            $stmtDrList->execute([$activeChildId]);
            $disciplineRecords = $stmtDrList->fetchAll();
        }

    } catch (PDOException $e) {
        die("خطا در واکشی گزارش‌های تحلیلی فرزند: " . $e->getMessage());
    }
}
?>

<div class="container-fluid">
    <div class="row g-4">
        <!-- ۱. نمودار مقایسه معدل تحصیلی با کلاس (Line Chart) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-graph-up text-primary me-2"></i>مقایسه عملکرد تحصیلی فرزند با میانگین کلاس</h5>
                </div>
                <div class="card-body">
                    <canvas id="lineChart" style="max-height: 320px;"></canvas>
                </div>
            </div>
        </div>

        <!-- ۲. نمودار دونات تکالیف (Doughnut Chart) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart text-success me-2"></i>وضعیت تکالیف درسی</h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="max-width: 220px; width: 100%;">
                        <canvas id="doughnutChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ۳. نمودار تعادل مهارت‌ها (Radar Chart) -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-patch-check-fill text-warning me-2"></i>رادار تعادل مهارت‌های فردی و تحصیلی</h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="max-width: 280px; width: 100%;">
                        <canvas id="radarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ۴. آرشیو تاریخچه انضباطی -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-shield-exclamation text-danger me-2"></i>آرشیو گزارش‌ها و تاریخچه انضباطی</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($disciplineRecords)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-shield-check fs-1 text-success mb-2"></i>
                            <p class="text-secondary mb-0">گزارش انضباطی منفی برای فرزند شما ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>وضعیت انضباطی</th>
                                        <th>ثبت کننده (دبیر)</th>
                                        <th>علت و توضیح تذکر</th>
                                        <th>تاریخ ثبت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($disciplineRecords as $dr): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= get_status_class($dr['status']) ?>-subtle text-<?= get_status_class($dr['status']) ?> border">
                                                    <?php 
                                                        switch($dr['status']) {
                                                            case 'excellent': echo 'عالی'; break;
                                                            case 'good': echo 'خوب'; break;
                                                            case 'average': echo 'متوسط'; break;
                                                            case 'poor': echo 'ضعیف'; break;
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold"><?= htmlspecialchars($dr['teacher_name']) ?></td>
                                            <td>
                                                <small class="text-dark"><?= htmlspecialchars($dr['reason']) ?></small>
                                            </td>
                                            <td><?= to_shamsi($dr['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // ۱. مقداردهی نمودار مقایسه عملکرد تحصیلی (Line Chart)
        const ctxLine = document.getElementById('lineChart').getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartCoursesNames) ?>,
                datasets: [
                    {
                        label: 'نمره فرزند شما',
                        data: <?= json_encode($chartChildAverages) ?>,
                        borderColor: '#0D6EFD',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'میانگین کل کلاس',
                        data: <?= json_encode($chartClassAverages) ?>,
                        borderColor: '#94A3B8',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Vazirmatn' } }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 20,
                        ticks: { font: { family: 'Vazirmatn' } }
                    },
                    x: {
                        ticks: { font: { family: 'Vazirmatn' } }
                    }
                }
            }
        });

        // ۲. نمودار دونات تکالیف (Doughnut Chart)
        const ctxDoughnut = document.getElementById('doughnutChart').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['تکالیف تحویل‌شده (تصحیح شده)', 'در انتظار تصحیح / اقدام', 'معوقه (گذشت مهلت)'],
                datasets: [{
                    data: [<?= $hwStats['completed'] ?>, <?= $hwStats['pending_grade'] ?>, <?= $hwStats['overdue'] ?>],
                    backgroundColor: ['#198754', '#FFC107', '#DC3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: 'Vazirmatn' } }
                    }
                }
            }
        });

        // ۳. نمودار تعادل مهارت‌ها (Radar Chart)
        const ctxRadar = document.getElementById('radarChart').getContext('2d');
        new Chart(ctxRadar, {
            type: 'radar',
            data: {
                labels: ['آمادگی علمی و درسی', 'انضباط و حضور سر کلاس', 'تعامل و کار تیمی (تالار گفتگو)'],
                datasets: [{
                    label: 'مهارت‌های فرزند',
                    data: [<?= $radarSkills['academic'] ?>, <?= $radarSkills['discipline'] ?>, <?= $radarSkills['teamwork'] ?>],
                    backgroundColor: 'rgba(255, 193, 7, 0.2)',
                    borderColor: '#FFC107',
                    borderWidth: 2,
                    pointBackgroundColor: '#FFC107'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    r: {
                        min: 0,
                        max: 100,
                        ticks: { display: false }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

    });
</script>

<?php require_once '../includes/footer.php'; ?>
