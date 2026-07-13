<?php
/**
 * تعریف ساختارهای کلان مدرسه (دروس، دوره‌ها و ترم‌ها)
 */

require_once '../includes/header.php';
check_auth('admin');

$error = '';
$success = '';

// ثبت درس جدید در سیستم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    $course_name = trim($_POST['course_name'] ?? '');
    $grade_level = (int)($_POST['grade_level'] ?? 0);

    if (empty($course_name) || $grade_level <= 0) {
        $error = 'ثبت نام درس و پایه تحصیلی هدف الزامی است.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO `courses` (`course_name`, `grade_level`) VALUES (?, ?)");
            $stmt->execute([$course_name, $grade_level]);
            $success = "درس جدید «{$course_name}» با موفقیت در ساختار آموزشی ثبت گردید.";
        } catch (PDOException $e) {
            $error = "خطا در ثبت درس: " . $e->getMessage();
        }
    }
}

// واکشی کل دروس فعال
try {
    $coursesList = $pdo->query("SELECT * FROM courses ORDER BY grade_level ASC, course_name ASC")->fetchAll();
} catch (PDOException $e) {
    die("خطا در لود دروس: " . $e->getMessage());
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
        <!-- فرم ثبت درس جدید -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-plus text-primary me-1"></i> تعریف درس جدید در سیستم</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_course">
                        <div class="mb-3">
                            <label class="form-label">نام درس</label>
                            <input type="text" name="course_name" class="form-control" placeholder="مثال: عربی، علوم تجربی، زبان انگلیسی" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">پایه تحصیلی هدف</label>
                            <select name="grade_level" class="form-select" required>
                                <option value="7">پایه هفتم</option>
                                <option value="8">پایه هشتم</option>
                                <option value="9">پایه نهم</option>
                                <option value="10">پایه دهم</option>
                                <option value="11">پایه یازدهم</option>
                                <option value="12">پایه دوازدهم</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">ثبت درس جدید</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- جدول آرشیو دروس مدرسه -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">کل دروس ثبت شده در مدرسه</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($coursesList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-x fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">درسی ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>شناسه درس</th>
                                        <th>نام درس</th>
                                        <th>پایه تحصیلی هدف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coursesList as $co): ?>
                                        <tr>
                                            <td><code>#<?= $co['id'] ?></code></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($co['course_name']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary">پایه <?= $co['grade_level'] ?></span>
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
