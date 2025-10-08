<?php
require_once('../connect/session.php'); // Session first
require_once('../connect/security_headers.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['form_errors'] = ['เกิดข้อผิดพลาดด้านความปลอดภัย โปรดลองอีกครั้ง'];
    header("Location: ../patient_add.php");
    exit();
}

$errors = [];
$formData = $_POST; // Store all submitted data to repopulate the form on error

// --- 1. VALIDATE ALL DATA ---

// Numeric and ID fields
if (!filter_var($formData['hospital_id'], FILTER_VALIDATE_INT)) $errors[] = 'กรุณาเลือก รพ.สต. ที่ถูกต้อง';
if (!filter_var($formData['age'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 0, "max_range" => 120]])) $errors[] = 'กรุณาระบุอายุที่ถูกต้อง';
if (!filter_var($formData['adl'], FILTER_VALIDATE_INT)) $errors[] = 'กรุณาระบุค่า ADL ที่ถูกต้อง';
if (!filter_var($formData['cm_id'], FILTER_VALIDATE_INT)) $errors[] = 'กรุณาเลือก CM ที่ถูกต้อง';
if (!filter_var($formData['cg_id'], FILTER_VALIDATE_INT)) $errors[] = 'กรุณาเลือก CG ที่ถูกต้อง';

// Text fields that must not be empty
$required_text_fields = [
    'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'house_no' => 'บ้านเลขที่', 
    'village_no' => 'หมู่ที่', 'subdistrict' => 'ตำบล', 'district' => 'อำเภอ', 'province' => 'จังหวัด',
    'location' => 'พิกัด', 'disease' => 'โรคประจำตัว', 'disability_type' => 'ประเภทความพิการ', 
    'allergy' => 'ประวัติการแพ้ยา/อาหาร', 'needs' => 'ความต้องการช่วยเหลือ', 
    'relative_name' => 'ญาติผู้ดูแล', 'precaution' => 'ข้อควรระวัง', 'care_expectation' => 'ความคาดหวังในการดูแล'
];
foreach ($required_text_fields as $field => $label) {
    if (empty(trim($formData[$field]))) {
        $errors[] = "กรุณาระบุ " . $label;
    }
}

// Fields with specific formats
if (!preg_match('/^\d{13}$/', trim($formData['citizen_id']))) $errors[] = 'เลขบัตรประชาชนต้องเป็น 13 หลัก';
if (!preg_match('/^[0-9]{9,15}$/', trim($formData['phone']))) $errors[] = 'เบอร์โทรติดต่อไม่ถูกต้อง';
if (!preg_match('/^[0-9]{9,15}$/', trim($formData['relative_phone']))) $errors[] = 'เบอร์โทรศัพท์ญาติไม่ถูกต้อง';
if (!DateTime::createFromFormat('Y-m-d', trim($formData['birthdate']))) $errors[] = 'รูปแบบวันเกิดไม่ถูกต้อง';

