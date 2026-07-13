<?php
/**
 * مدیریت نشان‌های مهارتی و آموزشی مدرسه هوشمند
 */

require_once '../includes/header.php';
check_auth(['operator', 'admin']);

$error = '';
$success = '';

// دایرکتوری بارگذاری آیکون‌های نشان‌ها
$uploadDir = '../uploads/badges/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// هندل کردن فرم‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ۱. افزودن نشان جدید
    if ($action === 'add_badge') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'educational';
        $icon = 'trophy-fill'; // مقدار پیش‌فرض آیکون

        if (empty($name) || empty($description)) {
            $error = 'وارد کردن نام و توضیحات نشان الزامی است.';
        } else {
            // بررسی آپلود فایل آیکون سفارشی
            if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['icon_file']['tmp_name'];
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $_FILES['icon_file']['name']);
                $targetPath = $uploadDir . $fileName;

                // بررسی نوع فایل (تصاویر مجاز)
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
                $fileType = mime_content_type($fileTmp);

                if (in_array($fileType, $allowedTypes)) {
                    if (move_uploaded_file($fileTmp, $targetPath)) {
                        $icon = 'uploads/badges/' . $fileName; // ذخیره مسیر نسبی
                    } else {
                        $error = 'خطا در ذخیره‌سازی فایل آیکون.';
                    }
                } else {
                    $error = 'فرمت فایل آیکون نامعتبر است. فرمت‌های مجاز: JPG, PNG, GIF, SVG';
                }
            } elseif (!empty($_POST['icon_class'])) {
                // اگر فایل آپلود نشده باشد ولی کلاس آیکون متنی وارد شده باشد
                $icon = trim($_POST['icon_class']);
            }

            if (empty($error)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `badges` (`name`, `description`, `icon`, `type`) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $icon, $type]);
                    $success = "نشان جدید با موفقیت در سیستم تعریف شد.";
                } catch (PDOException $e) {
                    $error = "خطا در تعریف نشان: " . $e->getMessage();
                }
            }
        }
    }

    // ۲. ویرایش نشان ثبت‌شده
    elseif ($action === 'edit_badge') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'educational';

        if ($badgeId <= 0 || empty($name) || empty($description)) {
            $error = 'اطلاعات ارسالی نامعتبر است.';
        } else {
            try {
                // واکشی آیکون قبلی جهت نگهداری در صورت عدم آپلود فایل جدید
                $stmtIcon = $pdo->prepare("SELECT icon FROM badges WHERE id = ?");
                $stmtIcon->execute([$badgeId]);
                $currentIcon = $stmtIcon->fetchColumn();

                $icon = $currentIcon;

                // بررسی آپلود فایل جدید
                if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['icon_file']['tmp_name'];
                    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $_FILES['icon_file']['name']);
                    $targetPath = $uploadDir . $fileName;

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
                    $fileType = mime_content_type($fileTmp);

                    if (in_array($fileType, $allowedTypes)) {
                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            // حذف فایل آیکون قدیمی اگر تصویر فیزیکی بود
                            if ($currentIcon && strpos($currentIcon, 'uploads/') === 0 && file_exists('../' . $currentIcon)) {
                                @unlink('../' . $currentIcon);
                            }
                            $icon = 'uploads/badges/' . $fileName;
                        } else {
                            $error = 'خطا در ذخیره‌سازی فایل آیکون جدید.';
                        }
                    } else {
                        $error = 'فرمت فایل آیکون نامعتبر است.';
                    }
                } elseif (!empty($_POST['icon_class'])) {
                    $icon = trim($_POST['icon_class']);
                }

                if (empty($error)) {
                    $stmt = $pdo->prepare("UPDATE `badges` SET `name` = ?, `description` = ?, `icon` = ?, `type` = ? WHERE `id` = ?");
                    $stmt->execute([$name, $description, $icon, $type, $badgeId]);
                    $success = "اطلاعات نشان مهارتی با موفقیت به‌روزرسانی شد.";
                }
            } catch (PDOException $e) {
                $error = "خطا در ویرایش نشان: " . $e->getMessage();
            }
        }
    }

    // ۳. حذف نشان
    elseif ($action === 'delete_badge') {
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        if ($badgeId > 0) {
            try {
                // پاک کردن فایل آیکون فیزیکی در صورت وجود
                $stmtIcon = $pdo->prepare("SELECT icon FROM badges WHERE id = ?");
                $stmtIcon->execute([$badgeId]);
                $currentIcon = $stmtIcon->fetchColumn();

                if ($currentIcon && strpos($currentIcon, 'uploads/') === 0 && file_exists('../' . $currentIcon)) {
                    @unlink('../' . $currentIcon);
                }

                $stmt = $pdo->prepare("DELETE FROM `badges` WHERE `id` = ?");
                $stmt->execute([$badgeId]);
                $success = "نشان مورد نظر با موفقیت حذف گردید.";
            } catch (PDOException $e) {
                $error = "خطا در حذف نشان: " . $e->getMessage();
            }
        }
    }
}

