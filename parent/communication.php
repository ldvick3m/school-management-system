<?php
/**
 * هاب ارتباطی اولیا (گفتگو با معلمان فرزند و سامانه تیکتینگ اداری/مالی)
 */

require_once '../includes/header.php';
check_auth(['parent', 'admin']);

$activeChildId = $_SESSION['active_child_id'] ?? 0;
$myUserId = $_SESSION['user_id'];
$error = '';
$success = '';

$activeTeacherUserId = isset($_GET['chat_teacher_id']) ? (int)$_GET['chat_teacher_id'] : 0;
$activeTeacher = null;

$activeTicketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$activeTicket = null;
$ticketMessages = [];

try {
    // ۱. پیدا کردن اطلاعات معلم فعال برای چت در صورت انتخاب
    if ($activeTeacherUserId > 0) {
        $stmtT = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = ? AND role = 'teacher'");
        $stmtT->execute([$activeTeacherUserId]);
        $activeTeacher = $stmtT->fetch();
    }

    // ۲. پیدا کردن اطلاعات تیکت فعال در صورت انتخاب
    if ($activeTicketId > 0) {
        $stmtTk = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND creator_id = ?");
        $stmtTk->execute([$activeTicketId, $myUserId]);
        $activeTicket = $stmtTk->fetch();

        if ($activeTicket) {
            $stmtTkMsgs = $pdo->prepare("SELECT tm.*, u.full_name, u.role FROM ticket_messages tm 
                JOIN users u ON tm.sender_id = u.id 
                WHERE tm.ticket_id = ? ORDER BY tm.created_at ASC");
            $stmtTkMsgs->execute([$activeTicketId]);
            $ticketMessages = $stmtTkMsgs->fetchAll();
        }
    }

    // ۳. واکشی لیست معلمان فرزند فعال
    $myTeachers = [];
    if ($activeChildId > 0) {
        // پیدا کردن کلاس فرزند
        $classId = $pdo->query("SELECT class_id FROM class_student WHERE student_id = $activeChildId LIMIT 1")->fetchColumn();
        
        if ($classId) {
            $stmtTList = $pdo->prepare("SELECT DISTINCT u.id as user_id, u.full_name, co.course_name,
                (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
                FROM class_teacher_course ctc
                JOIN teachers t ON ctc.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                JOIN courses co ON ctc.course_id = co.id
                WHERE ctc.class_id = ? AND u.status = 1");
            $stmtTList->execute([$_SESSION['user_id'], $classId]);
            $myTeachers = $stmtTList->fetchAll();
        }
    }

    // ۴. واکشی لیست تیکت‌های پشتیبانی قبلی این والد
    $stmtTickets = $pdo->prepare("SELECT * FROM tickets WHERE creator_id = ? ORDER BY created_at DESC");
    $stmtTickets->execute([$myUserId]);
    $myTickets = $stmtTickets->fetchAll();

} catch (PDOException $e) {
    die("خطا در لود اطلاعات ارتباطی: " . $e->getMessage());
}

// هندل کردن ثبت تیکت جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'admin';
    $message = trim($_POST['message'] ?? '');

    if (empty($title) || empty($message)) {
        $error = 'ثبت عنوان تیکت و شرح مشکل الزامی است.';
    } else {
        try {
            $pdo->beginTransaction();

            // ۱. ثبت سربرگ تیکت
            $stmtT = $pdo->prepare("INSERT INTO `tickets` (`creator_id`, `category`, `title`, `status`) VALUES (?, ?, ?, 'open')");
            $stmtT->execute([$myUserId, $category, $title]);
            $newTkId = $pdo->lastInsertId();

            // ۲. ثبت اولین پیام در تیکت
            $stmtM = $pdo->prepare("INSERT INTO `ticket_messages` (`ticket_id`, `sender_id`, `message_text`) VALUES (?, ?, ?)");
            $stmtM->execute([$newTkId, $myUserId, $message]);

            $pdo->commit();
            $success = "تیکت پشتیبانی شما با موفقیت ثبت شد و به بخش مربوطه ارجاع گردید.";
            // بارگذاری مجدد جهت بروزرسانی لیست تیکت‌ها
            redirect("communication.php?ticket_id=" . $newTkId . "&success=1");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "خطا در ثبت تیکت: " . $e->getMessage();
        }
    }
}

// هندل کردن ثبت پاسخ در تیکت فعال
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_ticket' && $activeTicket) {
    $reply_text = trim($_POST['reply_text'] ?? '');

    if (empty($reply_text)) {
        $error = 'متن پیام پاسخ نمی‌تواند خالی باشد.';
    } else {
        try {
            $pdo->beginTransaction();

            // ۱. درج پیام جدید
            $stmtMsg = $pdo->prepare("INSERT INTO `ticket_messages` (`ticket_id`, `sender_id`, `message_text`) VALUES (?, ?, ?)");
            $stmtMsg->execute([$activeTicketId, $myUserId, $reply_text]);

            // ۲. تغییر وضعیت تیکت به open جهت آگاهی اپراتور
            $stmtStatus = $pdo->prepare("UPDATE `tickets` SET `status` = 'open' WHERE `id` = ?");
            $stmtStatus->execute([$activeTicketId]);

            $pdo->commit();
            $success = "پیام شما به تیکت با موفقیت اضافه شد.";
            redirect("communication.php?ticket_id=" . $activeTicketId . "&success=2");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "خطا در ثبت پیام تیکت: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'] == 1 ? "تیکت پشتیبانی شما با موفقیت ثبت شد." : "پیام شما به تیکت با موفقیت اضافه شد.";
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
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ستون سمت راست: لیست معلمان و تیکت‌ها -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <ul class="nav nav-tabs custom-tabs px-3 bg-light border-bottom-0" id="comTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="chat-tab" data-bs-toggle="tab" data-bs-target="#chat-pane" type="button" role="tab" aria-controls="chat-pane" aria-selected="true">
                            <i class="bi bi-chat-dots-fill"></i> چت با معلمان
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ticket-tab" data-bs-toggle="tab" data-bs-target="#ticket-pane" type="button" role="tab" aria-controls="ticket-pane" aria-selected="false">
                            <i class="bi bi-envelope-open-fill text-warning"></i> تیکتینگ اداری/مالی
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="comTabContent">
                    <!-- تب چت با معلمان -->
                    <div class="tab-pane fade show active" id="chat-pane" role="tabpanel" aria-labelledby="chat-tab" style="max-height: 480px; overflow-y: auto;">
                        <div class="list-group list-group-flush">
                            <?php if (empty($myTeachers)): ?>
                                <span class="text-secondary small p-3 text-center d-block">معلمی برای کلاس فرزند شما تعریف نشده است.</span>
                            <?php else: foreach ($myTeachers as $teacher): ?>
                                <a href="communication.php?chat_teacher_id=<?= $teacher['user_id'] ?>" data-chat-user-id="<?= $teacher['user_id'] ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between <?= $activeTeacherUserId == $teacher['user_id'] ? 'active bg-light' : '' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center text-success fw-bold" style="width: 40px; height: 40px;">د</div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($teacher['full_name']) ?></h6>
                                            <small class="text-secondary">دبیر درس: <?= htmlspecialchars($teacher['course_name']) ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-danger rounded-circle unread-badge <?= ($teacher['unread_count'] > 0 && $activeTeacherUserId != $teacher['user_id']) ? '' : 'd-none' ?>" style="font-size: 0.7rem; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
                                        <?= $teacher['unread_count'] ?>
                                    </span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- تب تیکت‌های پشتیبانی -->
                    <div class="tab-pane fade p-3" id="ticket-pane" role="tabpanel" aria-labelledby="ticket-tab">
                        <button class="btn btn-warning btn-sm w-100 fw-bold mb-3 text-dark" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                            <i class="bi bi-plus-lg me-1"></i> ارسال تیکت جدید به مدرسه
                        </button>

                        <div class="list-group list-group-flush" style="max-height: 380px; overflow-y: auto;">
                            <?php if (empty($myTickets)): ?>
                                <span class="text-secondary small text-center p-3 d-block">تیکتی ثبت نکرده‌اید.</span>
                            <?php else: foreach ($myTickets as $t): ?>
                                <a href="communication.php?ticket_id=<?= $t['id'] ?>" class="list-group-item list-group-item-action p-3 border rounded mb-2 text-start <?= $activeTicketId == $t['id'] ? 'active bg-light text-dark' : '' ?>">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h6 class="fw-bold mb-0 <?= $activeTicketId == $t['id'] ? 'text-primary' : '' ?>"><?= htmlspecialchars($t['title']) ?></h6>
                                        <span class="badge bg-<?= get_status_class($t['status']) ?>-subtle text-<?= get_status_class($t['status']) ?> border border-<?= get_status_class($t['status']) ?>-subtle">
                                            <?= $t['status'] === 'open' ? 'در جریان' : ($t['status'] === 'closed' ? 'حل شده' : 'پاسخ داده شده') ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between text-secondary text-xs mt-2">
                                        <span>بخش: <?= $t['category'] === 'admin' ? 'اداری' : 'مالی' ?></span>
                                        <span><?= to_shamsi($t['created_at']) ?></span>
                                    </div>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ستون سمت چپ: چت‌باکس یا تیکت فعال -->
        <div class="col-lg-8">
            <!-- وضعیت ۱: چت با معلم فعال است -->
            <?php if ($activeTeacher): ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-success" style="width: 45px; height: 45px; font-size: 1.1rem;">د</div>
                        <div>
                            <h5 class="fw-bold mb-0"><?= htmlspecialchars($activeTeacher['full_name']) ?></h5>
                            <small class="text-secondary">دبیر مربوطه فرزند شما</small>
                        </div>
                    </div>
                    
                    <div class="card-body bg-light p-4 overflow-y-auto" id="chatBox" style="height: 380px;">
                        <!-- پیام‌ها با AJAX پر می‌شوند -->
                    </div>

                    <div class="card-footer bg-white p-3">
                        <form id="chatSendForm">
                            <div class="input-group">
                                <input type="text" id="chatMessageInput" class="form-control" placeholder="پیام خود را به دبیر بنویسید..." required autocomplete="off">
                                <button type="submit" class="btn btn-primary fw-bold px-4">ارسال پیام</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        initChatRoom(<?= $activeTeacherUserId ?>, <?= $myUserId ?>);
                    });

                    function initChatRoom(chatUserId, currentUserId) {
                        const chatBox = document.getElementById('chatBox');
                        const sendForm = document.getElementById('chatSendForm');
                        const messageInput = document.getElementById('chatMessageInput');
                        let lastMsgId = 0;

                        function fetchChatMessages() {
                            fetch(`../ajax/chat.php?action=get&chat_user_id=${chatUserId}&last_id=${lastMsgId}`)
                                .then(res => res.json())
                                .then(messages => {
                                    if (messages.length > 0) {
                                        messages.forEach(msg => {
                                            const isMine = msg.sender_id == currentUserId;
                                            const msgDiv = document.createElement('div');
                                            msgDiv.className = `mb-3 d-flex flex-column ${isMine ? 'align-items-start' : 'align-items-end'}`;
                                            
                                            msgDiv.innerHTML = `
                                                <div class="p-2 px-3 rounded-3 shadow-sm text-dark small" style="max-width: 75%; background-color: ${isMine ? '#DBEAFE' : '#FFFFFF'}; border: 1px solid ${isMine ? '#BFDBFE' : '#E2E8F0'};">
                                                    <p class="mb-1">${msg.message_text}</p>
                                                    <div class="text-muted text-xs text-start" style="font-size: 0.65rem;">${msg.time_fa}</div>
                                                </div>
                                            `;
                                            chatBox.appendChild(msgDiv);
                                            lastMsgId = Math.max(lastMsgId, msg.id);
                                        });
                                        chatBox.scrollTop = chatBox.scrollHeight;
                                    }
                                })
                                .catch(err => console.error(err));
                        }

                        sendForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const text = messageInput.value.trim();
                            if (text === '') return;

                            const formData = new FormData();
                            formData.append('action', 'send');
                            formData.append('chat_user_id', chatUserId);
                            formData.append('message_text', text);

                            messageInput.value = '';

                            fetch('../ajax/chat.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(res => {
                                if (res.success) {
                                    fetchChatMessages();
                                }
                            });
                        });

                        fetchChatMessages();
                        setInterval(fetchChatMessages, 4000);
                    }
                </script>

            <!-- وضعیت ۲: تیکت فعال است -->
            <?php elseif ($activeTicket): ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-1 text-primary"><?= htmlspecialchars($activeTicket['title']) ?></h5>
                        <p class="mb-0 text-secondary small">بخش پشتیبانی: <strong><?= $activeTicket['category'] === 'admin' ? 'اداری' : 'مالی' ?></strong> | وضعیت: <strong><?= $activeTicket['status'] === 'open' ? 'در جریان' : ($activeTicket['status'] === 'closed' ? 'بسته شده' : 'پاسخ داده شده') ?></strong></p>
                    </div>

                    <div class="card-body bg-light p-4 overflow-y-auto" style="height: 380px;">
                        <?php foreach ($ticketMessages as $msg): ?>
                            <div class="mb-3 d-flex flex-column <?= $msg['sender_id'] == $myUserId ? 'align-items-start' : 'align-items-end' ?>">
                                <div class="p-3 rounded-3 shadow-sm text-dark small" style="max-width: 75%; background-color: <?= $msg['sender_id'] == $myUserId ? '#E0F2FE' : '#FFFFFF' ?>; border: 1px solid <?= $msg['sender_id'] == $myUserId ? '#BAE6FD' : '#E2E8F0' ?>;">
                                    <div class="d-flex justify-content-between align-items-center gap-4 mb-2 border-bottom pb-1 border-opacity-10 border-dark">
                                        <strong class="small text-secondary"><?= htmlspecialchars($msg['full_name']) ?> (<?= get_role_fa($msg['role']) ?>)</strong>
                                        <small class="text-muted text-xs"><?= to_shamsi($msg['created_at']) ?></small>
                                    </div>
                                    <p class="mb-0" style="white-space: pre-line; line-height: 1.6;"><?= htmlspecialchars($msg['message_text']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($activeTicket['status'] === 'closed'): ?>
                        <div class="card-footer bg-light text-center py-3 text-secondary small">
                            <i class="bi bi-lock-fill me-1"></i> این تیکت بسته شده است و امکان تغییر وضعیت آن وجود ندارد.
                        </div>
                    <?php else: ?>
                        <div class="card-footer bg-white p-3">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="reply_ticket">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">ارسال پیام جدید در تیکت:</label>
                                    <textarea name="reply_text" class="form-control" rows="3" placeholder="توضیح جدید یا پاسخ خود را بنویسید..." required></textarea>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-send-fill me-1"></i> ارسال پیام به پشتیبانی</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

            <!-- وضعیت ۳: هیچ‌کدام فعال نیستند -->
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-3 py-5 text-center h-100 d-flex align-items-center justify-content-center">
                    <i class="bi bi-chat-text fs-1 text-secondary mb-3"></i>
                    <h5 class="fw-bold">بخش گفتگوی اولیا با دبیران و مدرسه</h5>
                    <p class="text-secondary small max-w-400">یکی از مخاطبان چت یا تیکت‌های پشتیبانی خود در ستون راست را انتخاب کنید تا پنل فعال شود.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- مودال ساخت تیکت جدید -->
<div class="modal fade" id="newTicketModal" tabindex="-1" aria-labelledby="newTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_ticket">
                
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="newTicketModalLabel">ثبت تیکت پشتیبانی جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">موضوع تیکت *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثال: سوال در مورد شهریه یا زمان آزمون‌ها" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ارجاع به بخش *</label>
                        <select name="category" class="form-select" required>
                            <option value="admin">امور اداری و آموزشی مدرسه</option>
                            <option value="financial">امور مالی و حسابداری</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">متن شرح درخواست / مشکل *</label>
                        <textarea name="message" class="form-control" rows="5" placeholder="مشکل یا درخواست خود را به تفصیل بنویسید..." required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark">ارسال قطعی تیکت</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