// Select fields (enums)
if (!in_array(trim($formData['prefix']), ['นาย', 'นาง', 'นางสาว', 'เด็กหญิง', 'เด็กชาย', 'พระ'])) $errors[] = 'กรุณาเลือกคำนำหน้าที่ถูกต้อง';
if (!in_array(trim($formData['medical_rights']), ['สิทธิสวัสดิการการรักษาพยาบาลของข้าราชการ', 'สิทธิประกันสังคม', 'สิทธิหลักประกันสุขภาพ 30 บาท'])) $errors[] = 'กรุณาเลือกสิทธิการรักษาที่ถูกต้อง';
if (!in_array(trim($formData['status']), ['ผู้สูงอายุ', 'ผู้พิการ', 'ผู้มีภาวะพึ่งพิง', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC'])) $errors[] = 'กรุณาเลือกสถานะที่ถูกต้อง';
if (!in_array(trim($formData['life_status']), ['มีชีวิตอยู่', 'เสียชีวิต'])) $errors[] = 'กรุณาเลือกสถานะการมีชีวิตที่ถูกต้อง';
if (!in_array(trim($formData['group']), ['1', '2', '3', '4'])) $errors[] = 'กรุณาเลือกกลุ่มที่ถูกต้อง';
if (!in_array(trim($formData['tai']), ['B3','B4','B5','C2','C3','C4','I1','I2','I3'])) $errors[] = 'กรุณาเลือก TAI ที่ถูกต้อง';
if (!filter_var($formData['project_year'], FILTER_VALIDATE_INT)) $errors[] = 'กรุณาเลือกปีโครงการที่ถูกต้อง';

// File upload validation
$photoPath = null;
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'กรุณาอัพโหลดภาพถ่ายผู้ป่วย';
} else {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) $errors[] = 'ประเภทไฟล์รูปภาพไม่ถูกต้อง (JPG, PNG, WEBP เท่านั้น)';
    if ($_FILES['photo']['size'] > 2 * 1024 * 1024) $errors[] = 'ไฟล์รูปภาพต้องมีขนาดไม่เกิน 2MB';
}

// Unique Citizen ID check (only if there are no other errors)
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT id FROM patient WHERE citizen_id = ?");
    $stmt->bind_param("s", $formData['citizen_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
      $errors[] = 'เลขบัตรประชาชนนี้มีอยู่ในระบบแล้ว';
    }
    $stmt->close();
}

// --- 2. FINAL DECISION POINT ---
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $formData;
    header('Location: ../patient_add.php');
    exit();
}

// --- 3. PROCESS IF VALIDATION PASSED ---

// A. Handle file upload
$fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
$photoPath = 'patient_' . uniqid('', true) . '.' . $fileExt;
$destPath = '../upload/patient/' . $photoPath;
if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
    $_SESSION['form_errors'] = ['เกิดข้อผิดพลาดของเซิร์ฟเวอร์: ไม่สามารถบันทึกรูปภาพได้'];
    $_SESSION['form_data'] = $formData;
    header('Location: ../patient_add.php');
    exit();
}

// B. Prepare and execute database insert
$stmt = $conn->prepare("INSERT INTO patient ( hospital_id, prefix, first_name, last_name, birthdate, age, citizen_id, medical_rights, house_no, village_no, subdistrict, district, province, location, disease, disability_type, allergy, phone, photo, status, life_status, `group`, adl, tai, needs, cm_id, cg_id, project_year, relative_name, relative_phone, precaution, care_expectation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['form_errors'] = ['เกิดข้อผิดพลาดของฐานข้อมูล โปรดติดต่อผู้ดูแลระบบ'];
    header('Location: ../patient_add.php');
    exit();
}

$stmt->bind_param("issssissssssssssssssssissiisssss", 
    $formData['hospital_id'], $formData['prefix'], $formData['first_name'], $formData['last_name'], $formData['birthdate'], $formData['age'], $formData['citizen_id'], 
    $formData['medical_rights'], $formData['house_no'], $formData['village_no'], $formData['subdistrict'], $formData['district'], $formData['province'], 
    $formData['location'], $formData['disease'], $formData['disability_type'], $formData['allergy'], $formData['phone'], $photoPath, 
    $formData['status'], $formData['life_status'], $formData['group'], $formData['adl'], $formData['tai'], $formData['needs'], 
    $formData['cm_id'], $formData['cg_id'], $formData['project_year'], $formData['relative_name'], $formData['relative_phone'], 
    $formData['precaution'], $formData['care_expectation']
);

if ($stmt->execute()) {
  log_audit($conn, $_SESSION['s_id'], 'add_patient', 'เพิ่มข้อมูลผู้ป่วย: ' . $formData['first_name']);
  $_SESSION['toast_message'] = ['type' => 'success', 'message' => 'เพิ่มข้อมูลผู้ป่วยใหม่เรียบร้อยแล้ว'];
  header('Location: ../patient_info.php');
  exit();
} else {
  error_log("Database insert failed: " . $stmt->error);
  if (file_exists($destPath)) unlink($destPath); // Delete orphaned file
  $_SESSION['form_errors'] = ['เกิดข้อผิดพลาดของฐานข้อมูล: ไม่สามารถบันทึกข้อมูลได้'];
  $_SESSION['form_data'] = $formData;
  header('Location: ../patient_add.php');
  exit();
}
?>