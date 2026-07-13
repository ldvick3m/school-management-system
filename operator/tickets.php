<?php
/**
 * مدیریت تیکت‌های پشتیبانی اداری/مالی
 */

require_once '../includes/header.php';
check_auth(['operator', 'admin']);

$error = '';
$success = '';
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$activeTicket = null;
$messages = [];

// هندل کردن ثبت پاسخ تیکت یا بستن تیکت
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticketId > 0) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'reply') {
            $reply_text = trim($_POST['reply_text'] ?? '');
            $new_status = $_POST['status'] ?? 'in_progress';

            if (empty($reply_text)) {
                $error = 'متن پاسخ نمی‌تواند خالی باشد.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // ۱. ثبت پیام جدید در تیکت
                    $stmtMsg = $pdo->prepare("INSERT INTO `ticket_messages` (`ticket_id`, `sender_id`, `message_text`) VALUES (?, ?, ?)");
                    $stmtMsg->execute([$ticketId, $_SESSION['user_id'], $reply_text]);

                    // ۲. آپدیت وضعیت تیکت
                    $stmtStatus = $pdo->prepare("UPDATE `tickets` SET `status` = ? WHERE `id` = ?");
                    $stmtStatus->execute([$new_status, $ticketId]);

                    $pdo->commit();
                    $success = "پاسخ شما با موفقیت ثبت شد.";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "خطا در ثبت پاسخ: " . $e->getMessage();
                }
            }
        } elseif ($action === 'close') {
            try {
                $stmtClose = $pdo->prepare("UPDATE `tickets` SET `status` = 'closed' WHERE `id` = ?");
                $stmtClose->execute([$ticketId]);
                $success = "تیکت با موفقیت بسته شد.";
            } catch (PDOException $e) {
                $error = "خطا در بستن تیکت: " . $e->getMessage();
            }
        }
    }
}

