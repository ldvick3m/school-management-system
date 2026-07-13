<?php
/**
 * مرکز اعلانات هوشمند و ارسال با فیلترهای پیشرفته
 */

require_once '../includes/header.php';
check_auth(['operator', 'admin']);

$error = '';
$success = '';

// ثبت اطلاعیه جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target_role = $_POST['target_role'] ?? 'all';

    if (empty($title) || empty($content)) {
        $error = 'لطفاً عنوان و متن اطلاعیه را وارد نمایید.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO `announcements` (`title`, `content`, `target_role`) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $target_role]);
            $success = "اطلاعیه جدید با موفقیت ثبت و ارسال شد.";
        } catch (PDOException $e) {
            $error = "خطا در ثبت اطلاعیه: " . $e->getMessage();
        }
    }
}

// واکشی اطلاعیه‌های قبلی
try {
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعیه‌ها: " . $e->getMessage());
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
        <!-- فرم ایجاد اطلاعیه جدید -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-megaphone-fill text-primary me-1"></i> ارسال اطلاعیه جدید</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">عنوان اطلاعیه</label>
                            <input type="text" name="title" class="form-control" placeholder="مثال: برگزاری امتحانات مستمر نوبت دوم" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">گیرندگان هدف (فیلتر هوشمند)</label>
                            <select name="target_role" class="form-select" required>
                                <option value="all">کل مدرسه (همه کاربران)</option>
                                <option value="teacher">فقط دبیران</option>
                                <option value="parent">فقط اولیا</option>
                                <option value="student">فقط دانش‌آموزان</option>
                                <option value="grade_7">دانش‌آموزان پایه هفتم</option>
                                <option value="grade_8">دانش‌آموزان پایه هشتم</option>
                                <option value="grade_9">دانش‌آموزان پایه نهم</option>
                                <option value="parents_overdue_homework">والدین دانش‌آموزان دارای تکالیف معوقه</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">متن اطلاعیه</label>
                            <textarea name="content" class="form-control" rows="6" placeholder="متن کامل اطلاعیه را بنویسید..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 fw-bold">انتشار و ارسال اطلاعیه</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- آرشیو اعلانات منتشر شده -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">آرشیو اطلاعیه‌های منتشر شده</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-megaphone fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">هنوز هیچ اطلاعیه‌ای ارسال نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>عنوان اطلاعیه</th>
                                        <th>فیلتر گیرندگان</th>
                                        <th>تاریخ انتشار</th>
                                        <th>متن کوتاه</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $ann): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($ann['title']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                    <?php 
                                                        switch($ann['target_role']) {
                                                            case 'all': echo 'عمومی'; break;
                                                            case 'teacher': echo 'دبیران'; break;
                                                            case 'parent': echo 'اولیا'; break;
                                                            case 'student': echo 'دانش‌آموزان'; break;
                                                            case 'grade_7': echo 'پایه هفتم'; break;
                                                            case 'grade_8': echo 'پایه هشتم'; break;
                                                            case 'grade_9': echo 'پایه نهم'; break;
                                                            case 'parents_overdue_homework': echo 'والدین تکالیف معوقه'; break;
                                                            default: echo $ann['target_role'];
                                                        }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?= to_shamsi($ann['created_at']) ?></td>
                                            <td>
                                                <span class="text-secondary small d-inline-block text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($ann['content']) ?>">
                                                    <?= htmlspecialchars($ann['content']) ?>
                                                </span>
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
