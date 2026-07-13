<?php
/**
 * ایجاد مبحث درسی جدید و آپلود ویدیوی آموزشی
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error = '';
$success = '';

try {
    // پیدا کردن شناسه معلم در جدول teachers
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم ثبت نشده‌اید.");
    }

    // واکشی کلاس‌ها و دروس مربوط به این دبیر
    $stmtAlloc = $pdo->prepare("SELECT ctc.id, c.class_name, co.course_name 
        FROM class_teacher_course ctc
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ?");
    $stmtAlloc->execute([$teacherId]);
    $allocations = $stmtAlloc->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات تخصیص معلم: " . $e->getMessage());
}

// هندل کردن ذخیره مبحث جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_teacher_course_id = (int)($_POST['class_teacher_course_id'] ?? 0);
    $topic_title = trim($_POST['topic_title'] ?? '');
    $topic_description = trim($_POST['topic_description'] ?? '');
    $uploaded_video_path = '';

    if ($class_teacher_course_id <= 0 || empty($topic_title)) {
        $error = 'انتخاب کلاس/درس و وارد کردن عنوان مبحث الزامی است.';
    } else {
        try {
            // آپلود ویدیو آموزشی در صورت وجود
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $video_file = $_FILES['video_file'];
                
                // بررسی پسوند ویدیو
                $allowed_extensions = ['mp4', 'webm', 'ogg', 'mov'];
                $ext = strtolower(pathinfo($video_file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed_extensions)) {
                    $error = 'فرمت ویدیوی ارسالی معتبر نیست. لطفاً فرمت‌های mp4, webm یا mov را امتحان کنید.';
                } else {
                    $uploadDir = '../uploads/videos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $filename = uniqid('vid_') . '.' . $ext;
                    $targetPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($video_file['tmp_name'], $targetPath)) {
                        $uploaded_video_path = 'uploads/videos/' . $filename;
                    } else {
                        $error = 'خطا در بارگذاری ویدیو بر روی سرور.';
                    }
                }
            }

            if (empty($error)) {
                $pdo->beginTransaction();

                // درج مبحث در دیتابیس
                $stmtInsert = $pdo->prepare("INSERT INTO `topics` (`class_teacher_course_id`, `topic_title`, `topic_description`, `video_path`) 
                    VALUES (?, ?, ?, ?)");
                $stmtInsert->execute([$class_teacher_course_id, $topic_title, $topic_description, $uploaded_video_path ?: null]);
                $topic_id = $pdo->lastInsertId();

                // واکشی و درج منابع ضمیمه
                $res_names = $_POST['res_name'] ?? [];
                $res_types = $_POST['res_type'] ?? [];
                $res_links = $_POST['res_link'] ?? [];
                $res_files = $_FILES['res_file'] ?? null;

                if (!empty($res_names)) {
                    $stmtRes = $pdo->prepare("INSERT INTO `resources` (`topic_id`, `resource_name`, `resource_type`, `resource_path`) 
                        VALUES (?, ?, ?, ?)");

                    for ($i = 0; $i < count($res_names); $i++) {
                        $name = trim($res_names[$i]);
                        if (empty($name)) continue;

                        $type = $res_types[$i];
                        $path = '';

                        if ($type === 'link') {
                            $path = trim($res_links[$i]);
                            if (empty($path)) continue;
                        } else {
                            // آپلود فایل ضمیمه (PDF, Image, Video)
                            if ($res_files && isset($res_files['error'][$i]) && $res_files['error'][$i] === UPLOAD_ERR_OK) {
                                $tmp_name = $res_files['tmp_name'][$i];
                                $orig_name = $res_files['name'][$i];
                                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                                
                                $uploadDir = '../uploads/resources/';
                                if (!is_dir($uploadDir)) {
                                    mkdir($uploadDir, 0755, true);
                                }

                                $filename = uniqid('res_') . '.' . $ext;
                                $targetPath = $uploadDir . $filename;

                                if (move_uploaded_file($tmp_name, $targetPath)) {
                                    $path = 'uploads/resources/' . $filename;
                                } else {
                                    throw new Exception("خطا در ذخیره‌سازی فایل منبع: " . $orig_name);
                                }
                            } else {
                                // فایلی آپلود نشده، این ردیف را نادیده می‌گیریم
                                continue;
                            }
                        }

                        $stmtRes->execute([$topic_id, $name, $type, $path]);
                    }
                }

                $pdo->commit();
                $success = "مبحث درسی جدید به همراه منابع و فایل‌های ضمیمه با موفقیت ایجاد و منتشر گردید.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "خطا در ثبت مبحث و منابع: " . $e->getMessage();
        }
    }
}

// واکشی کل مباحث درسی ثبت شده توسط این دبیر
try {
    $stmtAllTopics = $pdo->prepare("SELECT t.*, c.class_name, co.course_name 
        FROM topics t
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ?
        ORDER BY t.created_at DESC");
    $stmtAllTopics->execute([$teacherId]);
    $topicsList = $stmtAllTopics->fetchAll();
} catch (PDOException $e) {
    die("خطا در بارگذاری مباحث درسی: " . $e->getMessage());
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
        <!-- فرم ایجاد مبحث جدید -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-plus text-primary me-1"></i> ایجاد مبحث درسی جدید</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">انتخاب کلاس و درس هدف *</label>
                            <select name="class_teacher_course_id" class="form-select" required>
                                <option value="">انتخاب کنید...</option>
                                <?php foreach ($allocations as $alloc): ?>
                                    <option value="<?= $alloc['id'] ?>" <?= (isset($_GET['allocation_id']) && $_GET['allocation_id'] == $alloc['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($alloc['class_name']) ?> - <?= htmlspecialchars($alloc['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">عنوان مبحث آموزشی *</label>
                            <input type="text" name="topic_title" class="form-control" placeholder="مثال: فصل اول - درس اول: اعداد صحیح و گویا" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">آپلود ویدیوی تدریس مبحث (فرمت MP4)</label>
                            <input type="file" name="video_file" class="form-control" accept="video/mp4,video/mov">
                            <small class="text-secondary">فرمت‌های مجاز: MP4, MOV - حداکثر حجم ۵۰ مگابایت</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">خلاصه توضیحات متنی / تکالیف و راهنما</label>
                            <textarea name="topic_description" class="form-control" rows="4" placeholder="خلاصه درس، تکالیف و کارهایی که دانش‌آموز در این مبحث باید انجام دهد را بنویسید..."></textarea>
                        </div>

                        <!-- بخش بارگذاری منابع ضمیمه اضافی -->
                        <div class="mb-4 border-top pt-3">
                            <label class="form-label fw-bold d-flex justify-content-between align-items-center mb-3">
                                <span><i class="bi bi-paperclip text-primary me-1"></i>منابع و فایل‌های ضمیمه مبحث</span>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="add-resource-row" style="font-size: 0.8rem;">
                                    <i class="bi bi-plus-lg me-1"></i>افزودن منبع جدید
                                </button>
                            </label>
                            
                            <div id="resources-container">
                                <!-- سطر اول منبع -->
                                <div class="resource-row border rounded-3 p-3 bg-light mb-2">
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold">نام منبع / فایل</label>
                                            <input type="text" name="res_name[]" class="form-control form-control-sm" placeholder="مثال: جزوه آموزشی یا کتاب کار">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">نوع منبع</label>
                                            <select name="res_type[]" class="form-select form-select-sm res-type-select">
                                                <option value="pdf">فایل PDF / Word</option>
                                                <option value="image">عکس</option>
                                                <option value="link">لینک اینترنتی</option>
                                                <option value="video">ویدیو کمکی</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end gap-2">
                                            <div class="flex-grow-1 res-input-container">
                                                <!-- ورودی فایل پیش‌فرض -->
                                                <input type="file" name="res_file[]" class="form-control form-control-sm res-file-input" accept=".pdf,.doc,.docx">
                                                <!-- ورودی لینک پیش‌فرض (پنهان) -->
                                                <input type="text" name="res_link[]" class="form-control form-control-sm res-link-input d-none" placeholder="https://example.com">
                                            </div>
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-resource-row" title="حذف این سطر"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <small class="text-secondary d-block mt-1">فرمت‌های مجاز فایل: PDF, Image, Word, MP4</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 fw-bold">ایجاد و انتشار مبحث آموزشی</button>
                    </form>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const container = document.getElementById('resources-container');
                        const btnAdd = document.getElementById('add-resource-row');
                        
                        // تغییر فیلد بر اساس نوع منبع
                        container.addEventListener('change', function(e) {
                            if (e.target.classList.contains('res-type-select')) {
                                const row = e.target.closest('.resource-row');
                                const fileInput = row.querySelector('.res-file-input');
                                const linkInput = row.querySelector('.res-link-input');
                                const type = e.target.value;
                                
                                if (type === 'link') {
                                    fileInput.classList.add('d-none');
                                    fileInput.value = '';
                                    linkInput.classList.remove('d-none');
                                } else {
                                    linkInput.classList.add('d-none');
                                    linkInput.value = '';
                                    fileInput.classList.remove('d-none');
                                    
                                    // تنظیم پسوندهای مجاز بر اساس نوع انتخاب شده
                                    if (type === 'pdf') {
                                        fileInput.accept = '.pdf,.doc,.docx';
                                    } else if (type === 'image') {
                                        fileInput.accept = 'image/*';
                                    } else if (type === 'video') {
                                        fileInput.accept = 'video/*';
                                    }
                                }
                            }
                        });
                        
                        // حذف یک سطر منبع
                        container.addEventListener('click', function(e) {
                            if (e.target.closest('.remove-resource-row')) {
                                const rows = container.querySelectorAll('.resource-row');
                                if (rows.length > 1) {
                                    e.target.closest('.resource-row').remove();
                                } else {
                                    // اگر فقط یک سطر بود، مقادیر آن را پاک کنید
                                    const firstRow = rows[0];
                                    firstRow.querySelector('input[type="text"]').value = '';
                                    const fileInput = firstRow.querySelector('input[type="file"]');
                                    if (fileInput) fileInput.value = '';
                                    const select = firstRow.querySelector('select');
                                    select.selectedIndex = 0;
                                    select.dispatchEvent(new Event('change'));
                                }
                            }
                        });
                        
                        // افزودن سطر جدید منبع
                        btnAdd.addEventListener('click', function() {
                            const rows = container.querySelectorAll('.resource-row');
                            const clone = rows[0].cloneNode(true);
                            
                            // پاکسازی داده‌های کپی شده
                            clone.querySelectorAll('input').forEach(input => {
                                input.value = '';
                            });
                            clone.querySelector('select').selectedIndex = 0;
                            
                            // نمایش فیلد فایل پیش‌فرض و پنهان کردن لینک
                            clone.querySelector('.res-file-input').classList.remove('d-none');
                            clone.querySelector('.res-file-input').accept = '.pdf,.doc,.docx';
                            clone.querySelector('.res-link-input').classList.add('d-none');
                            
                            container.appendChild(clone);
                        });
                    });
                    </script>
                </div>
            </div>
        </div>

        <!-- لیست مباحث ایجاد شده دبیر -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">مباحث منتشر شده قبلی</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($topicsList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-excel fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">هنوز مبحث درسی توسط شما ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>عنوان مبحث</th>
                                        <th>کلاس / درس</th>
                                        <th>ویدیو ضمیمه</th>
                                        <th>تاریخ انتشار</th>
                                        <th>عملیات فرعی</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topicsList as $topic): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($topic['topic_title']) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($topic['course_name']) ?></span><br>
                                                <small class="text-secondary"><?= htmlspecialchars($topic['class_name']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($topic['video_path']): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                        <i class="bi bi-play-btn-fill"></i> بارگذاری شده
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">فاقد ویدیو</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= to_shamsi($topic['created_at']) ?></td>
                                            <td>
                                                <a href="resources.php?topic_id=<?= $topic['id'] ?>" class="btn btn-outline-success btn-sm" title="مدیریت منابع درس">
                                                    <i class="bi bi-folder-plus"></i> منابع
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
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
