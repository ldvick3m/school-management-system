<?php
/**
 * جزئیات درس و محتوای آموزشی (ویدیو، فایل‌ها، تالار گفتگو، تکالیف و آزمون‌ها)
 */

require_once '../includes/header.php';
check_auth(['student', 'admin']);

$error = '';
$success = '';

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$allocationId = isset($_GET['allocation_id']) ? (int)$_GET['allocation_id'] : 0;
$topicId = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;

try {
    // ۱. پیدا کردن اطلاعات دانش‌آموز
    $stmtS = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $studentId = $stmtS->fetchColumn();

    // ۲. بررسی وجود و اعتبار تخصیص درس
    $stmtAlloc = $pdo->prepare("SELECT ctc.*, c.class_name, co.course_name, u.full_name as teacher_name 
        FROM class_teacher_course ctc
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        JOIN teachers t ON ctc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ctc.id = ?");
    $stmtAlloc->execute([$allocationId]);
    $allocation = $stmtAlloc->fetch();

    if (!$allocation) {
        die("اطلاعات کلاس درس معتبر نمی‌باشد.");
    }

    // ۳. واکشی تمامی مباحث درسی این کلاس/درس
    $stmtTopics = $pdo->prepare("SELECT * FROM topics WHERE class_teacher_course_id = ? ORDER BY created_at ASC");
    $stmtTopics->execute([$allocationId]);
    $topics = $stmtTopics->fetchAll();

    // تعیین مبحث فعال جاری
    $activeTopic = null;
    if ($topicId > 0) {
        foreach ($topics as $t) {
            if ($t['id'] == $topicId) {
                $activeTopic = $t;
                break;
            }
        }
    }
    // اگر مبحثی انتخاب نشده بود، اولین مبحث را به صورت پیش‌فرض فعال قرار می‌دهیم
    if (!$activeTopic && !empty($topics)) {
        $activeTopic = $topics[0];
        $topicId = $activeTopic['id'];
    }

} catch (PDOException $e) {
    die("خطا در بارگذاری مباحث درسی: " . $e->getMessage());
}

// هندل کردن ارسال تکالیف (Homework Submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_homework') {
    $homework_id = (int)($_POST['homework_id'] ?? 0);
    
    if ($homework_id <= 0 || !isset($_FILES['homework_file']) || $_FILES['homework_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'لطفاً فایل پاسخ تکلیف خود را انتخاب کنید.';
    } else {
        try {
            $file = $_FILES['homework_file'];
            $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_exts)) {
                $error = 'فرمت فایل ارسال شده مجاز نیست. فرمت‌های معتبر: PDF, Word, JPG';
            } else {
                $uploadDir = '../uploads/homeworks/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = uniqid('hw_') . '_' . $studentId . '.' . $ext;
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $relativeFilePath = 'uploads/homeworks/' . $filename;

                    // ثبت یا به‌روزرسانی پاسخ تکلیف در دیتابیس
                    $stmtSubmit = $pdo->prepare("INSERT INTO `homework_submissions` (`homework_id`, `student_id`, `file_path`, `status`, `submitted_at`) 
                        VALUES (?, ?, ?, 'pending', NOW())
                        ON DUPLICATE KEY UPDATE `file_path` = VALUES(`file_path`), `status` = 'pending', `submitted_at` = NOW()");
                    $stmtSubmit->execute([$homework_id, $studentId, $relativeFilePath]);

                    $success = "پاسخ تکلیف شما با موفقیت آپلود گردید و برای تصحیح به معلم ارسال شد.";
                } else {
                    $error = 'خطا در بارگذاری فیزیکی فایل روی سرور.';
                }
            }
        } catch (PDOException $e) {
            $error = "خطا در ثبت تکلیف در سیستم: " . $e->getMessage();
        }
    }
}

// واکشی اطلاعات تفصیلی مبحث فعال (منابع، تکالیف و آزمون‌ها)
$resources = [];
$homeworks = [];
$quizzes = [];
$homeworkSubmissions = [];
$quizAttempts = [];

