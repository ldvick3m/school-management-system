<?php
/**
 * دشبورد پنل معلم
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

try {
    // ۱. پیدا کردن شناسه معلم در جدول teachers
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان معلم در سیستم تعریف نشده‌اید.");
    }

    // ۲. تعداد کل کلاس‌ها/دروس تحت پوشش دبیر
    $stmtTCount = $pdo->prepare("SELECT COUNT(*) FROM class_teacher_course WHERE teacher_id = ?");
    $stmtTCount->execute([$teacherId]);
    $classesCount = $stmtTCount->fetchColumn();

    // ۳. تعداد مباحث ایجاد شده توسط این دبیر
    $stmtTopicsCount = $pdo->prepare("SELECT COUNT(*) FROM topics t 
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        WHERE ctc.teacher_id = ?");
    $stmtTopicsCount->execute([$teacherId]);
    $topicsCount = $stmtTopicsCount->fetchColumn();

    // ۴. تعداد کل تکالیف و آزمون‌های تصحیح‌نشده در انتظار بررسی (Grading Inbox)
    // تکالیف در انتظار تصحیح
    $stmtHwPending = $pdo->prepare("SELECT COUNT(*) FROM homework_submissions hs
        JOIN homeworks h ON hs.homework_id = h.id
        JOIN topics t ON h.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        WHERE ctc.teacher_id = ? AND hs.status = 'pending'");
    $stmtHwPending->execute([$teacherId]);
    $hwPendingCount = $stmtHwPending->fetchColumn();

    // پاسخبرگ‌های تشریحی آزمون‌ها در انتظار تصحیح
    $stmtQuizPending = $pdo->prepare("SELECT COUNT(*) FROM student_quiz_answers sqa
        JOIN student_quizzes sq ON sqa.student_quiz_id = sq.id
        JOIN quizzes q ON sq.quiz_id = q.id
        JOIN topics t ON q.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        WHERE ctc.teacher_id = ? AND q.quiz_type = 'essay' AND sqa.score IS NULL AND sqa.essay_answer_file_path IS NOT NULL AND sqa.essay_answer_file_path != ''");
    $stmtQuizPending->execute([$teacherId]);
    $quizPendingCount = $stmtQuizPending->fetchColumn();

    $totalPendingGrading = $hwPendingCount + $quizPendingCount;

    // ۵. لیست تخصیص‌های کلاس و درس این دبیر
    $stmtAlloc = $pdo->prepare("SELECT ctc.id, c.class_name, co.course_name, c.grade_level,
        (SELECT COUNT(*) FROM class_student WHERE class_id = c.id) as students_count
        FROM class_teacher_course ctc
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ?");
    $stmtAlloc->execute([$teacherId]);
    $allocations = $stmtAlloc->fetchAll();

    // ۶. آخرین پیام‌های تالار گفتگوی مباحث این دبیر
    $stmtForum = $pdo->prepare("SELECT fm.*, u.full_name, t.topic_title, co.course_name
        FROM forum_messages fm
        JOIN users u ON fm.sender_id = u.id
        JOIN topics t ON fm.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ? AND fm.sender_id != ?
        ORDER BY fm.created_at DESC LIMIT 5");
    $stmtForum->execute([$teacherId, $_SESSION['user_id']]);
    $recentForumMessages = $stmtForum->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات دشبورد معلم: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- بخش کارت‌های آمار -->
    <div class="row g-4 mb-4">
        <!-- کارت ۱: کلاس‌ها و دروس -->
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کلاس‌ها و دروس من</h6>
                    <h3 class="fw-bold mb-0"><?= $classesCount ?> کلاس</h3>
                </div>
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                    <i class="bi bi-journal-bookmark-fill fs-3"></i>
                </div>
            </div>
        </div>

        <!-- کارت ۲: مباحث آموزشی ایجادشده -->
        <div class="col-md-4">
            <div class="stat-card d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary mb-1">کل موضوعات و مباحث تدریس</h6>
                    <h3 class="fw-bold mb-0"><?= $topicsCount ?> مبحث</h3>
                </div>
                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                    <i class="bi bi-file-earmark-slides-fill fs-3"></i>
                </div>
            </div>
        </div>

        <!-- کارت ۳: کارتابل ارزیابی در انتظار تصحیح (با بج عددی) -->
        <div class="col-md-4">
            <a href="grading.php" class="text-decoration-none text-dark">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-secondary mb-1">کارهای در انتظار تصحیح</h6>
                        <h3 class="fw-bold mb-0">
                            <?= $totalPendingGrading ?> مورد
                            <?php if ($totalPendingGrading > 0): ?>
                                <span class="badge bg-danger rounded-pill fs-7 ms-2 live-pulse">جدید</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="bi bi-patch-check-fill fs-3"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- لیست کلاس‌ها و دروس فعال دبیر -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">کلاس‌ها و دروس تحت پوشش</h5>
                    <a href="course_creator.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>ثبت مبحث جدید</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($allocations)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-x fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">هنوز هیچ کلاس یا درسی به شما اختصاص داده نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>نام کلاس</th>
                                        <th>درس</th>
                                        <th>پایه تحصیلی</th>
                                        <th>تعداد دانش‌آموزان</th>
                                        <th>عملیات درسی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allocations as $alloc): ?>
                                        <tr>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($alloc['class_name']) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($alloc['course_name']) ?></td>
                                            <td>پایه <?= $alloc['grade_level'] ?></td>
                                            <td><?= $alloc['students_count'] ?> نفر</td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-light btn-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        عملیات کلاس
                                                    </button>
                                                    <ul class="dropdown-menu shadow border-0">
                                                        <li><a class="dropdown-item" href="course_creator.php?allocation_id=<?= $alloc['id'] ?>"><i class="bi bi-file-plus-fill me-2 text-primary"></i>ایجاد مبحث جدید</a></li>
                                                        <li><a class="dropdown-item" href="resources.php?allocation_id=<?= $alloc['id'] ?>"><i class="bi bi-folder-fill me-2 text-success"></i>مدیریت منابع درسی</a></li>
                                                        <li><a class="dropdown-item" href="quiz_builder.php?allocation_id=<?= $alloc['id'] ?>"><i class="bi bi-question-square-fill me-2 text-warning"></i>آزمون‌ساز پویا</a></li>
                                                        <li><a class="dropdown-item" href="attendance.php?class_id=<?= $alloc['class_id'] ?>"><i class="bi bi-calendar-check-fill me-2 text-info"></i>حضور و غیاب</a></li>
                                                        <li><a class="dropdown-item" href="discipline.php?class_id=<?= $alloc['class_id'] ?>"><i class="bi bi-shield-exclamation-fill me-2 text-danger"></i>رادار انضباطی</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item fw-bold text-dark" href="#" onclick="showTopStudents(<?= $alloc['class_id'] ?>, '<?= htmlspecialchars($alloc['class_name']) ?>')"><i class="bi bi-trophy-fill me-2 text-warning"></i>برترین‌های کلاس</a></li>
                                                    </ul>
                                                </div>
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

        <!-- پیام‌های تالار گفتگوی اخیر -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-chat-text-fill text-success me-2"></i>آخرین گفتگوهای دانش‌آموزان</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentForumMessages)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-chat-left-heart fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">هنوز گفتگویی در تالارهای دروس شما ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentForumMessages as $msg): ?>
                                <div class="list-group-item px-0 py-3">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($msg['full_name']) ?></h6>
                                        <small class="text-muted"><?= to_shamsi($msg['created_at']) ?></small>
                                    </div>
                                    <span class="badge bg-light text-secondary border mb-2" style="font-size: 0.75rem;">
                                        درس: <?= htmlspecialchars($msg['course_name']) ?> | مبحث: <?= htmlspecialchars($msg['topic_title']) ?>
                                    </span>
                                    <p class="mb-0 text-secondary small bg-light p-2 rounded">
                                        <?= htmlspecialchars(mb_substr($msg['message_text'], 0, 100, 'utf-8')) ?>...
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
