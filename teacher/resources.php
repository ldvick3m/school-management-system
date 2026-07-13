<?php
/**
 * مدیریت منابع آموزشی ضمیمه مباحث درسی (کتابخانه پیشرفته با آپلود داینامیک)
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error = '';
$success = '';
$topicId = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
$activeTopic = null;

try {
    // پیدا کردن شناسه معلم
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم ثبت نشده‌اید.");
    }

    // بررسی تعلق مبحث فعال به دبیر
    if ($topicId > 0) {
        $stmtCheck = $pdo->prepare("SELECT t.*, c.class_name, co.course_name 
            FROM topics t
            JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
            JOIN classes c ON ctc.class_id = c.id
            JOIN courses co ON ctc.course_id = co.id
            WHERE t.id = ? AND ctc.teacher_id = ?");
        $stmtCheck->execute([$topicId, $teacherId]);
        $activeTopic = $stmtCheck->fetch();
        
        if (!$activeTopic) {
            die("مبحث درسی مورد نظر یافت نشد یا دسترسی مجاز ندارید.");
        }
    }

    // واکشی لیست تمام مباحث درسی این دبیر جهت انتخاب در فیلتر
    $stmtTopics = $pdo->prepare("SELECT t.id, t.topic_title, c.class_name, co.course_name 
        FROM topics t
        JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ?
        ORDER BY t.created_at DESC");
    $stmtTopics->execute([$teacherId]);
    $topics = $stmtTopics->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات مباحث دبیر: " . $e->getMessage());
}

// هندل کردن ثبت منابع جدید به صورت پویای هم‌زمان
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $topicId > 0) {
    $res_names = $_POST['res_name'] ?? [];
    $res_types = $_POST['res_type'] ?? [];
    $res_links = $_POST['res_link'] ?? [];
    $res_files = $_FILES['res_file'] ?? null;

    if (empty($res_names)) {
        $error = 'هیچ منبعی جهت ثبت ارسال نشده است.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtInsert = $pdo->prepare("INSERT INTO `resources` (`topic_id`, `resource_name`, `resource_type`, `resource_path`) 
                VALUES (?, ?, ?, ?)");

            for ($i = 0; $i < count($res_names); $i++) {
                $name = trim($res_names[$i]);
                $type = $res_types[$i];
                $path = '';

                if (empty($name)) continue;

                if ($type === 'link') {
                    $path = trim($res_links[$i]);
                    if (empty($path)) continue;
                } else {
                    // آپلود فایل (تصویر، ویدیو یا پی‌دی‌اف)
                    if ($res_files && $res_files['error'][$i] === UPLOAD_ERR_OK) {
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
                        continue; // اگر فایل آپلود نشد سطر را رد می‌کنیم
                    }
                }

                // ثبت در پایگاه داده
                $stmtInsert->execute([$topicId, $name, $type, $path]);
            }

            $pdo->commit();
            $success = "کتابخانه منابع با موفقیت به‌روزرسانی شد.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "خطا در ثبت منابع آموزشی: " . $e->getMessage();
        }
    }
}

// هندل کردن حذف منبع
if (isset($_GET['delete_res_id'])) {
    $delId = (int)$_GET['delete_res_id'];
    try {
        // ابتدا حذف فایل فیزیکی در صورت نیاز
        $stmtFile = $pdo->prepare("SELECT resource_path, resource_type FROM resources WHERE id = ?");
        $stmtFile->execute([$delId]);
        $res = $stmtFile->fetch();
        
        if ($res) {
            if ($res['resource_type'] !== 'link' && file_exists('../' . $res['resource_path'])) {
                unlink('../' . $res['resource_path']);
            }
            // حذف از دیتابیس
            $stmtDel = $pdo->prepare("DELETE FROM resources WHERE id = ?");
            $stmtDel->execute([$delId]);
            $success = "منبع آموزشی با موفقیت حذف شد.";
        }
    } catch (PDOException $e) {
        $error = "خطا در حذف منبع: " . $e->getMessage();
    }
}

// واکشی منابع مربوط به مبحث فعال
$resources = [];
if ($topicId > 0) {
    try {
        $stmtRes = $pdo->prepare("SELECT * FROM resources WHERE topic_id = ? ORDER BY created_at DESC");
        $stmtRes->execute([$topicId]);
        $resources = $stmtRes->fetchAll();
    } catch (PDOException $e) {
        die("خطا در واکشی منابع مبحث درسی: " . $e->getMessage());
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

    <!-- سربرگ فیلتر مباحث -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label class="form-label mb-0 me-2 d-inline-block fw-bold">مبحث درسی فعال:</label>
                    <select name="topic_id" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                        <option value="">-- انتخاب مبحث درسی --</option>
                        <?php foreach ($topics as $tp): ?>
                            <option value="<?= $tp['id'] ?>" <?= $topicId == $tp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tp['class_name']) ?> - <?= htmlspecialchars($tp['course_name']) ?> : <?= htmlspecialchars($tp['topic_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($topicId <= 0): ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center">
            <div class="card-body">
                <i class="bi bi-folder-symlink fs-1 text-secondary mb-3"></i>
                <h5 class="fw-bold">انتخاب مبحث درسی جهت مدیریت منابع</h5>
                <p class="text-secondary small">لطفاً از منوی کشویی بالا، مبحث درسی مربوطه را جهت بارگذاری و تعریف منابع انتخاب نمایید.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- فرم افزودن منابع به صورت پویا (داینامیک فرانت‌انند) -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-folder-plus text-success me-1"></i> افزودن منابع جدید</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">به کمک کلید «افزودن منبع جدید» می‌توانید بی‌نهایت لینک و فایل پیوست آپلود نمایید.</p>
                        
                        <form method="POST" action="" enctype="multipart/form-data" id="resourcesForm">
                            <div id="resourcesFieldsContainer">
                                <!-- سطر اول منبع -->
                                <div class="resource-row border rounded p-3 mb-3 bg-light">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small">نام/عنوان منبع *</label>
                                            <input type="text" name="res_name[]" class="form-control form-control-sm" placeholder="مثال: جزوه ریاضی فصل ۱" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">نوع منبع *</label>
                                            <select name="res_type[]" class="form-select form-select-sm" onchange="toggleResourceType(this)">
                                                <option value="pdf">فایل PDF</option>
                                                <option value="image">تصویر آموزشی</option>
                                                <option value="video">ویدیو ضمیمه</option>
                                                <option value="link">لینک خارجی</option>
                                            </select>
                                        </div>
                                        
                                        <!-- بخش فایل آپلودی -->
                                        <div class="col-md-12 file-input-group">
                                            <label class="form-label small">انتخاب فایل پیوست *</label>
                                            <input type="file" name="res_file[]" class="form-control form-control-sm" required>
                                        </div>
                                        
                                        <!-- بخش لینک متنی (در ابتدا پنهان) -->
                                        <div class="col-md-12 link-input-group d-none">
                                            <label class="form-label small">آدرس لینک خارجی *</label>
                                            <input type="url" name="res_link[]" class="form-control form-control-sm" placeholder="https://site.com/resource">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="add-resource-btn mb-3 fw-bold" onclick="addNewResourceRow()">
                                <i class="bi bi-plus-circle-fill me-1"></i> + افزودن منبع جدید
                            </button>
                            
                            <button type="submit" class="btn btn-success w-100 fw-bold text-white">آپلود و ذخیره‌سازی کتابخانه</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- لیست منابع فعلی مبحث -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">منابع آموزشی ضمیمه شده به این مبحث</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($resources)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-folder2-open fs-1 text-secondary mb-2"></i>
                                <p class="text-secondary mb-0">هیچ فایلی برای این مبحث آپلود نشده است.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table custom-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>عنوان منبع</th>
                                            <th>نوع منبع</th>
                                            <th>آدرس / فایل</th>
                                            <th>اقدام</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resources as $res): ?>
                                            <tr>
                                                <td class="fw-bold text-dark"><?= htmlspecialchars($res['resource_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <?php 
                                                            switch ($res['resource_type']) {
                                                                case 'pdf': echo 'فایل PDF'; break;
                                                                case 'image': echo 'تصویر آموزشی'; break;
                                                                case 'video': echo 'ویدیو ضمیمه'; break;
                                                                case 'link': echo 'لینک خارجی'; break;
                                                            }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($res['resource_type'] === 'link'): ?>
                                                        <a href="<?= htmlspecialchars($res['resource_path']) ?>" target="_blank" class="small text-decoration-none">
                                                            <i class="bi bi-box-arrow-up-right me-1"></i> مشاهده لینک
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?= '../' . htmlspecialchars($res['resource_path']) ?>" download class="small text-decoration-none text-success">
                                                            <i class="bi bi-cloud-arrow-down-fill me-1"></i> دانلود فایل
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="resources.php?topic_id=<?= $topicId ?>&delete_res_id=<?= $res['id'] ?>" class="btn btn-link text-danger p-0" onclick="return confirm('آیا از حذف این منبع مطمئن هستید؟')" title="حذف منبع">
                                                        <i class="bi bi-trash3-fill"></i>
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
    <?php endif; ?>
</div>

<script>
    // سوئیچ فیلد فایل و آدرس بر اساس نوع انتخاب شده در فرم منبع
    function toggleResourceType(selectElem) {
        const row = selectElem.closest('.resource-row');
        const fileGroup = row.querySelector('.file-input-group');
        const fileInput = fileGroup.querySelector('input[type="file"]');
        const linkGroup = row.querySelector('.link-input-group');
        const linkInput = linkGroup.querySelector('input[type="url"]');

        if (selectElem.value === 'link') {
            fileGroup.classList.add('d-none');
            fileInput.removeAttribute('required');
            linkGroup.classList.remove('d-none');
            linkInput.setAttribute('required', 'required');
        } else {
            fileGroup.classList.remove('d-none');
            fileInput.setAttribute('required', 'required');
            linkGroup.classList.add('d-none');
            linkInput.removeAttribute('required');
        }
    }

    // اضافه کردن سطر فرم جدید با جاوااسکریپت به صورت پویا و نامحدود
    function addNewResourceRow() {
        const container = document.getElementById('resourcesFieldsContainer');
        const newRow = document.createElement('div');
        newRow.className = 'resource-row border rounded p-3 mb-3 bg-light position-relative';
        
        newRow.innerHTML = `
            <button type="button" class="btn-close position-absolute top-0 end-0 m-2" style="font-size: 0.75rem;" onclick="this.closest('.resource-row').remove()"></button>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small">نام/عنوان منبع *</label>
                    <input type="text" name="res_name[]" class="form-control form-control-sm" placeholder="مثال: جزوه ریاضی فصل ۱" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">نوع منبع *</label>
                    <select name="res_type[]" class="form-select form-select-sm" onchange="toggleResourceType(this)">
                        <option value="pdf">فایل PDF</option>
                        <option value="image">تصویر آموزشی</option>
                        <option value="video">ویدیو ضمیمه</option>
                        <option value="link">لینک خارجی</option>
                    </select>
                </div>
                
                <div class="col-md-12 file-input-group">
                    <label class="form-label small">انتخاب فایل پیوست *</label>
                    <input type="file" name="res_file[]" class="form-control form-control-sm" required>
                </div>
                
                <div class="col-md-12 link-input-group d-none">
                    <label class="form-label small">آدرس لینک خارجی *</label>
                    <input type="url" name="res_link[]" class="form-control form-control-sm" placeholder="https://site.com/resource">
                </div>
            </div>
        `;
        container.appendChild(newRow);
    }
</script>

<?php require_once '../includes/footer.php'; ?>
