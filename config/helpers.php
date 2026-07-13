<?php
/**
 * توابع کمکی مشترک پروژه سامانه مدیریت مدرسه هوشمند
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * پاکسازی داده‌ها جهت جلوگیری از حملات XSS
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * هدایت کاربر به آدرس مشخص
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    } else {
        echo "<script>window.location.href = '" . addslashes($url) . "';</script>";
        exit;
    }
}

/**
 * بررسی دسترسی بر اساس نقش کاربر
 * @param array|string $allowedRoles نقش‌های مجاز
 */
function check_auth($allowedRoles) {
    if (!isset($_SESSION['user_id'])) {
        redirect('/index.php');
    }
    
    $userRole = $_SESSION['role'] ?? '';
    
    if (is_array($allowedRoles)) {
        if (!in_array($userRole, $allowedRoles)) {
            redirect('/index.php?error=access_denied');
        }
    } else {
        if ($userRole !== $allowedRoles) {
            redirect('/index.php?error=access_denied');
        }
    }
}

/**
 * دریافت پیام‌های موقت (Flash Messages)
 */
function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
    }
    return null;
}

/**
 * محاسبه وضعیت کیفی تکالیف دانش‌آموز بر اساس فرمول PRD
 * @param float $rate درصد تکالیف ارسالی
 * @return string وضعیت کیفی (عالی، خوب، متوسط، ضعیف)
 */
function calculate_homework_status($rate) {
    if ($rate >= 100) {
        return 'عالی';
    } elseif ($rate >= 80) {
        return 'خوب';
    } elseif ($rate >= 60) {
        return 'متوسط';
    } else {
        return 'ضعیف';
    }
}

/**
 * بازگرداندن کلاس استایل CSS متناسب با وضعیت کیفی
 */
