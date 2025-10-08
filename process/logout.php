<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
include('../connect/connect.php');
include('../connect/audit.php');
if (isset($_SESSION['s_id'])) {
    log_audit($conn, $_SESSION['s_id'], 'logout', 'User logged out');
}
session_unset();
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
$_SESSION['toast_message'] = [
    'type' => 'info',
    'message' => 'ออกจากระบบเรียบร้อยแล้ว'
];
header('Location: ../index.php');
exit();
?>