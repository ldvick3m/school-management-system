<?php
/**
 * مدیریت کلاس‌ها، تخصیص دروس/دبیران و ثبت‌نام دانش‌آموزان
 */

require_once '../includes/header.php';
check_auth(['operator', 'admin']);

$error = '';
$success = '';

// هندل کردن فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // ۱. ایجاد کلاس جدید
        if ($action === 'create_class') {
            $class_name = trim($_POST['class_name'] ?? '');
            $grade_level = (int)($_POST['grade_level'] ?? 0);

            if (empty($class_name) || $grade_level <= 0) {
                $error = 'لطفاً نام کلاس و پایه تحصیلی را وارد کنید.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `classes` (`class_name`, `grade_level`) VALUES (?, ?)");
                    $stmt->execute([$class_name, $grade_level]);
                    $success = "کلاس جدید با موفقیت ایجاد شد.";
                } catch (PDOException $e) {
                    $error = "خطا در ایجاد کلاس: " . $e->getMessage();
                }
            }
        }
        
        // ۲. تخصیص دبیر به کلاس و درس
        elseif ($action === 'assign_teacher') {
            $class_id = (int)($_POST['class_id'] ?? 0);
            $teacher_id = (int)($_POST['teacher_id'] ?? 0);
            $course_id = (int)($_POST['course_id'] ?? 0);

            if ($class_id <= 0 || $teacher_id <= 0 || $course_id <= 0) {
                $error = 'لطفاً کلاس، درس و دبیر را انتخاب کنید.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `class_teacher_course` (`class_id`, `teacher_id`, `course_id`) VALUES (?, ?, ?)");
                    $stmt->execute([$class_id, $teacher_id, $course_id]);
                    $success = "تخصیص درس و دبیر با موفقیت ثبت شد.";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "این تخصیص (این دبیر و درس برای این کلاس) قبلاً ثبت شده است.";
                    } else {
                        $error = "خطا در تخصیص: " . $e->getMessage();
                    }
                }
            }
        }

        // ۳. ثبت‌نام دانش‌آموز در کلاس
        elseif ($action === 'enroll_student') {
            $class_id = (int)($_POST['class_id'] ?? 0);
            $student_id = (int)($_POST['student_id'] ?? 0);

            if ($class_id <= 0 || $student_id <= 0) {
                $error = 'لطفاً کلاس و دانش‌آموز را انتخاب کنید.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `class_student` (`class_id`, `student_id`) VALUES (?, ?)");
                    $stmt->execute([$class_id, $student_id]);
                    $success = "دانش‌آموز با موفقیت در کلاس ثبت‌نام شد.";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "این دانش‌آموز قبلاً در این کلاس عضو شده است.";
                    } else {
                        $error = "خطا در ثبت‌نام دانش‌آموز: " . $e->getMessage();
                    }
                }
            }
        }

        // ۴. حذف ایمن و مشروط کلاس
        elseif ($action === 'delete_class') {
            $class_id = (int)($_POST['class_id'] ?? 0);
            $delete_mode = $_POST['delete_mode'] ?? 'orphan'; // cascade or orphan

            if ($class_id <= 0) {
                $error = 'کلاس مورد نظر یافت نشد.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // پیدا کردن تخصیص‌های کلاس
                    $stmtAlloc = $pdo->prepare("SELECT id FROM class_teacher_course WHERE class_id = ?");
                    $stmtAlloc->execute([$class_id]);
                    $allocations = $stmtAlloc->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($allocations)) {
                        $allocList = implode(',', $allocations);
                        
                        if ($delete_mode === 'cascade') {
                            // حذف کامل مباحث و منابع متصل به این تخصیص‌ها
                            // در دیتابیس به خاطر کلید خارجی resources خودکار با حذف topics پاک می‌شوند.
                            // ما فقط باید فایل‌های فیزیکی را هم پاک کنیم (در پروژه‌های واقعی)
                            $pdo->exec("DELETE FROM `topics` WHERE `class_teacher_course_id` IN ($allocList)");
                        } else {
                            // در حالت orphan، مباحث به NULL تخصیص داده می‌شوند. 
                            // این مورد در دیتابیس به صورت خودکار با ON DELETE SET NULL انجام می‌شود.
                        }
                    }

                    // حذف کلاس (که باعث حذف خودکار رکوردهای class_student و class_teacher_course می‌شود)
                    $stmtDel = $pdo->prepare("DELETE FROM `classes` WHERE `id` = ?");
                    $stmtDel->execute([$class_id]);

                    $pdo->commit();
                    $success = "کلاس مورد نظر با موفقیت حذف شد.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "خطا در حذف کلاس: " . $e->getMessage();
                }
            }
        }
    }
}

