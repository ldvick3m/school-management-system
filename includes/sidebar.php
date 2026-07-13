<?php
/**
 * سایدبار پویا متناسب با نقش کاربر به همراه سوییچر فرزند
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// پیدا کردن مسیر نسبی روت برای پیوندهای سایدبار
$root = "";
if (file_exists("assets/css/style.css")) {
    $root = "";
} elseif (file_exists("../assets/css/style.css")) {
    $root = "../";
} elseif (file_exists("../../assets/css/style.css")) {
    $root = "../../";
}

$role = $_SESSION['role'] ?? '';
$pageName = basename($_SERVER['PHP_SELF']);

// هندل کردن سوییچ فرزند برای اولیا
if ($role === 'parent') {
    // گرفتن شناسه والد در جدول parents
    $stmtP = $pdo->prepare("SELECT id FROM parents WHERE user_id = ?");
    $stmtP->execute([$_SESSION['user_id']]);
    $parentId = $stmtP->fetchColumn();
    
    // گرفتن لیست فرزندان
    $stmtC = $pdo->prepare("SELECT s.id, u.full_name FROM students s 
        JOIN users u ON s.user_id = u.id 
        JOIN parent_student ps ON s.id = ps.student_id 
        WHERE ps.parent_id = ?");
    $stmtC->execute([$parentId]);
    $children = $stmtC->fetchAll();
    
    // تخصیص فرزند پیش‌فرض اول در صورت نبود سشن فعال
    if (count($children) > 0 && !isset($_SESSION['active_child_id'])) {
        $_SESSION['active_child_id'] = $children[0]['id'];
        $_SESSION['active_child_name'] = $children[0]['full_name'];
    }
    
    // اگر درخواست سوییچ فرزند رسید
    if (isset($_GET['switch_child']) && $parentId) {
        $reqChildId = (int)$_GET['switch_child'];
        // بررسی تعلق فرزند به والد جهت امنیت
        $stmtV = $pdo->prepare("SELECT COUNT(*) FROM parent_student WHERE parent_id = ? AND student_id = ?");
        $stmtV->execute([$parentId, $reqChildId]);
        if ($stmtV->fetchColumn() > 0) {
            $_SESSION['active_child_id'] = $reqChildId;
            // پیدا کردن نام فرزند جدید
            foreach ($children as $c) {
                if ($c['id'] == $reqChildId) {
                    $_SESSION['active_child_name'] = $c['full_name'];
                    break;
                }
            }
            // رفرش صفحه بدون کوئری استرینگ
            $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
            header("Location: " . $cleanUrl);
            exit;
        }
    }
}

// واکشی تعداد گفتگوهای خوانده‌نشده و کارهای معوقه جهت نمایش نوتیفیکیشن در سایدبار
$sidebarUnreadCount = 0;
$overdueCount = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmtS = $pdo->prepare("SELECT COUNT(DISTINCT sender_id) FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
        $stmtS->execute([$_SESSION['user_id']]);
        $sidebarUnreadCount = (int)$stmtS->fetchColumn();
        
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `status` != 'done' AND `deadline` < NOW() AND `assigned_to` = ?");
        $stmtCount->execute([$_SESSION['user_id']]);
        $overdueCount = (int)$stmtCount->fetchColumn();
    } catch (PDOException $ex) {
        $sidebarUnreadCount = 0;
        $overdueCount = 0;
    }
}
?>
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo" style="width: 38px; height: 38px; padding: 0; background: none !important; box-shadow: none !important; border: none !important; border-radius: 0 !important;">
            <img src="<?= $root ?>assets/images/logo.png" alt="دبستان هوشمند" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <div class="brand-name">دبستان هوشمند</div>
    </div>
    
    <!-- سوییچر فرزند (مخصوص پنل والدین) -->
    <?php if ($role === 'parent' && !empty($children)): ?>
        <div class="px-3 pt-3 pb-2 border-bottom border-secondary border-opacity-25">
            <label class="form-label text-white-50 small mb-1"><i class="bi bi-children me-1"></i>انتخاب و سوییچ فرزند:</label>
            <form method="GET" action="">
                <select name="switch_child" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                    <?php foreach ($children as $child): ?>
                        <option value="<?= $child['id'] ?>" <?= (isset($_SESSION['active_child_id']) && $_SESSION['active_child_id'] == $child['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($child['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    <?php endif; ?>
    
    <ul class="sidebar-menu">
        
        <!-- ۱. پنل دانش‌آموز -->
        <?php if ($role === 'student'): ?>
            <li class="menu-item <?= $pageName === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>student/dashboard.php"><i class="bi bi-speedometer2"></i>داشبورد دانش‌آموز</a>
            </li>
            <li class="menu-item <?= ($pageName === 'courses.php' || $pageName === 'course_details.php') ? 'active' : '' ?>">
                <a href="<?= $root ?>student/courses.php"><i class="bi bi-journal-bookmark-fill"></i>کلاس‌های من</a>
            </li>
            <li class="menu-item <?= $pageName === 'notepad.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>student/notepad.php"><i class="bi bi-journal-text"></i>دفترچه یادداشت</a>
            </li>
            <li class="menu-item <?= $pageName === 'analytics.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>student/analytics.php"><i class="bi bi-bar-chart-line-fill"></i>کارنامه و نمرات</a>
            </li>
            <li class="menu-item <?= $pageName === 'badges.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>student/badges.php"><i class="bi bi-trophy-fill"></i>مدال‌ها و دستاوردها</a>
            </li>
            <li class="menu-item <?= $pageName === 'tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>student/tasks.php"><i class="bi bi-journal-check"></i>وظایف مدرسه</a>
            </li>
            <li class="menu-item <?= $pageName === 'tasks_personal.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>student/tasks_personal.php"><i class="bi bi-clipboard-check"></i>کارهای شخصی من</a>
            </li>
            <li class="menu-item <?= $pageName === 'overdue_tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>overdue_tasks.php" class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>کارهای معوقه</span>
                    <span class="badge bg-danger rounded-circle <?= $overdueCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.75rem; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;">
                        <?= $overdueCount ?>
                    </span>
                </a>
            </li>
        
        <!-- ۲. پنل معلم -->
        <?php elseif ($role === 'teacher'): ?>
            <li class="menu-item <?= $pageName === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/dashboard.php"><i class="bi bi-speedometer2"></i>داشبورد معلم</a>
            </li>
            <li class="menu-item <?= $pageName === 'attendance.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/attendance.php"><i class="bi bi-calendar-check-fill"></i>حضور و غیاب دیجیتال</a>
            </li>
            <li class="menu-item <?= $pageName === 'discipline.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/discipline.php"><i class="bi bi-shield-exclamation-fill"></i>ثبت انضباط (رادار)</a>
            </li>
            <li class="menu-item <?= $pageName === 'grading.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/grading.php"><i class="bi bi-check2-square"></i>کارتابل تصحیح و نمرات</a>
            </li>
            <li class="menu-item <?= $pageName === 'my_topics.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/my_topics.php"><i class="bi bi-collection-play-fill"></i>مباحث منتشرشده</a>
            </li>
            <li class="menu-item <?= $pageName === 'messages.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/messages.php" class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-chat-dots-fill"></i>پیام‌ها و چت‌روم‌ها</span>
                    <span class="badge bg-danger rounded-circle sidebar-unread-badge <?= $sidebarUnreadCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.75rem; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;">
                        <?= $sidebarUnreadCount ?>
                    </span>
                </a>
            </li>
            <li class="menu-item <?= $pageName === 'tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/tasks.php"><i class="bi bi-clipboard-check"></i>تسک‌های من</a>
            </li>
            <li class="menu-item <?= $pageName === 'student_tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>teacher/student_tasks.php"><i class="bi bi-people-fill"></i>تسک‌های دانش‌آموزان</a>
            </li>
            <li class="menu-item <?= $pageName === 'overdue_tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>overdue_tasks.php" class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>کارهای معوقه</span>
                    <span class="badge bg-danger rounded-circle <?= $overdueCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.75rem; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;">
                        <?= $overdueCount ?>
                    </span>
                </a>
            </li>
        
        <!-- ۳. پنل والدین -->
        <?php elseif ($role === 'parent'): ?>
            <li class="menu-item <?= $pageName === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>parent/dashboard.php"><i class="bi bi-speedometer2"></i>داشبورد والدین</a>
            </li>
            <li class="menu-item <?= $pageName === 'analytics.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>parent/analytics.php"><i class="bi bi-graph-up-arrow"></i>رصد تحصیلی فرزند</a>
            </li>
            <li class="menu-item <?= $pageName === 'communication.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>parent/communication.php" class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-chat-text-fill"></i>ارتباط با مدرسه و معلمان</span>
                    <span class="badge bg-danger rounded-circle sidebar-unread-badge <?= $sidebarUnreadCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.75rem; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;">
                        <?= $sidebarUnreadCount ?>
                    </span>
                </a>
            </li>
        
        <!-- ۴. پنل اپراتور -->
        <?php elseif ($role === 'operator'): ?>
            <li class="menu-item <?= $pageName === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/dashboard.php"><i class="bi bi-speedometer2"></i>داشبورد اجرایی</a>
            </li>
            <li class="menu-item <?= $pageName === 'users.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/users.php"><i class="bi bi-people-fill"></i>ثبت‌نام و کاربران</a>
            </li>
            <li class="menu-item <?= $pageName === 'classes.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/classes.php"><i class="bi bi-house-gear-fill"></i>کلاس‌ها و تخصیص‌ها</a>
            </li>
            <li class="menu-item <?= $pageName === 'live_scheduler.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/live_scheduler.php"><i class="bi bi-clock-history"></i>برنامه‌ریزی لایوها</a>
            </li>
            <li class="menu-item <?= $pageName === 'tickets.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/tickets.php"><i class="bi bi-envelope-open-fill"></i>تیکت‌های پشتیبانی</a>
            </li>
            <li class="menu-item <?= $pageName === 'announcements.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/announcements.php"><i class="bi bi-megaphone-fill"></i>ارسال اعلانات عمومی</a>
            </li>
            <li class="menu-item <?= $pageName === 'badges.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/badges.php"><i class="bi bi-trophy-fill"></i>مدیریت نشان‌های مهارتی</a>
            </li>
            <li class="menu-item <?= $pageName === 'tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/tasks.php"><i class="bi bi-clipboard-check"></i>مدیریت تسک‌ها</a>
            </li>
            <li class="menu-item <?= $pageName === 'overdue_tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>overdue_tasks.php" class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>کارهای معوقه</span>
                    <span class="badge bg-danger rounded-circle <?= $overdueCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.75rem; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;">
                        <?= $overdueCount ?>
                    </span>
                </a>
            </li>
        
        <!-- ۵. پنل مدیر کل -->
        <?php elseif ($role === 'admin'): ?>
            <li class="menu-item <?= $pageName === 'dashboard.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>admin/dashboard.php"><i class="bi bi-speedometer2"></i>کنترل‌پنل مدیریتی</a>
            </li>
            <li class="menu-item <?= $pageName === 'classes_status.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>admin/classes_status.php"><i class="bi bi-house-gear-fill"></i>وضعیت کلاس‌ها</a>
            </li>
            <li class="menu-item <?= $pageName === 'structures.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>admin/structures.php"><i class="bi bi-diagram-3-fill"></i>تعریف ترم‌ها و دوره‌ها</a>
            </li>
            <li class="menu-item <?= $pageName === 'calendar.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>admin/calendar.php"><i class="bi bi-calendar3"></i>تقویم جامع تداخل‌ها</a>
            </li>
            <li class="menu-item <?= $pageName === 'analytics.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>admin/analytics.php"><i class="bi bi-pie-chart-fill"></i>گزارش‌های نظارتی کلان</a>
            </li>
            <li class="menu-item <?= $pageName === 'badges.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>operator/badges.php"><i class="bi bi-trophy-fill"></i>مدیریت نشان‌های مهارتی</a>
            </li>
            <li class="menu-item <?= $pageName === 'tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>admin/tasks.php"><i class="bi bi-clipboard-check"></i>مدیریت تسک‌ها</a>
            </li>
            <li class="menu-item <?= $pageName === 'overdue_tasks.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>overdue_tasks.php" class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>کارهای معوقه</span>
                    <span class="badge bg-danger rounded-circle <?= $overdueCount > 0 ? '' : 'd-none' ?>" style="font-size: 0.75rem; min-width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px;">
                        <?= $overdueCount ?>
                    </span>
                </a>
            </li>
            <li class="menu-item <?= $pageName === 'tasks_analytics.php' ? 'active' : '' ?>">
                <a href="<?= $root ?>admin/tasks_analytics.php"><i class="bi bi-graph-up-arrow text-info"></i>آنالیز و هوش تجاری تسک‌ها</a>
            </li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-footer">
        <a href="<?= $root ?>logout.php" class="text-danger text-decoration-none fw-bold small">
            <i class="bi bi-box-arrow-left me-1"></i>خروج از سیستم
        </a>
    </div>
</aside>