if ($activeTopic) {
    try {
        // ۱. منابع
        $stmtRes = $pdo->prepare("SELECT * FROM resources WHERE topic_id = ?");
        $stmtRes->execute([$topicId]);
        $resources = $stmtRes->fetchAll();

        // ۲. تکالیف
        $stmtHw = $pdo->prepare("SELECT * FROM homeworks WHERE topic_id = ?");
        $stmtHw->execute([$topicId]);
        $homeworks = $stmtHw->fetchAll();

        // واکشی پاسخ‌های ارسال شده دانش‌آموز برای تکالیف این مبحث
        if (!empty($homeworks)) {
            $hwIds = array_column($homeworks, 'id');
            $placeholders = implode(',', array_fill(0, count($hwIds), '?'));
            
            $stmtHwSub = $pdo->prepare("SELECT * FROM homework_submissions 
                WHERE student_id = ? AND homework_id IN ($placeholders)");
            $stmtHwSub->execute(array_merge([$studentId], $hwIds));
            $subs = $stmtHwSub->fetchAll();
            
            foreach ($subs as $sub) {
                $homeworkSubmissions[$sub['homework_id']] = $sub;
            }
        }

        // ۳. آزمون‌ها
        $stmtQz = $pdo->prepare("SELECT * FROM quizzes WHERE topic_id = ?");
        $stmtQz->execute([$topicId]);
        $quizzes = $stmtQz->fetchAll();

        // واکشی تلاش‌های شرکت در آزمون دانش‌آموز
        if (!empty($quizzes)) {
            $qzIds = array_column($quizzes, 'id');
            $placeholdersQ = implode(',', array_fill(0, count($qzIds), '?'));

            $stmtQzAtt = $pdo->prepare("SELECT * FROM student_quizzes 
                WHERE student_id = ? AND quiz_id IN ($placeholdersQ)");
            $stmtQzAtt->execute(array_merge([$studentId], $qzIds));
            $atts = $stmtQzAtt->fetchAll();

            foreach ($atts as $att) {
                $quizAttempts[$att['quiz_id']] = $att;
            }
        }

    } catch (PDOException $e) {
        $error = "خطا در لود جزئیات مبحث درسی: " . $e->getMessage();
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
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- سربرگ نام کلاس و درس -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 text-white" style="background-color: #4C1D95;">
        <div class="card-body p-4 d-flex justify-content-between align-items-center">
            <div>
                <span class="badge mb-2 text-white" style="background-color: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.25);"><i class="bi bi-journal-bookmark-fill me-1"></i>کلاس فعال درس</span>
                <h3 class="fw-bold mb-1 text-white">
                    <?= htmlspecialchars($allocation['course_name']) ?>
                    <?php if ($activeTopic): ?>
                        <span class="text-white-50"> - </span><?= htmlspecialchars($activeTopic['topic_title']) ?>
                    <?php endif; ?>
                </h3>
                <p class="mb-0 text-white">دبیر: <?= htmlspecialchars($allocation['teacher_name']) ?> | کلاس: <?= htmlspecialchars($allocation['class_name']) ?></p>
            </div>
            <a href="courses.php" class="btn btn-outline-light btn-sm fw-bold"><i class="bi bi-arrow-right me-1"></i>بازگشت به کلاس‌ها</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- ستون محتوا و ویدیو (سمت راست - در RTL اول می‌آید) -->
        <div class="col-lg-9">
            <?php if (!$activeTopic): ?>
                <div class="card border-0 shadow-sm rounded-3 py-5 text-center">
                    <i class="bi bi-file-earmark-slides fs-1 text-secondary mb-3"></i>
                    <h5 class="fw-bold">انتخاب مبحث درسی</h5>
                    <p class="text-secondary small">جهت مشاهده فیلم‌های تدریس، منابع ضمیمه، تکالیف و امتحانات، یک مبحث را از منوی سایدبار انتخاب کنید.</p>
                </div>
            <?php else: ?>
                <!-- کارت ویدیوی تدریس مبحث -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3 text-secondary"><i class="bi bi-play-btn-fill text-primary me-1"></i>ویدیوی آموزشی تدریس</h5>
                        
                        <?php if ($activeTopic['video_path']): ?>
                            <div class="text-center rounded border bg-black p-1 shadow-sm">
                                <!-- پخش‌کننده ویدیوی استاندارد HTML5 -->
                                <video class="w-100 rounded" controls style="max-height: 480px; background-color: black;">
                                    <source src="<?= '../' . htmlspecialchars($activeTopic['video_path']) ?>" type="video/mp4">
                                    مرورگر شما از پخش مستقیم ویدیو پشتیبانی نمی‌کند.
                                </video>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary py-4 text-center small border-0 mb-0">
                                <i class="bi bi-play-btn-fill me-1 fs-4 d-block mb-2"></i> ویدیوی تدریسی برای این مبحث توسط دبیر بارگذاری نشده است.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- تب‌های جزئیات و خدمات مبحث در زیر ویدیو -->
                <div class="card border-0 shadow-sm rounded-3">
                    <!-- منوی تب‌ها -->
                    <ul class="nav nav-tabs custom-tabs px-3 bg-light border-bottom-0" id="topicDetailTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc-pane" type="button" role="tab" aria-controls="desc-pane" aria-selected="true">
                                <i class="bi bi-file-text-fill me-1"></i> خلاصه و توضیحات درس
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="res-tab" data-bs-toggle="tab" data-bs-target="#res-pane" type="button" role="tab" aria-controls="res-pane" aria-selected="false">
                                <i class="bi bi-folder-fill me-1"></i> منابع و فایل‌های ضمیمه (<?= count($resources) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="forum-tab" data-bs-toggle="tab" data-bs-target="#forum-pane" type="button" role="tab" aria-controls="forum-pane" aria-selected="false">
                                <i class="bi bi-chat-dots-fill me-1"></i> تالار گفتگو درس
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="hw-tab" data-bs-toggle="tab" data-bs-target="#hw-pane" type="button" role="tab" aria-controls="hw-pane" aria-selected="false">
                                <i class="bi bi-journal-check me-1"></i> تکالیف (<?= count($homeworks) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="quiz-tab" data-bs-toggle="tab" data-bs-target="#quiz-pane" type="button" role="tab" aria-controls="quiz-pane" aria-selected="false">
                                <i class="bi bi-question-square-fill me-1"></i> آزمون‌ها (<?= count($quizzes) ?>)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4" id="topicDetailTabContent">
                        
                        <!-- تب ۱: خلاصه و توضیحات درس -->
                        <div class="tab-pane fade show active" id="desc-pane" role="tabpanel" aria-labelledby="desc-tab">
                            <h6 class="fw-bold text-secondary mb-2">توضیحات و راهنمای درسی دبیر:</h6>
                            <div class="p-3 rounded bg-light border small text-dark" style="line-height: 1.7; white-space: pre-line;">
                                <?= $activeTopic['topic_description'] ?: 'توضیحات متنی ثبت نشده است.' ?>
                            </div>
                        </div>

                        <!-- تب ۲: منابع درسی ضمیمه شده -->
                        <div class="tab-pane fade" id="res-pane" role="tabpanel" aria-labelledby="res-tab">
                            <h5 class="fw-bold mb-3">منابع کمکی درسی</h5>
                            <?php if (empty($resources)): ?>
                                <div class="text-center py-4 text-secondary small">منبع کمکی ضمیمه‌ای یافت نشد.</div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($resources as $res): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-0 border-bottom">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="bi bi-file-earmark-arrow-down-fill text-primary fs-3"></i>
                                                <div>
                                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($res['resource_name']) ?></h6>
                                                    <small class="text-secondary">نوع: <?= $res['resource_type'] === 'link' ? 'لینک خارجی' : 'فایل پیوست' ?></small>
                                                </div>
                                            </div>
                                            <?php if ($res['resource_type'] === 'link'): ?>
                                                <a href="<?= htmlspecialchars($res['resource_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm fw-bold">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i> مشاهده لینک
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= '../' . htmlspecialchars($res['resource_path']) ?>" download class="btn btn-success btn-sm text-white fw-bold">
                                                    <i class="bi bi-cloud-arrow-down-fill me-1"></i> دانلود فایل
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- تب ۳: تالار گفتگوی همزمان درس -->
                        <div class="tab-pane fade" id="forum-pane" role="tabpanel" aria-labelledby="forum-tab">
                            <h5 class="fw-bold mb-3">تالار گفتگوی همکلاسی‌ها با دبیر</h5>
                            <p class="text-secondary small mb-3">می‌توانید سوالات علمی خود را در این بخش بنویسید تا دبیر یا دانش‌آموزان به آن پاسخ دهند (چت پس‌زمینه زنده).</p>
                            
                            <!-- تالار چت -->
                            <div class="forum-chat-box mb-3" id="forumChatBox">
                                <!-- پیام‌ها با JS پولینگ پر می‌شوند -->
                            </div>
                            
                            <!-- فرم ارسال پیام چت -->
                            <form id="forumSendForm">
                                <div class="input-group">
                                    <input type="text" id="forumMessageInput" class="form-control" placeholder="سوال درسی خود را اینجا بپرسید..." required autocomplete="off">
                                    <button type="submit" class="btn btn-primary fw-bold px-4">
                                        <i class="bi bi-send-fill me-1"></i> ارسال سوال
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- تب ۴: تکالیف مبحث درسی -->
                        <div class="tab-pane fade" id="hw-pane" role="tabpanel" aria-labelledby="hw-tab">
                            <h5 class="fw-bold mb-3">تکالیف درسی محوله</h5>
                            
                            <?php if (empty($homeworks)): ?>
                                <div class="text-center py-4 text-secondary small">تکلیفی برای این مبحث تعریف نشده است.</div>
                            <?php else: foreach ($homeworks as $hw): 
                                $submission = $homeworkSubmissions[$hw['id']] ?? null;
                                $isOverdue = strtotime($hw['deadline']) < time() && !$submission;
                            ?>
                                <div class="border rounded p-4 mb-3 bg-light shadow-sm">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($hw['homework_title']) ?></h5>
                                        <!-- وضعیت تحویل تکلیف -->
                                        <?php if ($submission): ?>
                                            <span class="badge bg-<?= get_status_class($submission['status']) ?> border">
                                                <?= $submission['status'] === 'graded' ? 'نمره‌دهی شده: ' . $submission['grade'] : 'در انتظار بررسی دبیر' ?>
                                            </span>
                                        <?php elseif ($isOverdue): ?>
                                            <span class="badge bg-danger border">معوقه (گذشت ددلاین)</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark border">ارسال‌نشده</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="text-secondary small mb-3">مهلت تحویل: <strong><?= to_shamsi($hw['deadline']) ?> (ساعت <?= date('H:i', strtotime($hw['deadline'])) ?>)</strong></p>
                                    
                                    <div class="p-3 bg-white border rounded mb-3 small text-dark" style="line-height: 1.6;">
                                        <strong>صورت تکلیف دبیر:</strong><br>
                                        <?= htmlspecialchars($hw['homework_description']) ?>
                                    </div>

                                    <?php if ($submission && $submission['feedback']): ?>
                                        <div class="p-3 bg-warning bg-opacity-10 border border-warning-subtle text-dark rounded mb-3 small">
                                            <strong>بازخورد و راهنمایی معلم:</strong><br>
                                            <?= htmlspecialchars($submission['feedback']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- فرم آپلود پاسخ تکلیف -->
                                    <?php if (!$submission && !$isOverdue): ?>
                                        <form method="POST" action="" enctype="multipart/form-data" class="bg-white border rounded p-3">
                                            <input type="hidden" name="action" value="submit_homework">
                                            <input type="hidden" name="homework_id" value="<?= $hw['id'] ?>">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-8">
                                                    <label class="form-label small fw-bold">فایل پاسخبرگ (فرمت PDF, Word, JPG):</label>
                                                    <input type="file" name="homework_file" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-4 mt-md-4 pt-1">
                                                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> آپلود و ارسال پاسخ
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <!-- تب ۵: آزمون‌های درسی -->
                        <div class="tab-pane fade" id="quiz-pane" role="tabpanel" aria-labelledby="quiz-tab">
                            <h5 class="fw-bold mb-3">آزمون‌های مربوط به مبحث درسی</h5>
                            
                            <?php if (empty($quizzes)): ?>
                                <div class="text-center py-4 text-secondary small">آزمونی برای این مبحث زمان‌بندی نشده است.</div>
                            <?php else: foreach ($quizzes as $quiz): 
                                $attempt = $quizAttempts[$quiz['id']] ?? null;
                            ?>
                                <div class="border rounded p-4 mb-3 bg-light shadow-sm d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($quiz['quiz_title']) ?></h5>
                                        <small class="text-secondary">
                                            نوع آزمون: <strong><?= $quiz['quiz_type'] === 'multiple_choice' ? 'تستی (چهار گزینه‌ای)' : 'تشریحی' ?></strong> 
                                            <?= $quiz['duration_minutes'] ? ' | زمان: <strong>' . $quiz['duration_minutes'] . ' دقیقه</strong>' : '' ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php if ($attempt): ?>
                                            <?php if ($attempt['score'] !== null): ?>
                                                 <div class="d-flex align-items-center gap-2">
                                                     <span class="badge bg-success p-2 border fs-6">نمره کسب‌شده: <?= $attempt['score'] ?></span>
                                                     <?php if ($quiz['quiz_type'] === 'multiple_choice'): ?>
                                                         <a href="quiz.php?quiz_id=<?= $quiz['id'] ?>&show_answers=1" class="btn btn-outline-primary btn-sm fw-bold">
                                                             <i class="bi bi-eye-fill me-1"></i> مشاهده پاسخ‌نامه
                                                         </a>
                                                     <?php endif; ?>
                                                 </div>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark p-2 border">در انتظار تصحیح تشریحی</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="quiz.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-warning fw-bold text-dark">
                                                <i class="bi bi-play-fill me-1"></i> شروع شرکت در آزمون
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ستون مباحث درسی (منوی سایدبار درس - سمت چپ) -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0">سرفصل‌ها و مباحث درس</h6>
                </div>
                <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                    <?php if (empty($topics)): ?>
                        <span class="text-secondary small p-3 text-center">هنوز مبحثی برای این درس ثبت نشده است.</span>
                    <?php else: foreach ($topics as $tp): ?>
                        <a href="course_details.php?course_id=<?= $courseId ?>&allocation_id=<?= $allocationId ?>&topic_id=<?= $tp['id'] ?>" class="list-group-item list-group-item-action p-3 <?= $topicId == $tp['id'] ? 'active bg-light border-start border-primary border-4' : '' ?>">
                            <div class="fw-bold small text-dark"><?= htmlspecialchars($tp['topic_title']) ?></div>
                            <small class="text-secondary d-block mt-1"><i class="bi bi-calendar-event me-1"></i><?= to_shamsi($tp['created_at']) ?></small>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- کارت دفترچه یادداشت مبحث -->
            <?php if ($activeTopic): ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-1"></i> یادداشت‌های مبحث</h6>
                        <button class="btn btn-outline-primary btn-sm px-2 py-1" id="btnNewTopicNote" style="font-size: 0.75rem;">
                            <i class="bi bi-plus-lg"></i> جدید
                        </button>
                    </div>
                    <div class="card-body p-3">
                        <!-- فرم ثبت یادداشت -->
                        <div id="topicNoteForm" class="d-none border-bottom pb-3 mb-3">
                            <input type="hidden" id="topicNoteId" value="0">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">عنوان یادداشت</label>
                                <input type="text" id="topicNoteTitle" class="form-control form-control-sm" value="<?= htmlspecialchars($activeTopic['topic_title']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold">متن یادداشت</label>
                                <textarea id="topicNoteContent" class="form-control form-control-sm" rows="3" placeholder="یادداشت خود را اینجا بنویسید..." required></textarea>
                            </div>
                            <div class="d-flex gap-1 justify-content-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCancelTopicNote" style="font-size: 0.75rem; padding: 4px 8px;">انصراف</button>
                                <button type="button" class="btn btn-primary btn-sm" id="btnSaveTopicNote" style="font-size: 0.75rem; padding: 4px 8px;">ذخیره</button>
                            </div>
                        </div>

                        <!-- لیست یادداشت‌های مبحث فعال -->
                        <div id="topicNotesList" style="max-height: 250px; overflow-y: auto;">
                            <div class="text-center py-3 text-secondary small">در حال بارگذاری یادداشت‌ها...</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($activeTopic): ?>
<script>
    // فعال‌سازی ردیاب تعاملی تالار گفتگوی مبحث درسی (AJAX)
    document.addEventListener('DOMContentLoaded', function() {
        initLessonForum(<?= $topicId ?>, <?= $_SESSION['user_id'] ?>);
        initTopicNotepad();
    });

    function initTopicNotepad() {
        const topicId = <?= (int)$topicId ?>;
        const courseId = <?= (int)$courseId ?>;
        const listContainer = document.getElementById('topicNotesList');
        const formContainer = document.getElementById('topicNoteForm');
        const noteIdInput = document.getElementById('topicNoteId');
        const noteTitleInput = document.getElementById('topicNoteTitle');
        const noteContentInput = document.getElementById('topicNoteContent');
        const btnNew = document.getElementById('btnNewTopicNote');
        const btnCancel = document.getElementById('btnCancelTopicNote');
        const btnSave = document.getElementById('btnSaveTopicNote');

        function loadNotes() {
            listContainer.innerHTML = '<div class="text-center py-3 text-secondary small">در حال بارگذاری یادداشت‌ها...</div>';
            fetch(`../ajax/notepad.php?action=list&note_type=course&course_id=${courseId}&topic_id=${topicId}`)
                .then(res => res.json())
                .then(notes => {
                    if (notes.length === 0) {
                        listContainer.innerHTML = '<div class="text-center py-3 text-secondary small">یادداشتی برای این مبحث ثبت نشده است.</div>';
                        return;
                    }
                    listContainer.innerHTML = notes.map(note => `
                        <div class="border rounded p-2 mb-2 bg-light">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="fw-bold text-dark small d-block mb-1">${escapeHtml(note.title)}</h6>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-link p-0 text-warning" onclick="editNote(${note.id}, '${escapeHtml(note.title)}', '${escapeHtml(note.content)}')" title="ویرایش"><i class="bi bi-pencil-fill" style="font-size:0.8rem;"></i></button>
                                    <button class="btn btn-link p-0 text-danger" onclick="deleteNote(${note.id})" title="حذف"><i class="bi bi-trash3-fill" style="font-size:0.8rem;"></i></button>
                                </div>
                            </div>
                            <p class="text-secondary mb-0 mt-1 small" style="white-space: pre-line; font-size:0.8rem; line-height:1.5;">${escapeHtml(note.content)}</p>
                        </div>
                    `).join('');
                })
                .catch(err => {
                    listContainer.innerHTML = '<div class="text-center py-3 text-danger small">خطا در دریافت یادداشت‌ها</div>';
                });
        }

        window.editNote = function(id, title, content) {
            noteIdInput.value = id;
            noteTitleInput.value = title;
            noteContentInput.value = content;
            formContainer.classList.remove('d-none');
            formContainer.scrollIntoView({ behavior: 'smooth' });
        };

        window.deleteNote = function(id) {
            if (confirm('آیا از حذف این یادداشت اطمینان دارید؟')) {
                const formData = new FormData();
                formData.append('id', id);
                fetch('../ajax/notepad.php?action=delete', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        loadNotes();
                    } else {
                        alert(res.message || 'خطا در حذف یادداشت');
                    }
                });
            }
        };

        btnNew.addEventListener('click', () => {
            noteIdInput.value = 0;
            noteTitleInput.value = `<?= htmlspecialchars($activeTopic['topic_title'], ENT_QUOTES) ?>`;
            noteContentInput.value = '';
            formContainer.classList.remove('d-none');
        });

        btnCancel.addEventListener('click', () => {
            formContainer.classList.add('d-none');
        });

        btnSave.addEventListener('click', () => {
            const title = noteTitleInput.value.trim();
            const content = noteContentInput.value.trim();

            if (!title || !content) {
                alert('لطفاً عنوان و متن یادداشت را وارد کنید.');
                return;
            }

            const formData = new FormData();
            formData.append('id', noteIdInput.value);
            formData.append('note_type', 'course');
            formData.append('course_id', courseId);
            formData.append('topic_id', topicId);
            formData.append('title', title);
            formData.append('content', content);

            fetch('../ajax/notepad.php?action=save', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    formContainer.classList.add('d-none');
                    loadNotes();
                } else {
                    alert(res.message || 'خطا در ذخیره یادداشت');
                }
            });
        });

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        loadNotes();
    }
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
