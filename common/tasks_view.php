<?php
/**
 * نمای مشترک و تعاملی مدیریت وظایف (بورد کانبان روتین و کاستوم)
 * این فایل توسط فایل‌های وظایف نقش‌های مختلف ایمپورت می‌شود.
 */

// متغیرهای ورودی پیش‌فرض در صورت عدم تعریف در فایل والد
$viewMode = $viewMode ?? 'normal'; // normal, personal, assigned
$filterAssignedTo = $filterAssignedTo ?? null;
$filterCreatedBy = $filterCreatedBy ?? null;

// ۱. واکشی لیست کلاس‌ها برای فرم‌های واگذاری تسک گروهی
$classesList = [];
try {
    $classesList = $pdo->query("SELECT * FROM `classes` ORDER BY `grade_level` ASC, `class_name` ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch classes: " . $e->getMessage());
}

$teacherClasses = [];
if ($currentUser['role'] === 'teacher') {
    try {
        $stmtTC = $pdo->prepare("SELECT DISTINCT c.id, c.class_name 
            FROM `classes` c 
            JOIN `class_teacher_course` ctc ON c.id = ctc.class_id
            JOIN `teachers` t ON ctc.teacher_id = t.id
            WHERE t.user_id = ? ORDER BY c.class_name ASC");
        $stmtTC->execute([$currentUser['id']]);
        $teacherClasses = $stmtTC->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch teacher classes: " . $e->getMessage());
    }
}

// ۲. واکشی لیست معلمان برای فرم‌های واگذاری تسک
$teachersList = [];
try {
    $teachersList = $pdo->query("SELECT u.id, u.full_name, u.username FROM `users` u JOIN `teachers` t ON u.id = t.user_id WHERE u.status = 1 ORDER BY u.full_name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch teachers: " . $e->getMessage());
}

// ۳. واکشی لیست دانش‌آموزان کلاس‌های تحت پوشش معلم (اگر نقش معلم باشد) یا همه دانش‌آموزان
$studentsList = [];
try {
    if ($currentUser['role'] === 'teacher') {
        // دانش‌آموزان کلاس‌هایی که معلم در آن‌ها تدریس می‌کند
        $stmtS = $pdo->prepare("SELECT DISTINCT u.id, u.full_name, u.username, c.class_name 
            FROM `users` u 
            JOIN `students` s ON u.id = s.user_id 
            JOIN `class_student` cs ON s.id = cs.student_id
            JOIN `class_teacher_course` ctc ON cs.class_id = ctc.class_id
            JOIN `teachers` t ON ctc.teacher_id = t.id
            JOIN `classes` c ON cs.class_id = c.id
            WHERE t.user_id = ? AND u.status = 1
            ORDER BY c.class_name ASC, u.full_name ASC");
        $stmtS->execute([$currentUser['id']]);
        $studentsList = $stmtS->fetchAll();
    } else {
        $stmtS = $pdo->query("SELECT u.id, u.full_name, u.username FROM `users` u JOIN `students` s ON u.id = s.user_id WHERE u.status = 1 ORDER BY u.full_name ASC");
        $studentsList = $stmtS->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Failed to fetch students: " . $e->getMessage());
}

// ۴. ساخت شرط کوئری تسک‌ها بر اساس نقش و مود نمایش
$whereClause = "WHERE 1=1";
$queryParams = [];

if ($currentUser['role'] === 'student') {
    if ($viewMode === 'personal') {
        $whereClause .= " AND t.assigned_to = ? AND t.created_by = ?";
        $queryParams[] = $currentUser['id'];
        $queryParams[] = $currentUser['id'];
    } else { // assigned
        $whereClause .= " AND t.assigned_to = ? AND t.created_by != ?";
        $queryParams[] = $currentUser['id'];
        $queryParams[] = $currentUser['id'];
    }
} elseif ($currentUser['role'] === 'teacher') {
    if ($viewMode === 'student_tasks') {
        // معلم فقط تسک‌هایی که خودش ایجاد کرده و به دانش‌آموزان تخصیص داده است را می‌بیند
        $whereClause .= " AND t.created_by = ? AND u_assignee.role = 'student'";
        $queryParams[] = $currentUser['id'];
    } else {
        // معلم فقط تسک‌هایی که به خودش تخصیص یافته است را می‌بیند
        $whereClause .= " AND t.assigned_to = ?";
        $queryParams[] = $currentUser['id'];
    }
} elseif ($currentUser['role'] === 'operator') {
    // اپراتور تسک‌های خودش و تسک‌هایی که ساخته یا به دبیران/دانش‌آموزان تخصیص داده است را می‌بیند
    $whereClause .= " AND (t.assigned_to = ? OR t.created_by = ? OR t.task_type = 'custom' OR t.task_type = 'routine')";
    $queryParams[] = $currentUser['id'];
    $queryParams[] = $currentUser['id'];
} else { // admin
    // مدیر به همه دسترسی دارد
}

// واکشی کل تسک‌ها
$tasks = [];
try {
    $stmtT = $pdo->prepare("SELECT t.*, 
        u_creator.full_name as creator_name, u_creator.role as creator_role,
        u_assignee.full_name as assignee_name, u_assignee.role as assignee_role,
        CASE WHEN t.is_timer_running = 1 AND t.timer_last_started IS NOT NULL 
             THEN GREATEST(0, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(t.timer_last_started))
             ELSE 0 END as elapsed_seconds
        FROM `tasks` t
        JOIN `users` u_creator ON t.created_by = u_creator.id
        JOIN `users` u_assignee ON t.assigned_to = u_assignee.id
        $whereClause
        ORDER BY t.deadline ASC, t.id DESC");
    $stmtT->execute($queryParams);
    $tasks = $stmtT->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch tasks: " . $e->getMessage());
}

// تفکیک تسک‌ها برای رندر بورد کانبان
$routineTasks = ['todo' => [], 'in_progress' => [], 'done' => []];
$customTasks = ['todo' => [], 'in_progress' => [], 'done' => []];

foreach ($tasks as $t) {
    $type = $t['task_type']; // routine, custom
    $status = $t['status']; // todo, in_progress, done
    
    // ارزیابی وضعیت ددلاین (هشدار معوقه یا نزدیک)
    $t['border_color_class'] = 'border-info';
    $t['is_overdue'] = false;
    $t['is_near_deadline'] = false;
    
    if ($status !== 'done') {
        $deadlineTime = strtotime($t['deadline']);
        $now = time();
        if ($deadlineTime < $now) {
            $t['border_color_class'] = 'border-danger';
            $t['is_overdue'] = true;
        } elseif ($deadlineTime - $now <= 86400) { // کمتر از ۲۴ ساعت
            $t['border_color_class'] = 'border-warning';
            $t['is_near_deadline'] = true;
        }
    } else {
        $t['border_color_class'] = 'border-success';
    }

    if ($type === 'routine') {
        $routineTasks[$status][] = $t;
    } else {
        $customTasks[$status][] = $t;
    }
}
?>

<style>
    /* نمایش تقویم شمسی جلوی مودال‌های بوت‌استرپ */
    #jdp-container, .jdp-container {
        z-index: 11000 !important;
    }

    .kanban-board {
        display: flex;
        gap: 1.5rem;
        overflow-x: auto;
        padding-bottom: 1rem;
        min-height: 550px;
    }
    .kanban-column {
        flex: 1;
        min-width: 300px;
        background: #F8FAFC;
        border-radius: 12px;
        border: 1px solid #E2E8F0;
        display: flex;
        flex-direction: column;
        max-height: 800px;
    }
    .kanban-column-header {
        padding: 1rem;
        font-weight: 700;
        border-bottom: 2px solid #E2E8F0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .kanban-cards {
        padding: 0.75rem;
        overflow-y: auto;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .kanban-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        padding: 1rem;
        cursor: grab;
        border-right: 5px solid #E2E8F0;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kanban-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .kanban-card.dragging {
        opacity: 0.5;
        cursor: grabbing;
    }
    .priority-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-weight: 700;
    }
    .priority-normal { background-color: #E2E8F0; color: #475569; }
    .priority-urgent { background-color: #FEF3C7; color: #D97706; }
    .priority-critical { background-color: #FEE2E2; color: #DC2626; }
    
    .timer-btn {
        border: none;
        background: none;
        padding: 0;
        cursor: pointer;
        outline: none;
        display: inline-flex;
        align-items: center;
    }
    .timer-container {
        font-family: monospace;
        font-weight: bold;
    }
    .btn-xs {
        padding: 0.15rem 0.4rem;
        font-size: 0.75rem;
        border-radius: 4px;
    }
</style>

<div class="container-fluid">
    <!-- هدر صفحه -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <?php 
                if ($viewMode === 'personal') echo '📋 برنامه‌ریزی و کارهای شخصی من';
                elseif ($viewMode === 'assigned') echo '🏫 وظایف و تسک‌های تخصیص‌یافته مدرسه';
                elseif ($viewMode === 'student_tasks') echo '👨‍🎓 تسک‌های ارجاع شده به دانش‌آموزان';
                else echo '📋 سیستم مدیریت وظایف هوشمند';
                ?>
            </h4>
            <p class="text-secondary small mb-0">مدیریت وظایف روزانه، تکرار شونده و پروژه‌ای با تایمر تجمیعی دقیق</p>
        </div>
        
        <div class="d-flex gap-2">
            <?php if ($currentUser['role'] !== 'student' || $viewMode === 'personal'): ?>
                <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                    <i class="bi bi-plus-lg me-1"></i> ایجاد وظیفه جدید
                </button>
            <?php endif; ?>
            <?php if (($currentUser['role'] === 'admin' || $currentUser['role'] === 'operator') && $viewMode === 'normal'): ?>
                <button class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#createBulkTaskModal">
                    <i class="bi bi-people-fill me-1"></i> تخصیص گروهی (Bulk)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- تب‌های تفکیک روتین و کاستوم -->
    <ul class="nav nav-pills mb-4 bg-white p-1 rounded-3 shadow-xs border" id="taskTab" role="tablist" style="width: fit-content;">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-4 fw-bold" id="custom-tab" data-bs-toggle="pill" data-bs-target="#custom-pane" type="button" role="tab" aria-controls="custom-pane" aria-selected="true">
                <i class="bi bi-clipboard-check me-1"></i> وظایف کاستوم (سفارشی)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 fw-bold" id="routine-tab" data-bs-toggle="pill" data-bs-target="#routine-pane" type="button" role="tab" aria-controls="routine-pane" aria-selected="false">
                <i class="bi bi-arrow-repeat me-1"></i> وظایف روتین (تکراری روزانه/هفتگی)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="taskTabContent">
        <!-- پنل وظایف سفارشی -->
        <div class="tab-pane fade show active" id="custom-pane" role="tabpanel" aria-labelledby="custom-tab">
            <div class="kanban-board">
                <!-- ستون Todo -->
                <div class="kanban-column" data-status="todo" ondragover="allowDrop(event)" ondrop="drop(event, 'custom')">
                    <div class="kanban-column-header">
                        <span class="text-secondary"><i class="bi bi-hourglass-split me-1"></i> پیش‌رو</span>
                        <span class="badge bg-secondary rounded-pill"><?= count($customTasks['todo']) ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($customTasks['todo'] as $task) renderTaskCard($task); ?>
                    </div>
                </div>
                <!-- ستون In Progress -->
                <div class="kanban-column" data-status="in_progress" ondragover="allowDrop(event)" ondrop="drop(event, 'custom')">
                    <div class="kanban-column-header text-primary">
                        <span><i class="bi bi-play-circle-fill me-1 text-primary"></i> در حال انجام</span>
                        <span class="badge bg-primary rounded-pill"><?= count($customTasks['in_progress']) ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($customTasks['in_progress'] as $task) renderTaskCard($task); ?>
                    </div>
                </div>
                <!-- ستون Done -->
                <div class="kanban-column" data-status="done" ondragover="allowDrop(event)" ondrop="drop(event, 'custom')">
                    <div class="kanban-column-header text-success">
                        <span><i class="bi bi-check-circle-fill me-1 text-success"></i> انجام شده</span>
                        <span class="badge bg-success rounded-pill"><?= count($customTasks['done']) ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($customTasks['done'] as $task) renderTaskCard($task); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- پنل وظایف روتین -->
        <div class="tab-pane fade" id="routine-pane" role="tabpanel" aria-labelledby="routine-tab">
            <div class="kanban-board">
                <!-- ستون Todo -->
                <div class="kanban-column" data-status="todo" ondragover="allowDrop(event)" ondrop="drop(event, 'routine')">
                    <div class="kanban-column-header">
                        <span class="text-secondary"><i class="bi bi-hourglass-split me-1"></i> پیش‌رو</span>
                        <span class="badge bg-secondary rounded-pill"><?= count($routineTasks['todo']) ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($routineTasks['todo'] as $task) renderTaskCard($task); ?>
                    </div>
                </div>
                <!-- ستون In Progress -->
                <div class="kanban-column" data-status="in_progress" ondragover="allowDrop(event)" ondrop="drop(event, 'routine')">
                    <div class="kanban-column-header text-primary">
                        <span><i class="bi bi-play-circle-fill me-1 text-primary"></i> در حال انجام</span>
                        <span class="badge bg-primary rounded-pill"><?= count($routineTasks['in_progress']) ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($routineTasks['in_progress'] as $task) renderTaskCard($task); ?>
                    </div>
                </div>
                <!-- ستون Done -->
                <div class="kanban-column" data-status="done" ondragover="allowDrop(event)" ondrop="drop(event, 'routine')">
                    <div class="kanban-column-header text-success">
                        <span><i class="bi bi-check-circle-fill me-1 text-success"></i> انجام شده</span>
                        <span class="badge bg-success rounded-pill"><?= count($routineTasks['done']) ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($routineTasks['done'] as $task) renderTaskCard($task); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- تابع رندر کارت تسک -->
<?php
function renderTaskCard($task) {
    global $currentUser;
    $badgeClass = 'priority-normal';
    $badgeText = 'عادی';
    if ($task['priority'] === 'urgent') { $badgeClass = 'priority-urgent'; $badgeText = 'فوری'; }
    elseif ($task['priority'] === 'critical') { $badgeClass = 'priority-critical'; $badgeText = 'اضطراری'; }

    $timerState = $task['is_timer_running'] ? 'running' : 'paused';
    $timerClass = $task['is_timer_running'] ? 'bi-pause-circle-fill text-danger' : 'bi-play-circle-fill text-success';
    $isOwner = ($task['assigned_to'] == $currentUser['id'] || $task['created_by'] == $currentUser['id'] || in_array($currentUser['role'], ['admin', 'operator', 'teacher']));
    
    // محاسبه پس‌زمینه کارت بر اساس تاخیر
    $bgStyle = "";
    if ($task['status'] !== 'done') {
        if ($task['is_overdue']) {
            $bgStyle = "background-color: #FFF5F5;";
        } elseif ($task['is_near_deadline']) {
            $bgStyle = "background-color: #FFFDF5;";
        }
    }
?>
    <div class="kanban-card <?= $task['border_color_class'] ?>" 
         id="task-card-<?= $task['id'] ?>" 
         draggable="true" 
         ondragstart="drag(event, <?= $task['id'] ?>)" 
         ondragend="dragEnd(event)"
         onclick="openTaskDetails(<?= $task['id'] ?>, event)"
         style="<?= $bgStyle ?> cursor: pointer;">
        
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="priority-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            <small class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-calendar-event me-0.5"></i> <?= to_shamsi($task['deadline']) ?> ساعت <?= date('H:i', strtotime($task['deadline'])) ?></small>
        </div>

        <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($task['title']) ?></h6>
        <p class="text-secondary small mb-2 text-truncate" style="max-height: 40px;"><?= htmlspecialchars($task['description']) ?></p>

        <div class="d-flex justify-content-between align-items-center mt-2 border-top pt-2">
            <!-- زمان‌سنج تجمیعی روی کارت -->
            <div class="d-flex align-items-center gap-1.5">
                <?php if ($task['status'] !== 'done' && $isOwner): ?>
                    <button type="button" class="timer-btn" onclick="toggleTimer(<?= $task['id'] ?>, event)" id="timer-btn-<?= $task['id'] ?>">
                        <i class="bi <?= $timerClass ?> fs-4" id="timer-icon-<?= $task['id'] ?>"></i>
                    </button>
                    <?php 
                    $displaySeconds = $task['timer_seconds'];
                    if ($task['is_timer_running'] && isset($task['elapsed_seconds'])) {
                        $displaySeconds += max(0, (int)$task['elapsed_seconds']);
                    }
                    ?>
                    <span class="timer-container small text-secondary" 
                          id="timer-display-<?= $task['id'] ?>" 
                          data-seconds="<?= $displaySeconds ?>" 
                          data-state="<?= $timerState ?>">
                        <?= formatTimer($displaySeconds) ?>
                    </span>
                <?php else: ?>
                    <!-- در صورت done بودن فقط زمان صرف شده را چاپ می‌کنیم -->
                    <span class="small text-success fw-bold"><i class="bi bi-clock-history me-1"></i>زمان: <?= formatTimer($task['timer_seconds']) ?></span>
                <?php endif; ?>
            </div>

            <!-- مشخصات فرد واگذارکننده یا انجام‌دهنده -->
            <small class="text-secondary fw-bold" style="font-size: 0.7rem;">
                <?php if ($currentUser['id'] == $task['assigned_to']): ?>
                    <i class="bi bi-person-fill text-primary" title="سازنده: <?= htmlspecialchars($task['creator_name']) ?>"></i> <?= htmlspecialchars($task['creator_name']) ?>
                <?php else: ?>
                    <i class="bi bi-person-check-fill text-success" title="انجام‌دهنده: <?= htmlspecialchars($task['assignee_name']) ?>"></i> <?= htmlspecialchars($task['assignee_name']) ?>
                <?php endif; ?>
            </small>
        </div>

        <div class="text-end mt-2">
            <button class="btn btn-link btn-sm p-0 fw-bold text-decoration-none" onclick="openTaskDetails(<?= $task['id'] ?>, event)">
                مشاهده و گفتگو <i class="bi bi-arrow-left"></i>
            </button>
        </div>
    </div>
<?php
}

// تابع کمکی فرمت‌دهی زمان ثانیه‌شمار
function formatTimer($seconds) {
    if ($seconds <= 0) return "00:00:00";
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}
?>

<!-- مودال ایجاد وظیفه جدید شخصی / تک نفره -->
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title fw-bold text-dark" id="createTaskModalLabel">ایجاد وظیفه جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="createTaskForm" onsubmit="submitCreateTask(event)">
                    <input type="hidden" name="action" value="create_task">
                    
                    <div class="mb-3">
                        <label class="form-label">عنوان تسک *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثلاً: طراحی آزمون مستمر هماهنگ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیحات تکمیلی</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="توضیحات و نیازمندی‌های انجام تسک..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">نوع تسک *</label>
                            <select name="task_type" class="form-select" id="createTaskTypeSelect" onchange="toggleFormFrequency()" required>
                                <option value="custom">سفارشی (کاستوم)</option>
                                <?php if ($currentUser['role'] !== 'student' || $viewMode === 'personal'): ?>
                                    <option value="routine">روتین (تکرار شونده)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="formFrequencyGroup">
                            <label class="form-label">تکرار روتین *</label>
                            <select name="frequency" class="form-select">
                                <option value="daily">روزانه</option>
                                <option value="weekly">هفتگی</option>
                                <option value="monthly">ماهانه</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">اولویت انجام</label>
                            <select name="priority" class="form-select">
                                <option value="normal">عادی</option>
                                <option value="urgent">فوری</option>
                                <option value="critical">اضطراری</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="formDeadlineDateGroup">
                            <label class="form-label">تاریخ ددلاین *</label>
                            <input type="text" name="deadline_date" id="createTaskDeadlineDateInput" data-jdp class="form-control" placeholder="۱۴۰۵/۰۴/۲۲" autocomplete="off" required>
                        </div>
                        <div class="col-md-4" id="formDeadlineTimeGroup">
                            <label class="form-label">ساعت ددلاین *</label>
                            <input type="time" name="deadline_time" id="createTaskDeadlineTimeInput" class="form-control" value="23:59" required>
                        </div>
                    </div>

                    <!-- بخش انتخاب شخص ارجاع شونده -->
                    <div class="mb-3">
                        <?php if ($currentUser['role'] === 'teacher'): ?>
                            <label class="form-label">ارجاع به *</label>
                            <select name="assigned_target" id="teacherAssignedTargetSelect" class="form-select mb-3" onchange="toggleTeacherAssignType()" required>
                                <option value="myself">خودم (دبیر)</option>
                                <option value="students">دانش‌آموزان</option>
                            </select>
                            
                            <!-- بخش انتخاب کلاس و دانش‌آموزان که به صورت پیش‌فرض مخفی است -->
                            <div id="teacherStudentsAssignSection" class="d-none border p-3 rounded-3 bg-light">
                                <div class="mb-3">
                                    <label class="form-label">انتخاب کلاس *</label>
                                    <select name="teacher_assign_class_id" id="teacherAssignClassSelect" class="form-select" onchange="loadClassStudentsForTeacher()">
                                        <option value="">انتخاب کلاس...</option>
                                        <?php foreach ($teacherClasses as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2 d-none" id="studentCheckboxesContainerHeader">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="teacherSelectAllStudentsCheckbox" onchange="toggleAllTeacherStudents(this)">
                                        <label class="form-check-label fw-bold text-primary" for="teacherSelectAllStudentsCheckbox">
                                            انتخاب همه دانش‌آموزان کلاس
                                        </label>
                                    </div>
                                </div>
                                <div id="teacherStudentsCheckboxesContainer" class="d-flex flex-column gap-1" style="max-height: 200px; overflow-y: auto; padding-right: 5px;">
                                    <!-- چک‌باکس‌های دانش‌آموزان اینجا لود می‌شوند -->
                                </div>
                            </div>
                        <?php else: ?>
                            <label class="form-label">ارجاع به *</label>
                            <select name="assigned_to" class="form-select" required>
                                <?php if ($viewMode === 'personal' || $currentUser['role'] === 'student'): ?>
                                    <option value="<?= $currentUser['id'] ?>"><?= htmlspecialchars($currentUser['full_name']) ?> (خودم)</option>
                                <?php else: ?>
                                    <option value="<?= $currentUser['id'] ?>"><?= htmlspecialchars($currentUser['full_name']) ?> (خودم)</option>
                                    <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'operator'): ?>
                                        <optgroup label="معلمان مدرسه">
                                            <?php foreach ($teachersList as $tc): ?>
                                                <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['full_name']) ?> (دبیر)</option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="دانش‌آموزان">
                                            <?php foreach ($studentsList as $st): ?>
                                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?> (دانش‌آموز)</option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php if ($currentUser['role'] === 'admin'): ?>
                                            <optgroup label="اپراتورها">
                                                <?php 
                                                $ops = $pdo->query("SELECT id, full_name FROM users WHERE role = 'operator' AND status = 1")->fetchAll();
                                                foreach ($ops as $op):
                                                ?>
                                                    <option value="<?= $op['id'] ?>"><?= htmlspecialchars($op['full_name']) ?> (اپراتور)</option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- فایل ضمیمه تسک -->
                    <div class="mb-3">
                        <label class="form-label">فایل ضمیمه (پژوهش، نمونه سوال، فایل صوتی/تصویری)</label>
                        <input type="file" name="attachment_file" class="form-control">
                    </div>

                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">ثبت نهایی تسک</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- مودال ایجاد وظیفه گروهی (Bulk) مخصوص مدیر و اپراتور -->
<div class="modal fade" id="createBulkTaskModal" tabindex="-1" aria-labelledby="createBulkTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title fw-bold text-dark" id="createBulkTaskModalLabel">تخصیص وظیفه گروهی (Bulk)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="createBulkTaskForm" onsubmit="submitCreateBulkTask(event)">
                    <input type="hidden" name="action" value="create_task_bulk">
                    
                    <div class="mb-3">
                        <label class="form-label">عنوان تسک گروهی *</label>
                        <input type="text" name="title" class="form-control" placeholder="مثلاً: ثبت نمرات مستمر نوبت اول" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیحات تسک</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">گروه هدف *</label>
                            <select name="target_group" id="bulkTargetGroupSelect" class="form-select" onchange="toggleBulkGroupFields()" required>
                                <option value="">انتخاب گروه...</option>
                                <option value="all_teachers">تمامی معلمان</option>
                                <option value="all_students">تمامی دانش‌آموزان مدرسه</option>
                                <option value="class_students">دانش‌آموزان یک کلاس خاص</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="bulkClassSelectGroup">
                            <label class="form-label">انتخاب کلاس *</label>
                            <select name="class_id" class="form-select">
                                <?php foreach ($classesList as $cl): ?>
                                    <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">نوع تسک *</label>
                            <select name="task_type" class="form-select" id="bulkTaskTypeSelect" onchange="toggleBulkFrequency()" required>
                                <option value="custom">سفارشی (کاستوم)</option>
                                <option value="routine">روتین (تکرار شونده)</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="bulkFrequencyGroup">
                            <label class="form-label">تکرار روتین *</label>
                            <select name="frequency" class="form-select">
                                <option value="daily">روزانه</option>
                                <option value="weekly">هفتگی</option>
                                <option value="monthly">ماهانه</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">اولویت</label>
                            <select name="priority" class="form-select">
                                <option value="normal">عادی</option>
                                <option value="urgent">فوری</option>
                                <option value="critical">اضطراری</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="bulkDeadlineDateGroup">
                            <label class="form-label">تاریخ ددلاین *</label>
                            <input type="text" name="deadline_date" id="bulkDeadlineDateInput" data-jdp class="form-control" placeholder="۱۴۰۵/۰۴/۲۲" autocomplete="off" required>
                        </div>
                        <div class="col-md-4" id="bulkDeadlineTimeGroup">
                            <label class="form-label">ساعت ددلاین *</label>
                            <input type="time" name="deadline_time" id="bulkDeadlineTimeInput" class="form-control" value="23:59" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">فایل ضمیمه</label>
                        <input type="file" name="attachment_file" class="form-control">
                    </div>

                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">ارسال برای اعضای گروه</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- مودال بزرگ نمایش جزئیات تسک، گفتگو، ضمیمه‌ها و سیستم منشن -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title fw-bold text-dark" id="taskDetailsModalLabel">بررسی جزئیات وظیفه</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="taskDetailsModalContent">
                <!-- به صورت داینامیک توسط JS پر می‌شود -->
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * رفع مشکل تقویم شمسی در مودال‌های Bootstrap 5
     * Bootstrap 5 یک FocusTrap فعال دارد که کلیک/focus روی عناصر خارج مودال را block می‌کند.
     * این patch هنگام DOMContentLoaded اجرا می‌شه و listener اضافه می‌کنه که focus trap رو
     * برای عناصر jdp غیرفعال کنه.
     */
    (function patchBootstrapFocusTrap() {
        // هر بار که مودال باز می‌شه، focusin رو intercept می‌کنیم
        document.addEventListener('focusin', function(e) {
            // اگه target داخل یه container جدول شمسی (jdp) بود، bubble رو stop کن
            const t = e.target;
            if (t && (
                (t.id && t.id.startsWith('jdp')) ||
                t.classList.contains('jdp-cell') ||
                t.closest('[id^="jdp"]') ||
                t.closest('.jdp-container') ||
                t.closest('#jdp-container')
            )) {
                e.stopImmediatePropagation();
            }
        }, true);

        // همچنین mousedown خارج از مودال رو برای jdp allow کنیم
        document.addEventListener('click', function(e) {
            const t = e.target;
            if (t && (t.closest('[id^="jdp"]') || t.closest('.jdp-container') || t.closest('#jdp-container'))) {
                e.stopPropagation();
            }
        }, true);
    })();

    // تنظیم فیلد تکرار و ددلاین در فرم ثبت تسک تکی
    function toggleFormFrequency() {
        const type = document.getElementById('createTaskTypeSelect').value;
        const freqGroup = document.getElementById('formFrequencyGroup');
        const dateGroup = document.getElementById('formDeadlineDateGroup');
        const timeGroup = document.getElementById('formDeadlineTimeGroup');
        
        const dateInput = document.getElementById('createTaskDeadlineDateInput');
        const timeInput = document.getElementById('createTaskDeadlineTimeInput');
        
        if (type === 'routine') {
            freqGroup.classList.remove('d-none');
            dateGroup.classList.add('d-none');
            timeGroup.classList.add('d-none');
            
            dateInput.removeAttribute('required');
            timeInput.removeAttribute('required');
        } else {
            freqGroup.classList.add('d-none');
            dateGroup.classList.remove('d-none');
            timeGroup.classList.remove('d-none');
            
            dateInput.setAttribute('required', 'required');
            timeInput.setAttribute('required', 'required');
        }
    }

    // تنظیم فیلد کلاس در فرم تسک گروهی
    function toggleBulkGroupFields() {
        const group = document.getElementById('bulkTargetGroupSelect').value;
        const classGroup = document.getElementById('bulkClassSelectGroup');
        if (group === 'class_students') {
            classGroup.classList.remove('d-none');
        } else {
            classGroup.classList.add('d-none');
        }
    }

    // تنظیم فیلد فرکانس و ددلاین در فرم تسک گروهی
    function toggleBulkFrequency() {
        const type = document.getElementById('bulkTaskTypeSelect').value;
        const freqGroup = document.getElementById('bulkFrequencyGroup');
        const dateGroup = document.getElementById('bulkDeadlineDateGroup');
        const timeGroup = document.getElementById('bulkDeadlineTimeGroup');
        
        const dateInput = document.getElementById('bulkDeadlineDateInput');
        const timeInput = document.getElementById('bulkDeadlineTimeInput');
        
        if (type === 'routine') {
            freqGroup.classList.remove('d-none');
            dateGroup.classList.add('d-none');
            timeGroup.classList.add('d-none');
            
            dateInput.removeAttribute('required');
            timeInput.removeAttribute('required');
        } else {
            freqGroup.classList.add('d-none');
            dateGroup.classList.remove('d-none');
            timeGroup.classList.remove('d-none');
            
            dateInput.setAttribute('required', 'required');
            timeInput.setAttribute('required', 'required');
        }
    }

    // هندل Drag & Drop
    let draggedTaskId = null;

    function drag(ev, taskId) {
        draggedTaskId = taskId;
        ev.target.classList.add('dragging');
        ev.dataTransfer.setData("text", taskId);
    }

    function dragEnd(ev) {
        ev.target.classList.remove('dragging');
    }

    function allowDrop(ev) {
        ev.preventDefault();
    }

    function drop(ev, boardType) {
        ev.preventDefault();
        const targetColumn = ev.target.closest('.kanban-column');
        if (!targetColumn) return;

        const newStatus = targetColumn.getAttribute('data-status');
        const card = document.getElementById(`task-card-${draggedTaskId}`);
        
        if (card && targetColumn.querySelector('.kanban-cards')) {
            targetColumn.querySelector('.kanban-cards').appendChild(card);
            
            // فراخوانی وب‌سرویس به‌روزرسانی وضعیت در دیتابیس
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('task_id', draggedTaskId);
            formData.append('status', newStatus);

            fetch('../ajax/tasks_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // بارگذاری مجدد اطلاعات تسک یا رندر وضعیت جدید کارت به صورت لحظه‌ای
                    location.reload();
                } else {
                    alert(data.message);
                    location.reload();
                }
            })
            .catch(err => {
                console.error("Failed to update status:", err);
                location.reload();
            });
        }
    }

    // ارسال تسک جدید تکی
    function submitCreateTask(ev) {
        ev.preventDefault();
        const form = document.getElementById('createTaskForm');
        const formData = new FormData(form);

        fetch('../ajax/tasks_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert("خطا در برقراری ارتباط با سرور."));
    }

    // ارسال تسک گروهی
    function submitCreateBulkTask(ev) {
        ev.preventDefault();
        const form = document.getElementById('createBulkTaskForm');
        const formData = new FormData(form);

        fetch('../ajax/tasks_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert("خطا در ارسال اطلاعات وظایف گروهی."));
    }

    // زمان‌سنج و ثانیه‌شمار زنده کارت‌ها
    const activeIntervals = {};

    function toggleTimer(taskId, ev) {
        ev.stopPropagation();
        const display = document.getElementById(`timer-display-${taskId}`);
        const icon = document.getElementById(`timer-icon-${taskId}`);
        const currentState = display.getAttribute('data-state');

        const formData = new FormData();
        formData.append('task_id', taskId);

        if (currentState === 'paused') {
            formData.append('action', 'timer_play');
            fetch('../ajax/tasks_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    display.setAttribute('data-state', 'running');
                    display.setAttribute('data-started', Math.floor(Date.now() / 1000));
                    icon.className = 'bi bi-pause-circle-fill text-danger fs-4';
                    startLiveTick(taskId);
                } else {
                    alert(data.message);
                }
            });
        } else {
            formData.append('action', 'timer_pause');
            fetch('../ajax/tasks_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    display.setAttribute('data-state', 'paused');
                    display.setAttribute('data-seconds', data.timer_seconds);
                    display.removeAttribute('data-started');
                    icon.className = 'bi bi-play-circle-fill text-success fs-4';
                    stopLiveTick(taskId);
                    display.innerText = formatTimeDisplay(data.timer_seconds);
                } else {
                    alert(data.message);
                }
            });
        }
    }

    function startLiveTick(taskId) {
        if (activeIntervals[taskId]) clearInterval(activeIntervals[taskId]);
        
        const display = document.getElementById(`timer-display-${taskId}`);
        // data-seconds = کل ثانیه‌های انباشته تا لحظه شروع
        // data-started = timestamp کلاینت در لحظه‌ای که تایمر شروع شد
        const baseSeconds = parseInt(display.getAttribute('data-seconds') || '0');
        const startedAt = parseInt(display.getAttribute('data-started') || '0');

        activeIntervals[taskId] = setInterval(() => {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = startedAt > 0 ? Math.max(0, now - startedAt) : 0;
            display.innerText = formatTimeDisplay(baseSeconds + elapsed);
        }, 1000);
    }

    function stopLiveTick(taskId) {
        if (activeIntervals[taskId]) {
            clearInterval(activeIntervals[taskId]);
            delete activeIntervals[taskId];
        }
    }

    function formatTimeDisplay(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    // اجرای ثانیه‌شمار زنده برای تمام تسک‌های در حال اجرا در زمان لود صفحه
    document.addEventListener("DOMContentLoaded", () => {
        const startedAt = Math.floor(Date.now() / 1000);
        document.querySelectorAll(".timer-container[data-state='running']").forEach(display => {
            const taskId = display.id.replace('timer-display-', '');
            // data-seconds از PHP آمده و شامل elapsed تا لحظه render است
            // data-started را روی زمان کلاینت می‌گذاریم تا از این لحظه شمارش ادامه یابد
            display.setAttribute('data-started', startedAt);
            startLiveTick(taskId);
        });

        // فعال‌سازی تقویم شمسی - اجازه دادن به کلیک‌های خارج از مودال (برای datepicker)
        // روش مطمئن: پچ کردن enforceFocus بوت‌استرپ تا کانتینر تقویم رو نادیده بگیره
        const origEnforceFocus = window.bootstrap && bootstrap.Modal && bootstrap.Modal.prototype._enforceFocus;
        if (origEnforceFocus) {
            bootstrap.Modal.prototype._enforceFocus = function() {
                const jdpContainer = document.querySelector('#jdp-container, .jdp-container, [class*="jdp"]');
                if (jdpContainer && document.activeElement && jdpContainer.contains(document.activeElement)) {
                    return; // اجازه بده datepicker focus داشته باشه
                }
                origEnforceFocus.call(this);
            };
        }

        // روش جایگزین: listener روی focusin document برای جلوگیری از trap
        document.addEventListener('focusin', function(e) {
            const jdpEl = e.target.closest('#jdp-container, .jdp-container, [id^="jdp"]');
            if (jdpEl) {
                e.stopPropagation(); // جلوگیری از رسیدن event به bootstrap modal focustrap
            }
        }, true);

        // بازیابی تب فعال از localStorage
        const activeTabId = localStorage.getItem('activeTaskTab');
        if (activeTabId) {
            const tabEl = document.getElementById(activeTabId);
            if (tabEl) {
                const tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        }

        // ذخیره تب انتخاب شده در localStorage
        const tabTriggerList = [].slice.call(document.querySelectorAll('#taskTab button'));
        tabTriggerList.forEach(function (tabEl) {
            tabEl.addEventListener('shown.bs.tab', function (event) {
                localStorage.setItem('activeTaskTab', event.target.id);
            });
        });
    });

    // باز کردن پنجره گفتگو و جزئیات تسک
    function openTaskDetails(taskId, ev) {
        if (ev) ev.stopPropagation();
        
        const content = document.getElementById('taskDetailsModalContent');
        content.innerHTML = '<div class="text-center py-5"><span class="spinner-border text-primary" role="status"></span><p class="mt-2 text-secondary">در حال بارگذاری اطلاعات گفتگو...</p></div>';
        
        const modalEl = document.getElementById('taskDetailsModal');
        new bootstrap.Modal(modalEl).show();

        // دریافت محتوای گفتگو و جزئیات
        fetch(`../ajax/task_details_view.php?task_id=${taskId}`)
        .then(res => res.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<div class="alert alert-danger">خطا در دریافت اطلاعات تسک از سرور رخ داده است.</div>';
        });
    }

    // بانک اطلاعات دانش‌آموزان به تفکیک کلاس برای پنل معلمان
    const teacherStudentsByClass = {
        <?php if ($currentUser['role'] === 'teacher'): ?>
            <?php foreach ($teacherClasses as $c): 
                $stmtClassStudents = $pdo->prepare("SELECT u.id, u.full_name FROM users u JOIN students s ON u.id = s.user_id JOIN class_student cs ON s.id = cs.student_id WHERE cs.class_id = ? AND u.status = 1 ORDER BY u.full_name ASC");
                $stmtClassStudents->execute([$c['id']]);
                $clsStudents = $stmtClassStudents->fetchAll();
            ?>
                "<?= $c['id'] ?>": <?= json_encode($clsStudents) ?>,
            <?php endforeach; ?>
        <?php endif; ?>
    };

    function toggleTeacherAssignType() {
        const target = document.getElementById('teacherAssignedTargetSelect').value;
        const section = document.getElementById('teacherStudentsAssignSection');
        if (target === 'students') {
            section.classList.remove('d-none');
            document.getElementById('teacherAssignClassSelect').setAttribute('required', 'required');
        } else {
            section.classList.add('d-none');
            document.getElementById('teacherAssignClassSelect').removeAttribute('required');
        }
    }

    function loadClassStudentsForTeacher() {
        const classId = document.getElementById('teacherAssignClassSelect').value;
        const container = document.getElementById('teacherStudentsCheckboxesContainer');
        const header = document.getElementById('studentCheckboxesContainerHeader');
        container.innerHTML = '';
        header.classList.add('d-none');

        if (!classId || !teacherStudentsByClass[classId]) return;

        const students = teacherStudentsByClass[classId];
        if (students.length === 0) {
            container.innerHTML = '<span class="text-secondary small">هیچ دانش‌آموزی در این کلاس یافت نشد.</span>';
            return;
        }

        header.classList.remove('d-none');
        document.getElementById('teacherSelectAllStudentsCheckbox').checked = false;

        students.forEach(st => {
            const wrapper = document.createElement('div');
            wrapper.className = 'form-check';
            wrapper.innerHTML = `
                <input class="form-check-input teacher-student-checkbox" type="checkbox" name="assigned_students[]" value="${st.id}" id="st-chk-${st.id}">
                <label class="form-check-label small" for="st-chk-${st.id}">
                    ${st.full_name}
                </label>
            `;
            container.appendChild(wrapper);
        });
    }

    function toggleAllTeacherStudents(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.teacher-student-checkbox');
        checkboxes.forEach(chk => {
            chk.checked = masterCheckbox.checked;
        });
    }

    // قرار دادن متن منشن در باکس کامنت
    function insertMention(username) {
        const textarea = document.getElementById('commentTextarea');
        if (textarea) {
            textarea.value += `@${username} `;
            textarea.focus();
        }
    }

    // ارسال کامنت جدید
    function submitAddComment(ev, taskId) {
        ev.preventDefault();
        const form = document.getElementById('addCommentForm');
        const formData = new FormData(form);
        formData.append('action', 'add_comment');
        formData.append('task_id', taskId);

        fetch('../ajax/tasks_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                openTaskDetails(taskId);
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert("خطا در ارسال پیام."));
    }

    // تغییر حالت ویرایش تسک
    function toggleEditTaskMode(isEdit) {
        const viewDiv = document.getElementById('taskDetailsViewMode');
        const editDiv = document.getElementById('taskDetailsEditMode');
        const btnEdit = document.getElementById('btnEnterEditMode');
        
        if (isEdit) {
            viewDiv.classList.add('d-none');
            editDiv.classList.remove('d-none');
            if (btnEdit) btnEdit.classList.add('d-none');
            if (window.jalaliDatepicker) {
                jalaliDatepicker.startWatch({
                    persianDigits: false,
                    zIndex: 99999
                });
            }
        } else {
            viewDiv.classList.remove('d-none');
            if (editDiv) editDiv.classList.add('d-none');
            if (btnEdit) btnEdit.classList.remove('d-none');
        }
    }

    // ثبت ویرایش تسک
    function submitEditTask(ev, taskId) {
        ev.preventDefault();
        const form = document.getElementById('editTaskForm');
        const formData = new FormData(form);
        formData.append('action', 'edit_task');
        formData.append('task_id', taskId);

        fetch('../ajax/tasks_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert("خطا در ذخیره تغییرات تسک."));
    }

    // حذف وظیفه به همراه تایید
    function deleteTask(taskId) {
        if (typeof showCustomConfirm === 'function') {
            showCustomConfirm(
                'حذف وظیفه',
                'آیا از حذف این وظیفه اطمینان دارید؟ این عمل کاملاً غیرقابل بازگشت است.',
                'danger',
                function(confirmed) {
                    if (confirmed) {
                        executeDeleteTask(taskId);
                    }
                }
            );
        } else {
            if (confirm('آیا از حذف این وظیفه اطمینان دارید؟')) {
                executeDeleteTask(taskId);
            }
        }
    }

    function executeDeleteTask(taskId) {
        const formData = new FormData();
        formData.append('action', 'delete_task');
        formData.append('task_id', taskId);

        fetch('../ajax/tasks_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert("خطا در ارتباط با سرور جهت حذف وظیفه."));
    }
</script>
