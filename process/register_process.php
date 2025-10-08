<?php
require_once('../connect/session.php');
require_once('../connect/security_headers.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['form_errors'] = ['เกิดข้อผิดพลาดด้านความปลอดภัย โปรดลองอีกครั้ง'];
    header("Location: ../register.php");
    exit();
}

$errors = [];
$formData = $_POST; // Store all submitted data to repopulate the form on error

// --- 1. ALL VALIDATION CHECKS ---

// Username
$username = trim($formData['username'] ?? '');
if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    $errors[] = 'ชื่อผู้ใช้ต้องมี 4-20 ตัวอักษร และใช้เฉพาะ a-z, A-Z, 0-9, _ เท่านั้น';
}

// Password
if (empty($formData['password']) || strlen($formData['password']) < 8) {
    $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
}
if ($formData['password'] !== $formData['confirm_password']) {
    $errors[] = 'รหัสผ่านไม่ตรงกัน';
}

// Other text fields
if (empty(trim($formData['prefix'] ?? ''))) $errors[] = 'กรุณาเลือกคำนำหน้า';
if (empty(trim($formData['name'] ?? ''))) $errors[] = 'กรุณาระบุชื่อจริง';
if (empty(trim($formData['surname'] ?? ''))) $errors[] = 'กรุณาระบุนามสกุล';
if (empty(trim($formData['position'] ?? ''))) $errors[] = 'กรุณาระบุตำแหน่ง';

// Phone
if (!preg_match('/^[0-9]{9,15}$/', trim($formData['phone_number'] ?? ''))) {
    $errors[] = 'กรุณาระบุเบอร์โทรศัพท์ให้ถูกต้อง';
}

// Select fields
$role = trim($formData['role'] ?? '');
if (!in_array($role, ['admin', 'cm', 'cg'])) $errors[] = 'กรุณาเลือกสิทธิ์ผู้ใช้ที่ถูกต้อง';
if (empty($formData['hospital_id'])) $errors[] = 'กรุณาเลือกโรงพยาบาล';

// Conditional field
if ($role === 'admin' && empty(trim($formData['affiliation'] ?? ''))) {
    $errors[] = 'กรุณาเลือกสังกัดสำหรับ Admin';
}

// File Validation
if (!isset($_FILES['profile']) || $_FILES['profile']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'กรุณาอัพโหลดภาพโปรไฟล์';
} else {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['profile']['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) $errors[] = 'ประเภทไฟล์รูปภาพไม่ถูกต้อง (ต้องเป็น JPG, PNG, หรือ WEBP)';
    if ($_FILES['profile']['size'] > 2 * 1024 * 1024) $errors[] = 'ไฟล์รูปภาพต้องมีขนาดไม่เกิน 2MB';
}

// Unique Username Check (only if there are no other errors yet)
if (empty($errors)) {
    $check = $conn->prepare("SELECT s_id FROM user WHERE s_username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $errors[] = 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
    }
    $check->close();
}

// --- 2. FINAL DECISION POINT ---
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $formData;
    header("Location: ../register.php");
    exit();
}

// --- 3. PROCESS IF VALIDATION PASSED ---

// A. Handle file upload first
$fileExt = strtolower(pathinfo($_FILES['profile']['name'], PATHINFO_EXTENSION));
$profileName = 'user_' . time() . uniqid() . '.' . $fileExt;
$destination = "../upload/profile/" . $profileName;

if (!move_uploaded_file($_FILES['profile']['tmp_name'], $destination)) {
    $_SESSION['form_errors'] = ['เกิดข้อผิดพลาดของเซิร์ฟเวอร์: ไม่สามารถบันทึกรูปโปรไฟล์ได้'];
    $_SESSION['form_data'] = $formData;
    header("Location: ../register.php");
    exit();
}

// B. Prepare data for database
$hashed_password = password_hash($formData['password'], PASSWORD_DEFAULT);
$hospital_id = (int)$formData['hospital_id'];
$affiliation = ($role === 'admin') ? trim($formData['affiliation']) : null;

// C. Insert into database
$stmt = $conn->prepare("INSERT INTO user (s_username, s_password, s_profile, s_role, s_prefix, s_name, s_surname, s_position, s_phone_number, s_hospital_id, s_affiliation) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "sssssssssis",
    $username,
    $hashed_password,
    $profileName,
    $role,
    $formData['prefix'],
    $formData['name'],
    $formData['surname'],
    $formData['position'],
    $formData['phone_number'],
    $hospital_id,
    $affiliation
);

if ($stmt->execute()) {
    log_audit($conn, $_SESSION['s_id'], 'register', "Created new user: $username");
    $_SESSION['toast_message'] = ['type' => 'success', 'message' => 'สร้างบัญชีผู้ใช้ใหม่เรียบร้อยแล้ว'];
    header("Location: ../home.php");
    exit();
} else {
    // If the database fails, set an error and delete the orphaned profile picture.
    $_SESSION['form_errors'] = ['เกิดข้อผิดพลาดของฐานข้อมูล: ไม่สามารถสร้างผู้ใช้ได้ โปรดติดต่อผู้ดูแลระบบ'];
    $_SESSION['form_data'] = $formData;
    if (file_exists($destination)) {
        unlink($destination);
    }
    header("Location: ../register.php");
    exit();
}
?>