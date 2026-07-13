/**
 * کدهای مشترک جاوااسکریپت سامانه مدیریت مدرسه هوشمند
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ۱. هندلر ضامن منو در موبایل (Sidebar Toggle)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const appSidebar = document.querySelector('.app-sidebar');
    if (sidebarToggle && appSidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            appSidebar.classList.toggle('show');
        });
        
        // بستن سایدبار در صورت کلیک روی بدنه صفحه در موبایل
        document.addEventListener('click', function(e) {
            if (appSidebar.classList.contains('show') && !appSidebar.contains(e.target) && e.target !== sidebarToggle) {
                appSidebar.classList.remove('show');
            }
        });
    }

    // ۲. ناپدید شدن خودکار هشدارهای سیستم بعد از ۵ ثانیه
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // مخفی کردن شیک با کلاس‌های بوت‌استرپ
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });

    // ۳. مقداردهی اولیه تایم‌پیکرهای سفارشی ۲۴ ساعته
    initCustomTimePickers();

    // ۴. بررسی پیام‌های خوانده‌نشده چت و نمایش تعداد در نوتیفیکیشن دایره‌ای قرمز
    initUnreadChatCounters();
});

/**
 * شبیه‌ساز چک کردن کلاس لایو جاری در پنل دانش‌آموز (AJAX)
 */