// واکشی تیکت فعال در صورت انتخاب
if ($ticketId > 0) {
    try {
        $stmtTicket = $pdo->prepare("SELECT t.*, u.full_name as creator_name, u.role as creator_role, u.email as creator_email 
            FROM tickets t
            JOIN users u ON t.creator_id = u.id
            WHERE t.id = ?");
        $stmtTicket->execute([$ticketId]);
        $activeTicket = $stmtTicket->fetch();

        if ($activeTicket) {
            // واکشی پیام‌های تیکت به همراه مشخصات فرستنده
            $stmtMsgs = $pdo->prepare("SELECT tm.*, u.full_name, u.role 
                FROM ticket_messages tm
                JOIN users u ON tm.sender_id = u.id
                WHERE tm.ticket_id = ?
                ORDER BY tm.created_at ASC");
            $stmtMsgs->execute([$ticketId]);
            $messages = $stmtMsgs->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "خطا در واکشی تیکت: " . $e->getMessage();
    }
}

// واکشی لیست تمامی تیکت‌ها جهت نمایش در سایدبار/لیست
try {
    $stmtAll = $pdo->prepare("SELECT t.*, u.full_name as creator_name, u.role as creator_role 
        FROM tickets t
        JOIN users u ON t.creator_id = u.id
        ORDER BY t.created_at DESC");
    $stmtAll->execute();
    $allTickets = $stmtAll->fetchAll();
} catch (PDOException $e) {
    die("خطا در واکشی لیست تیکت‌ها: " . $e->getMessage());
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
        <!-- لیست کل تیکت‌ها (ستون راست) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">کارتابل تیکت‌های پشتیبانی</h5>
                </div>
                <div class="card-body p-0" style="max-height: 550px; overflow-y: auto;">
                    <?php if (empty($allTickets)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-envelope-open fs-1 text-secondary mb-2"></i>
                            <p class="text-secondary mb-0">تیکتی در سیستم ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($allTickets as $t): ?>
                                <a href="tickets.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action p-3 <?= $ticketId == $t['id'] ? 'active bg-light text-dark' : '' ?>">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h6 class="fw-bold mb-0 <?= $ticketId == $t['id'] ? 'text-primary' : '' ?>"><?= htmlspecialchars($t['title']) ?></h6>
                                        <span class="badge bg-<?= get_status_class($t['status']) ?>-subtle text-<?= get_status_class($t['status']) ?> border border-<?= get_status_class($t['status']) ?>-subtle">
                                            <?= $t['status'] === 'open' ? 'باز' : ($t['status'] === 'closed' ? 'بسته شده' : 'در حال بررسی') ?>
                                        </span>
                                    </div>
                                    <p class="mb-1 text-secondary small">
                                        فرستنده: <?= htmlspecialchars($t['creator_name']) ?> (<?= get_role_fa($t['creator_role']) ?>)
                                    </p>
                                    <div class="d-flex justify-content-between text-black-50 small mt-2">
                                        <span>بخش: <?= $t['category'] === 'admin' ? 'اداری' : 'مالی' ?></span>
                                        <span><i class="bi bi-clock me-1"></i><?= to_shamsi($t['created_at']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- جزئیات تیکت فعال و چت باکس پاسخ (ستون چپ) -->
        <div class="col-lg-8">
            <?php if (!$activeTicket): ?>
                <div class="card border-0 shadow-sm rounded-3 h-100 d-flex align-items-center justify-content-center py-5 text-center">
                    <i class="bi bi-chat-left-dots fs-1 text-secondary mb-3"></i>
                    <h5 class="fw-bold">جزئیات تیکت پشتیبانی</h5>
                    <p class="text-secondary small max-w-400">یکی از تیکت‌های کارتابل سمت راست را جهت مشاهده و پاسخگویی انتخاب نمایید.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-primary"><?= htmlspecialchars($activeTicket['title']) ?></h5>
                            <p class="mb-0 text-secondary small">
                                بخش: <strong><?= $activeTicket['category'] === 'admin' ? 'اداری' : 'مالی' ?></strong> | 
                                ارسال‌کننده: <strong><?= htmlspecialchars($activeTicket['creator_name']) ?> (<?= get_role_fa($activeTicket['creator_role']) ?>)</strong>
                            </p>
                        </div>
                        <?php if ($activeTicket['status'] !== 'closed'): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="close">
                                <button type="submit" class="btn btn-outline-danger btn-sm fw-bold">
                                    <i class="bi bi-check2-circle me-1"></i> بستن تیکت
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- باکس نمایش گفتگوی تیکت -->
                    <div class="card-body bg-light overflow-y-auto p-4" style="height: 380px;">
                        <?php foreach ($messages as $msg): ?>
                            <div class="mb-3 d-flex flex-column <?= $msg['sender_id'] == $_SESSION['user_id'] ? 'align-items-start' : 'align-items-end' ?>">
                                <div class="p-3 rounded-3 shadow-sm" style="max-width: 75%; background-color: <?= $msg['sender_id'] == $_SESSION['user_id'] ? '#E0F2FE' : '#FFFFFF' ?>; border: 1px solid <?= $msg['sender_id'] == $_SESSION['user_id'] ? '#BAE6FD' : '#E2E8F0' ?>;">
                                    <div class="d-flex justify-content-between align-items-center gap-4 mb-2 border-bottom pb-1 border-opacity-10 border-dark">
                                        <strong class="small text-secondary"><?= htmlspecialchars($msg['full_name']) ?> (<?= get_role_fa($msg['role']) ?>)</strong>
                                        <small class="text-muted text-xs"><?= to_shamsi($msg['created_at']) ?></small>
                                    </div>
                                    <p class="mb-0 small text-dark" style="line-height: 1.6; white-space: pre-line;"><?= htmlspecialchars($msg['message_text']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- بخش نوشتن پاسخ جدید -->
                    <?php if ($activeTicket['status'] === 'closed'): ?>
                        <div class="card-footer bg-light text-center py-3 text-secondary small">
                            <i class="bi bi-lock-fill me-1"></i> این تیکت بسته شده است و امکان ثبت پاسخ جدید در آن وجود ندارد.
                        </div>
                    <?php else: ?>
                        <div class="card-footer bg-white p-3">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="reply">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">متن پاسخ پشتیبانی:</label>
                                    <textarea name="reply_text" class="form-control" rows="4" placeholder="پاسخ خود را در این بخش بنویسید..." required></textarea>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="small text-secondary">تغییر وضعیت تیکت به:</label>
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="in_progress" selected>در حال بررسی</option>
                                            <option value="closed">بستن تیکت (حل شده)</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary fw-bold px-4">
                                        <i class="bi bi-send-fill me-1"></i> ارسال پاسخ تیکت
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
