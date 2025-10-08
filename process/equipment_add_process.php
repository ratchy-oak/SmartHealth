<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: ../equipment_add.php?error=csrf");
    exit();
}
if (!isset($_SESSION['s_id'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

$errors = [];
$type_name = trim($_POST['type_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$serial_number = trim($_POST['serial_number'] ?? '');
$hospital_id = filter_input(INPUT_POST, 'hospital_id', FILTER_VALIDATE_INT);
$notes = trim($_POST['notes'] ?? '');
$added_date = $_POST['added_date'] ?? date('Y-m-d');

if (empty($hospital_id)) $errors[] = "กรุณาเลือกโรงพยาบาล";
if (empty($_POST['type_id'])) $errors[] = "กรุณาเลือกชื่อ/ประเภทอุปกรณ์";
if (!DateTime::createFromFormat('Y-m-d', $added_date)) $errors[] = "รูปแบบวันที่ไม่ถูกต้อง";

if (!empty($serial_number)) {
    $stmt_check_sn = $conn->prepare("SELECT id FROM equipment_items WHERE serial_number = ?");
    $stmt_check_sn->bind_param("s", $serial_number);
    $stmt_check_sn->execute();
    $result_sn = $stmt_check_sn->get_result();
    if ($result_sn->num_rows > 0) {
        $errors[] = "Serial Number นี้มีอยู่ในระบบแล้ว";
    }
    $stmt_check_sn->close();
}

$image_url = 'default_equipment.png';
if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] === UPLOAD_ERR_OK) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['image_url']['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($mime, $allowedMimes)) {
        if ($_FILES['image_url']['size'] <= 2 * 1024 * 1024) { // 2MB Max
            $fileExt = strtolower(pathinfo($_FILES['image_url']['name'], PATHINFO_EXTENSION));
            $newFileName = 'equip_' . uniqid('', true) . '.' . $fileExt;
            $destPath = '../upload/equipment/' . $newFileName;
            if (move_uploaded_file($_FILES['image_url']['tmp_name'], $destPath)) {
                $image_url = $newFileName;
            } else { $errors[] = 'ไม่สามารถย้ายไฟล์ที่อัปโหลดได้'; }
        } else { $errors[] = 'ไฟล์รูปภาพต้องมีขนาดไม่เกิน 2MB'; }
    } else { $errors[] = 'ประเภทไฟล์รูปภาพไม่ถูกต้อง (ต้องเป็น JPG, PNG, หรือ WEBP)'; }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: ../equipment_add.php?error=validation');
    exit();
}

$conn->begin_transaction();
try {
    $type_id = filter_input(INPUT_POST, 'type_id', FILTER_VALIDATE_INT);

    $stmt_item = $conn->prepare("INSERT INTO equipment_items (type_id, serial_number, hospital_id, status, notes, image_url, added_date) VALUES (?, ?, ?, 'พร้อมใช้งาน', ?, ?, ?)");
    $stmt_item->bind_param("isssss", $type_id, $serial_number, $hospital_id, $notes, $image_url, $added_date);
    $stmt_item->execute();
    $new_item_id = $stmt_item->insert_id;
    $stmt_item->close();

    $log_stmt = $conn->prepare("SELECT name FROM equipment_types WHERE id = ?");
    $log_stmt->bind_param("i", $type_id);
    $log_stmt->execute();
    $type_name_for_log = $log_stmt->get_result()->fetch_assoc()['name'] ?? 'Unknown Type';
    $log_stmt->close();

    log_audit($conn, $_SESSION['s_id'], 'add_equipment', "Added new equipment item ID: $new_item_id (Type: $type_name)");
    $conn->commit();

    $_SESSION['toast_message'] = [
        'type' => 'success',
        'message' => 'เพิ่มอุปกรณ์ใหม่ในคลังเรียบร้อยแล้ว'
    ];
    header("Location: ../equipment.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Equipment add transaction failed: " . $e->getMessage());
    $_SESSION['form_data'] = $_POST;
    
    $_SESSION['toast_message'] = [
        'type' => 'danger',
        'message' => 'เกิดข้อผิดพลาดในฐานข้อมูล โปรดลองอีกครั้ง'
    ];

    header('Location: ../equipment_add.php');
    exit();
}
?>