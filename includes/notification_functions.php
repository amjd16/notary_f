<?php
/**
 * قالب عرض الرسائل والإشعارات
 * Message and Notification Display Template
 */

// منع الوصول المباشر
if (!defined("SYSTEM_ACCESS")) {
    die("Access denied");
}

/**
 * دالة لعرض رسالة فلاش
 * @param string $type نوع الرسالة (success, error, warning, info)
 * @param string $message نص الرسالة
 */
function displayFlashMessage($type, $message) {
    echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}

/**
 * دالة لعرض إشعار
 * @param array $notification بيانات الإشعار
 */
function displayNotification($notification) {
    $icon = "fas fa-info-circle";
    $class = "alert-info";
    
    switch ($notification["type"]) {
        case "success":
            $icon = "fas fa-check-circle";
            $class = "alert-success";
            break;
        case "error":
            $icon = "fas fa-times-circle";
            $class = "alert-danger";
            break;
        case "warning":
            $icon = "fas fa-exclamation-triangle";
            $class = "alert-warning";
            break;
        case "info":
        default:
            $icon = "fas fa-info-circle";
            $class = "alert-info";
            break;
    }
    
    $isReadClass = $notification["is_read"] ? "notification-read" : "notification-unread";
    
    echo '<div class="alert ' . $class . ' ' . $isReadClass . ' d-flex align-items-center" role="alert" data-notification-id="' . $notification["notification_id"] . '">';
    echo '    <i class="' . $icon . ' me-2"></i>';
    echo '    <div>';
    echo '        <h6 class="alert-heading mb-1">' . htmlspecialchars($notification["title"]) . '</h6>';
    echo '        <p class="mb-0">' . htmlspecialchars($notification["message"]) . '</p>';
    echo '        <small class="text-muted">' . formatTimeAgo($notification["created_at"]) . '</small>';
    echo '    </div>';
    echo '    <button type="button" class="btn-close me-auto" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}

/**
 * دالة لعرض قائمة الإشعارات
 * @param array $notifications مصفوفة الإشعارات
 */
function displayNotificationList($notifications) {
    if (empty($notifications)) {
        echo '<div class="alert alert-info text-center">لا توجد إشعارات حالياً.</div>';
        return;
    }
    
    echo '<div class="list-group">';
    foreach ($notifications as $notification) {
        $isReadClass = $notification["is_read"] ? "list-group-item-secondary" : "list-group-item-primary";
        $icon = "fas fa-bell";
        
        echo '<a href="#" class="list-group-item list-group-item-action d-flex align-items-center ' . $isReadClass . '" data-notification-id="' . $notification["notification_id"] . '">';
        echo '    <i class="' . $icon . ' me-3"></i>';
        echo '    <div class="flex-grow-1">';
        echo '        <h6 class="mb-1">' . htmlspecialchars($notification["title"]) . '</h6>';
        echo '        <p class="mb-1 text-muted">' . htmlspecialchars(truncateText($notification["message"], 100)) . '</p>';
        echo '        <small class="text-muted">' . formatTimeAgo($notification["created_at"]) . '</small>';
        echo '    </div>';
        if (!$notification["is_read"]) {
            echo '    <span class="badge bg-primary rounded-pill">جديد</span>';
        }
        echo '</a>';
    }
    echo '</div>';
}

/**
 * دالة لإضافة رسالة فلاش إلى الجلسة
 * @param string $type نوع الرسالة (success, error, warning, info)
 * @param string $message نص الرسالة
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION["flash_messages"])) {
        $_SESSION["flash_messages"] = [];
    }
    $_SESSION["flash_messages"][] = ["type" => $type, "text" => $message];
}

/**
 * دالة لجلب الإشعارات غير المقروءة للمستخدم الحالي
 * @return array مصفوفة بالإشعارات غير المقروءة
 */
function getUnreadNotifications() {
    global $database;
    if (!isLoggedIn()) return [];
    
    $userId = getCurrentUser()["user_id"];
    return $database->select(
        "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC",
        [$userId]
    );
}

/**
 * دالة لجلب جميع الإشعارات للمستخدم الحالي
 * @return array مصفوفة بجميع الإشعارات
 */
function getAllNotifications() {
    global $database;
    if (!isLoggedIn()) return [];
    
    $userId = getCurrentUser()["user_id"];
    return $database->select(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC",
        [$userId]
    );
}

/**
 * دالة لوضع علامة على إشعار كمقروء
 * @param int $notificationId معرف الإشعار
 * @return bool True عند النجاح، False عند الفشل
 */
function markNotificationAsRead($notificationId) {
    global $database;
    if (!isLoggedIn()) return false;
    
    $userId = getCurrentUser()["user_id"];
    $result = $database->update(
        "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?",
        [$notificationId, $userId]
    );
    return $result !== false;
}

/**
 * دالة لوضع علامة على جميع الإشعارات كمقروءة
 * @return bool True عند النجاح، False عند الفشل
 */
function markAllNotificationsAsRead() {
    global $database;
    if (!isLoggedIn()) return false;
    
    $userId = getCurrentUser()["user_id"];
    $result = $database->update(
        "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
        [$userId]
    );
    return $result !== false;
}

/**
 * دالة لإرسال إشعار جديد
 * @param int $userId معرف المستخدم المستهدف
 * @param string $title عنوان الإشعار
 * @param string $message نص الإشعار
 * @param string $type نوع الإشعار (info, success, warning, error)
 * @param string $link رابط الإشعار (اختياري)
 * @return int|bool معرف الإشعار الجديد أو False عند الفشل
 */
function sendNotification($userId, $title, $message, $type = "info", $link = null) {
    global $database;
    
    return $database->insert(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)",
        [$userId, $title, $message, $type, $link]
    );
}

?>

