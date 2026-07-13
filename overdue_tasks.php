<?php
/**
 * صفحه مدیریت و نمایش کارهای معوقه (سررسید گذشته)
 */

require_once 'includes/header.php';

$userId = $currentUser['id'];
$userRole = $currentUser['role'];

// واکشی کارهای معوقه بر اساس نقش
try {
    if ($userRole === 'admin' || $userRole === 'operator') {
        // مدیر و اپراتور تمام کارهای معوقه سیستم را می‌بینند
        $stmt = $pdo->prepare("SELECT t.*, 
            u_creator.full_name as creator_name,
            u_assignee.full_name as assignee_name, u_assignee.role as assignee_role
            FROM `tasks` t
            JOIN `users` u_creator ON t.created_by = u_creator.id
            JOIN `users` u_assignee ON t.assigned_to = u_assignee.id
            WHERE t.status != 'done' AND t.deadline < NOW()
            ORDER BY t.deadline ASC");
        $stmt->execute();
    } else {
        // معلمان و دانش‌آموزان فقط کارهای معوقه خودشان را می‌بینند
        $stmt = $pdo->prepare("SELECT t.*, 
            u_creator.full_name as creator_name,
            u_assignee.full_name as assignee_name, u_assignee.role as assignee_role
            FROM `tasks` t
            JOIN `users` u_creator ON t.created_by = u_creator.id
            JOIN `users` u_assignee ON t.assigned_to = u_assignee.id
            WHERE t.status != 'done' AND t.deadline < NOW() AND t.assigned_to = ?
            ORDER BY t.deadline ASC");
        $stmt->execute([$userId]);
    }
    $overdueList = $stmt->fetchAll();
} catch (PDOException $e) {
    die("خطا در واکشی کارهای معوقه: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-danger"><i class="bi bi-exclamation-octagon-fill text-danger me-1"></i> کارهای معوقه (سررسید گذشته)</h4>
            <p class="text-secondary small mb-0">لیست وظایف معوقه که زمان ددلاین آنها به پایان رسیده و نیاز به پیگیری فوری دارند</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 border-top border-4 border-danger">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark">لیست کارهای معوقه</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($overdueList)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle-fill text-success fs-1 mb-2"></i>
                    <p class="text-secondary mb-0 fw-bold">تبریک! هیچ کار معوقه‌ای در سیستم وجود ندارد.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>عنوان وظیفه</th>
                                <th>ارجاع‌دهنده</th>
                                <th>مسئول انجام</th>
                                <th>مهلت انجام (ددلاین)</th>
                                <th>مدت زمان تأخیر</th>
                                <th>وضعیت</th>
                                <th>اقدام</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueList as $t): 
                                $deadlineTime = strtotime($t['deadline']);
                                $delaySeconds = time() - $deadlineTime;
                                $delayDays = floor($delaySeconds / 86400);
                                $delayHours = floor(($delaySeconds % 86400) / 3600);
                                
                                $delayText = "";
                                if ($delayDays > 0) {
                                    $delayText .= $delayDays . " روز و ";
                                }
                                $delayText .= $delayHours . " ساعت تاخیر";
                            ?>
                                <tr class="table-danger-subtle">
                                    <td>
                                        <span class="fw-bold text-danger"><?= htmlspecialchars($t['title']) ?></span>
                                        <small class="text-secondary d-block text-truncate" style="max-width: 300px;"><?= htmlspecialchars($t['description']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($t['creator_name']) ?></td>
                                    <td><?= htmlspecialchars($t['assignee_name']) ?> (<?= get_role_fa($t['assignee_role']) ?>)</td>
                                    <td><span class="text-danger fw-bold"><?= to_shamsi($t['deadline']) ?> ساعت <?= date('H:i', $deadlineTime) ?></span></td>
                                    <td><span class="badge bg-danger"><?= $delayText ?></span></td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            <?= $t['status'] === 'todo' ? 'پیش‌رو' : 'در حال انجام' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= $userRole ?>/tasks.php" class="btn btn-outline-danger btn-sm fw-bold">
                                            <i class="bi bi-clipboard-check me-1"></i> مشاهده در بورد
                                        </a>
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

<?php require_once 'includes/footer.php'; ?>
