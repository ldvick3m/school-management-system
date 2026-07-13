<?php
/**
 * کارتابل ارزیابی و تصحیح تکالیف و آزمون‌های تشریحی
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error = '';
$success = '';

try {
    // پیدا کردن شناسه معلم
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم تعریف نشده‌اید.");
    }
} catch (PDOException $e) {
    die("خطا در لود اطلاعات معلم: " . $e->getMessage());
}

// هندل کردن ثبت نمره‌دهی تکالیف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'grade_homework') {
        $submission_id = (int)($_POST['submission_id'] ?? 0);
        $grade = (float)($_POST['grade'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');

        if ($submission_id <= 0 || $grade < 0 || $grade > 20) {
            $error = 'ثبت نمره معتبر بین ۰ تا ۲۰ الزامی است.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE `homework_submissions` 
                    SET `grade` = ?, `feedback` = ?, `status` = 'graded', `graded_at` = NOW() 
                    WHERE `id` = ?");
                $stmt->execute([$grade, $feedback, $submission_id]);
                $success = "نمره و بازخورد تکلیف با موفقیت ثبت شد.";
            } catch (PDOException $e) {
                $error = "خطا در ثبت نمره تکلیف: " . $e->getMessage();
            }
        }
    }
    
    // هندل کردن ثبت نمره‌دهی پاسخبرگ تشریحی آزمون
    elseif ($action === 'grade_essay') {
        $answer_id = (int)($_POST['answer_id'] ?? 0);
        $grade = (float)($_POST['grade'] ?? 0);

        if ($answer_id <= 0 || $grade < 0 || $grade > 20) {
            $error = 'ثبت نمره معتبر بین ۰ تا ۲۰ الزامی است.';
        } else {
            try {
                $pdo->beginTransaction();

                // ۱. ثبت نمره برای سوال تشریحی در جدول جواب‌ها
                $stmt = $pdo->prepare("UPDATE `student_quiz_answers` SET `score` = ? WHERE `id` = ?");
                $stmt->execute([$grade, $answer_id]);

                // پیدا کردن شناسه آزمون کل
                $stmtQuizInfo = $pdo->prepare("SELECT student_quiz_id FROM student_quiz_answers WHERE id = ?");
                $stmtQuizInfo->execute([$answer_id]);
                $studentQuizId = $stmtQuizInfo->fetchColumn();

                if ($studentQuizId) {
                    // ۲. محاسبه مجموع نمرات کل آزمون تشریحی برای این دانش‌آموز
                    $stmtSum = $pdo->prepare("SELECT SUM(score) FROM student_quiz_answers WHERE student_quiz_id = ?");
                    $stmtSum->execute([$studentQuizId]);
                    $totalScore = $stmtSum->fetchColumn();

                    // ۳. ثبت نمره نهایی در جدول آزمون‌های دانش‌آموزان
                    $stmtUpdateQuiz = $pdo->prepare("UPDATE `student_quizzes` 
                        SET `score` = ?, `status` = 'completed', `submit_time` = NOW() 
                        WHERE `id` = ?");
                    $stmtUpdateQuiz->execute([$totalScore, $studentQuizId]);
                }

                $pdo->commit();
                $success = "نمره پاسخ‌برگ آزمون تشریحی با موفقیت ثبت گردید.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "خطا در ثبت نمره آزمون: " . $e->getMessage();
            }
        }
    }
}

// واکشی لیست‌های ارزیابی دبیر
try {
    // ۱. لیست تکالیف ارسالی دانش‌آموزان در انتظار تصحیح
    $stmtHws = $pdo->prepare("SELECT hs.*, h.homework_title, u.full_name as student_name, co.course_name, c.class_name
        FROM homework_submissions hs
        JOIN homeworks h ON hs.homework_id = h.id
        JOIN topics t ON h.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        JOIN students s ON hs.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE ctc.teacher_id = ? AND hs.status = 'pending'
        ORDER BY hs.submitted_at ASC");
    $stmtHws->execute([$teacherId]);
    $pendingHomeworks = $stmtHws->fetchAll();

    // ۲. لیست پاسخ‌برگ‌های تشریحی ارسالی دانش‌آموزان در انتظار تصحیح
    $stmtEssays = $pdo->prepare("SELECT sqa.*, q.quiz_title, u.full_name as student_name, co.course_name, c.class_name
        FROM student_quiz_answers sqa
        JOIN student_quizzes sq ON sqa.student_quiz_id = sq.id
        JOIN quizzes q ON sq.quiz_id = q.id
        JOIN topics t ON q.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        JOIN students s ON sq.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE ctc.teacher_id = ? AND q.quiz_type = 'essay' AND sqa.score IS NULL AND sqa.essay_answer_file_path IS NOT NULL AND sqa.essay_answer_file_path != ''
        ORDER BY sq.start_time ASC");
    $stmtEssays->execute([$teacherId]);
    $pendingEssays = $stmtEssays->fetchAll();

    // ۳. لیست تکالیف تصحیح شده
    $stmtGradedHws = $pdo->prepare("SELECT hs.*, h.homework_title, u.full_name as student_name, co.course_name, c.class_name
        FROM homework_submissions hs
        JOIN homeworks h ON hs.homework_id = h.id
        JOIN topics t ON h.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        JOIN students s ON hs.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE ctc.teacher_id = ? AND hs.status = 'graded'
        ORDER BY hs.graded_at DESC");
    $stmtGradedHws->execute([$teacherId]);
    $gradedHomeworks = $stmtGradedHws->fetchAll();

    $totalPending = count($pendingHomeworks) + count($pendingEssays);

} catch (PDOException $e) {
    die("خطا در لود کارتابل ارزیابی: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-25 text-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- سربرگ کارتابل ارزیابی -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">کارتابل ارزیابی تکالیف و آزمون‌ها</h5>
            <span class="badge bg-danger p-2 fs-6">
                <?= $totalPending ?> کار در انتظار تصحیح
            </span>
        </div>
    </div>

    <!-- بخش تب‌های ارزیابی -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <!-- منوی تب‌ها -->
            <ul class="nav nav-tabs custom-tabs px-3 bg-light border-bottom-0" id="gradingTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="hw-tab" data-bs-toggle="tab" data-bs-target="#hw-pane" type="button" role="tab" aria-controls="hw-pane" aria-selected="true">
                        <i class="bi bi-file-earmark-text-fill me-1"></i> تکالیف ارسالی دانش‌آموزان (<?= count($pendingHomeworks) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="essay-tab" data-bs-toggle="tab" data-bs-target="#essay-pane" type="button" role="tab" aria-controls="essay-pane" aria-selected="false">
                        <i class="bi bi-file-earmark-person-fill me-1"></i> پاسخ‌برگ آزمون‌های تشریحی (<?= count($pendingEssays) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="graded-hw-tab" data-bs-toggle="tab" data-bs-target="#graded-hw-pane" type="button" role="tab" aria-controls="graded-hw-pane" aria-selected="false">
                        <i class="bi bi-patch-check-fill me-1"></i> تکالیف تصحیح‌شده (<?= count($gradedHomeworks) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content p-4" id="gradingTabContent">
                <!-- تب تکالیف ارسالی -->
                <div class="tab-pane fade show active" id="hw-pane" role="tabpanel" aria-labelledby="hw-tab">
                    <?php if (empty($pendingHomeworks)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-check fs-1 text-success mb-2"></i>
                            <p class="text-secondary mb-0">تمامی تکالیف ارسالی تصحیح و نمره‌دهی شده‌اند.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>دانش‌آموز</th>
                                        <th>عنوان تکلیف</th>
                                        <th>درس / کلاس</th>
                                        <th>تاریخ ارسال</th>
                                        <th>فایل پاسخ</th>
                                        <th>عملیات نمره‌دهی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingHomeworks as $hw): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($hw['student_name']) ?></td>
                                            <td><?= htmlspecialchars($hw['homework_title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($hw['course_name']) ?></span><br>
                                                <small class="text-secondary"><?= htmlspecialchars($hw['class_name']) ?></small>
                                            </td>
                                            <td><?= to_shamsi($hw['submitted_at']) ?></td>
                                            <td>
                                                <a href="<?= '../' . htmlspecialchars($hw['file_path']) ?>" download class="btn btn-outline-success btn-sm">
                                                    <i class="bi bi-cloud-arrow-down-fill"></i> دانلود فایل پاسخ
                                                </a>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary btn-sm fw-bold" onclick="openHomeworkGradingModal(<?= $hw['id'] ?>, '<?= htmlspecialchars($hw['student_name']) ?>', '<?= htmlspecialchars($hw['homework_title']) ?>')">
                                                    <i class="bi bi-bookmark-plus-fill"></i> ثبت نمره
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- تب پاسخ‌برگ‌های تشریحی آزمون‌ها -->
                <div class="tab-pane fade" id="essay-pane" role="tabpanel" aria-labelledby="essay-tab">
                    <?php if (empty($pendingEssays)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check2-all fs-1 text-success mb-2"></i>
                            <p class="text-secondary mb-0">هیچ پاسخ‌برگ تشریحی آزمونی در انتظار تصحیح وجود ندارد.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>دانش‌آموز</th>
                                        <th>عنوان آزمون</th>
                                        <th>درس / کلاس</th>
                                        <th>فایل پاسخبرگ</th>
                                        <th>عملیات نمره‌دهی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingEssays as $es): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($es['student_name']) ?></td>
                                            <td><?= htmlspecialchars($es['quiz_title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($es['course_name']) ?></span><br>
                                                <small class="text-secondary"><?= htmlspecialchars($es['class_name']) ?></small>
                                            </td>
                                            <td>
                                                <a href="<?= '../' . htmlspecialchars($es['essay_answer_file_path']) ?>" download class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-cloud-arrow-down-fill"></i> دانلود پاسخبرگ
                                                </a>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm text-white fw-bold" onclick="openEssayGradingModal(<?= $es['id'] ?>, '<?= htmlspecialchars($es['student_name']) ?>', '<?= htmlspecialchars($es['quiz_title']) ?>')">
                                                    <i class="bi bi-clipboard-check-fill"></i> ثبت نمره
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- تب تکالیف تصحیح‌شده -->
                <div class="tab-pane fade" id="graded-hw-pane" role="tabpanel" aria-labelledby="graded-hw-tab">
                    <?php if (empty($gradedHomeworks)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-info-circle fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">هنوز تکلیفی توسط شما تصحیح نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>دانش‌آموز</th>
                                        <th>عنوان تکلیف</th>
                                        <th>درس / کلاس</th>
                                        <th>نمره ثبت‌شده</th>
                                        <th>بازخورد دبیر</th>
                                        <th>فایل پاسخ</th>
                                        <th>عملیات ویرایش</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gradedHomeworks as $ghw): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($ghw['student_name']) ?></td>
                                            <td><?= htmlspecialchars($ghw['homework_title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($ghw['course_name']) ?></span><br>
                                                <small class="text-secondary"><?= htmlspecialchars($ghw['class_name']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-success p-2 border fs-6"><?= $ghw['grade'] ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted text-wrap d-block" style="max-width: 200px;">
                                                    <?= $ghw['feedback'] ? htmlspecialchars($ghw['feedback']) : '---' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="<?= '../' . htmlspecialchars($ghw['file_path']) ?>" download class="btn btn-outline-success btn-sm">
                                                    <i class="bi bi-cloud-arrow-down-fill"></i> دانلود
                                                </a>
                                            </td>
                                            <td>
                                                <button class="btn btn-warning btn-sm text-dark fw-bold" onclick="openHomeworkGradingModal(<?= $ghw['id'] ?>, '<?= htmlspecialchars(addslashes($ghw['student_name'])) ?>', '<?= htmlspecialchars(addslashes($ghw['homework_title'])) ?>', '<?= $ghw['grade'] ?>', '<?= htmlspecialchars(addslashes($ghw['feedback'])) ?>')">
                                                    <i class="bi bi-pencil-fill"></i> ویرایش نمره
                                                </button>
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
    </div>
</div>

<!-- مودال ثبت نمره تکلیف -->
<div class="modal fade" id="gradeHomeworkModal" tabindex="-1" aria-labelledby="gradeHomeworkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="grade_homework">
                <input type="hidden" name="submission_id" id="hw_submission_id">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="gradeHomeworkModalLabel">تصحیح و نمره‌دهی تکلیف</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">دانش‌آموز: <strong id="hw_student_name"></strong> | تکلیف: <strong id="hw_title"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ثبت نمره تکلیف (بین ۰ تا ۲۰) *</label>
                        <input type="number" name="grade" class="form-control" step="0.25" min="0" max="20" placeholder="مثال: 19.5" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">بازخورد و تذکر آموزشی دبیر</label>
                        <textarea name="feedback" class="form-control" rows="4" placeholder="متن بازخورد خود را بنویسید (مانند: تلاش بسیار عالی بود، خطاهای فصل یک برطرف شود)"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary fw-bold">ثبت نمره نهایی</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- مودال ثبت نمره آزمون تشریحی -->
<div class="modal fade" id="gradeEssayModal" tabindex="-1" aria-labelledby="gradeEssayModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="grade_essay">
                <input type="hidden" name="answer_id" id="essay_answer_id">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="gradeEssayModalLabel">تصحیح پاسخبرگ تشریحی</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">دانش‌آموز: <strong id="essay_student_name"></strong> | آزمون: <strong id="essay_title"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ثبت نمره پاسخبرگ (بین ۰ تا ۲۰) *</label>
                        <input type="number" name="grade" class="form-control" step="0.25" min="0" max="20" placeholder="مثال: 18.25" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-info text-white fw-bold">ثبت و درج در کارنامه</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openHomeworkGradingModal(id, studentName, title, grade = '', feedback = '') {
        document.getElementById('hw_submission_id').value = id;
        document.getElementById('hw_student_name').innerText = studentName;
        document.getElementById('hw_title').innerText = title;
        
        // درج مقادیر پیش‌فرض نمره و بازخورد جهت امکان ثبت یا ویرایش مجدد
        const modalForm = document.querySelector('#gradeHomeworkModal form');
        modalForm.querySelector('input[name="grade"]').value = grade;
        modalForm.querySelector('textarea[name="feedback"]').value = feedback;
        
        const myModal = new bootstrap.Modal(document.getElementById('gradeHomeworkModal'));
        myModal.show();
    }

    function openEssayGradingModal(id, studentName, title) {
        document.getElementById('essay_answer_id').value = id;
        document.getElementById('essay_student_name').innerText = studentName;
        document.getElementById('essay_title').innerText = title;
        
        const myModal = new bootstrap.Modal(document.getElementById('gradeEssayModal'));
        myModal.show();
    }
</script>

<?php require_once '../includes/footer.php'; ?>
