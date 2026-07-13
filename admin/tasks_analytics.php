<?php
/**
 * داشبورد هوش تجاری (BI) و آنالیز پیشرفت تسک‌های معلمان و کارکنان
 */

require_once '../includes/header.php';
check_auth(['admin']);

// فیلترهای ارسالی
$userIdFilter = (int)($_GET['user_id'] ?? 0);
$groupFilter = $_GET['target_group'] ?? 'all'; // all, teachers, students

try {
    // ۱. دریافت آمار کلی تسک‌ها
    $stmtStats = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN `status` = 'todo' THEN 1 ELSE 0 END) as todo_count,
        SUM(CASE WHEN `status` = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN `status` = 'done' THEN 1 ELSE 0 END) as done_count,
        SUM(`timer_seconds`) as total_seconds
        FROM `tasks` WHERE 1=1");
    $stmtStats->execute();
    $stats = $stmtStats->fetch();

    $totalTasks = (int)$stats['total'];
    $doneTasks = (int)$stats['done_count'];
    $progressPercent = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0;
    
    // ۲. دریافت لیست معلمان برای دراپ‌دان فیلتر
    $teachers = $pdo->query("SELECT u.id, u.full_name FROM `users` u JOIN `teachers` t ON u.id = t.user_id WHERE u.status = 1 ORDER BY u.full_name ASC")->fetchAll();

    // ۳. کوئری ساخت تایملاین کاری و گزارش فعالیت‌ها بر اساس فیلتر
    $where = "WHERE 1=1";
    $params = [];
    if ($userIdFilter > 0) {
        $where .= " AND t.assigned_to = ?";
        $params[] = $userIdFilter;
    } elseif ($groupFilter === 'teachers') {
        $where .= " AND u.role = 'teacher'";
    } elseif ($groupFilter === 'students') {
        $where .= " AND u.role = 'student'";
    }

    $stmtTimeline = $pdo->prepare("SELECT t.*, 
        u.full_name as assignee_name, u.role as assignee_role,
        u_creator.full_name as creator_name
        FROM `tasks` t
        JOIN `users` u ON t.assigned_to = u.id
        JOIN `users` u_creator ON t.created_by = u_creator.id
        $where
        ORDER BY t.updated_at DESC, t.id DESC");
    $stmtTimeline->execute($params);
    $timelineTasks = $stmtTimeline->fetchAll();

    // ۴. دریافت جزئیات و عملکرد هر معلم به صورت تفکیک‌شده (آمار اختصاصی هر معلم)
    $stmtTeachersPerformance = $pdo->query("SELECT u.id, u.full_name,
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(t.timer_seconds) as total_seconds_spent
        FROM `users` u
        JOIN `teachers` te ON u.id = te.user_id
        LEFT JOIN `tasks` t ON u.id = t.assigned_to
        GROUP BY u.id
        ORDER BY completed_tasks DESC, total_seconds_spent DESC");
    $performanceList = $stmtTeachersPerformance->fetchAll();

} catch (PDOException $e) {
    die("خطا در بارگذاری آمار هوش تجاری تسک‌ها: " . $e->getMessage());
}

// تابع ثانیه‌شمار به فرمت خوانای ساعت و دقیقه
function formatSecondsToHours($seconds) {
    if ($seconds <= 0) return "۰ دقیقه";
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    
    $text = "";
    if ($h > 0) {
        $text .= $h . " ساعت و ";
    }
    $text .= $m . " دقیقه";
    return $text;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-bar-chart-line-fill text-primary me-1"></i> هوش تجاری (BI) و آنالیز عملکرد تسک‌ها</h4>
            <p class="text-secondary small mb-0">نظارت کلان بر راندمان کاری، زمان صرف‌شده و میزان پیشرفت برنامه‌ها در کل مدرسه</p>
        </div>
    </div>

    <!-- بخش اول: کارت‌های آمار سریع و درصد پیشرفت کلی -->
    <div class="row g-3 mb-4">
        <!-- کارت درصد پیشرفت کلی -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 small mb-1 fw-bold">درصد پیشرفت کل تسک‌ها</h6>
                        <h2 class="fw-bold mb-0"><?= $progressPercent ?>%</h2>
                    </div>
                    <i class="bi bi-graph-up fs-1 text-white-50"></i>
                </div>
                <div class="progress mt-3 bg-white bg-opacity-25" style="height: 8px;">
                    <div class="progress-bar bg-white" role="progressbar" style="width: <?= $progressPercent ?>%"></div>
                </div>
                <small class="text-white-50 d-block mt-2">انجام شده: <?= $doneTasks ?> از <?= $totalTasks ?> وظیفه ثبت‌شده</small>
            </div>
        </div>

        <!-- کارت توزیع وضعیت تسک‌ها -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white">
                <h6 class="text-secondary small mb-3 fw-bold"><i class="bi bi-pie-chart text-secondary me-1"></i> وضعیت وظایف فعال</h6>
                <div class="d-flex justify-content-around text-center mt-2">
                    <div>
                        <span class="text-secondary small d-block">پیش‌رو</span>
                        <strong class="fs-4 text-dark"><?= (int)$stats['todo_count'] ?></strong>
                    </div>
                    <div class="border-start border-secondary border-opacity-10"></div>
                    <div>
                        <span class="text-primary small d-block">در حال انجام</span>
                        <strong class="fs-4 text-primary"><?= (int)$stats['in_progress_count'] ?></strong>
                    </div>
                    <div class="border-start border-secondary border-opacity-10"></div>
                    <div>
                        <span class="text-success small d-block">انجام شده</span>
                        <strong class="fs-4 text-success"><?= (int)$stats['done_count'] ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- کارت مجموع زمان ثبت‌شده معلمان -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-dark text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 small mb-1 fw-bold">کل کارکرد ثبت‌شده (تایمر)</h6>
                        <h3 class="fw-bold mb-1 text-warning"><?= formatSecondsToHours((int)$stats['total_seconds']) ?></h3>
                        <small class="text-white-50">مجموع زمان متمرکز صرف‌شده معلمان بر روی وظایف</small>
                    </div>
                    <i class="bi bi-clock-history fs-1 text-white-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- بخش دوم: فیلترها و تایملاین فعالیت معلمان -->
    <div class="row g-4 mb-4">
        <!-- ستون راست: تایملاین کاری و وقایع -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0 text-dark">تایملاین کاری و گزارش فعالیت افراد</h5>
                    <!-- فرم فیلتر سریع -->
                    <form method="GET" action="" class="d-flex gap-2">
                        <select name="target_group" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?= $groupFilter === 'all' ? 'selected' : '' ?>>همه گروه‌ها</option>
                            <option value="teachers" <?= $groupFilter === 'teachers' ? 'selected' : '' ?>>فقط معلمان</option>
                            <option value="students" <?= $groupFilter === 'students' ? 'selected' : '' ?>>فقط دانش‌آموزان</option>
                        </select>
                        <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="0">انتخاب شخص...</option>
                            <?php foreach ($teachers as $tc): ?>
                                <option value="<?= $tc['id'] ?>" <?= $userIdFilter == $tc['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tc['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body" style="max-height: 550px; overflow-y: auto;">
                    <?php if (empty($timelineTasks)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-timeline fs-1 text-secondary"></i>
                            <p class="text-secondary mt-2">هیچ وظیفه‌ای با فیلتر مشخص شده ثبت نشده است.</p>
                        </div>
                    <?php else: ?>
                        <div class="position-relative border-start ps-3 ms-2" style="border-color: #E2E8F0 !important;">
                            <?php foreach ($timelineTasks as $t): 
                                $statusIcon = 'bi-hourglass';
                                $statusColor = 'bg-secondary';
                                if ($t['status'] === 'in_progress') { $statusIcon = 'bi-play-fill'; $statusColor = 'bg-primary'; }
                                elseif ($t['status'] === 'done') { $statusIcon = 'bi-check-lg'; $statusColor = 'bg-success'; }
                                
                                $ago = date('Y/m/d H:i', strtotime($t['updated_at']));
                            ?>
                                <div class="mb-4 position-relative">
                                    <!-- دایره وضعیت تایملاین -->
                                    <span class="position-absolute d-flex align-items-center justify-content-center rounded-circle text-white <?= $statusColor ?>" 
                                          style="width: 24px; height: 24px; right: -25px; top: 0; font-size: 0.8rem;">
                                        <i class="bi <?= $statusIcon ?>"></i>
                                    </span>
                                    <div class="bg-light p-3 rounded-3 border ms-2">
                                        <div class="d-flex justify-content-between align-items-start mb-1 flex-wrap">
                                            <strong class="text-dark small"><?= htmlspecialchars($t['assignee_name']) ?> (<?= get_role_fa($t['assignee_role']) ?>)</strong>
                                            <span class="text-secondary" style="font-size: 0.72rem;"><i class="bi bi-clock me-1"></i><?= $ago ?></span>
                                        </div>
                                        <span class="small d-block text-secondary mb-1">
                                            وظیفه «<strong><?= htmlspecialchars($t['title']) ?></strong>» را به وضعیت 
                                            <strong>
                                                <?php 
                                                if ($t['status'] === 'todo') echo 'پیش‌رو';
                                                elseif ($t['status'] === 'in_progress') echo 'در حال انجام';
                                                elseif ($t['status'] === 'done') echo 'انجام شده';
                                                ?>
                                            </strong> تغییر داد.
                                        </span>
                                        <div class="d-flex justify-content-between align-items-center mt-2 border-top pt-2">
                                            <small class="text-secondary" style="font-size: 0.7rem;">ارجاع‌دهنده: <?= htmlspecialchars($t['creator_name']) ?></small>
                                            <small class="text-success fw-bold" style="font-size: 0.72rem;"><i class="bi bi-stopwatch me-1"></i>مدت کارکرد: <?= formatSecondsToHours($t['timer_seconds']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ستون چپ: رتبه‌بندی عملکرد معلمان -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-warning me-1"></i> راندمان کاری معلمان</h5>
                </div>
                <div class="card-body p-0" style="max-height: 550px; overflow-y: auto;">
                    <ul class="list-group list-group-flush">
                        <?php 
                        $rank = 1;
                        foreach ($performanceList as $perf): 
                            $percent = $perf['total_tasks'] > 0 ? round(($perf['completed_tasks'] / $perf['total_tasks']) * 100) : 0;
                            $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : ''));
                        ?>
                            <li class="list-group-item p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold small text-muted" style="min-width: 20px;"><?= $medal ?: $rank ?></span>
                                    <div>
                                        <strong class="text-dark small d-block"><?= htmlspecialchars($perf['full_name']) ?></strong>
                                        <small class="text-secondary" style="font-size: 0.75rem;">زمان متمرکز: <?= formatSecondsToHours($perf['total_seconds_spent']) ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold" style="font-size: 0.75rem;">
                                        <?= $perf['completed_tasks'] ?> / <?= $perf['total_tasks'] ?> انجام شده
                                    </span>
                                    <div class="progress mt-1.5" style="width: 80px; height: 5px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </div>
                            </li>
                        <?php 
                            $rank++;
                        endforeach; 
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
