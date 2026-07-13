<?php
/**
 * پنل امن آزمون دانش‌آموز (آزمون تستی با تایمر زنده و آزمون تشریحی ۲۴ ساعته)
 */

require_once '../includes/header.php';
check_auth(['student', 'admin']);

$error = '';
$success = '';
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$studentId = 0;
$quiz = null;
$attempt = null;

try {
    // ۱. پیدا کردن شناسه دانش‌آموز
    $stmtS = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $studentId = $stmtS->fetchColumn();

    if (!$studentId) {
        die("شما دانش‌آموز ثبت‌نامی نیستید.");
    }

    // ۲. واکشی اطلاعات آزمون
    $stmtQuiz = $pdo->prepare("SELECT q.*, co.course_name 
        FROM quizzes q
        JOIN topics t ON q.topic_id = t.id
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE q.id = ?");
    $stmtQuiz = $pdo->prepare("SELECT q.*, co.course_name FROM quizzes q JOIN topics t ON q.topic_id = t.id JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id JOIN courses co ON ctc.course_id = co.id WHERE q.id = ?");
    $stmtQuiz->execute([$quizId]);
    $quiz = $stmtQuiz->fetch();

    if (!$quiz) {
        die("آزمون مورد نظر یافت نشد.");
    }

    // ۳. واکشی وضعیت شرکت دانش‌آموز در این آزمون
    $stmtAttempt = $pdo->prepare("SELECT * FROM student_quizzes WHERE student_id = ? AND quiz_id = ?");
    $stmtAttempt->execute([$studentId, $quizId]);
    $attempt = $attemptInfo = $stmtAttempt->fetch();

} catch (PDOException $e) {
    die("خطا در لود اطلاعات آزمون: " . $e->getMessage());
}

// هندل کردن دکمه "شروع آزمون"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_quiz') {
    if (!$attempt) {
        try {
            // ثبت رکورد شروع آزمون در دیتابیس (ثبت زمان شروع امن)
            $stmtStart = $pdo->prepare("INSERT INTO `student_quizzes` (`student_id`, `quiz_id`, `start_time`, `status`) 
                VALUES (?, ?, NOW(), 'started')");
            $stmtStart->execute([$studentId, $quizId]);
            
            // هدایت به همین صفحه جهت لود امن سوالات
            redirect("quiz.php?quiz_id=" . $quizId);
        } catch (PDOException $e) {
            $error = "خطا در شروع آزمون: " . $e->getMessage();
        }
    }
}

// هندل کردن ثبت نهایی پاسخ‌های آزمون
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_quiz' && $attempt) {
    if ($attempt['status'] === 'started') {
        try {
            $pdo->beginTransaction();

            if ($quiz['quiz_type'] === 'multiple_choice') {
                // الف) تصحیح خودکار آزمون تستی
                $answers = $_POST['answers'] ?? []; // [question_id => selected_option]
                
                // واکشی کل سوالات آزمون جهت انطباق
                $stmtQs = $pdo->prepare("SELECT id, correct_option FROM quiz_questions WHERE quiz_id = ?");
                $stmtQs->execute([$quizId]);
                $questionsList = $stmtQs->fetchAll();
                
                $totalQuestions = count($questionsList);
                $correctCount = 0;

                $stmtAnswer = $pdo->prepare("INSERT INTO `student_quiz_answers` (`student_quiz_id`, `question_id`, `selected_option`, `score`) VALUES (?, ?, ?, ?)");
                
                foreach ($questionsList as $q) {
                    $qId = $q['id'];
                    $selected = isset($answers[$qId]) ? (int)$answers[$qId] : 0;
                    $score = 0;

                    if ($selected == $q['correct_option']) {
                        $correctCount++;
                        $score = 20 / $totalQuestions; // نمره دهی از سقف ۲۰
                    }

                    $stmtAnswer->execute([$attempt['id'], $qId, $selected ?: null, $score]);
                }

                $finalScore = ($correctCount / $totalQuestions) * 20;

                // ثبت پایان آزمون در جدول نتایج
                $stmtUpdate = $pdo->prepare("UPDATE `student_quizzes` 
                    SET `submit_time` = NOW(), `score` = ?, `status` = 'completed' 
                    WHERE `id` = ?");
                $stmtUpdate->execute([$finalScore, $attempt['id']]);

            } else {
                // ب) دریافت و ذخیره فایل پاسخبرگ آزمون تشریحی
                if (isset($_FILES['essay_file']) && $_FILES['essay_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['essay_file'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    $uploadDir = '../uploads/quiz_essays/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $filename = uniqid('essay_ans_') . '_' . $studentId . '.' . $ext;
                    $targetPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $relativeFilePath = 'uploads/quiz_essays/' . $filename;

                        // ابتدا پیدا کردن سوال تشریحی این آزمون (معمولاً یک فیلد کلی است)
                        $qId = $pdo->query("SELECT id FROM quiz_questions WHERE quiz_id = $quizId LIMIT 1")->fetchColumn();

                        if ($qId) {
                            $stmtAnswer = $pdo->prepare("INSERT INTO `student_quiz_answers` (`student_quiz_id`, `question_id`, `essay_answer_file_path`) VALUES (?, ?, ?)");
                            $stmtAnswer->execute([$attempt['id'], $qId, $relativeFilePath]);

                            // اتمام موفق آزمون (اما نمره همچنان NULL است تا معلم نمره دهد)
                            $stmtUpdate = $pdo->prepare("UPDATE `student_quizzes` 
                                SET `submit_time` = NOW(), `status` = 'completed' 
                                WHERE `id` = ?");
                            $stmtUpdate->execute([$attempt['id']]);
                        }
                    } else {
                        throw new Exception("خطا در جابجایی فایل پاسخبرگ در سرور.");
                    }
                } else {
                    throw new Exception("فایل پاسخبرگ بارگذاری نشده است.");
                }
            }

            $pdo->commit();
            $success = "آزمون شما با موفقیت ثبت نهایی شد.";
            // بارگذاری مجدد صفحه برای نمایش وضعیت جدید
            redirect("quiz.php?quiz_id=" . $quizId . "&success=1");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "خطا در ثبت نهایی آزمون: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $success = "آزمون شما با موفقیت ثبت نهایی شد.";
}

// محاسبات مربوط به تایمر و نمایش سوالات
$questions = [];
$remainingSeconds = 0;
$isOverdue = false;

if ($attempt && $attempt['status'] === 'started') {
    // ۱. واکشی سوالات (چون آزمون شروع شده است، سوالات فاش می‌شوند)
    try {
        $stmtQs = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
        $stmtQs->execute([$quizId]);
        $questions = $stmtQs->fetchAll();

        // ۲. محاسبه زمان آزمون
        $startTime = strtotime($attempt['start_time']);
        $currentTime = time();
        $elapsedSeconds = $currentTime - $startTime;

        if ($quiz['quiz_type'] === 'multiple_choice') {
            $totalAllowedSeconds = $quiz['duration_minutes'] * 60;
            $remainingSeconds = $totalAllowedSeconds - $elapsedSeconds;

            if ($remainingSeconds <= 0) {
                $isOverdue = true;
                // ثبت خودکار نمره صفر به دلیل اتمام زمان و عدم ثبت دستی
                $pdo->prepare("UPDATE `student_quizzes` SET `status` = 'failed_deadline', `score` = 0, `submit_time` = NOW() WHERE `id` = ?")
                    ->execute([$attempt['id']]);
                // رفرش صفحه جهت خروج از محیط آزمون
                redirect("quiz.php?quiz_id=" . $quizId);
            }
        } else {
            // آزمون تشریحی مهلت ۲۴ ساعته دارد
            $totalAllowedSeconds = 24 * 3600;
            $remainingSeconds = $totalAllowedSeconds - $elapsedSeconds;

            if ($remainingSeconds <= 0) {
                $isOverdue = true;
                // ثبت خودکار نمره صفر به دلیل رد شدن از ۲۴ ساعت ددلاین
                $pdo->prepare("UPDATE `student_quizzes` SET `status` = 'failed_deadline', `score` = 0, `submit_time` = NOW() WHERE `id` = ?")
                    ->execute([$attempt['id']]);
                redirect("quiz.php?quiz_id=" . $quizId);
            }
        }

    } catch (PDOException $e) {
        $error = "خطا در بارگذاری اطلاعات امنیتی آزمون: " . $e->getMessage();
    }
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
            <i class="bi bi-patch-check-fill me-1"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <!-- وضعیت ۱: آزمون شروع نشده است (عدم فاش شدن سوالات در کلاینت) -->
            <?php if (!$attempt): ?>
                <div class="card border-0 shadow-sm rounded-3 p-4">
                    <div class="card-header bg-white border-0 text-center pb-3">
                        <i class="bi bi-shield-lock-fill text-warning fs-1"></i>
                        <h4 class="fw-bold mt-2">قوانین و امنیت شروع آزمون</h4>
                        <span class="badge bg-light text-secondary border">درس: <?= htmlspecialchars($quiz['course_name']) ?></span>
                    </div>
                    <div class="card-body">
                        <h5 class="fw-bold text-dark mb-3 text-center"><?= htmlspecialchars($quiz['quiz_title']) ?></h5>
                        
                        <div class="alert alert-info border-0 bg-info bg-opacity-10 text-dark small py-3 mb-4">
                            <strong>قوانین آزمون:</strong>
                            <ul class="ps-3 mb-0 mt-2">
                                <?php if ($quiz['quiz_type'] === 'multiple_choice'): ?>
                                    <li>این آزمون به صورت چهارگزینه‌ای (تستی) است.</li>
                                    <li>مدت زمان پاسخ‌گویی دقیقاً <strong><?= $quiz['duration_minutes'] ?> دقیقه</strong> است.</li>
                                    <li>پس از کلیک روی شروع آزمون، تایمر فعال می‌شود و در صورت رفرش یا بستن صفحه، زمان معکوس متوقف نخواهد شد.</li>
                                    <li>در صورت اتمام وقت، آزمون به صورت خودکار ثبت نهایی شده و نمره صفر یا پاسخ‌های تا آن لحظه محاسبه می‌شود.</li>
                                <?php else: ?>
                                    <li>این آزمون به صورت تشریحی است.</li>
                                    <li>پس از شروع آزمون، سوالات تشریحی نمایش داده می‌شوند.</li>
                                    <li>شما حداکثر <strong>۲۴ ساعت</strong> فرصت دارید تا پاسخ‌برگ خود را به صورت عکس یا پی‌دی‌اف آپلود کنید.</li>
                                    <li>در صورت عدم آپلود در مهلت مقرر، سیستم نمره صفر را به صورت خودکار ثبت می‌کند.</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="start_quiz">
                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold text-dark">
                                <i class="bi bi-play-fill me-1"></i> تایید قوانین و شروع آزمون
                            </button>
                        </form>
                    </div>
                </div>

            <!-- وضعیت ۲: دانش‌آموز در حال آزمون دادن است (سوالات فاش و تایمر فعال است) -->
            <?php elseif ($attempt && $attempt['status'] === 'started'): ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center position-sticky top-0 z-3 border-bottom shadow-sm">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($quiz['quiz_title']) ?></h5>
                            <small class="text-secondary">آزمون در حال برگزاری...</small>
                        </div>
                        
                        <!-- تایمر زنده جاوااسکریپت -->
                        <div class="d-flex align-items-center gap-2 bg-dark text-white px-3 py-2 rounded shadow-sm">
                            <i class="bi bi-clock-fill text-danger animate-pulse"></i>
                            <span class="fw-bold" id="quizTimerDisplay" style="font-family: monospace; font-size: 1.15rem;">--:--</span>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <form method="POST" action="" enctype="multipart/form-data" id="quizForm">
                            <input type="hidden" name="action" value="submit_quiz">
                            
                            <?php if ($quiz['quiz_type'] === 'multiple_choice'): ?>
                                <!-- سوالات تستی -->
                                <?php $idx = 0; foreach ($questions as $q): $idx++; ?>
                                    <div class="p-3 border rounded mb-4 bg-light">
                                        <h6 class="fw-bold mb-3"><?= $idx ?>) <?= htmlspecialchars($q['question_text']) ?></h6>
                                        <div class="ps-3">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" id="q-<?= $q['id'] ?>-1" value="1">
                                                <label class="form-check-label" for="q-<?= $q['id'] ?>-1">الف) <?= htmlspecialchars($q['option_1']) ?></label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" id="q-<?= $q['id'] ?>-2" value="2">
                                                <label class="form-check-label" for="q-<?= $q['id'] ?>-2">ب) <?= htmlspecialchars($q['option_2']) ?></label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" id="q-<?= $q['id'] ?>-3" value="3">
                                                <label class="form-check-label" for="q-<?= $q['id'] ?>-3">ج) <?= htmlspecialchars($q['option_3']) ?></label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" id="q-<?= $q['id'] ?>-4" value="4">
                                                <label class="form-check-label" for="q-<?= $q['id'] ?>-4">د) <?= htmlspecialchars($q['option_4']) ?></label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- سوالات تشریحی -->
                                <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-dark mb-4">
                                    <i class="bi bi-exclamation-triangle-fill"></i> شما تا ۲۴ ساعت آینده فرصت دارید پاسخ‌برگ خود را اسکن و آپلود نمایید.
                                </div>
                                <?php $idx = 0; foreach ($questions as $q): $idx++; ?>
                                    <div class="p-3 border rounded mb-4 bg-light">
                                        <h6 class="fw-bold mb-2">سوال <?= $idx ?>:</h6>
                                        <p class="mb-0 small text-dark" style="white-space: pre-line; line-height: 1.6;"><?= htmlspecialchars($q['question_text']) ?></p>
                                    </div>
                                <?php endforeach; ?>

                                <!-- آپلود پاسخبرگ -->
                                <div class="border rounded p-3 mb-4 bg-light border-primary border-opacity-25">
                                    <label class="form-label fw-bold"><i class="bi bi-file-earmark-arrow-up text-primary"></i> آپلود فایل پاسخ‌برگ تشریحی (تصویر یا PDF)</label>
                                    <input type="file" name="essay_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                </div>
                            <?php endif; ?>

                            <div class="text-end">
                                <button type="button" class="btn btn-primary btn-lg fw-bold px-5" id="btnSubmitQuiz">ثبت نهایی و اتمام آزمون</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- فعال‌سازی تایمر زنده از سشن -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        initQuizTimer(<?= $remainingSeconds ?>, 'quizForm');
                        
                        document.getElementById('btnSubmitQuiz').addEventListener('click', function(e) {
                            showCustomConfirm(
                                'ثبت نهایی آزمون',
                                'آیا از اتمام آزمون و ثبت نهایی پاسخ‌ها مطمئن هستید؟',
                                'warning',
                                function(confirmed) {
                                    if (confirmed) {
                                        document.getElementById('quizForm').submit();
                                    }
                                }
                            );
                        });
                    });
                </script>

            <!-- وضعیت ۳: آزمون به اتمام رسیده است -->
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-3 p-4 text-center">
                    <div class="card-header bg-white border-0 pb-3">
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h4 class="fw-bold mt-2">شرکت در آزمون به اتمام رسید</h4>
                        <span class="badge bg-light text-secondary border">درس: <?= htmlspecialchars($quiz['course_name']) ?></span>
                    </div>
                    <div class="card-body">
                        <h5 class="fw-bold text-dark mb-4"><?= htmlspecialchars($quiz['quiz_title']) ?></h5>

                        <div class="border rounded p-3 bg-light d-inline-block px-5 mb-4 shadow-sm">
                            <?php if ($attempt['status'] === 'failed_deadline'): ?>
                                <h6 class="text-danger fw-bold mb-0">عدم ارسال به موقع پاسخ (نمره ثبت شده: ۰)</h6>
                            <?php elseif ($attempt['score'] !== null): ?>
                                <h6 class="text-success fw-bold mb-1">آزمون به صورت خودکار تصحیح شد:</h6>
                                <h3 class="fw-bold mb-0"><?= $attempt['score'] ?> <span class="fs-6 text-secondary">/ ۲۰</span></h3>
                            <?php else: ?>
                                <h6 class="text-info fw-bold mb-0">پاسخ تشریحی شما دریافت گردید. در انتظار نمره‌دهی معلم.</h6>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex flex-column gap-2 col-md-6 mx-auto">
                            <?php if ($quiz['quiz_type'] === 'multiple_choice' && ($attempt['status'] === 'completed' || $attempt['status'] === 'failed_deadline')): ?>
                                <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="collapse" data-bs-target="#quizAnswersCollapse">
                                    <i class="bi bi-eye-fill me-1"></i> مشاهده پاسخ‌نامه و جزئیات آزمون
                                </button>
                            <?php endif; ?>
                            <a href="courses.php" class="btn btn-outline-primary fw-bold">بازگشت به کلاس‌های من</a>
                        </div>

                        <!-- پاسخ‌نامه تشریحی و مقایسه‌ای آزمون تستی -->
                        <?php if ($quiz['quiz_type'] === 'multiple_choice' && ($attempt['status'] === 'completed' || $attempt['status'] === 'failed_deadline')): ?>
                            <div class="collapse mt-4 text-start" id="quizAnswersCollapse">
                                <div class="card border-0 shadow-sm rounded-3">
                                    <div class="card-header bg-light py-3 border-0">
                                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-card-checklist text-primary me-1"></i> پاسخ‌نامه تشریحی و گزینه‌های صحیح</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        // واکشی سوالات و پاسخ‌های ثبت شده کلاینت
                                        $stmtAnswers = $pdo->prepare("SELECT qz.*, qa.selected_option 
                                            FROM quiz_questions qz
                                            LEFT JOIN student_quiz_answers qa ON qz.id = qa.question_id AND qa.student_quiz_id = ?
                                            WHERE qz.quiz_id = ?
                                            ORDER BY qz.id ASC");
                                        $stmtAnswers->execute([$attempt['id'], $quizId]);
                                        $quizAnswers = $stmtAnswers->fetchAll();
                                        
                                        $qIdx = 0;
                                        foreach ($quizAnswers as $qa): $qIdx++;
                                            $isCorrect = $qa['selected_option'] == $qa['correct_option'];
                                            $isUnanswered = $qa['selected_option'] === null;
                                        ?>
                                            <div class="p-3 border rounded-3 mb-3 <?= $isCorrect ? 'border-success bg-success-subtle bg-opacity-10' : ($isUnanswered ? 'border-secondary bg-light' : 'border-danger bg-danger-subtle bg-opacity-10') ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="fw-bold small text-dark"><?= $qIdx ?>) <?= htmlspecialchars($qa['question_text']) ?></span>
                                                    <?php if ($isCorrect): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-lg me-1"></i>پاسخ صحیح</span>
                                                    <?php elseif ($isUnanswered): ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">بدون پاسخ</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-lg me-1"></i>پاسخ نادرست</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="ps-3 small">
                                                    <?php
                                                    for ($o = 1; $o <= 4; $o++):
                                                        $optText = $qa['option_' . $o];
                                                        $optPrefix = ($o === 1 ? 'الف' : ($o === 2 ? 'ب' : ($o === 3 ? 'ج' : 'د')));
                                                        
                                                        $isMyChoice = $qa['selected_option'] == $o;
                                                        $isCorrectChoice = $qa['correct_option'] == $o;
                                                        
                                                        $style = '';
                                                        $badgeMarkup = '';
                                                        
                                                        if ($isCorrectChoice) {
                                                            $style = 'font-weight: bold; color: #10B981;';
                                                            $badgeMarkup = ' <span class="badge bg-success-subtle text-success border border-success-subtle py-1 ms-1 text-xs">گزینه صحیح</span>';
                                                        }
                                                        if ($isMyChoice && !$isCorrectChoice) {
                                                            $style = 'font-weight: bold; color: #EF4444; text-decoration: line-through;';
                                                            $badgeMarkup = ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 ms-1 text-xs">انتخاب شما</span>';
                                                        } elseif ($isMyChoice && $isCorrectChoice) {
                                                            $badgeMarkup = ' <span class="badge bg-success border border-success text-white py-1 ms-1 text-xs">انتخاب صحیح شما</span>';
                                                        }
                                                    ?>
                                                        <div class="mb-1" style="<?= $style ?>">
                                                            <?= $optPrefix ?>) <?= htmlspecialchars($optText) ?> <?= $badgeMarkup ?>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