function initLiveClassChecker(studentId) {
    const bannerContainer = document.getElementById('liveClassBannerContainer');
    if (!bannerContainer) return;
    
    function checkLive() {
        fetch(`../ajax/live_check.php?student_id=${studentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.active && data.class) {
                    bannerContainer.innerHTML = `
                        <div class="live-class-banner">
                            <div class="d-flex align-items-center gap-3">
                                <div class="spinner-grow text-light" role="status" style="width: 1.2rem; height: 1.2rem;"></div>
                                <div>
                                    <h5 class="fw-bold mb-1">کلاس آنلاین هم‌اکنون در حال برگزاری است!</h5>
                                    <p class="mb-0 text-white-50">درس: ${data.class.course_name} | دبیر: ${data.class.teacher_name} | عنوان: ${data.class.title}</p>
                                </div>
                            </div>
                            <a href="${data.class.join_link}" target="_blank" class="btn btn-light text-danger fw-bold shadow-sm">ورود به کلاس آنلاین</a>
                        </div>
                    `;
                } else {
                    bannerContainer.innerHTML = ''; // پاک کردن بنر در صورت نبود کلاس
                }
            })
            .catch(err => console.error("خطا در بررسی وضعیت کلاس لایو:", err));
    }
    
    // اجرای اولیه و سپس تکرار هر ۳۰ ثانیه
    checkLive();
    setInterval(checkLive, 30000);
}

/**
 * تایمر معکوس برای آزمون‌های تستی
 * @param {number} remainingSeconds ثانیه‌های باقی‌مانده واقعی
 * @param {string} quizFormId آی‌دی فرم آزمون جهت ثبت خودکار در صورت اتمام زمان
 */
function initQuizTimer(remainingSeconds, quizFormId) {
    const timerDisplay = document.getElementById('quizTimerDisplay');
    if (!timerDisplay) return;
    
    let secondsLeft = remainingSeconds;
    
    function updateDisplay() {
        if (secondsLeft <= 0) {
            timerDisplay.innerHTML = "زمان به پایان رسید!";
            timerDisplay.classList.add('text-danger');
            clearInterval(timerInterval);
            
            // ارسال خودکار فرم آزمون
            const form = document.getElementById(quizFormId);
            if (form) {
                // ثبت فیلد کمکی که مشخص کند فرم به صورت خودکار به علت اتمام وقت ارسال شده
                const timeoutInput = document.createElement('input');
                timeoutInput.type = 'hidden';
                timeoutInput.name = 'auto_submit';
                timeoutInput.value = '1';
                form.appendChild(timeoutInput);
                form.submit();
            }
            return;
        }
        
        const hours = Math.floor(secondsLeft / 3600);
        const minutes = Math.floor((secondsLeft % 3600) / 60);
        const seconds = secondsLeft % 60;
        
        let displayStr = '';
        if (hours > 0) {
            displayStr += (hours < 10 ? '0' : '') + hours + ':';
        }
        displayStr += (minutes < 10 ? '0' : '') + minutes + ':';
        displayStr += (seconds < 10 ? '0' : '') + seconds;
        
        timerDisplay.innerHTML = displayStr;
        
        if (secondsLeft <= 60) {
            timerDisplay.classList.add('text-danger', 'fw-bold');
            // افکت لرزش یا فلش در ثانیه‌های پایانی
            timerDisplay.style.opacity = timerDisplay.style.opacity === '0.5' ? '1' : '0.5';
        }
        
        secondsLeft--;
    }
    
    updateDisplay();
    const timerInterval = setInterval(updateDisplay, 1000);
}

/**
 * گفتگوهای هم‌زمان مبحث درس (Lesson Forum)
 */
function initLessonForum(topicId, currentUserId) {
    const chatBox = document.getElementById('forumChatBox');
    const sendForm = document.getElementById('forumSendForm');
    const messageInput = document.getElementById('forumMessageInput');
    
    if (!chatBox || !sendForm) return;
    
    let lastMessageId = 0;
    
    function fetchMessages() {
        fetch(`../ajax/forum.php?action=get&topic_id=${topicId}&last_id=${lastMessageId}`)
            .then(res => res.json())
            .then(messages => {
                if (messages.length > 0) {
                    messages.forEach(msg => {
                        const isMine = msg.sender_id == currentUserId;
                        const bubble = document.createElement('div');
                        bubble.className = `forum-message ${isMine ? 'mine' : ''}`;
                        bubble.setAttribute('data-id', msg.id);
                        
                        // آواتار دایره‌ای با حروف اول نام
                        const initials = msg.full_name.substring(0, 1);
                        
                        bubble.innerHTML = `
                            <div class="message-avatar" title="${msg.full_name}">${initials}</div>
                            <div class="message-bubble">
                                <div class="message-sender text-secondary">${msg.full_name} (${msg.role_fa})</div>
                                <p class="message-text">${msg.message_text}</p>
                                <div class="message-time">${msg.time_fa}</div>
                            </div>
                        `;
                        chatBox.appendChild(bubble);
                        lastMessageId = Math.max(lastMessageId, msg.id);
                    });
                    
                    // اسکرول به انتهای باکس
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            })
            .catch(err => console.error("خطا در بارگذاری پیام‌های گفتگو:", err));
    }
    
    // ارسال پیام جدید
    sendForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (text === '') return;
        
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('topic_id', topicId);
        formData.append('message_text', text);
        
        messageInput.value = ''; // خالی کردن سریع فیلد
        
        fetch('../ajax/forum.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                fetchMessages(); // بازیابی مجدد سریع
            } else {
                alert("خطا در ارسال پیام: " + res.message);
            }
        })
        .catch(err => console.error("خطا در ارسال پیام:", err));
    });
    
    // لود اولیه و پولینگ هر ۵ ثانیه
    fetchMessages();
    setInterval(fetchMessages, 5000);
}

/**
 * مدیریت و راه‌اندازی تایم‌پیکر سفارشی ۲۴ ساعته (ساعت | دقیقه) بدون AM/PM راست به چپ
 */
function initCustomTimePickers() {
    const inputs = document.querySelectorAll('input.custom-time-picker');
    inputs.forEach(input => {
        input.setAttribute('readonly', 'true');
        input.style.cursor = 'pointer';
        
        // ایجاد محفظه نگهدارنده جهت جایگذاری صحیح منو
        const wrapper = document.createElement('div');
        wrapper.className = 'time-picker-wrapper d-inline-block w-100';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        input.addEventListener('click', function(e) {
            e.stopPropagation();
            closeAllTimePickers();
            
            const popover = document.createElement('div');
            popover.className = 'time-picker-popover';
            
            // ستون راست: ساعت‌ها (00-23)
            const hoursCol = document.createElement('div');
            hoursCol.className = 'time-picker-column';
            const hoursHeader = document.createElement('div');
            hoursHeader.className = 'time-picker-column-header';
            hoursHeader.innerText = 'ساعت';
            hoursCol.appendChild(hoursHeader);
            
            // ستون چپ: دقیقه‌ها (00-59)
            const minutesCol = document.createElement('div');
            minutesCol.className = 'time-picker-column';
            const minutesHeader = document.createElement('div');
            minutesHeader.className = 'time-picker-column-header';
            minutesHeader.innerText = 'دقیقه';
            minutesCol.appendChild(minutesHeader);
            
            let currentVal = input.value || '12:00';
            let [currentHour, currentMin] = currentVal.split(':').map(Number);
            if (isNaN(currentHour)) currentHour = 12;
            if (isNaN(currentMin)) currentMin = 0;
            
            // پر کردن ساعت‌ها
            for (let h = 0; h < 24; h++) {
                const hStr = h.toString().padStart(2, '0');
                const item = document.createElement('div');
                item.className = 'time-picker-item' + (h === currentHour ? ' active' : '');
                item.innerText = hStr;
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    hoursCol.querySelectorAll('.time-picker-item').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    updateVal();
                });
                hoursCol.appendChild(item);
            }
            
            // پر کردن دقیقه‌ها
            for (let m = 0; m < 60; m++) {
                const mStr = m.toString().padStart(2, '0');
                const item = document.createElement('div');
                item.className = 'time-picker-item' + (m === currentMin ? ' active' : '');
                item.innerText = mStr;
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    minutesCol.querySelectorAll('.time-picker-item').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    updateVal();
                });
                minutesCol.appendChild(item);
            }
            
            // چیدمان راست به چپ: ساعت در راست، دقیقه در چپ
            popover.appendChild(hoursCol);
            popover.appendChild(minutesCol);
            
            wrapper.appendChild(popover);
            
            // اسکرول اتوماتیک گزینه فعال به مرکز دید
            scrollActiveIntoView(hoursCol);
            scrollActiveIntoView(minutesCol);
            
            function updateVal() {
                const activeHour = hoursCol.querySelector('.time-picker-item.active').innerText;
                const activeMin = minutesCol.querySelector('.time-picker-item.active').innerText;
                input.value = `${activeHour}:${activeMin}`;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
    
    document.addEventListener('click', function() {
        closeAllTimePickers();
    });
    
    function closeAllTimePickers() {
        document.querySelectorAll('.time-picker-popover').forEach(el => el.remove());
    }
    
    function scrollActiveIntoView(column) {
        const active = column.querySelector('.time-picker-item.active');
        if (active) {
            column.scrollTop = active.offsetTop - column.offsetTop - 60;
        }
    }
}

/**
 * مدیریت و به‌روزرسانی نوتیفیکیشن تعداد پیام‌های خوانده‌نشده چت‌باکس‌ها و سایدبار
 */
function initUnreadChatCounters() {
    function updateCounters() {
        const badges = document.querySelectorAll('.unread-badge');
        const sidebarBadges = document.querySelectorAll('.sidebar-unread-badge');
        
        // اگر هیچ یک از نوتیفیکیشن‌ها در صفحه نباشند متوقف شو
        if (badges.length === 0 && sidebarBadges.length === 0) return;
        
        const urlParams = new URLSearchParams(window.location.search);
        const activeUserId = urlParams.get('chat_user_id') || urlParams.get('chat_teacher_id') || 0;
        
        let ajaxPath = "../ajax/chat.php";
        if (window.location.pathname.includes('/operator/') || window.location.pathname.includes('/teacher/') || window.location.pathname.includes('/parent/')) {
            ajaxPath = "../ajax/chat.php";
        } else {
            ajaxPath = "ajax/chat.php";
        }

        fetch(`${ajaxPath}?action=unread_counts`)
            .then(res => res.json())
            .then(counts => {
                const countsMap = {};
                counts.forEach(item => {
                    countsMap[item.sender_id] = item.unread_count;
                });
                
                // ۱. بروزرسانی شمارنده‌های مخاطبین داخل صفحه گفتگو
                badges.forEach(badge => {
                    const itemLink = badge.closest('[data-chat-user-id]');
                    if (itemLink) {
                        const userId = itemLink.getAttribute('data-chat-user-id');
                        const count = countsMap[userId] || 0;
                        
                        if (count > 0 && userId != activeUserId) {
                            badge.innerText = count;
                            badge.classList.remove('d-none');
                        } else {
                            badge.classList.add('d-none');
                        }
                    }
                });
                
                // ۲. بروزرسانی تعداد کل گفتگوهای جدید در سایدبار
                const distinctChatsCount = counts.length;
                sidebarBadges.forEach(sidebarBadge => {
                    if (distinctChatsCount > 0) {
                        sidebarBadge.innerText = distinctChatsCount;
                        sidebarBadge.classList.remove('d-none');
                    } else {
                        sidebarBadge.classList.add('d-none');
                    }
                });
            })
            .catch(err => console.error("Error fetching unread counts:", err));
    }
    
    updateCounters();
    setInterval(updateCounters, 4000);
}

/**
 * نمایش کادر تایید هوشمند و سفارشی با طراحی منطبق بر درخواست کاربر (سبز، قرمز، زرد، آبی)
 * @param {string} title - عنوان پیام
 * @param {string} message - متن پیام
 * @param {string} type - نوع پیام (success, error, warning, info)
 * @param {function} callback - تابعی که نتیجه تایید یا انصراف را برمی‌گرداند
 */
function showCustomConfirm(title, message, type, callback) {
    const modalEl = document.getElementById('customConfirmModal');
    if (!modalEl) {
        // اگر مودال در صفحه نبود، به confirm معمولی مرورگر رجوع شود
        const res = confirm(message);
        callback(res);
        return;
    }
    
    const iconContainer = document.getElementById('confirmModalIconContainer');
    const icon = document.getElementById('confirmModalIcon');
    const titleEl = document.getElementById('confirmModalTitle');
    const msgEl = document.getElementById('confirmModalMessage');
    const btnOk = document.getElementById('btnConfirmOk');
    
    titleEl.textContent = title || 'تایید عملیات';
    msgEl.textContent = message;
    
    // تنظیم تم رنگی و آیکون (بر اساس عکس‌های ارسالی کاربر)
    if (type === 'success') {
        iconContainer.style.backgroundColor = 'rgba(16, 185, 129, 0.15)';
        iconContainer.style.color = '#10B981';
        icon.className = 'bi bi-check-circle-fill';
        btnOk.style.backgroundColor = '#10B981';
        btnOk.style.borderColor = '#10B981';
    } else if (type === 'danger' || type === 'error') {
        iconContainer.style.backgroundColor = 'rgba(239, 68, 68, 0.15)';
        iconContainer.style.color = '#EF4444';
        icon.className = 'bi bi-exclamation-circle-fill';
        btnOk.style.backgroundColor = '#EF4444';
        btnOk.style.borderColor = '#EF4444';
    } else if (type === 'info') {
        iconContainer.style.backgroundColor = 'rgba(59, 130, 246, 0.15)';
        iconContainer.style.color = '#3B82F6';
        icon.className = 'bi bi-info-circle-fill';
        btnOk.style.backgroundColor = '#3B82F6';
        btnOk.style.borderColor = '#3B82F6';
    } else {
        // warning یا پیش‌فرض
        iconContainer.style.backgroundColor = 'rgba(245, 158, 11, 0.15)';
        iconContainer.style.color = '#F59E0B';
        icon.className = 'bi bi-exclamation-triangle-fill';
        btnOk.style.backgroundColor = '#F59E0B';
        btnOk.style.borderColor = '#F59E0B';
    }
    
    const modal = new bootstrap.Modal(modalEl);
    
    // پاک کردن لیسنرهای قدیمی دکمه تایید جهت جلوگیری از انباشت
    const newBtnOk = btnOk.cloneNode(true);
    btnOk.parentNode.replaceChild(newBtnOk, btnOk);
    
    newBtnOk.addEventListener('click', function() {
        modal.hide();
        callback(true);
    });
    
    modal.show();
}
