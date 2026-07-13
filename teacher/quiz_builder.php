<?php
/**
 * آزمون‌ساز پویا (طراحی آزمون تستی و تشریحی)
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error = '';
$success = '';
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$activeQuiz = null;

try {
    // پیدا کردن شناسه معلم
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم ثبت نشده‌اید.");
    }

    // واکشی آزمون فعال در صورت انتخاب
    if ($quizId > 0) {
        $stmtCheck = $pdo->prepare("SELECT q.*, t.topic_title, c.class_name, co.course_name 
            FROM quizzes q
            JOIN topics t ON q.topic_id = t.id
            JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
            JOIN classes c ON ctc.class_id = c.id
            JOIN courses co ON ctc.course_id = co.id
            WHERE q.id = ? AND ctc.teacher_id = ?");
        $stmtCheck->execute([$quizId, $teacherId]);
        $activeQuiz = $stmtCheck->fetch();

        if (!$activeQuiz) {
            die("آزمون مورد نظر یافت نشد یا دسترسی مجاز ندارید.");
        }
    }

    // واکشی لیست تمام مباحث درسی این دبیر جهت انتخاب در فرم ساخت آزمون
    $stmtTopics = $pdo->prepare("SELECT t.id, t.topic_title, c.class_name, co.course_name 
        FROM topics t
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ?");
    $stmtTopics->execute([$teacherId]);
    $topics = $stmtTopics->fetchAll();

} catch (PDOException $e) {
    die("خطا در لود مباحث: " . $e->getMessage());
}

// هندل کردن ثبت فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ۱. ایجاد آزمون جدید
    if ($action === 'create_quiz') {
        $topic_id = (int)($_POST['topic_id'] ?? 0);
        $quiz_title = trim($_POST['quiz_title'] ?? '');
        $quiz_type = $_POST['quiz_type'] ?? 'multiple_choice';
        $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);

        if ($topic_id <= 0 || empty($quiz_title)) {
            $error = 'انتخاب مبحث آموزشی و عنوان آزمون الزامی است.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO `quizzes` (`topic_id`, `quiz_title`, `quiz_type`, `duration_minutes`) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$topic_id, $quiz_title, $quiz_type, $quiz_type === 'multiple_choice' ? $duration_minutes : null]);
                $newQuizId = $pdo->lastInsertId();
                
                $success = "آزمون با موفقیت ایجاد شد. اکنون می‌توانید سوالات را اضافه کنید.";
                redirect("quiz_builder.php?quiz_id=" . $newQuizId);
            } catch (PDOException $e) {
                $error = "خطا در ایجاد آزمون: " . $e->getMessage();
            }
        }
    }
    
    // ۲. افزودن سوال به آزمون فعال
    elseif ($action === 'add_question' && $activeQuiz) {
        $question_text = trim($_POST['question_text'] ?? '');
        
        if (empty($question_text)) {
            $error = 'متن صورت سوال نمی‌تواند خالی باشد.';
        } else {
            try {
                if ($activeQuiz['quiz_type'] === 'multiple_choice') {
                    $opt1 = trim($_POST['option_1'] ?? '');
                    $opt2 = trim($_POST['option_2'] ?? '');
                    $opt3 = trim($_POST['option_3'] ?? '');
                    $opt4 = trim($_POST['option_4'] ?? '');
                    $correct = (int)($_POST['correct_option'] ?? 0);

                    if (empty($opt1) || empty($opt2) || empty($opt3) || empty($opt4) || $correct <= 0) {
                        $error = 'وارد کردن ۴ گزینه و تعیین گزینه صحیح برای آزمون‌های تستی الزامی است.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO `quiz_questions` 
                            (`quiz_id`, `question_text`, `question_type`, `option_1`, `option_2`, `option_3`, `option_4`, `correct_option`) 
                            VALUES (?, ?, 'multiple_choice', ?, ?, ?, ?, ?)");
                        $stmt->execute([$quizId, $question_text, $opt1, $opt2, $opt3, $opt4, $correct]);
                        $success = "سوال تستی جدید با موفقیت اضافه شد.";
                    }
                } else {
                    // سوال تشریحی
                    $stmt = $pdo->prepare("INSERT INTO `quiz_questions` 
                        (`quiz_id`, `question_text`, `question_type`) 
                        VALUES (?, ?, 'essay')");
                    $stmt->execute([$quizId, $question_text]);
                    $success = "سوال تشریحی با موفقیت اضافه شد.";
                }
            } catch (PDOException $e) {
                $error = "خطا در ثبت سوال: " . $e->getMessage();
            }
        }
    }
}

// هندل کردن حذف سوال
if (isset($_GET['delete_question_id']) && $activeQuiz) {
    $delQId = (int)$_GET['delete_question_id'];
    try {
        $stmtDel = $pdo->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?");
        $stmtDel->execute([$delQId, $quizId]);
        $success = "سوال با موفقیت حذف شد.";
    } catch (PDOException $e) {
        $error = "خطا در حذف سوال: " . $e->getMessage();
    }
}

// واکشی سوالات آزمون فعال
$questions = [];
if ($activeQuiz) {
    try {
        $stmtQ = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
        $stmtQ->execute([$quizId]);
        $questions = $stmtQ->fetchAll();
    } catch (PDOException $e) {
        die("خطا در واکشی سوالات: " . $e->getMessage());
    }
}

// واکشی لیست تمام آزمون‌های ثبت شده معلم
try {
    $stmtAllQuizzes = $pdo->prepare("SELECT q.*, t.topic_title, c.class_name, co.course_name,
        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as questions_count
        FROM quizzes q
        JOIN topics t ON q.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ?
        ORDER BY q.created_at DESC");
    $stmtAllQuizzes->execute([$teacherId]);
    $quizzesList = $stmtAllQuizzes->fetchAll();
} catch (PDOException $e) {
    die("خطا در بارگذاری آزمون‌ها: " . $e->getMessage());
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

    <div class="row g-4">
        <!-- ستون سمت راست: لیست کل آزمون‌ها و فرم ساخت آزمون -->
        <div class="col-lg-5">
            <!-- ۱. فرم ساخت آزمون جدید -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-question-circle-fill text-warning me-1"></i> طراحی آزمون جدید</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_quiz">
                        <div class="mb-3">
                            <label class="form-label">انتخاب مبحث درسی هدف *</label>
                            <select name="topic_id" class="form-select" required>
                                <option value="">انتخاب مبحث...</option>
                                <?php foreach ($topics as $tp): ?>
                                    <option value="<?= $tp['id'] ?>"><?= htmlspecialchars($tp['class_name']) ?> - <?= htmlspecialchars($tp['course_name']) ?> : <?= htmlspecialchars($tp['topic_title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">عنوان آزمون *</label>
                            <input type="text" name="quiz_title" class="form-control" placeholder="مثال: آزمون تستی فصل اول ریاضی" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع آزمون *</label>
                            <select name="quiz_type" class="form-select" id="quizTypeSelect" onchange="toggleDurationField()" required>
                                <option value="multiple_choice">آزمون تستی (چهار گزینه‌ای)</option>
                                <option value="essay">آزمون تشریحی (بارگذاری فایل پاسخبرگ)</option>
                            </select>
                        </div>
                        <div class="mb-3" id="durationField">
                            <label class="form-label">مدت زمان آزمون (به دقیقه)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="20" min="1">
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark">ثبت و شروع طراحی سوالات</button>
                    </form>
                </div>
            </div>

            <!-- ۲. لیست تمام آزمون‌ها -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">آزمون‌های طراحی شده</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($quizzesList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-question-diamond fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">هنوز آزمونی طراحی نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>عنوان آزمون</th>
                                        <th>نوع آزمون</th>
                                        <th>سوالات</th>
                                        <th>اقدام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quizzesList as $qz): ?>
                                        <tr class="<?= $quizId == $qz['id'] ? 'bg-light-subtle' : '' ?>">
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($qz['quiz_title']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $qz['quiz_type'] === 'multiple_choice' ? 'primary' : 'info' ?>-subtle text-<?= $qz['quiz_type'] === 'multiple_choice' ? 'primary' : 'info' ?> border">
                                                    <?= $qz['quiz_type'] === 'multiple_choice' ? 'تستی' : 'تشریحی' ?>
                                                </span>
                                            </td>
                                            <td><?= $qz['questions_count'] ?> سوال</td>
                                            <td>
                                                <a href="quiz_builder.php?quiz_id=<?= $qz['id'] ?>" class="btn btn-primary btn-sm py-1 px-2" title="مدیریت سوالات">
                                                    <i class="bi bi-pencil-square"></i> سوالات
                                                </a>
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

        <!-- ستون سمت چپ: طراحی و مدیریت سوالات آزمون فعال -->
        <div class="col-lg-7">
            <?php if (!$activeQuiz): ?>
                <div class="card border-0 shadow-sm rounded-3 py-5 text-center h-100 d-flex align-items-center justify-content-center">
                    <i class="bi bi-patch-question fs-1 text-secondary mb-3"></i>
                    <h5 class="fw-bold">بخش طراحی سوالات آزمون</h5>
                    <p class="text-secondary small">جهت اضافه کردن سوال، از لیست سمت راست یک آزمون انتخاب کنید یا یک آزمون جدید بسازید.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-1 text-primary">طراحی سوالات: <?= htmlspecialchars($activeQuiz['quiz_title']) ?></h5>
                        <p class="mb-0 text-secondary small">
                            نوع آزمون: <strong><?= $activeQuiz['quiz_type'] === 'multiple_choice' ? 'تستی (۴گزینه‌ای)' : 'تشریحی' ?></strong> 
                            <?= $activeQuiz['duration_minutes'] ? ' | زمان: <strong>' . $activeQuiz['duration_minutes'] . ' دقیقه</strong>' : '' ?>
                        </p>
                    </div>
                    
                    <div class="card-body">
                        <!-- فرم اضافه کردن سوال جدید بر اساس نوع آزمون -->
                        <form method="POST" action="" class="bg-light p-3 rounded border mb-4">
                            <input type="hidden" name="action" value="add_question">
                            <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle-fill text-primary"></i> افزودن سوال جدید</h6>
                            
                            <div class="mb-3">
                                <label class="form-label small">صورت سوال *</label>
                                <textarea name="question_text" class="form-control" rows="3" placeholder="متن کامل صورت سوال را بنویسید..." required></textarea>
                            </div>

                            <?php if ($activeQuiz['quiz_type'] === 'multiple_choice'): ?>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small">گزینه ۱ *</label>
                                        <input type="text" name="option_1" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">گزینه ۲ *</label>
                                        <input type="text" name="option_2" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">گزینه ۳ *</label>
                                        <input type="text" name="option_3" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small">گزینه ۴ *</label>
                                        <input type="text" name="option_4" class="form-control form-control-sm" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small text-success fw-bold">شماره گزینه صحیح *</label>
                                    <select name="correct_option" class="form-select form-select-sm" required>
                                        <option value="1">گزینه ۱</option>
                                        <option value="2">گزینه ۲</option>
                                        <option value="3">گزینه ۳</option>
                                        <option value="4">گزینه ۴</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4">ثبت و افزودن سوال</button>
                        </form>

                        <!-- نمایش لیست سوالات ثبت شده فعلی -->
                        <h6 class="fw-bold mb-3">سوالات ثبت شده در این آزمون:</h6>
                        <?php if (empty($questions)): ?>
                            <div class="alert alert-secondary py-3 text-center small border-0">
                                هنوز هیچ سوالی برای این آزمون ثبت نشده است. از فرم بالای صفحه جهت ثبت سوال استفاده کنید.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php $idx = 0; foreach ($questions as $q): $idx++; ?>
                                    <div class="list-group-item p-3 border mb-2 rounded bg-white position-relative">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>سوال <?= $idx ?>: </strong>
                                                <p class="mb-2 mt-1 small text-dark" style="white-space: pre-line;"><?= htmlspecialchars($q['question_text']) ?></p>
                                                
                                                <?php if ($q['question_type'] === 'multiple_choice'): ?>
                                                    <!-- نمایش گزینه‌ها -->
                                                    <div class="row g-1 small text-secondary ps-3">
                                                        <div class="col-md-6 <?= $q['correct_option'] == 1 ? 'text-success fw-bold' : '' ?>">۱) <?= htmlspecialchars($q['option_1']) ?> <?= $q['correct_option'] == 1 ? '✓' : '' ?></div>
                                                        <div class="col-md-6 <?= $q['correct_option'] == 2 ? 'text-success fw-bold' : '' ?>">۲) <?= htmlspecialchars($q['option_2']) ?> <?= $q['correct_option'] == 2 ? '✓' : '' ?></div>
                                                        <div class="col-md-6 <?= $q['correct_option'] == 3 ? 'text-success fw-bold' : '' ?>">۳) <?= htmlspecialchars($q['option_3']) ?> <?= $q['correct_option'] == 3 ? '✓' : '' ?></div>
                                                        <div class="col-md-6 <?= $q['correct_option'] == 4 ? 'text-success fw-bold' : '' ?>">۴) <?= htmlspecialchars($q['option_4']) ?> <?= $q['correct_option'] == 4 ? '✓' : '' ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <a href="quiz_builder.php?quiz_id=<?= $quizId ?>&delete_question_id=<?= $q['id'] ?>" class="btn btn-link text-danger btn-sm p-0 ms-3" onclick="return confirm('آیا از حذف این سوال مطمئن هستید؟')">
                                                <i class="bi text-danger bi-trash"></i> حذف
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // فعال/غیرفعال کردن فیلد زمان بر اساس نوع آزمون
    function toggleDurationField() {
        const type = document.getElementById('quizTypeSelect').value;
        const durationField = document.getElementById('durationField');
        
        if (type === 'essay') {
            durationField.classList.add('d-none');
        } else {
            durationField.classList.remove('d-none');
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>
