<?php
/**
 * دفتر رادار انضباطی دانش‌آموزان با ولیدیشن بک‌انند
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error = '';
$success = '';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$studentsList = [];

try {
    // پیدا کردن شناسه معلم
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم ثبت نشده‌اید.");
    }

    // واکشی کلاس‌های این دبیر برای فیلتر
    $stmtClasses = $pdo->prepare("SELECT DISTINCT c.id, c.class_name 
        FROM class_teacher_course ctc
        JOIN classes c ON ctc.class_id = c.id
        WHERE ctc.teacher_id = ?");
    $stmtClasses->execute([$teacherId]);
    $myClasses = $stmtClasses->fetchAll();

} catch (PDOException $e) {
    die("خطا در لود اطلاعات کلاس‌های معلم: " . $e->getMessage());
}

// هندل کردن ثبت انضباطی جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_record') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $status = $_POST['status'] ?? 'excellent';
    $reason = trim($_POST['reason'] ?? '');

    // ولیدیشن سمت سرور بر اساس PRD:
    // "در صورتی که وضعیت روی «عالی» نباشد، نوشتن علت و یادداشت توضیحی برای والدین اجباری است"
    if ($student_id <= 0) {
        $error = 'لطفاً دانش‌آموز را انتخاب کنید.';
    } elseif ($status !== 'excellent' && empty($reason)) {
        $error = 'تأیید ناموفق: برای وضعیت‌های انضباطی غیر از «عالی»، نوشتن علت و توضیح برای والدین الزامی است.';
    } else {
        try {
            $stmtInsert = $pdo->prepare("INSERT INTO `discipline_records` (`student_id`, `teacher_id`, `status`, `reason`) 
                VALUES (?, ?, ?, ?)");
            $stmtInsert->execute([$student_id, $_SESSION['user_id'], $status, $status === 'excellent' ? ($reason ?: 'ندارد') : $reason]);
            $success = "وضعیت انضباطی دانش‌آموز با موفقیت ثبت شد و در کارتابل والدین قرار گرفت.";
        } catch (PDOException $e) {
            $error = "خطا در ثبت وضعیت انضباطی: " . $e->getMessage();
        }
    }
}

// واکشی دانش‌آموزان در صورت انتخاب کلاس
if ($class_id > 0) {
    try {
        $stmtStudents = $pdo->prepare("SELECT s.id, u.full_name FROM class_student cs
            JOIN students s ON cs.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE cs.class_id = ? AND u.status = 1
            ORDER BY u.full_name ASC");
        $stmtStudents->execute([$class_id]);
        $studentsList = $stmtStudents->fetchAll();
    } catch (PDOException $e) {
        $error = "خطا در بارگذاری دانش‌آموزان کلاس: " . $e->getMessage();
    }
}

// واکشی آرشیو تذکرات انضباطی صادر شده توسط این دبیر
try {
    $stmtArchive = $pdo->prepare("SELECT dr.*, u.full_name as student_name, c.class_name 
        FROM discipline_records dr
        JOIN students s ON dr.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN class_student cs ON s.id = cs.student_id
        JOIN classes c ON cs.class_id = c.id
        WHERE dr.teacher_id = ?
        ORDER BY dr.created_at DESC");
    $stmtArchive->execute([$_SESSION['user_id']]);
    $disciplineArchive = $stmtArchive->fetchAll();
} catch (PDOException $e) {
    die("خطا در لود آرشیو انضباطی: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-25 text-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- سربرگ فیلتر کلاس -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label class="form-label mb-0 me-2 d-inline-block fw-bold">کلاس دانش‌آموز:</label>
                    <select name="class_id" class="form-select d-inline-block w-auto" required onchange="this.form.submit()">
                        <option value="">-- انتخاب کلاس درس --</option>
                        <?php foreach ($myClasses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $class_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($class_id <= 0): ?>
        <div class="card border-0 shadow-sm rounded-3 py-5 text-center">
            <div class="card-body">
                <i class="bi bi-shield-exclamation fs-1 text-secondary mb-3"></i>
                <h5 class="fw-bold">بخش دفتر رادار انضباطی</h5>
                <p class="text-secondary small">لطفاً ابتدا کلاس دانش‌آموز مورد نظر را جهت ورود به سیستم انتخاب کنید.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- فرم ثبت وضعیت انضباطی جدید -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-bookmark-plus text-danger me-1"></i> ثبت وضعیت انضباطی</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="disciplineForm" onsubmit="return validateDisciplineForm()">
                            <input type="hidden" name="action" value="add_record">
                            
                            <div class="mb-3">
                                <label class="form-label">انتخاب دانش‌آموز *</label>
                                <select name="student_id" class="form-select" required>
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($studentsList as $st): ?>
                                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">وضعیت انضباطی دانش‌آموز *</label>
                                <select name="status" id="disciplineStatusSelect" class="form-select" onchange="checkStatusReasonRequirement()" required>
                                    <option value="excellent">عالی</option>
                                    <option value="good">خوب</option>
                                    <option value="average">متوسط</option>
                                    <option value="poor">ضعیف</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label" id="reasonLabel">یادداشت انضباطی و علت (توضیح برای والدین)</label>
                                <textarea name="reason" id="disciplineReasonInput" class="form-control" rows="5" placeholder="مثال: عدم تمرکز در کلاس درس یا تأخیر ورود..."></textarea>
                                <small class="text-danger d-none" id="reasonAlert">نوشتن علت برای وضعیت غیر از «عالی» اجباری است!</small>
                            </div>
                            
                            <button type="submit" class="btn btn-danger w-100 fw-bold">ثبت وضعیت رادار انضباطی</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- آرشیو ثبت شده‌های اخیر دبیر -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">آرشیو وضعیت‌های ثبت شده</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($disciplineArchive)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-shield-check fs-1 text-success mb-2"></i>
                                <p class="text-secondary mb-0">هیچ مورد تذکر یا گزارش انضباطی توسط شما ثبت نشده است.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table custom-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>دانش‌آموز</th>
                                            <th>کلاس</th>
                                            <th>وضعیت</th>
                                            <th>تاریخ ثبت</th>
                                            <th>توضیحات ولی</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($disciplineArchive as $arch): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($arch['student_name']) ?></td>
                                                <td><?= htmlspecialchars($arch['class_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= get_status_class($arch['status']) ?>-subtle text-<?= get_status_class($arch['status']) ?> border">
                                                        <?php 
                                                            switch($arch['status']) {
                                                                case 'excellent': echo 'عالی'; break;
                                                                case 'good': echo 'خوب'; break;
                                                                case 'average': echo 'متوسط'; break;
                                                                case 'poor': echo 'ضعیف'; break;
                                                            }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?= to_shamsi($arch['created_at']) ?></td>
                                                <td>
                                                    <span class="text-secondary small d-inline-block text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($arch['reason']) ?>">
                                                        <?= htmlspecialchars($arch['reason']) ?>
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
    <?php endif; ?>
</div>

<script>
    // چک کردن تغییر وضعیت و اجباری کردن علت در فرانت‌انند
    function checkStatusReasonRequirement() {
        const status = document.getElementById('disciplineStatusSelect').value;
        const reasonLabel = document.getElementById('reasonLabel');
        const reasonInput = document.getElementById('disciplineReasonInput');

        if (status !== 'excellent') {
            reasonLabel.innerHTML = 'علت وضعیت و توضیح برای والدین <span class="text-danger">* (اجباری)</span>';
            reasonInput.setAttribute('required', 'required');
        } else {
            reasonLabel.innerText = 'یادداشت انضباطی و علت (توضیح برای والدین)';
            reasonInput.removeAttribute('required');
        }
    }

    // ولیدیشن فرم فرانت‌انند با جاوااسکریپت جهت اطمینان
    function validateDisciplineForm() {
        const status = document.getElementById('disciplineStatusSelect').value;
        const reason = document.getElementById('disciplineReasonInput').value.trim();
        const alert = document.getElementById('reasonAlert');

        if (status !== 'excellent' && reason === '') {
            alert.classList.remove('d-none');
            return false;
        }
        alert.classList.add('d-none');
        return true;
    }
</script>

<?php require_once '../includes/footer.php'; ?>
