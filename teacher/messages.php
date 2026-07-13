<?php
/**
 * صندوق پیام‌ها و چت‌روم‌های معلمان با دانش‌آموزان و اولیا
 */

require_once '../includes/header.php';
check_auth(['teacher', 'admin']);

$error = '';
$activeUserId = isset($_GET['chat_user_id']) ? (int)$_GET['chat_user_id'] : 0;
$activeChatUser = null;

try {
    // پیدا کردن شناسه معلم
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$_SESSION['user_id']]);
    $teacherId = $stmtT->fetchColumn();

    if (!$teacherId) {
        die("شما به عنوان دبیر در سیستم ثبت نشده‌اید.");
    }

    // گرفتن مشخصات مخاطب فعال چت در صورت انتخاب (با محدودسازی به اولیا و دانش‌آموزان همین معلم)
    if ($activeUserId > 0) {
        $stmtCUser = $pdo->prepare("
            SELECT u.id, u.full_name, u.role 
            FROM users u
            WHERE u.id = ? AND u.status = 1
              AND (
                -- حالت اول: کاربر دانش‌آموز این معلم باشد
                (u.role = 'student' AND EXISTS (
                    SELECT 1 FROM class_student cs
                    JOIN students s ON cs.student_id = s.id
                    JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
                    WHERE s.user_id = u.id AND ctc.teacher_id = ?
                ))
                OR
                -- حالت دوم: کاربر ولیِ دانش‌آموز این معلم باشد
                (u.role = 'parent' AND EXISTS (
                    SELECT 1 FROM class_student cs
                    JOIN students s ON cs.student_id = s.id
                    JOIN parent_student ps ON s.id = ps.student_id
                    JOIN parents p ON ps.parent_id = p.id
                    JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
                    WHERE p.user_id = u.id AND ctc.teacher_id = ?
                ))
              )
        ");
        $stmtCUser->execute([$activeUserId, $teacherId, $teacherId]);
        $activeChatUser = $stmtCUser->fetch();
    }

    // ۱. واکشی لیست دانش‌آموزان دبیر که دارای سابقه چت فعال هستند
    $stmtStudents = $pdo->prepare("SELECT DISTINCT u.id as user_id, u.full_name, c.class_name,
        (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM class_student cs
        JOIN students s ON cs.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
        JOIN classes c ON cs.class_id = c.id
        WHERE ctc.teacher_id = ? AND u.status = 1
          AND EXISTS (SELECT 1 FROM chat_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id))
        ORDER BY u.full_name ASC");
    $stmtStudents->execute([$_SESSION['user_id'], $teacherId, $_SESSION['user_id'], $_SESSION['user_id']]);
    $myStudents = $stmtStudents->fetchAll();

    // ۲. واکشی لیست والدین کلاس دبیر که دارای سابقه چت فعال هستند
    $stmtParents = $pdo->prepare("SELECT DISTINCT pu.id as user_id, pu.full_name as parent_name, su.full_name as student_name,
        (SELECT COUNT(*) FROM chat_messages WHERE sender_id = pu.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM class_student cs
        JOIN students s ON cs.student_id = s.id
        JOIN users su ON s.user_id = su.id
        JOIN parent_student ps ON s.id = ps.student_id
        JOIN parents p ON ps.parent_id = p.id
        JOIN users pu ON p.user_id = pu.id
        JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
        WHERE ctc.teacher_id = ? AND pu.status = 1
          AND EXISTS (SELECT 1 FROM chat_messages WHERE (sender_id = pu.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = pu.id))
        ORDER BY pu.full_name ASC");
    $stmtParents->execute([$_SESSION['user_id'], $teacherId, $_SESSION['user_id'], $_SESSION['user_id']]);
    $myParents = $stmtParents->fetchAll();

    // ۳. واکشی لیست کل والدین مرتبط با دانش‌آموزان کلاس دبیر (بدون فیلتر تعداد چت - جهت تب مخاطبین)
    $stmtAllParents = $pdo->prepare("SELECT DISTINCT pu.id as user_id, pu.full_name as parent_name, su.full_name as student_name
        FROM class_student cs
        JOIN students s ON cs.student_id = s.id
        JOIN users su ON s.user_id = su.id
        JOIN parent_student ps ON s.id = ps.student_id
        JOIN parents p ON ps.parent_id = p.id
        JOIN users pu ON p.user_id = pu.id
        JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
        WHERE ctc.teacher_id = ? AND pu.status = 1
        ORDER BY pu.full_name ASC");
    $stmtAllParents->execute([$teacherId]);
    $allParentsList = $stmtAllParents->fetchAll();

    // ۴. واکشی لیست کل دانش‌آموزان مرتبط با کلاس‌های دبیر (بدون فیلتر تعداد چت - جهت تب مخاطبین)
    $stmtAllStudents = $pdo->prepare("SELECT DISTINCT u.id as user_id, u.full_name, c.class_name
        FROM class_student cs
        JOIN students s ON cs.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN class_teacher_course ctc ON cs.class_id = ctc.class_id
        JOIN classes c ON cs.class_id = c.id
        WHERE ctc.teacher_id = ? AND u.status = 1
        ORDER BY u.full_name ASC");
    $stmtAllStudents->execute([$teacherId]);
    $allStudentsList = $stmtAllStudents->fetchAll();

    // ۵. واکشی اعلانات رسمی مدرسه جهت نمایش در تب مجزا
    $stmtAnn = $pdo->prepare("SELECT * FROM announcements 
        WHERE target_role = 'all' OR target_role = 'teacher'
        ORDER BY created_at DESC LIMIT 10");
    $stmtAnn->execute();
    $myAnnouncements = $stmtAnn->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری صندوق پیام‌ها: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="row g-4">
        <!-- ستون سمت راست: لیست مخاطبین چت و اعلانات -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <!-- سربرگ تب‌های چت و اعلانات -->
                <ul class="nav nav-tabs custom-tabs px-2 bg-light border-bottom-0 flex-nowrap w-100 nav-justified" id="chatListTab" role="tablist" style="scrollbar-width: none; -ms-overflow-style: none;">
                    <style>
                        #chatListTab::-webkit-scrollbar { display: none; }
                    </style>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active w-100 px-1 py-2 text-center" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts-pane" type="button" role="tab" aria-controls="contacts-pane" aria-selected="true" style="font-size: 0.83rem; white-space: nowrap;">
                            <i class="bi bi-chat-text-fill"></i> چت‌های فعال
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link w-100 px-1 py-2 text-center" id="all-contacts-tab" data-bs-toggle="tab" data-bs-target="#all-contacts-pane" type="button" role="tab" aria-controls="all-contacts-pane" aria-selected="false" style="font-size: 0.83rem; white-space: nowrap;">
                            <i class="bi bi-people-fill"></i> لیست مخاطبان
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link w-100 px-1 py-2 text-center" id="ann-tab" data-bs-toggle="tab" data-bs-target="#ann-pane" type="button" role="tab" aria-controls="ann-pane" aria-selected="false" style="font-size: 0.83rem; white-space: nowrap;">
                            <i class="bi bi-megaphone-fill text-danger"></i> اعلانات رسمی
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-0" id="chatListTabContent">
                    <!-- تب لیست مخاطبین چت فعال -->
                    <div class="tab-pane fade show active" id="contacts-pane" role="tabpanel" aria-labelledby="contacts-tab" style="max-height: 480px; overflow-y: auto;">
                        
                        <!-- بخش اولیا -->
                        <div class="bg-light px-3 py-2 fw-bold text-secondary border-bottom border-top small">چت‌روم با والدین دانش‌آموزان</div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($myParents)): ?>
                                <span class="text-secondary small p-3 text-center d-block">والدی با سابقه چت یافت نشد.</span>
                            <?php else: foreach ($myParents as $parent): ?>
                                <a href="messages.php?chat_user_id=<?= $parent['user_id'] ?>" data-chat-user-id="<?= $parent['user_id'] ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between <?= $activeUserId == $parent['user_id'] ? 'active bg-light' : '' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 40px; height: 40px;">و</div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($parent['parent_name']) ?></h6>
                                            <small class="text-secondary">ولیِ دانش‌آموز: <?= htmlspecialchars($parent['student_name']) ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-danger rounded-circle unread-badge <?= ($parent['unread_count'] > 0 && $activeUserId != $parent['user_id']) ? '' : 'd-none' ?>" style="font-size: 0.7rem; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
                                        <?= $parent['unread_count'] ?>
                                    </span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>

                        <!-- بخش دانش‌آموزان -->
                        <div class="bg-light px-3 py-2 fw-bold text-secondary border-bottom border-top small">چت‌روم با دانش‌آموزان (رفع اشکال)</div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($myStudents)): ?>
                                <span class="text-secondary small p-3 text-center d-block">دانش‌آموزی با سابقه چت یافت نشد.</span>
                            <?php else: foreach ($myStudents as $student): ?>
                                <a href="messages.php?chat_user_id=<?= $student['user_id'] ?>" data-chat-user-id="<?= $student['user_id'] ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between <?= $activeUserId == $student['user_id'] ? 'active bg-light' : '' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center text-success fw-bold" style="width: 40px; height: 40px;">د</div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($student['full_name']) ?></h6>
                                            <small class="text-secondary">کلاس: <?= htmlspecialchars($student['class_name']) ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-danger rounded-circle unread-badge <?= ($student['unread_count'] > 0 && $activeUserId != $student['user_id']) ? '' : 'd-none' ?>" style="font-size: 0.7rem; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
                                        <?= $student['unread_count'] ?>
                                    </span>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- تب لیست مخاطبان کل والدین و دانش‌آموزان (با امکان سوئیچ) -->
                    <div class="tab-pane fade" id="all-contacts-pane" role="tabpanel" aria-labelledby="all-contacts-tab" style="max-height: 480px; overflow-y: auto;">
                        <!-- دکمه‌های سوئیچ بین اولیا و دانش‌آموزان -->
                        <div class="p-3 bg-light border-bottom d-flex gap-2 justify-content-center">
                            <button type="button" id="btnShowParents" class="btn btn-sm btn-primary w-50 justify-content-center" onclick="switchContactType('parent')">
                                <i class="bi bi-person-fill-gear"></i> لیست اولیاء
                            </button>
                            <button type="button" id="btnShowStudents" class="btn btn-sm btn-outline-primary w-50 justify-content-center" onclick="switchContactType('student')">
                                <i class="bi bi-mortarboard-fill"></i> لیست دانش‌آموزان
                            </button>
                        </div>

                        <!-- فیلد جستجو -->
                        <div class="p-3 bg-white border-bottom sticky-top">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-secondary"></i></span>
                                <input type="text" id="contactSearchInput" class="form-control border-start-0" placeholder="جستجوی نام ولی..." onkeyup="filterContacts()">
                            </div>
                        </div>

                        <!-- لیست مخاطبان اولیاء -->
                        <div class="list-group list-group-flush" id="allParentsList">
                            <?php if (empty($allParentsList)): ?>
                                <span class="text-secondary small p-3 text-center d-block">مخاطبی یافت نشد.</span>
                            <?php else: foreach ($allParentsList as $contact): ?>
                                <a href="messages.php?chat_user_id=<?= $contact['user_id'] ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between contact-item" data-name="<?= htmlspecialchars($contact['parent_name']) ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center text-info fw-bold" style="width: 40px; height: 40px;">و</div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark contact-name"><?= htmlspecialchars($contact['parent_name']) ?></h6>
                                            <small class="text-secondary">ولیِ دانش‌آموز: <?= htmlspecialchars($contact['student_name']) ?></small>
                                        </div>
                                    </div>
                                    <i class="bi bi-chat-dots text-primary"></i>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>

                        <!-- لیست مخاطبان دانش‌آموزان (در ابتدا پنهان است) -->
                        <div class="list-group list-group-flush d-none" id="allStudentsList">
                            <?php if (empty($allStudentsList)): ?>
                                <span class="text-secondary small p-3 text-center d-block">دانش‌آموزی یافت نشد.</span>
                            <?php else: foreach ($allStudentsList as $contact): ?>
                                <a href="messages.php?chat_user_id=<?= $contact['user_id'] ?>" class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between contact-item" data-name="<?= htmlspecialchars($contact['full_name']) ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center text-success fw-bold" style="width: 40px; height: 40px;">د</div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark contact-name"><?= htmlspecialchars($contact['full_name']) ?></h6>
                                            <small class="text-secondary">کلاس: <?= htmlspecialchars($contact['class_name']) ?></small>
                                        </div>
                                    </div>
                                    <i class="bi bi-chat-dots text-primary"></i>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- تب اطلاعیه‌های مدرسه -->
                    <div class="tab-pane fade p-3" id="ann-pane" role="tabpanel" aria-labelledby="ann-tab" style="max-height: 480px; overflow-y: auto;">
                        <?php if (empty($myAnnouncements)): ?>
                            <div class="text-center py-4 text-secondary small">هیچ اطلاعیه رسمی وجود ندارد.</div>
                        <?php else: foreach ($myAnnouncements as $ann): ?>
                            <div class="border rounded p-3 mb-2 bg-light">
                                <h6 class="fw-bold text-danger mb-1"><i class="bi bi-bell-fill"></i> <?= htmlspecialchars($ann['title']) ?></h6>
                                <small class="text-secondary d-block mb-2"><?= to_shamsi($ann['created_at']) ?></small>
                                <p class="mb-0 text-dark small" style="line-height: 1.6;"><?= htmlspecialchars($ann['content']) ?></p>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ستون سمت چپ: پنجره گفتگو چت‌باکس فعال -->
        <div class="col-lg-8">
            <?php if (!$activeChatUser): ?>
                <div class="card border-0 shadow-sm rounded-3 py-5 text-center h-100 d-flex align-items-center justify-content-center">
                    <i class="bi bi-chat-text fs-1 text-secondary mb-3"></i>
                    <h5 class="fw-bold">صندوق گفتگوی تعاملی</h5>
                    <p class="text-secondary small max-w-400">یکی از چت‌روم‌های والدین یا دانش‌آموزان در ستون راست را انتخاب کنید تا چت فعال شود.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-secondary" style="width: 45px; height: 45px; font-size: 1.1rem;">
                            <?= mb_substr($activeChatUser['full_name'], 0, 1, 'utf-8') ?>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0"><?= htmlspecialchars($activeChatUser['full_name']) ?></h5>
                            <span class="badge bg-<?= get_status_class($activeChatUser['role']) ?>-subtle text-<?= get_status_class($activeChatUser['role']) ?> border" style="font-size: 0.75rem;">
                                <?= get_role_fa($activeChatUser['role']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- باکس چت همزمان -->
                    <div class="card-body bg-light p-4 overflow-y-auto" id="chatBox" style="height: 380px;">
                        <!-- پیام‌ها با AJAX پر می‌شوند -->
                    </div>

                    <!-- بخش تایپ پیام جدید -->
                    <div class="card-footer bg-white p-3">
                        <form id="chatSendForm">
                            <div class="input-group">
                                <input type="text" id="chatMessageInput" class="form-control" placeholder="پیام خود را تایپ کنید..." required autocomplete="off">
                                <button type="submit" class="btn btn-primary fw-bold px-4">
                                    <i class="bi bi-send-fill me-1"></i> ارسال پیام
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($activeChatUser): ?>
<script>
    // فعال‌سازی ردیاب چت تعاملی (AJAX)
    document.addEventListener('DOMContentLoaded', function() {
        initChatRoom(<?= $activeUserId ?>, <?= $_SESSION['user_id'] ?>);
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
                .catch(err => console.error("خطا در خواندن پیام‌ها:", err));
        }

        // ارسال پیام
        sendForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const text = messageInput.value.trim();
            if (text === '') return;

            const formData = new FormData();
            formData.append('action', 'send');
            formData.append('chat_user_id', chatUserId);
            formData.append('message_text', text);

            messageInput.value = ''; // تخلیه سریع

            fetch('../ajax/chat.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    fetchChatMessages(); // بازیابی مجدد سریع
                } else {
                    alert("خطا در ارسال پیام: " + res.message);
                }
            })
            .catch(err => console.error("خطا در ارسال پیام:", err));
        });

        // لود اول و پولینگ هر ۴ ثانیه
        fetchChatMessages();
        setInterval(fetchChatMessages, 4000);
    }
</script>
<?php endif; ?>

<script>
    let currentContactType = 'parent';

    // سوئیچ بین لیست والدین و دانش‌آموزان
    function switchContactType(type) {
        currentContactType = type;
        const btnParents = document.getElementById('btnShowParents');
        const btnStudents = document.getElementById('btnShowStudents');
        const listParents = document.getElementById('allParentsList');
        const listStudents = document.getElementById('allStudentsList');
        const searchInput = document.getElementById('contactSearchInput');

        // ریست کردن ورودی سرچ
        searchInput.value = '';

        if (type === 'parent') {
            btnParents.className = 'btn btn-sm btn-primary w-50 justify-content-center';
            btnStudents.className = 'btn btn-sm btn-outline-primary w-50 justify-content-center';
            listParents.classList.remove('d-none');
            listStudents.classList.add('d-none');
            searchInput.placeholder = 'جستجوی نام ولی...';
        } else {
            btnParents.className = 'btn btn-sm btn-outline-primary w-50 justify-content-center';
            btnStudents.className = 'btn btn-sm btn-primary w-50 justify-content-center';
            listParents.classList.add('d-none');
            listStudents.classList.remove('d-none');
            searchInput.placeholder = 'جستجوی نام دانش‌آموز...';
        }

        // نمایش مجدد همه آیتم‌های لیست فعال
        filterContacts();
    }

    // فیلتر و جستجوی زنده در لیست فعال
    function filterContacts() {
        const query = document.getElementById('contactSearchInput').value.toLowerCase().trim();
        const activeListId = currentContactType === 'parent' ? '#allParentsList' : '#allStudentsList';
        const items = document.querySelectorAll(activeListId + ' .contact-item');
        
        items.forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            if (name.includes(query)) {
                item.classList.remove('d-none');
            } else {
                item.classList.add('d-none');
            }
        });
    }
</script>

<?php require_once '../includes/footer.php'; ?>
