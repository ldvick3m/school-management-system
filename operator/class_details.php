<?php
/**
 * جزئیات کلاس شامل دبیران، سه دانش‌آموز برتر و لیست سایر دانش‌آموزان
 */

require_once '../includes/header.php';
check_auth(['operator', 'admin']);

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId <= 0) {
    die("شناسه کلاس نامعتبر است.");
}

try {
    // ۱. دریافت اطلاعات اصلی کلاس
    $stmtC = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
    $stmtC->execute([$classId]);
    $classInfo = $stmtC->fetch();
    if (!$classInfo) {
        die("کلاس مورد نظر یافت نشد.");
    }

    // ۲. دریافت دبیران کلاس و دروس مربوطه
    $stmtT = $pdo->prepare("SELECT DISTINCT u.full_name, u.avatar_path, co.course_name 
        FROM class_teacher_course ctc
        JOIN teachers t ON ctc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.class_id = ?");
    $stmtT->execute([$classId]);
    $teachers = $stmtT->fetchAll();

    // ۳. دریافت ۳ دانش‌آموز برتر کلاس بر اساس معدل کل تکالیف و آزمون‌ها
    $stmtTop = $pdo->prepare("SELECT s.id, u.full_name, u.avatar_path,
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

    // استخراج شناسه‌های دانش‌آموزان برتر جهت فیلتر کردن آن‌ها در لیست بقیه
    $topStudentIds = [];
    foreach ($topStudents as $ts) {
        $topStudentIds[] = $ts['id'];
    }

    // ۴. دریافت لیست سایر دانش‌آموزان کلاس و خلاصه وضعیت هر کدام
    // اگر لیست برترین‌ها خالی نبود، شناسه‌های آن‌ها را فیلتر می‌کنیم
    $otherStudents = [];
    if (!empty($classId)) {
        $queryOther = "SELECT s.id, u.full_name, u.avatar_path,
            -- معدل کل
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
            ) as total_avg,
            -- تعداد تکالیف ارسال شده / کل تکالیف کلاس
            (SELECT COUNT(*) FROM homework_submissions hs JOIN homeworks h ON hs.homework_id = h.id JOIN topics t ON h.topic_id = t.id JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id WHERE hs.student_id = s.id AND ctc.class_id = cs.class_id) as hw_submitted,
            (SELECT COUNT(*) FROM homeworks h JOIN topics t ON h.topic_id = t.id JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id WHERE ctc.class_id = cs.class_id) as hw_total,
            -- تعداد آزمون‌های شرکت شده / کل آزمون‌های کلاس
            (SELECT COUNT(*) FROM student_quizzes sq JOIN quizzes q ON sq.quiz_id = q.id JOIN topics t ON q.topic_id = t.id JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id WHERE sq.student_id = s.id AND ctc.class_id = cs.class_id AND sq.status = 'completed') as quizzes_submitted,
            (SELECT COUNT(*) FROM quizzes q JOIN topics t ON q.topic_id = t.id JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id WHERE ctc.class_id = cs.class_id) as quizzes_total,
            -- انضباط
            (SELECT status FROM discipline_records dr WHERE dr.student_id = s.id ORDER BY dr.created_at DESC LIMIT 1) as last_discipline
            FROM class_student cs
            JOIN students s ON cs.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE cs.class_id = ?";
        
        if (!empty($topStudentIds)) {
            $placeholders = implode(',', array_fill(0, count($topStudentIds), '?'));
            $queryOther .= " AND s.id NOT IN ($placeholders)";
        }
        
        $queryOther .= " ORDER BY u.full_name ASC";
        $stmtOther = $pdo->prepare($queryOther);
        
        $params = array_merge([$classId], $topStudentIds);
        $stmtOther->execute($params);
        $otherStudents = $stmtOther->fetchAll();
    }

} catch (PDOException $e) {
    die("خطا در لود اطلاعات کلاس: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- هدر صفحه و دکمه بازگشت -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">بررسی وضعیت کلاس: <?= htmlspecialchars($classInfo['class_name']) ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="#" onclick="window.history.back(); return false;" class="text-decoration-none">کلاس‌ها</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($classInfo['class_name']) ?></li>
                </ol>
            </nav>
        </div>
        <button onclick="window.history.back();" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="bi bi-arrow-right me-1"></i> بازگشت
        </button>
    </div>

    <!-- کارت اطلاعات کلیدی بالا (دبیران در چپ و سه دانش‌آموز برتر در راست) -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <!-- راست: سه دانش‌آموز برتر کلاس -->
                <div class="col-md-8 d-flex align-items-center flex-wrap gap-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-trophy-fill text-warning fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">برترین‌های کلاس:</h6>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <?php 
                        $rank = 1;
                        foreach ($topStudents as $ts): 
                            $avatar = !empty($ts['avatar_path']) ? '../' . $ts['avatar_path'] : '../assets/images/default-avatar.png';
                            $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉');
                        ?>
                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded-3 border" style="min-width: 170px;">
                                <div class="position-relative" style="width: 38px; height: 38px;">
                                    <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($ts['full_name']) ?>" class="rounded-circle border" style="width: 38px; height: 38px; object-fit: cover;">
                                    <span class="position-absolute top-0 end-0 translate-middle-y fs-6"><?= $medal ?></span>
                                </div>
                                <div>
                                    <span class="small fw-bold text-dark d-block" style="font-size: 0.85rem;"><?= htmlspecialchars($ts['full_name']) ?></span>
                                    <small class="text-success fw-bold" style="font-size: 0.75rem;">معدل: <?= number_format($ts['total_avg'], 2) ?></small>
                                </div>
                            </div>
                        <?php 
                            $rank++;
                        endforeach; 
                        if (empty($topStudents)):
                            echo '<span class="text-secondary small">نمره‌ای ثبت نشده است</span>';
                        endif;
                        ?>
                    </div>
                </div>
                
                <!-- چپ: مشخصات دبیران کلاس -->
                <div class="col-md-4 text-md-end d-flex align-items-center justify-content-md-end gap-3 mt-3 mt-md-0 border-start-md border-secondary border-opacity-10">
                    <div class="d-flex flex-column align-items-md-end">
                        <span class="small text-secondary fw-bold mb-1"><i class="bi bi-person-workspace text-primary me-1"></i> دبیران کلاس:</span>
                        <div class="d-flex align-items-center gap-2 justify-content-md-end flex-wrap">
                            <?php foreach ($teachers as $tc): 
                                $avatar = !empty($tc['avatar_path']) ? '../' . $tc['avatar_path'] : '../assets/images/default-avatar.png';
                            ?>
                                <div class="d-flex align-items-center gap-2 p-1 px-2 bg-light rounded-3 border" title="<?= htmlspecialchars($tc['course_name']) ?>">
                                    <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($tc['full_name']) ?>" class="rounded-circle border" style="width: 30px; height: 30px; object-fit: cover;">
                                    <div class="text-start">
                                        <span class="small fw-bold text-dark d-block" style="font-size: 0.8rem;"><?= htmlspecialchars($tc['full_name']) ?></span>
                                        <small class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($tc['course_name']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; 
                            if (empty($teachers)):
                                echo '<span class="text-secondary small">دبیری تخصیص داده نشده است</span>';
                            endif;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول لیست سایر دانش‌آموزان و خلاصه وضعیت -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill text-primary me-1"></i> لیست سایر دانش‌آموزان کلاس</h5>
            <span class="badge bg-secondary"><?= count($otherStudents) ?> دانش‌آموز</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($otherStudents)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people fs-1 text-secondary mb-2"></i>
                    <p class="text-secondary mb-0">دانش‌آموز دیگری در این کلاس یافت نشد یا ثبت‌نام نشده است.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>نام دانش‌آموز</th>
                                <th>معدل کل نمرات</th>
                                <th>تکالیف تحویل داده شده</th>
                                <th>آزمون‌های شرکت شده</th>
                                <th>آخرین وضعیت انضباطی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($otherStudents as $os): 
                                $avatar = !empty($os['avatar_path']) ? '../' . $os['avatar_path'] : '../assets/images/default-avatar.png';
                                
                                // تعیین بج انضباطی
                                $disciplineClass = 'bg-secondary';
                                $disciplineText = 'ثبت نشده';
                                if ($os['last_discipline'] === 'excellent') {
                                    $disciplineClass = 'bg-success';
                                    $disciplineText = 'عالی';
                                } elseif ($os['last_discipline'] === 'good') {
                                    $disciplineClass = 'bg-primary';
                                    $disciplineText = 'خوب';
                                } elseif ($os['last_discipline'] === 'average') {
                                    $disciplineClass = 'bg-warning text-dark';
                                    $disciplineText = 'متوسط';
                                } elseif ($os['last_discipline'] === 'poor') {
                                    $disciplineClass = 'bg-danger';
                                    $disciplineText = 'ضعیف';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($os['full_name']) ?>" class="rounded-circle border" style="width: 32px; height: 32px; object-fit: cover;">
                                            <span class="fw-bold"><?= htmlspecialchars($os['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle p-2 fw-bold">
                                            <?= number_format($os['total_avg'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold"><?= $os['hw_submitted'] ?> از <?= $os['hw_total'] ?></span>
                                            <div class="progress" style="width: 60px; height: 6px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $os['hw_total'] > 0 ? ($os['hw_submitted'] / $os['hw_total'] * 100) : 0 ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold"><?= $os['quizzes_submitted'] ?> از <?= $os['quizzes_total'] ?></span>
                                            <div class="progress" style="width: 60px; height: 6px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $os['quizzes_total'] > 0 ? ($os['quizzes_submitted'] / $os['quizzes_total'] * 100) : 0 ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $disciplineClass ?> px-3 py-1.5"><?= $disciplineText ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
