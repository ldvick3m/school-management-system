<?php
/**
 * پابرگ مشترک پنل‌های کاربری سامانه مدیریت مدرسه
 */

// محاسبه مسیر روت پروژه به صورت پویا
$root = "";
if (file_exists("assets/css/style.css")) {
    $root = "";
} elseif (file_exists("../assets/css/style.css")) {
    $root = "../";
} elseif (file_exists("../../assets/css/style.css")) {
    $root = "../../";
}
?>
        </main>
        
        <footer class="bg-white border-top py-3 text-center text-secondary small">
            <div class="container-fluid">
                طراحی شده با عشق برای مدرسه هوشمند متوسطه اول &copy; <?= date('Y') ?> | تمامی حقوق محفوظ است.
            </div>
        </footer>
    </div>
</div>

<!-- مودال تایید اختصاصی سفارشی -->
<div class="modal fade" id="customConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 9999;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4 text-center">
                <!-- کانتینر آیکون داینامیک -->
                <div id="confirmModalIconContainer" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; font-size: 1.8rem;">
                    <i id="confirmModalIcon" class="bi"></i>
                </div>
                
                <h5 class="fw-bold mb-2 text-dark" id="confirmModalTitle">تایید عملیات</h5>
                <p class="text-secondary small mb-4" id="confirmModalMessage"></p>
                
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-4" id="btnConfirmCancel" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-sm px-4 text-white" id="btnConfirmOk">تایید</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 Bundle JS (Popper + Bootstrap) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js for analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Jalali Datepicker JS -->
<script src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>
<script>
    jalaliDatepicker.startWatch({
        persianDigits: false,
        zIndex: 99999
    });

    // رفع تداخل Bootstrap Modal Focus Trap با تقویم شمسی jalaliDatepicker
    // Bootstrap 5 از tabindex و focusin استفاده می‌کند تا focus trap را پیاده‌سازی کند.
    // با override کردن رفتار مربوطه، تقویم خارج از مودال بدون مشکل کار می‌کند.
    document.addEventListener('focusin', function(e) {
        // اگر عنصری که focus گرفته داخل کانتینر تقویم شمسی باشد، رویداد را متوقف می‌کنیم
        if (e.target && e.target.closest('[class*="jdp"], [id*="jdp"]')) {
            e.stopImmediatePropagation();
        }
    }, true);
</script>
<!-- مودال نمایش سه دانش‌آموز برتر کلاس -->
<div class="modal fade" id="topStudentsClassModal" tabindex="-1" aria-labelledby="topStudentsClassModalLabel" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom-0 py-3">
                <h5 class="modal-title fw-bold text-dark" id="topStudentsClassModalLabel"><i class="bi bi-trophy-fill text-warning me-1"></i> سه دانش‌آموز برتر کلاس <span id="topStudentsClassNameSpan"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="topStudentsListContainer" class="d-flex flex-column gap-3">
                    <!-- به صورت داینامیک پر می‌شود -->
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
function showTopStudents(classId, className) {
    document.getElementById('topStudentsClassNameSpan').innerText = className;
    const container = document.getElementById('topStudentsListContainer');
    container.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-secondary" role="status"></span> در حال محاسبه برترین‌ها...</div>';
    
    // باز کردن مودال
    const modalEl = document.getElementById('topStudentsClassModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    
    // تعیین مسیر روت به صورت پویا در جاوااسکریپت بر اساس سرفصل لود شده
    let prefix = '';
    if (window.location.pathname.indexOf('/operator/') !== -1 || window.location.pathname.indexOf('/teacher/') !== -1 || window.location.pathname.indexOf('/student/') !== -1 || window.location.pathname.indexOf('/parent/') !== -1 || window.location.pathname.indexOf('/admin/') !== -1) {
        prefix = '../';
    }

    fetch(`${prefix}ajax/class_top_students.php?class_id=${classId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (data.students.length === 0) {
                    container.innerHTML = '<div class="text-center text-secondary py-3">هنوز نمره‌ای برای دانش‌آموزان این کلاس ثبت نشده است.</div>';
                } else {
                    container.innerHTML = '';
                    let rank = 1;
                    data.students.forEach(s => {
                        let avatar = s.avatar_path ? `${prefix}${s.avatar_path}` : `${prefix}assets/images/default-avatar.png`;
                        let medalIcon = rank === 1 ? '🥇' : (rank === 2 ? '🥈' : '🥉');
                        
                        const item = document.createElement('div');
                        item.className = 'd-flex align-items-center justify-content-between p-3 bg-light rounded-3 border';
                        item.innerHTML = `
                            <div class="d-flex align-items-center gap-3">
                                <div class="position-relative" style="width: 48px; height: 48px;">
                                    <img src="${avatar}" alt="${s.full_name}" class="rounded-circle border border-2 border-warning" style="width: 48px; height: 48px; object-fit: cover;">
                                    <span class="position-absolute top-0 end-0 translate-middle-y fs-5">${medalIcon}</span>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">${s.full_name}</h6>
                                    <small class="text-secondary">رتبه ${rank}</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success-subtle text-success border border-success-subtle p-2 fw-bold">
                                    معدل: ${parseFloat(s.total_avg).toFixed(2)}
                                </span>
                            </div>
                        `;
                        container.appendChild(item);
                        rank++;
                    });
                }
            } else {
                container.innerHTML = `<div class="text-danger py-3">خطا: ${data.message}</div>`;
            }
        })
        .catch(err => {
            container.innerHTML = '<div class="text-danger py-3">خطا در ارتباط با سرور.</div>';
        });
}
</script>

<!-- Custom JS -->
<script src="<?= $root ?>assets/js/main.js?v=3.0"></script>

</body>
</html>