// واکشی داده‌ها برای فرم‌ها و جداول
try {
    // لیست کلاس‌ها با تعداد دانش‌آموزان و دبیران اختصاص یافته
    $classes = $pdo->query("SELECT c.*, 
        (SELECT COUNT(*) FROM class_student WHERE class_id = c.id) as students_count,
        (SELECT COUNT(*) FROM class_teacher_course WHERE class_id = c.id) as allocations_count
        FROM classes c ORDER BY c.grade_level ASC, c.class_name ASC")->fetchAll();

    // لیست معلمان
    $teachers = $pdo->query("SELECT t.id, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.status = 1")->fetchAll();
    
    // لیست دروس
    $courses = $pdo->query("SELECT * FROM courses ORDER BY grade_level ASC")->fetchAll();
    
    // لیست دانش‌آموزان بدون کلاس یا کل دانش‌آموزان
    $students = $pdo->query("SELECT s.id, u.full_name, s.grade_level FROM students s JOIN users u ON s.user_id = u.id WHERE u.status = 1")->fetchAll();

    // جزئیات منابع هر کلاس برای نمایش در مودال حذف (AJAX یا پیش‌نمایش)
    // برای سادگی در لود اولیه، آمار کل منابع هر کلاس را به صورت آرایه آماده می‌کنیم
    $classResourcesInfo = [];
    foreach ($classes as $c) {
        $stmtRes = $pdo->prepare("SELECT r.resource_name, r.resource_type, t.topic_title 
            FROM resources r
            JOIN topics t ON r.topic_id = t.id
            JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
            WHERE ctc.class_id = ?");
        $stmtRes->execute([$c['id']]);
        $classResourcesInfo[$c['id']] = $stmtRes->fetchAll();
    }

} catch (PDOException $e) {
    die("خطا در لود اطلاعات کلاس‌ها: " . $e->getMessage());
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

    <!-- ردیف بالایی: سه فرم عملیاتی در سه ستون کنار هم -->
    <div class="row g-4 mb-4">
        <!-- ۱. فرم ایجاد کلاس -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-house-add-fill text-primary me-1"></i> ایجاد کلاس جدید</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <form method="POST" action="" class="h-100 d-flex flex-column justify-content-between">
                        <input type="hidden" name="action" value="create_class">
                        <div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">نام کلاس</label>
                                <input type="text" name="class_name" class="form-control" placeholder="مثال: هفتم الف، دهم تجربی ۲" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">پایه تحصیلی</label>
                                <select name="grade_level" class="form-select" required>
                                    <option value="7">پایه هفتم</option>
                                    <option value="8">پایه هشتم</option>
                                    <option value="9">پایه نهم</option>
                                    <option value="10">پایه دهم</option>
                                    <option value="11">پایه یازدهم</option>
                                    <option value="12">پایه دوازدهم</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold mt-3">ثبت کلاس جدید</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ۲. فرم تخصیص درس و معلم -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-workspace text-success me-1"></i> تخصیص درس و دبیر</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <form method="POST" action="" class="h-100 d-flex flex-column justify-content-between">
                        <input type="hidden" name="action" value="assign_teacher">
                        <div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">انتخاب کلاس</label>
                                <select name="class_id" class="form-select" required>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">انتخاب درس</label>
                                <select name="course_id" class="form-select" required>
                                    <?php foreach ($courses as $co): ?>
                                        <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['course_name']) ?> (پایه <?= $co['grade_level'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">انتخاب دبیر</label>
                                <select name="teacher_id" class="form-select" required>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold text-white mt-3">ثبت تخصیص جدید</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ۳. ثبت‌نام دانش‌آموز در کلاس -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-check-fill text-info me-1"></i> ثبت‌نام دانش‌آموز در کلاس</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <form method="POST" action="" class="h-100 d-flex flex-column justify-content-between">
                        <input type="hidden" name="action" value="enroll_student">
                        <div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">انتخاب کلاس</label>
                                <select name="class_id" class="form-select" required>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">انتخاب دانش‌آموز</label>
                                <select name="student_id" class="form-select" required>
                                    <?php foreach ($students as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (پایه <?= $s['grade_level'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-info w-100 fw-bold text-white mt-3">افزودن به کلاس</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ردیف پایینی: لیست کلاس‌های تعریف‌شده با عرض کامل ۱۰۰٪ -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">لیست کلاس‌های تعریف شده</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($classes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-house-gear fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">کلاسی در سامانه ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table custom-table mb-0">
                                <thead>
                                    <tr>
                                        <th>نام کلاس</th>
                                        <th>پایه تحصیلی</th>
                                        <th>دانش‌آموزان ثبت‌نامی</th>
                                        <th>دروس فعال</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $c): ?>
                                        <tr>
                                            <td><a href="class_details.php?class_id=<?= $c['id'] ?>" class="fw-bold text-decoration-none text-primary"><?= htmlspecialchars($c['class_name']) ?></a></td>
                                            <td>پایه <?= $c['grade_level'] ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $c['students_count'] ?> دانش‌آموز</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle"><?= $c['allocations_count'] ?> درس/معلم</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-outline-warning btn-sm fw-bold me-1 text-dark" onclick="showTopStudents(<?= $c['id'] ?>, '<?= htmlspecialchars($c['class_name']) ?>')">
                                                    <i class="bi bi-trophy-fill text-warning me-1"></i> برترین‌های کلاس
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="confirmDeleteClass(<?= $c['id'] ?>, '<?= htmlspecialchars($c['class_name']) ?>')">
                                                    <i class="bi bi-trash3-fill"></i> حذف کلاس
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

<!-- مودال حذف ایمن و مشروط کلاس -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_class">
                <input type="hidden" name="class_id" id="delete_class_id">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold" id="deleteClassModalLabel">⚠️ هشدار حذف ایمن کلاس</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <p class="text-danger fw-bold fs-6">شما در حال حذف کلاس «<span id="delete_class_name"></span>» هستید!</p>
                    <p class="small text-secondary">حذف کلاس باعث حذف پرونده‌های تخصیص دبیر و دانش‌آموزان متصل خواهد شد.</p>
                    
                    <!-- بخش منابع مرتبط کشف شده -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <label class="fw-bold mb-2 small text-secondary">منابع آموزشی مرتبط کشف‌شده:</label>
                        <div id="deleteClassResourcesList" class="small text-dark" style="max-height: 120px; overflow-y: auto;">
                            <!-- با جاوااسکریپت پر می‌شود -->
                        </div>
                    </div>

                    <!-- سوال در مورد سرنوشت منابع -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">سرنوشت فایل‌ها و مباحث آموزشی مرتبط:</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="delete_mode" id="modeOrphan" value="orphan" checked>
                            <label class="form-check-label small" for="modeOrphan">
                                <strong>نگهداری به عنوان منابع عمومی (فاقد کلاس - Orphan)</strong><br>
                                <span class="text-secondary text-xs">منابع باقی می‌مانند تا سایر دبیران برای سایر کلاس‌ها استفاده کنند.</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delete_mode" id="modeCascade" value="cascade">
                            <label class="form-check-label small" for="modeCascade">
                                <strong>حذف کامل کلاس و تمامی مباحث/منابع درسی مرتبط</strong><br>
                                <span class="text-secondary text-xs">تمام مباحث و فایل‌های آموزشی متصل به این کلاس به صورت دائمی حذف می‌شوند.</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-danger fw-bold">حذف قطعی کلاس</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // اطلاعات منابع هر کلاس جهت رندر پویای هشدار حذف
    const classResourcesData = <?= json_encode($classResourcesInfo) ?>;

    function confirmDeleteClass(classId, className) {
        document.getElementById('delete_class_id').value = classId;
        document.getElementById('delete_class_name').innerText = className;
        
        const resourcesList = document.getElementById('deleteClassResourcesList');
        resourcesList.innerHTML = '';
        
        const resources = classResourcesData[classId] || [];
        
        if (resources.length === 0) {
            resourcesList.innerHTML = '<span class="text-success small">هیچ منبع یا مبحث آموزشی برای این کلاس ثبت نشده است. (حذف ایمن است)</span>';
        } else {
            const ul = document.createElement('ul');
            ul.className = 'ps-3 mb-0';
            resources.forEach(r => {
                const li = document.createElement('li');
                li.innerHTML = `<strong>${r.topic_title}</strong>: فایل ${r.resource_name} (${r.resource_type})`;
                ul.appendChild(li);
            });
            resourcesList.appendChild(ul);
        }
        
        // نمایش مودال حذف
        const myModal = new bootstrap.Modal(document.getElementById('deleteClassModal'));
        myModal.show();
    }
</script>

<?php require_once '../includes/footer.php'; ?>
