<?php
/**
 * مدال‌ها و دستاوردهای تحصیلی و انضباطی دانش‌آموز (Badges)
 */

require_once '../includes/header.php';
check_auth(['student', 'admin']);

try {
    // ۱. پیدا کردن شناسه دانش‌آموز
    $stmtS = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmtS->execute([$_SESSION['user_id']]);
    $studentId = $stmtS->fetchColumn();

    if (!$studentId) {
        die("شما به عنوان دانش‌آموز در سیستم تعریف نشده‌اید.");
    }

    // ۲. واکشی تمامی مدال‌های ثبت شده سیستم
    $allBadges = $pdo->query("SELECT * FROM badges ORDER BY type ASC, id ASC")->fetchAll();

    // ۳. واکشی مدال‌های کسب شده توسط دانش‌آموز فعال به همراه تاریخ و دبیر اهداکننده
    $stmtMyBadges = $pdo->prepare("SELECT sb.*, u.full_name as teacher_name 
        FROM student_badges sb
        JOIN users u ON sb.awarded_by = u.id
        WHERE sb.student_id = ?");
    $stmtMyBadges->execute([$studentId]);
    $myBadgesRows = $stmtMyBadges->fetchAll();

    $myBadges = [];
    foreach ($myBadgesRows as $row) {
        $myBadges[$row['badge_id']] = $row;
    }

} catch (PDOException $e) {
    die("خطا در بارگذاری مدال‌ها: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-warning text-dark">
        <div class="card-body p-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">صندوق نشان‌ها و دستاوردهای من</h4>
                <p class="mb-0 text-black-50">نشان‌های آموزشی و مهارتی که به پاس تلاش‌های مستمر خود از معلمان دریافت کرده‌اید.</p>
            </div>
            <i class="bi bi-trophy-fill fs-1 opacity-20"></i>
        </div>
    </div>

    <!-- بخش دستاوردهای آموزشی -->
    <h5 class="fw-bold mb-3"><i class="bi bi-mortarboard-fill text-primary"></i> نشان‌های علمی و آموزشی</h5>
    <div class="row g-4 mb-5">
        <?php foreach ($allBadges as $badge): 
            if ($badge['type'] !== 'educational') continue;
            $earned = isset($myBadges[$badge['id']]);
            $earnedInfo = $earned ? $myBadges[$badge['id']] : null;
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 h-100 <?= $earned ? 'border-start border-warning border-4' : 'opacity-60 bg-light' ?>">
                    <div class="card-body p-4 d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-<?= $earned ? 'warning text-dark' : 'secondary bg-opacity-10 text-secondary' ?> d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; flex-shrink: 0; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                            <?php if (strpos($badge['icon'], 'uploads/') === 0): ?>
                                <img src="<?= $root . htmlspecialchars($badge['icon']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>" style="width: 42px; height: 42px; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-<?= htmlspecialchars($badge['icon']) ?> fs-2"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 <?= $earned ? 'text-dark' : 'text-secondary' ?>"><?= htmlspecialchars($badge['name']) ?></h6>
                            <p class="mb-0 text-secondary text-xs" style="line-height: 1.4;"><?= htmlspecialchars($badge['description']) ?></p>
                            
                            <?php if ($earned): ?>
                                <hr class="my-2 border-secondary border-opacity-10">
                                <small class="text-success text-xs d-block">
                                    <i class="bi bi-check-circle-fill me-1"></i> کسب شده در <?= to_shamsi($earnedInfo['awarded_at']) ?>
                                </small>
                                <small class="text-secondary text-xs">اهدا شده توسط: <?= htmlspecialchars($earnedInfo['teacher_name']) ?></small>
                            <?php else: ?>
                                <small class="text-secondary text-xs d-block mt-2">
                                    <i class="bi bi-lock-fill"></i> هنوز کسب نشده است
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- بخش دستاوردهای مهارتی و انضباطی -->
    <h5 class="fw-bold mb-3"><i class="bi bi-people-fill text-success"></i> نشان‌های مهارتی و انضباطی</h5>
    <div class="row g-4">
        <?php foreach ($allBadges as $badge): 
            if ($badge['type'] !== 'behavioral') continue;
            $earned = isset($myBadges[$badge['id']]);
            $earnedInfo = $earned ? $myBadges[$badge['id']] : null;
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 h-100 <?= $earned ? 'border-start border-success border-4' : 'opacity-60 bg-light' ?>">
                    <div class="card-body p-4 d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-<?= $earned ? 'success text-white' : 'secondary bg-opacity-10 text-secondary' ?> d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; flex-shrink: 0; overflow: hidden; border: 1px solid rgba(0,0,0,0.08);">
                            <?php if (strpos($badge['icon'], 'uploads/') === 0): ?>
                                <img src="<?= $root . htmlspecialchars($badge['icon']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>" style="width: 42px; height: 42px; object-fit: contain;">
                            <?php else: ?>
                                <i class="bi bi-<?= htmlspecialchars($badge['icon']) ?> fs-2"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 <?= $earned ? 'text-dark' : 'text-secondary' ?>"><?= htmlspecialchars($badge['name']) ?></h6>
                            <p class="mb-0 text-secondary text-xs" style="line-height: 1.4;"><?= htmlspecialchars($badge['description']) ?></p>
                            
                            <?php if ($earned): ?>
                                <hr class="my-2 border-secondary border-opacity-10">
                                <small class="text-success text-xs d-block">
                                    <i class="bi bi-check-circle-fill me-1"></i> کسب شده در <?= to_shamsi($earnedInfo['awarded_at']) ?>
                                </small>
                                <small class="text-secondary text-xs">اهدا شده توسط: <?= htmlspecialchars($earnedInfo['teacher_name']) ?></small>
                            <?php else: ?>
                                <small class="text-secondary text-xs d-block mt-2">
                                    <i class="bi bi-lock-fill"></i> هنوز کسب نشده است
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
