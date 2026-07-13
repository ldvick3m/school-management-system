<?php
/**
 * خروج کاربر و از بین بردن نشست فعال
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// خالی کردن متغیرهای سشن
$_SESSION = [];

// پاک کردن کوکی نشست در مرورگر
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// نابود کردن کامل سشن
session_destroy();

// هدایت به صفحه ورود
header("Location: index.php");
exit;
?>
