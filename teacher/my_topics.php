<?php
/**
 * لیست کامل مباحث منتشرشده توسط دبیر + ویرایش مبحث
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error   = '';
$success = '';

// جستجو و فیلتر
$search       = trim($_GET['search'] ?? '');
$filter_class = (int)($_GET['class_id'] ?? 0);

try {
    // پیدا کردن شناسه معلم
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم ثبت نشده‌اید.");
    }

    // واکشی کلاس‌های مربوط به دبیر جهت فیلتر
    $stmtClasses = $pdo->prepare("SELECT DISTINCT c.id, c.class_name
        FROM class_teacher_course ctc
        JOIN classes c ON ctc.class_id = c.id
        WHERE ctc.teacher_id = ?
        ORDER BY c.class_name ASC");
    $stmtClasses->execute([$teacherId]);
    $myClasses = $stmtClasses->fetchAll();

    // واکشی تخصیص‌های دبیر برای منوی درپشه ویرایش
    $stmtAlloc = $pdo->prepare("SELECT ctc.id, c.class_name, co.course_name
        FROM class_teacher_course ctc
        JOIN classes c ON ctc.class_id = c.id
        JOIN courses co ON ctc.course_id = co.id
        WHERE ctc.teacher_id = ?");
    $stmtAlloc->execute([$teacherId]);
    $allocations = $stmtAlloc->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری اطلاعات: " . $e->getMessage());
}

// ====== هندلر ویرایش مبحث ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_topic') {
    $edit_topic_id           = (int)($_POST['topic_id'] ?? 0);
    $edit_ctc_id             = (int)($_POST['class_teacher_course_id'] ?? 0);
    $edit_title              = trim($_POST['topic_title'] ?? '');
    $edit_description        = trim($_POST['topic_description'] ?? '');
    $remove_video            = isset($_POST['remove_video']);

    if ($edit_topic_id <= 0 || empty($edit_title) || $edit_ctc_id <= 0) {
        $error = 'عنوان مبحث و انتخاب کلاس/درس الزامی است.';
    } else {
        try {
            // بررسی اینکه مبحث واقعاً متعلق به همین دبیر است
            $stmtOwn = $pdo->prepare("SELECT t.id, t.video_path FROM topics t
                JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
                WHERE t.id = ? AND ctc.teacher_id = ?");
            $stmtOwn->execute([$edit_topic_id, $teacherId]);
            $ownedTopic = $stmtOwn->fetch();

            if (!$ownedTopic) {
                $error = 'دسترسی غیرمجاز.';
            } else {
                $new_video_path = $ownedTopic['video_path'];

                // حذف ویدیوی قبلی در صورت درخواست
                if ($remove_video && $new_video_path) {
                    $full_path = '../' . $new_video_path;
                    if (file_exists($full_path)) {
                        unlink($full_path);
                    }
                    $new_video_path = null;
                }

                // آپلود ویدیوی جدید در صورت ارسال
                if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                    $video_file = $_FILES['video_file'];
                    $allowed    = ['mp4', 'webm', 'ogg', 'mov'];
                    $ext        = strtolower(pathinfo($video_file['name'], PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowed)) {
                        $error = 'فرمت ویدیوی ارسالی معتبر نیست.';
                    } else {
                        $uploadDir = '../uploads/videos/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                        // حذف ویدیوی قبلی در صورت وجود ویدیوی جدید
                        if ($new_video_path && file_exists('../' . $new_video_path)) {
                            unlink('../' . $new_video_path);
                        }

                        $filename      = uniqid('vid_') . '.' . $ext;
                        $targetPath    = $uploadDir . $filename;
                        if (move_uploaded_file($video_file['tmp_name'], $targetPath)) {
                            $new_video_path = 'uploads/videos/' . $filename;
                        } else {
                            $error = 'خطا در بارگذاری ویدیو بر روی سرور.';
                        }
                    }
                }

                if (empty($error)) {
                    $stmtUpd = $pdo->prepare("UPDATE topics SET
                        class_teacher_course_id = ?,
                        topic_title             = ?,
                        topic_description       = ?,
                        video_path              = ?
                        WHERE id = ?");
                    $stmtUpd->execute([$edit_ctc_id, $edit_title, $edit_description, $new_video_path, $edit_topic_id]);
                    $success = 'مبحث با موفقیت ویرایش شد.';
                }
            }
        } catch (PDOException $e) {
            $error = 'خطا در ویرایش مبحث: ' . $e->getMessage();
        }
    }
}

// ====== واکشی لیست مباحث ======
try {
    $sql = "SELECT t.id, t.topic_title, t.topic_description, t.video_path, t.created_at,
                   c.class_name, co.course_name, ctc.id as ctc_id,
                   (SELECT COUNT(*) FROM resources r WHERE r.topic_id = t.id) as resource_count,
                   (SELECT COUNT(*) FROM homeworks hw WHERE hw.topic_id = t.id) as homework_count
            FROM topics t
            JOIN class_teacher_course ctc ON t.class_teacher_course_id = ctc.id
            JOIN classes c ON ctc.class_id = c.id
            JOIN courses co ON ctc.course_id = co.id
            WHERE ctc.teacher_id = ?";

    $params = [$teacherId];

    if (!empty($search)) {
        $sql .= " AND t.topic_title LIKE ?";
        $params[] = "%$search%";
    }

    if ($filter_class > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $filter_class;
    }

    $sql .= " ORDER BY t.created_at DESC";

    $stmtTopics = $pdo->prepare($sql);
    $stmtTopics->execute($params);
    $topicsList = $stmtTopics->fetchAll();

    $totalCount   = count($topicsList);
    $withVideo    = count(array_filter($topicsList, fn($t) => !empty($t['video_path'])));
    $withHomework = count(array_filter($topicsList, fn($t) => $t['homework_count'] > 0));

} catch (PDOException $e) {
    die("خطا در بارگذاری مباحث: " . $e->getMessage());
}
?>

<div class="container-fluid">

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-25 text-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- هدر صفحه -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-collection-play-fill text-primary me-2"></i>مباحث منتشرشده من</h4>
            <p class="text-secondary small mb-0">لیست کامل همه مباحثی که تاکنون ایجاد و منتشر کرده‌اید</p>
        </div>
        <a href="course_creator.php" class="btn btn-primary fw-bold">
            <i class="bi bi-plus-lg me-1"></i>ایجاد مبحث جدید
        </a>
    </div>

    <!-- کارت‌های آمار -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 text-center py-3">
                <div class="fs-2 fw-bold text-primary"><?= $totalCount ?></div>
                <div class="text-secondary small">کل مباحث منتشرشده</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 text-center py-3">
                <div class="fs-2 fw-bold text-success"><?= $withVideo ?></div>
                <div class="text-secondary small">مبحث با ویدیوی آموزشی</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 text-center py-3">
                <div class="fs-2 fw-bold text-warning"><?= $withHomework ?></div>
                <div class="text-secondary small">مبحث با تکلیف تعریف‌شده</div>
            </div>
        </div>
    </div>

    <!-- فیلتر و جستجو -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold">جستجو در عنوان مباحث</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="جستجو..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">فیلتر بر اساس کلاس</label>
                    <select name="class_id" class="form-select">
                        <option value="0">همه کلاس‌ها</option>
                        <?php foreach ($myClasses as $cls): ?>
                            <option value="<?= $cls['id'] ?>" <?= $filter_class == $cls['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel-fill me-1"></i>اعمال فیلتر</button>
                    <?php if ($search || $filter_class): ?>
                        <a href="my_topics.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول مباحث -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-list-ul text-secondary me-1"></i>لیست مباحث
                <?php if ($search || $filter_class): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2 small"><?= $totalCount ?> نتیجه</span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($topicsList)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-excel fs-1 text-secondary mb-3 d-block"></i>
                    <?php if ($search || $filter_class): ?>
                        <p class="text-secondary">نتیجه‌ای با این فیلتر یافت نشد.</p>
                        <a href="my_topics.php" class="btn btn-outline-primary btn-sm">حذف فیلتر</a>
                    <?php else: ?>
                        <p class="text-secondary">هنوز هیچ مبحثی ایجاد نکرده‌اید.</p>
                        <a href="course_creator.php" class="btn btn-primary btn-sm">ایجاد اولین مبحث</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table custom-table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th style="width:35%">عنوان مبحث</th>
                                <th>کلاس / درس</th>
                                <th class="text-center">ویدیو</th>
                                <th class="text-center">منابع</th>
                                <th class="text-center">تکالیف</th>
                                <th>تاریخ انتشار</th>
                                <th class="text-center">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topicsList as $topic): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($topic['topic_title']) ?></div>
                                        <?php if (!empty($topic['topic_description'])): ?>
                                            <small class="text-secondary"><?= mb_substr(htmlspecialchars($topic['topic_description']), 0, 60) ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($topic['course_name']) ?></span><br>
                                        <small class="text-secondary"><?= htmlspecialchars($topic['class_name']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($topic['video_path']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-play-btn-fill"></i> بارگذاری شده
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">فاقد ویدیو</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($topic['resource_count'] > 0): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle"><?= $topic['resource_count'] ?> فایل</span>
                                        <?php else: ?>
                                            <span class="text-secondary small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($topic['homework_count'] > 0): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= $topic['homework_count'] ?> تکلیف</span>
                                        <?php else: ?>
                                            <span class="text-secondary small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-secondary"><?= to_shamsi($topic['created_at']) ?></td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <!-- دکمه ویرایش -->
                                            <button type="button"
                                                class="btn btn-outline-warning btn-sm btn-edit-topic"
                                                title="ویرایش مبحث"
                                                data-id="<?= $topic['id'] ?>"
                                                data-ctc="<?= $topic['ctc_id'] ?>"
                                                data-title="<?= htmlspecialchars($topic['topic_title'], ENT_QUOTES) ?>"
                                                data-desc="<?= htmlspecialchars($topic['topic_description'] ?? '', ENT_QUOTES) ?>"
                                                data-video="<?= $topic['video_path'] ? '1' : '0' ?>"
                                                data-videoname="<?= htmlspecialchars(basename($topic['video_path'] ?? ''), ENT_QUOTES) ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editTopicModal">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <!-- دکمه منابع -->
                                            <a href="resources.php?topic_id=<?= $topic['id'] ?>" class="btn btn-outline-success btn-sm" title="مدیریت منابع و فایل‌های درس">
                                                <i class="bi bi-folder-plus"></i>
                                            </a>
                                        </div>
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

<!-- ===== مودال ویرایش مبحث ===== -->
<div class="modal fade" id="editTopicModal" tabindex="-1" aria-labelledby="editTopicModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="my_topics.php<?= ($search || $filter_class) ? '?' . http_build_query(['search' => $search, 'class_id' => $filter_class]) : '' ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_topic">
                <input type="hidden" name="topic_id" id="editTopicId">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editTopicModalLabel">
                        <i class="bi bi-pencil-square text-warning me-2"></i>ویرایش مبحث درسی
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3">
                    <!-- انتخاب کلاس/درس -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">کلاس و درس <span class="text-danger">*</span></label>
                        <select name="class_teacher_course_id" id="editCtcId" class="form-select" required>
                            <?php foreach ($allocations as $alloc): ?>
                                <option value="<?= $alloc['id'] ?>">
                                    <?= htmlspecialchars($alloc['class_name']) ?> — <?= htmlspecialchars($alloc['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- عنوان مبحث -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">عنوان مبحث <span class="text-danger">*</span></label>
                        <input type="text" name="topic_title" id="editTopicTitle" class="form-control" required>
                    </div>

                    <!-- توضیحات -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">توضیحات / تکالیف</label>
                        <textarea name="topic_description" id="editTopicDesc" class="form-control" rows="4"></textarea>
                    </div>

                    <!-- وضعیت ویدیو -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">ویدیوی آموزشی</label>
                        <div id="currentVideoInfo" class="d-none alert alert-success-subtle border border-success-subtle rounded-3 p-3 mb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-success small">
                                    <i class="bi bi-play-btn-fill me-1"></i>
                                    ویدیوی فعلی: <strong id="currentVideoName"></strong>
                                </span>
                                <div class="form-check mb-0">
                                    <input type="checkbox" name="remove_video" id="removeVideoCheck" class="form-check-input" value="1">
                                    <label class="form-check-label text-danger small fw-bold" for="removeVideoCheck">
                                        <i class="bi bi-trash3-fill"></i> حذف ویدیو
                                    </label>
                                </div>
                            </div>
                        </div>
                        <input type="file" name="video_file" id="editVideoFile" class="form-control" accept="video/mp4,video/mov,video/webm">
                        <small class="text-secondary">برای جایگزینی، ویدیوی جدید را انتخاب کنید. فرمت‌های مجاز: MP4, MOV, WebM</small>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark">
                        <i class="bi bi-check-lg me-1"></i>ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// پر کردن مودال ویرایش با داده‌های ردیف انتخابی
document.querySelectorAll('.btn-edit-topic').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('editTopicId').value    = this.dataset.id;
        document.getElementById('editTopicTitle').value = this.dataset.title;
        document.getElementById('editTopicDesc').value  = this.dataset.desc;

        // انتخاب کلاس/درس صحیح در دراپ‌داون
        var ctcSelect = document.getElementById('editCtcId');
        for (var i = 0; i < ctcSelect.options.length; i++) {
            if (ctcSelect.options[i].value == this.dataset.ctc) {
                ctcSelect.selectedIndex = i;
                break;
            }
        }

        // نمایش وضعیت ویدیوی فعلی
        var videoInfo = document.getElementById('currentVideoInfo');
        if (this.dataset.video === '1') {
            videoInfo.classList.remove('d-none');
            document.getElementById('currentVideoName').textContent = this.dataset.videoname || '—';
        } else {
            videoInfo.classList.add('d-none');
        }

        // ریست فیلد آپلود ویدیوی جدید
        document.getElementById('editVideoFile').value = '';
        document.getElementById('removeVideoCheck') && (document.getElementById('removeVideoCheck').checked = false);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