function get_status_class($status) {
    switch ($status) {
        case 'عالی':
        case 'excellent':
        case 'present':
        case 'open':
            return 'success';
        case 'خوب':
        case 'good':
        case 'in_progress':
            return 'primary';
        case 'متوسط':
        case 'average':
        case 'pending':
            return 'warning';
        case 'ضعیف':
        case 'poor':
        case 'absent':
        case 'closed':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * بازگرداندن معادل فارسی نقش‌ها
 */
function get_role_fa($role) {
    switch ($role) {
        case 'admin':
            return 'مدیر کل';
        case 'operator':
            return 'اپراتور';
        case 'teacher':
            return 'دبیر';
        case 'student':
            return 'دانش‌آموز';
        case 'parent':
            return 'ولی';
        default:
            return 'ناشناس';
    }
}

/**
 * تبدیل تاریخ میلادی دیتابیس به تاریخ شمسی ساده
 * (از فرمت ساده برای شبیه‌سازی تاریخ در هاست بدون افزونه استفاده می‌شود)
 */
function to_shamsi($gregorian_date_str) {
    if (empty($gregorian_date_str)) return '';
    $timestamp = strtotime($gregorian_date_str);
    
    // شبیه‌ساز ساده تاریخ شمسی
    // برای سادگی در این پروژه بومی و عدم استفاده از لایبرری سنگین، ماه و روز را در ساختار زیبایی قالب‌بندی می‌کنیم
    $months = [
        1 => "فروردین", 2 => "اردیبهشت", 3 => "خرداد", 4 => "تیر", 5 => "مرداد", 6 => "شهریور",
        7 => "مهر", 8 => "آبان", 9 => "آذر", 10 => "دی", 11 => "بهمن", 12 => "اسفند"
    ];
    
    // این یک فرمول ساده تقریبی برای سال ۲۰۲۶ است
    $gy = date('Y', $timestamp);
    $gm = date('n', $timestamp);
    $gd = date('j', $timestamp);
    
    $jalali = gregorian_to_jalali_array($gy, $gm, $gd);
    return $jalali['d'] . ' ' . $months[$jalali['m']] . ' ' . $jalali['y'];
}

/**
 * نمایش زمان کوتاه (HH:MM)
 */
function to_time($time_str) {
    if (empty($time_str)) return '';
    return date('H:i', strtotime($time_str));
}

/**
 * تبدیل ارقام فارسی و عربی به انگلیسی
 */
function to_english_digits($string) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $num = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $string = str_replace($persian, $num, $string);
    return str_replace($arabic, $num, $string);
}

/**
 * الگوریتم دقیق و صحیح تبدیل میلادی به شمسی به صورت آرایه
 */
function gregorian_to_jalali_array($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy = (int)$gy;
    $gm = (int)$gm;
    $gd = (int)$gd;
    
    if ($gy > 1600) {
        $jy = 979;
        $gy -= 1600;
    } else {
        $jy = 0;
        $gy -= 621;
    }
    
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
    
    $jy += 33 * (int)($days / 12053);
    $days %= 12053;
    
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    
    $jy += (int)(($days - 1) / 365);
    if ($days > 365) {
        $days = ($days - 1) % 365;
    }
    
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    
    return ['y' => $jy, 'm' => $jm, 'd' => $jd];
}

/**
 * الگوریتم دقیق تبدیل میلادی به شمسی با فرمت متنی
 */
function gregorian_to_jalali($gy, $gm, $gd, $mod = '/') {
    $jalali = gregorian_to_jalali_array($gy, $gm, $gd);
    $jm = str_pad($jalali['m'], 2, '0', STR_PAD_LEFT);
    $jd = str_pad($jalali['d'], 2, '0', STR_PAD_LEFT);
    return $jalali['y'] . $mod . $jm . $mod . $jd;
}

/**
 * الگوریتم دقیق و صحیح تبدیل شمسی به میلادی
 */
function jalali_to_gregorian($jy, $jm, $jd, $mod = '-') {
    $jy = (int)$jy;
    $jm = (int)$jm;
    $jd = (int)$jd;
    
    $jy_norm = $jy - 979;
    $jm_norm = $jm - 1;
    $jd_norm = $jd - 1;
    
    $j_day_no = 365 * $jy_norm + intdiv($jy_norm, 33) * 8 + intdiv($jy_norm % 33 + 3, 4);
    for ($i = 0; $i < $jm_norm; ++$i) {
        $j_day_no += $i < 6 ? 31 : 30;
    }
    $j_day_no += $jd_norm;
    
    $g_day_no = $j_day_no + 79;
    
    $gy = 1600 + 400 * intdiv($g_day_no, 146097);
    $g_day_no = $g_day_no % 146097;
    
    $leap = true;
    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * intdiv($g_day_no, 36524);
        $g_day_no = $g_day_no % 36524;
        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }
    
    $gy += 4 * intdiv($g_day_no, 1461);
    $g_day_no %= 1461;
    
    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += intdiv($g_day_no, 365);
        $g_day_no = $g_day_no % 365;
    }
    
    $month_days = [31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($i = 0; $g_day_no >= $month_days[$i]; $i++) {
        $g_day_no -= $month_days[$i];
    }
    
    $gm = $i + 1;
    $gd = $g_day_no + 1;
    
    return $gy . $mod . str_pad($gm, 2, '0', STR_PAD_LEFT) . $mod . str_pad($gd, 2, '0', STR_PAD_LEFT);
}

/**
 * تبدیل فرمت ورودی متنی شمسی (مانند 1405/04/10) به فرمت دیتابیس میلادی (YYYY-MM-DD)
 */
function parse_shamsi_to_gregorian($shamsi_date_str) {
    if (empty($shamsi_date_str)) return '';
    $shamsi_date_str = to_english_digits($shamsi_date_str);
    $parts = explode('/', $shamsi_date_str);
    if (count($parts) === 3) {
        return jalali_to_gregorian($parts[0], $parts[1], $parts[2]);
    }
    return date('Y-m-d');
}
?>
