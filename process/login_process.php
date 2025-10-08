<?php
require_once('../connect/session.php');

require_once('../connect/security_headers.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['toast_message'] = ['type' => 'danger', 'message' => 'เกิดข้อผิดพลาดด้านความปลอดภัย โปรดลองอีกครั้ง'];
    header('Location: ../index.php');
    exit();
}

if (isset($_POST['submit'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $limit = 5;
    $interval = '10 MINUTE';

    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM login_attempts
        WHERE ip_address = ?
        AND attempt_time > (NOW() - INTERVAL $interval)
    ");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->bind_result($attempts);
    $stmt->fetch();
    $stmt->close();

    if ($attempts >= $limit) {
        $_SESSION['toast_message'] = ['type' => 'danger', 'message' => 'คุณพยายามเข้าสู่ระบบบ่อยเกินไป โปรดรอสักครู่'];
        header('Location: ../index.php');
        exit();
    }
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM user WHERE s_username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['s_password'])) {
            $conn->query("DELETE FROM login_attempts WHERE ip_address = '$ip'");
            
            session_regenerate_id(true);

            $_SESSION['s_id']           = $user['s_id'];
            $_SESSION['s_username']     = $user['s_username'];
            $_SESSION['s_profile']      = $user['s_profile'];
            $_SESSION['s_role']         = $user['s_role'];
            $_SESSION['s_name']         = $user['s_name'] ?? '';
            $_SESSION['s_surname']      = $user['s_surname'] ?? '';
            $_SESSION['s_hospital_id']  = $user['s_hospital_id'] ?? null;
            $_SESSION['csrf_token']     = bin2hex(random_bytes(32));

            log_audit($conn, $user['s_id'], 'login', 'User logged in');
            header('Location: ../home.php');
            exit();

        }
    }

    $log_stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())");
    $log_stmt->bind_param("s", $ip);
    $log_stmt->execute();
    
    $_SESSION['toast_message'] = ['type' => 'danger', 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
    header('Location: ../index.php');
    exit();
}

?>