// واکشی کل نشان‌های تعریف شده در سیستم
try {
    $badges = $pdo->query("SELECT * FROM badges ORDER BY type ASC, id ASC")->fetchAll();
} catch (PDOException $e) {
    die("خطا در بارگذاری نشان‌ها: " . $e->getMessage());
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

    <!-- هدر صفحه -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i> مدیریت نشان‌های مهارتی و دستاوردها</h5>
            <button type="button" class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addBadgeModal">
                <i class="bi bi-plus-lg me-1"></i> تعریف نشان جدید
            </button>
        </div>
    </div>

    <!-- لیست نشان‌ها -->
    <div class="row g-4">
        <?php if (empty($badges)): ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-trophy fs-1 text-secondary opacity-30 mb-2"></i>
                <p class="text-secondary mb-0">هیچ نشانی در سیستم تعریف نشده است.</p>
            </div>
        <?php else: foreach ($badges as $bd): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 h-100 border-top border-4 <?= $bd['type'] === 'educational' ? 'border-primary' : 'border-success' ?>">
                    <div class="card-body p-4 d-flex align-items-start gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-light border" style="width: 64px; height: 64px; flex-shrink: 0; overflow: hidden;">
                            <?php if (strpos($bd['icon'], 'uploads/') === 0): ?>
                                <img src="<?= '../' . htmlspecialchars($bd['icon']) ?>" alt="<?= htmlspecialchars($bd['name']) ?>" style="width: 48px; height: 48px; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-<?= htmlspecialchars($bd['icon']) ?> fs-2 text-secondary"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($bd['name']) ?></h6>
                                <span class="badge rounded-pill text-xs p-1 px-2 <?= $bd['type'] === 'educational' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-success-subtle text-success border border-success-subtle' ?>">
                                    <?= $bd['type'] === 'educational' ? 'آموزشی' : 'مهارتی/انضباطی' ?>
                                </span>
                            </div>
                            <p class="text-secondary small mb-3" style="line-height: 1.4;"><?= htmlspecialchars($bd['description']) ?></p>
                            
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-outline-primary btn-xs py-1 px-2 fw-bold" 
                                        onclick="openEditBadgeModal(<?= $bd['id'] ?>, '<?= htmlspecialchars(addslashes($bd['name'])) ?>', '<?= htmlspecialchars(addslashes($bd['description'])) ?>', '<?= $bd['type'] ?>', '<?= htmlspecialchars(addslashes($bd['icon'])) ?>')" style="font-size: 0.75rem;">
                                    <i class="bi bi-pencil-fill me-1"></i> ویرایش
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-xs py-1 px-2 fw-bold" 
                                        onclick="confirmDeleteBadge(<?= $bd['id'] ?>, '<?= htmlspecialchars(addslashes($bd['name'])) ?>')" style="font-size: 0.75rem;">
                                    <i class="bi bi-trash-fill me-1"></i> حذف
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- مودال تعریف نشان جدید -->
<div class="modal fade" id="addBadgeModal" tabindex="-1" aria-labelledby="addBadgeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_badge">
                <div class="modal-header bg-light border-bottom-0 py-3">
                    <h5 class="modal-title fw-bold text-dark" id="addBadgeModalLabel">تعریف نشان مهارتی/آموزشی جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">نام نشان *</label>
                        <input type="text" name="name" class="form-control" placeholder="مثال: کتابخوان برتر، خلاق‌ترین دانش‌آموز" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">توضیحات دستاورد *</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="توضیحات و شرایط کسب نشان را بنویسید..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">نوع نشان</label>
                        <select name="type" class="form-select">
                            <option value="educational">آموزشی و تحصیلی</option>
                            <option value="behavioral">مهارتی، اخلاقی و انضباطی</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">تصویر اختصاصی نشان (آپلود فایل)</label>
                        <input type="file" name="icon_file" class="form-control" accept="image/*">
                        <div class="form-text text-muted small">در صورت عدم آپلود تصویر، می‌توانید از کلاس آیکون زیر استفاده کنید.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">نام آیکون پیش‌فرض (کلاس Bootstrap Icons)</label>
                        <input type="text" name="icon_class" class="form-control" value="trophy-fill" placeholder="مثال: trophy-fill, star-fill, award">
                        <div class="form-text text-muted small">لیست کامل آیکون‌ها در وبسایت <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a> قابل دسترسی است.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">ایجاد و ثبت نشان</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- مودال ویرایش نشان -->
<div class="modal fade" id="editBadgeModal" tabindex="-1" aria-labelledby="editBadgeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_badge">
                <input type="hidden" name="badge_id" id="edit_badge_id">
                <div class="modal-header bg-light border-bottom-0 py-3">
                    <h5 class="modal-title fw-bold text-dark" id="editBadgeModalLabel">ویرایش نشان مهارتی/آموزشی</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">نام نشان *</label>
                        <input type="text" name="name" id="edit_badge_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">توضیحات دستاورد *</label>
                        <textarea name="description" id="edit_badge_description" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">نوع نشان</label>
                        <select name="type" id="edit_badge_type" class="form-select">
                            <option value="educational">آموزشی و تحصیلی</option>
                            <option value="behavioral">مهارتی، اخلاقی و انضباطی</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">تصویر اختصاصی جدید (در صورت نیاز به تغییر)</label>
                        <input type="file" name="icon_file" class="form-control" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">نام آیکون پیش‌فرض (کلاس Bootstrap Icons)</label>
                        <input type="text" name="icon_class" id="edit_badge_icon_class" class="form-control" placeholder="مثال: trophy-fill, star-fill">
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- فرم پنهان برای حذف نشان -->
<form id="deleteBadgeForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete_badge">
    <input type="hidden" name="badge_id" id="delete_badge_id">
</form>

<script>
    function openEditBadgeModal(id, name, description, type, icon) {
        document.getElementById('edit_badge_id').value = id;
        document.getElementById('edit_badge_name').value = name;
        document.getElementById('edit_badge_description').value = description;
        document.getElementById('edit_badge_type').value = type;
        
        // اگر آیکون متنی بود کلاس آن را قرار بده
        if (icon.indexOf('uploads/') !== 0) {
            document.getElementById('edit_badge_icon_class').value = icon;
        } else {
            document.getElementById('edit_badge_icon_class').value = '';
        }

        const modalEl = document.getElementById('editBadgeModal');
        new bootstrap.Modal(modalEl).show();
    }

    function confirmDeleteBadge(id, name) {
        showCustomConfirm(
            'حذف نشان مهارتی',
            `آیا از حذف نشان «${name}» مطمئن هستید؟ این کار تمام نشان‌های داده شده به دانش‌آموزان از این نوع را نیز پاک خواهد کرد.`,
            'danger',
            function(confirmed) {
                if (confirmed) {
                    document.getElementById('delete_badge_id').value = id;
                    document.getElementById('deleteBadgeForm').submit();
                }
            }
        );
    }
</script>

<?php require_once '../includes/footer.php'; ?>
