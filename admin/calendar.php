<?php
/**
 * تقویم جامع کلاس‌های آنلاین و امتحانات جهت ردیابی تداخل‌های زمانی دبیران و کلاس‌ها
 */

require_once '../includes/header.php';
check_auth('admin');

$conflicts = [];

try {
    // ۱. ردیابی تداخل‌های دبیران (یک دبیر در یک ساعت در دو کلاس متفاوت کلاس آنلاین داشته باشد)
    $stmtTeacherConflicts = $pdo->query("SELECT a.id as id1, b.id as id2, a.date, 
        a.title as title1, a.start_time as start1, a.end_time as end1, 
        b.title as title2, b.start_time as start2, b.end_time as end2, 
        u.full_name as teacher_name, c1.class_name as class1, c2.class_name as class2
        FROM live_classes a 
        JOIN live_classes b ON a.teacher_id = b.teacher_id 
                           AND a.date = b.date 
                           AND a.id < b.id
        JOIN teachers t ON a.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN classes c1 ON a.class_id = c1.id
        JOIN classes c2 ON b.class_id = c2.id
        WHERE (a.start_time < b.end_time AND a.end_time > b.start_time)");
    $teacherConflicts = $stmtTeacherConflicts->fetchAll();

    // ۲. ردیابی تداخل‌های کلاس‌ها (یک کلاس در یک ساعت دو درس متفاوت آنلاین داشته باشد)
    $stmtClassConflicts = $pdo->query("SELECT a.id as id1, b.id as id2, a.date, 
        a.title as title1, a.start_time as start1, a.end_time as end1, 
        b.title as title2, b.start_time as start2, b.end_time as end2, 
        c.class_name, co1.course_name as course1, co2.course_name as course2
        FROM live_classes a 
        JOIN live_classes b ON a.class_id = b.class_id 
                           AND a.date = b.date 
                           AND a.id < b.id
        JOIN classes c ON a.class_id = c.id
        JOIN courses co1 ON a.course_id = co1.id
        JOIN courses co2 ON b.course_id = co2.id
        WHERE (a.start_time < b.end_time AND a.end_time > b.start_time)");
    $classConflicts = $stmtClassConflicts->fetchAll();

    // ادغام تداخل‌ها در یک آرایه جهت رندر متمایز
    foreach ($teacherConflicts as $tc) {
        $conflicts[] = [
            'type' => 'teacher',
            'date' => $tc['date'],
            'item_name' => $tc['teacher_name'] . ' (دبیر)',
            'desc' => "تداخل برگزاری کلاس «{$tc['title1']}» در کلاس {$tc['class1']} (ساعت " . to_time($tc['start1']) . " الی " . to_time($tc['end1']) . ") با کلاس «{$tc['title2']}» در کلاس {$tc['class2']} (ساعت " . to_time($tc['start2']) . " الی " . to_time($tc['end2']) . ")"
        ];
    }

    foreach ($classConflicts as $cc) {
        $conflicts[] = [
            'type' => 'class',
            'date' => $cc['date'],
            'item_name' => $cc['class_name'] . ' (کلاس)',
            'desc' => "تداخل برنامه‌ریزی درس {$cc['course1']} (ساعت " . to_time($cc['start1']) . " الی " . to_time($cc['end1']) . ") با درس {$cc['course2']} (ساعت " . to_time($cc['start2']) . " الی " . to_time($cc['end2']) . ")"
        ];
    }

    // ۳. واکشی تمامی کلاس‌های آنلاین جهت رندر در تقویم جامع هفتگی
    $stmtLiveClasses = $pdo->query("SELECT lc.*, c.class_name, co.course_name, u.full_name as teacher_name 
        FROM live_classes lc
        JOIN classes c ON lc.class_id = c.id
        JOIN courses co ON lc.course_id = co.id
        JOIN teachers t ON lc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        ORDER BY lc.date ASC, lc.start_time ASC");
    $scheduledClasses = $stmtLiveClasses->fetchAll();

} catch (PDOException $e) {
    die("خطا در واکشی تقویم و تداخل‌ها: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- بخش تداخل‌های زمانی کشف شده (ویجت هوشمند هشدار تداخل) -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-danger border-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>تداخل‌های زمانی کشف‌شده سیستم</h5>
        </div>
        <div class="card-body">
            <?php if (empty($conflicts)): ?>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-0 py-3">
                    <i class="bi bi-check-circle-fill me-1"></i> تبریک! هیچ تداخل زمانی در زمان‌بندی کلاس‌های آنلاین معلمان و کلاس‌های مدرسه وجود ندارد.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($conflicts as $con): ?>
                        <div class="list-group-item px-0 py-3 border-bottom d-flex align-items-start gap-3">
                            <span class="badge bg-danger p-2"><i class="bi bi-shield-slash-fill"></i> تداخل <?= $con['type'] === 'teacher' ? 'دبیر' : 'کلاس' ?></span>
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($con['item_name']) ?></h6>
                                <p class="mb-1 text-secondary small"><?= htmlspecialchars($con['desc']) ?></p>
                                <small class="text-black-50"><i class="bi bi-calendar3 me-1"></i>تاریخ: <?= to_shamsi($con['date']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- جدول تقویم جامع هفتگی -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-calendar3 text-primary me-2"></i>تقویم جامع زمان‌بندی آنلاین مدرسه</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($scheduledClasses)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-secondary mb-2"></i>
                    <p class="text-secondary mb-0">هیچ رویداد یا کلاس آنلاینی در سیستم ثبت نشده است.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>عنوان رویداد/کلاس</th>
                                <th>کلاس</th>
                                <th>درس</th>
                                <th>دبیر برگزارکننده</th>
                                <th>تاریخ تشکیل</th>
                                <th>ساعت تشکیل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduledClasses as $sc): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($sc['title']) ?></td>
                                    <td><?= htmlspecialchars($sc['class_name']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($sc['course_name']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($sc['teacher_name']) ?></td>
                                    <td><i class="bi bi-calendar-event text-secondary me-1"></i> <?= to_shamsi($sc['date']) ?></td>
                                    <td>
                                        <i class="bi bi-clock text-primary me-1"></i>
                                        <?= to_time($sc['start_time']) ?> الی <?= to_time($sc['end_time']) ?>
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

<?php require_once '../includes/footer.php'; ?>
