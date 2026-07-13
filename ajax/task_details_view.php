<?php
/**
 * دریافت جزئیات تسک و گفتگوهای آن برای نمایش در مودال تسک
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">شما وارد سیستم نشده‌اید.</div>';
    exit;
}

$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
    echo '<div class="alert alert-danger">شناسه وظیفه نامعتبر است.</div>';
    exit;
}

try {
    // ۱. دریافت اطلاعات تسک
    $stmtTask = $pdo->prepare("SELECT t.*, 
        u_creator.full_name as creator_name, u_creator.role as creator_role,
        u_assignee.full_name as assignee_name, u_assignee.role as assignee_role
        FROM `tasks` t
        JOIN `users` u_creator ON t.created_by = u_creator.id
        JOIN `users` u_assignee ON t.assigned_to = u_assignee.id
        WHERE t.id = ?");
    $stmtTask->execute([$taskId]);
    $task = $stmtTask->fetch();

    if (!$task) {
        echo '<div class="alert alert-danger">وظیفه مورد نظر یافت نشد.</div>';
        exit;
    }

    // ۲. دریافت کامنت‌های تسک
    $stmtComments = $pdo->prepare("SELECT c.*, u.full_name, u.role, u.avatar_path 
        FROM `task_comments` c 
        JOIN `users` u ON c.user_id = u.id 
        WHERE c.task_id = ? 
        ORDER BY c.created_at ASC");
    $stmtComments->execute([$taskId]);
    $comments = $stmtComments->fetchAll();

    // ۳. دریافت لیست همه کاربران سیستم برای راهنمای منشن
    $allUsers = $pdo->query("SELECT username, full_name FROM `users` WHERE `status` = 1 ORDER BY `full_name` ASC")->fetchAll();

    // تبدیل تاریخ ددلاین به فرمت شمسی
    $deadlineStr = to_shamsi($task['deadline']) . ' ساعت ' . date('H:i', strtotime($task['deadline']));
    $createdStr = to_shamsi($task['created_at']) . ' ساعت ' . date('H:i', strtotime($task['created_at']));
    $canEdit = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'operator' || $task['created_by'] == $_SESSION['user_id']);
?>

<div class="task-details-single-column">
    <!-- بخش بالا: هدر و دکمه ویرایش -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold text-primary mb-0"><?= htmlspecialchars($task['title']) ?></h4>
        <?php if ($canEdit): ?>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm fw-bold" onclick="toggleEditTaskMode(true)" id="btnEnterEditMode">
                    <i class="bi bi-pencil-square me-1"></i> ویرایش وظیفه
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm fw-bold" onclick="deleteTask(<?= $task['id'] ?>)">
                    <i class="bi bi-trash3-fill me-1"></i> حذف وظیفه
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- بخش توضیحات وظیفه -->
    <div id="taskDetailsViewMode">
        <div class="mb-4">
            <label class="text-secondary small fw-bold d-block mb-1">توضیحات وظیفه:</label>
            <div class="p-3 bg-light rounded-3 border small" style="min-height: 80px; white-space: pre-wrap;"><?= htmlspecialchars($task['description'] ?: 'توضیحاتی برای این تسک ثبت نشده است.') ?></div>
        </div>

        <!-- گرید اطلاعات شناسنامه‌ای تسک -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3 mb-4">
            <div class="col">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <span class="text-secondary small d-block mb-1">ارجاع‌دهنده:</span>
                    <strong class="small text-dark"><?= htmlspecialchars($task['creator_name']) ?></strong>
                    <div class="text-muted" style="font-size: 0.72rem;"><?= get_role_fa($task['creator_role']) ?></div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <span class="text-secondary small d-block mb-1">مسئول انجام:</span>
                    <strong class="small text-dark"><?= htmlspecialchars($task['assignee_name']) ?></strong>
                    <div class="text-muted" style="font-size: 0.72rem;"><?= get_role_fa($task['assignee_role']) ?></div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <span class="text-secondary small d-block mb-1">مهلت انجام (ددلاین):</span>
                    <strong class="small text-danger"><i class="bi bi-calendar-check me-1"></i><?= $deadlineStr ?></strong>
                    <div class="text-muted" style="font-size: 0.72rem;">تاریخ ایجاد: <?= to_shamsi($task['created_at']) ?></div>
                </div>
            </div>
            <div class="col">
                <div class="p-3 bg-light rounded-3 border h-100">
                    <span class="text-secondary small d-block mb-2">وضعیت و نوع وظیفه:</span>
                    <div class="d-flex flex-column gap-1.5 align-items-start">
                        <?php 
                        $statusClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                        $statusText = 'پیش‌رو';
                        if ($task['status'] === 'in_progress') { $statusClass = 'bg-primary-subtle text-primary border-primary-subtle'; $statusText = 'در حال انجام'; }
                        elseif ($task['status'] === 'done') { $statusClass = 'bg-success-subtle text-success border-success-subtle'; $statusText = 'انجام شده'; }
                        ?>
                        <span class="badge border p-1.5 rounded-2 <?= $statusClass ?>" style="font-size: 0.72rem;"><?= $statusText ?></span>
                        <span class="badge bg-secondary-subtle text-dark border p-1.5 rounded-2" style="font-size: 0.72rem;"><?= $task['task_type'] === 'routine' ? 'روتین (تکراری)' : 'سفارشی (کاستوم)' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($task['attachment_path'])): ?>
            <div class="mb-4 p-3 bg-info bg-opacity-10 border border-info rounded-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2 small">
                    <i class="bi bi-file-earmark-arrow-down-fill text-info fs-5"></i>
                    <span class="fw-bold">فایل ضمیمه اصلی تسک</span>
                </div>
                <a href="../<?= htmlspecialchars($task['attachment_path']) ?>" download class="btn btn-info btn-sm fw-bold px-3">دانلود فایل ضمیمه</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canEdit): ?>
        <!-- فرم ویرایش تسک (در حالت پیش‌فرض پنهان است) -->
        <div id="taskDetailsEditMode" class="d-none mb-4">
            <div class="p-3 bg-light rounded-3 border">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pencil-square text-primary me-1"></i> ویرایش اطلاعات تسک</h6>
                <form id="editTaskForm" onsubmit="submitEditTask(event, <?= $task['id'] ?>)">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">عنوان وظیفه *</label>
                        <input type="text" name="title" class="form-control form-control-sm" value="<?= htmlspecialchars($task['title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">توضیحات</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($task['description']) ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">اولویت</label>
                            <select name="priority" class="form-select form-select-sm">
                                <option value="normal" <?= $task['priority'] === 'normal' ? 'selected' : '' ?>>عادی</option>
                                <option value="urgent" <?= $task['priority'] === 'urgent' ? 'selected' : '' ?>>فوری</option>
                                <option value="critical" <?= $task['priority'] === 'critical' ? 'selected' : '' ?>>اضطراری</option>
                            </select>
                        </div>
                        
                        <?php 
                        $currentDeadlineGy = date('Y-m-d', strtotime($task['deadline']));
                        $currentDeadlineParts = explode('-', $currentDeadlineGy);
                        $currentDeadlineJalali = '';
                        if (count($currentDeadlineParts) === 3) {
                            $currentDeadlineJalali = gregorian_to_jalali((int)$currentDeadlineParts[0], (int)$currentDeadlineParts[1], (int)$currentDeadlineParts[2], '/');
                        }
                        ?>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">تاریخ ددلاین *</label>
                            <input type="text" name="deadline_date" id="editTaskDeadlineDateInput" data-jdp class="form-control form-control-sm" value="<?= $currentDeadlineJalali ?>" autocomplete="off" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">ساعت ددلاین *</label>
                            <input type="time" name="deadline_time" class="form-control form-control-sm" value="<?= date('H:i', strtotime($task['deadline'])) ?>" required>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-light btn-sm px-3 me-2" onclick="toggleEditTaskMode(false)">انصراف</button>
                        <button type="submit" class="btn btn-success btn-sm fw-bold px-3">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <hr class="my-4">

    <!-- بخش پایین: سیستم کامنت و گفتگو (تمام عرض) -->
    <div class="task-chat-section">
        <h5 class="fw-bold mb-3 text-secondary"><i class="bi bi-chat-left-text-fill text-primary me-1"></i> گفتگو و هماهنگی روی تسک</h5>
        
        <!-- لیست کامنت‌ها -->
        <div class="comment-thread mb-4 p-3 bg-light rounded-3 border" style="max-height: 350px; overflow-y: auto;">
            <?php if (empty($comments)): ?>
                <div class="text-center py-4 text-secondary small">هنوز گفتگویی برای این وظیفه ثبت نشده است.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($comments as $c): 
                        $avatar = !empty($c['avatar_path']) ? '../' . $c['avatar_path'] : '../assets/images/default-avatar.png';
                        $commentDate = date('m/d H:i', strtotime($c['created_at']));
                    ?>
                        <div class="d-flex gap-3">
                            <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($c['full_name']) ?>" class="rounded-circle border border-2 shadow-xs" style="width: 40px; height: 40px; object-fit: cover;">
                            <div class="flex-1 bg-white p-3 rounded-3 shadow-sm border" style="width: calc(100% - 55px);">
                                <div class="d-flex justify-content-between align-items-center mb-1.5">
                                    <span class="small fw-bold text-dark"><?= htmlspecialchars($c['full_name']) ?> <span class="text-muted fw-normal" style="font-size: 0.72rem;">(<?= get_role_fa($c['role']) ?>)</span></span>
                                    <span class="text-secondary" style="font-size: 0.72rem;"><i class="bi bi-clock me-0.5"></i> <?= $commentDate ?></span>
                                </div>
                                <p class="small text-secondary mb-0" style="white-space: pre-wrap; line-height: 1.6;"><?= htmlspecialchars($c['comment_text']) ?></p>
                                
                                <?php if (!empty($c['attachment_path'])): ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <a href="../<?= htmlspecialchars($c['attachment_path']) ?>" download class="small text-primary fw-bold text-decoration-none d-inline-flex align-items-center gap-1"><i class="bi bi-download"></i> دریافت فایل پیوست پیام</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- فرم ثبت کامنت جدید -->
        <div class="p-3 bg-white rounded-3 border shadow-xs">
            <form id="addCommentForm" onsubmit="submitAddComment(event, <?= $task['id'] ?>)" enctype="multipart/form-data">
                <div class="mb-3">
                    <textarea name="comment_text" id="commentTextarea" class="form-control form-control-sm" rows="3" placeholder="پیام خود را بنویسید... (برای منشن کردن افراد از @ استفاده کنید)" required></textarea>
                </div>
                
                <!-- راهنما و ابزار منشن -->
                <div class="mb-3">
                    <div class="d-flex flex-column gap-2">
                        <small class="text-secondary fw-bold" style="font-size: 0.85rem;"><i class="bi bi-at text-primary fs-5"></i> کاربران قابل منشن (جهت ارسال نوتیفیکیشن):</small>
                        <div class="d-flex gap-1.5 flex-wrap p-2 bg-light rounded-2 border" style="max-height: 110px; overflow-y: auto; padding-right: 5px;">
                            <?php foreach ($allUsers as $u): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 rounded-2" style="font-size: 0.78rem;" onclick="insertMention('<?= htmlspecialchars($u['username']) ?>')">
                                    <strong>@<?= htmlspecialchars($u['username']) ?></strong> <span class="text-muted" style="font-size: 0.72rem;">(<?= htmlspecialchars($u['full_name']) ?>)</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="row g-3 align-items-center">
                    <div class="col-sm-8 col-12">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-secondary text-nowrap"><i class="bi bi-paperclip"></i> پیوست فایل:</span>
                            <input type="file" name="attachment_file" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="col-sm-4 col-12 text-end">
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold w-100 w-sm-auto">
                            <i class="bi bi-send-fill me-1"></i> ارسال پیام گفتگو
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>



<?php
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">خطا در واکشی اطلاعات: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
