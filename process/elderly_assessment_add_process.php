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
    header('Location: ../index.php?error=csrf');
    exit();
}
if (!isset($_SESSION['s_id'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

$errors = [];
$data = [];

function validate_enum($key, $allowed_values, $is_required = true) {
    global $errors, $data;
    $value = trim($_POST[$key] ?? '');
    
    if ($is_required && empty($value) && $value !== '0') {
        $errors[] = "Missing required field: $key";
        return;
    }
    
    if (!empty($value) && !in_array($value, $allowed_values)) {
        $errors[] = "Invalid value for field: $key";
        return;
    }
    
    $data[$key] = empty($value) ? null : $value;
}

// === SECTION 1: IDs and Basic Info ===
$data['patient_id'] = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
$data['user_id'] = $_SESSION['s_id'];
$data['assessment_date'] = trim($_POST['assessment_date'] ?? '');
if (!DateTime::createFromFormat('Y-m-d', $data['assessment_date'])) {
    $errors[] = "Invalid assessment date format.";
}
validate_enum('patient_status_at_assessment', ['ผู้สูงอายุ', 'ผู้พิการ', 'ผู้มีภาวะพึ่งพิง', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC']);

// === SECTION 2: Vitals ===
$data['weight_kg'] = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT, ['flags' => FILTER_NULL_ON_FAILURE]);
$data['height_cm'] = filter_input(INPUT_POST, 'height_cm', FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
$data['waist_cm'] = filter_input(INPUT_POST, 'waist_cm', FILTER_VALIDATE_FLOAT, ['flags' => FILTER_NULL_ON_FAILURE]);
$data['bmi'] = filter_input(INPUT_POST, 'bmi', FILTER_VALIDATE_FLOAT, ['flags' => FILTER_NULL_ON_FAILURE]);
$data['bp_systolic'] = filter_input(INPUT_POST, 'bp_systolic', FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
$data['bp_diastolic'] = filter_input(INPUT_POST, 'bp_diastolic', FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);

// === SECTION 3: ADL Scores ===
$adl_keys = ["feeding", "grooming", "transfer", "toilet", "mobility", "dressing", "stairs", "bathing", "bowels", "bladder"];
foreach ($adl_keys as $key) {
    $data['adl_' . $key] = filter_input(INPUT_POST, 'adl_' . $key, FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
}
$data['adl_total_score'] = filter_input(INPUT_POST, 'adl_total_score', FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
$data['adl_result_display'] = trim($_POST['adl_result_display'] ?? null);

// === SECTION 4: Chronic Diseases ===
validate_enum('chronic_diabetes', ['มี', 'ไม่มี', 'ไม่เคยตรวจ']);
validate_enum('chronic_liver', ['มี', 'ไม่มี', 'ไม่เคยตรวจ']);
validate_enum('chronic_heart', ['มี', 'ไม่มี', 'ไม่เคยตรวจ']);
validate_enum('chronic_hypertension', ['มี', 'ไม่มี', 'ไม่เคยตรวจ']);
validate_enum('chronic_stroke', ['มี', 'ไม่มี', 'ไม่เคยตรวจ']);
validate_enum('chronic_dyslipidemia', ['มี', 'ไม่มี', 'ไม่เคยตรวจ']);
validate_enum('chronic_food_allergy', ['มี', 'ไม่มี']);
validate_enum('chronic_illness_practice', ['รับการรักษาอยู่/ปฏิบัติตามที่แพทย์แนะนำ', 'รับการรักษาแต่ไม่สม่ำเสมอ', 'เคยรักษา ขณะนี้ไม่รักษา/หายาทานเอง']);
$data['chronic_other_diseases'] = trim($_POST['chronic_other_diseases'] ?? null);

// === SECTION 5: Behavior ===
$data['behavior_food_flavors'] = isset($_POST['preferred_food_flavors']) && is_array($_POST['preferred_food_flavors']) ? implode(', ', $_POST['preferred_food_flavors']) : null;
validate_enum('behavior_smoking', ['ไม่สูบ', 'สูบ', 'เคยสูบแต่เลิกแล้ว']);
validate_enum('behavior_alcohol', ['ไม่ดื่ม', 'ดื่ม', 'เคยดื่มแต่เลิกแล้ว']);
validate_enum('behavior_exercise', ['ออกกำลังกายทุกวัน ครั้งละ 30 นาที', 'ออกกำลังกายสัปดาห์ละมากกว่า 3 ครั้งๆ ละ 30 นาทีสม่ำเสมอ', 'ออกกำลังกายสัปดาห์ละ 3 ครั้งๆ ละ 30 นาทีสม่ำเสมอ', 'ออกกำลังกายน้อยกว่าสัปดาห์ละ 3 ครั้ง', 'ไม่ออกกำลังกาย']);

// === SECTION 6: Oral Health ===
validate_enum('oral_chewing_problem', ['มี', 'ไม่มี']);
validate_enum('oral_loose_teeth', ['มี', 'ไม่มี']);
validate_enum('oral_cavities', ['มี', 'ไม่มี']);
validate_enum('oral_bleeding_gums', ['มี', 'ไม่มี']);
$data['oral_summary'] = trim($_POST['oral_summary'] ?? null);

// === SECTION 7: Depression Screening ===
validate_enum('depression_sad', ['มี', 'ไม่มี']);
validate_enum('depression_bored', ['มี', 'ไม่มี']);
validate_enum('depression_self_harm', ['มี', 'ไม่มี']);
$data['depression_summary'] = trim($_POST['depression_summary'] ?? null);
$data['suicide_risk_summary'] = trim($_POST['suicide_risk_summary'] ?? null);

// === SECTION 8: Other Screenings ===
validate_enum('brain_assessment', ['ปกติ กรณีตอบถูกหมด', 'ผิดปกติ กรณีที่ตอบผิด 1-2 ข้อ อาจมีปัญหาเรื่องความจำ']);
validate_enum('fall_history_6m', ['มี', 'ไม่มี']);
validate_enum('fall_risk_summary', ['เสี่ยง', 'ไม่เสี่ยง', 'เดินไม่ได้']);
validate_enum('knee_stiffness', ['ใช่', 'ไม่ใช่']);
validate_enum('knee_crepitus', ['ใช่', 'ไม่ใช่']);
validate_enum('knee_bone_pain', ['ใช่', 'ไม่ใช่']);
validate_enum('knee_walking_pain', ['ใช่', 'ไม่ใช่']);
$data['knee_summary'] = trim($_POST['knee_summary'] ?? null);
validate_enum('eye_glasses', ['ใส่แว่น', 'ไม่ใส่แว่น']);
validate_enum('eye_surgery_history', ['เคย', 'ไม่เคย']);
validate_enum('eye_surgery_side', ['ซ้าย', 'ขวา'], false); // Not required
$data['eye_exam_result'] = isset($_POST['eye_exam_result']) && is_array($_POST['eye_exam_result']) ? implode(', ', $_POST['eye_exam_result']) : null;
validate_enum('tb_fever', ['มี', 'ไม่มี']);
validate_enum('tb_cough', ['มี', 'ไม่มี']);
validate_enum('tb_bloody_cough', ['มี', 'ไม่มี']);
validate_enum('tb_weight_loss', ['มี', 'ไม่มี']);
validate_enum('tb_night_sweats', ['มี', 'ไม่มี']);
$data['tb_summary'] = trim($_POST['tb_summary'] ?? null);
$data['colon_cancer_screening'] = trim($_POST['colon_cancer_screening'] ?? null);
$data['colon_cancer_summary'] = trim($_POST['colon_cancer_summary'] ?? null);


// === SECTION 9: Secure File Upload ===
if (isset($_FILES['assessment_photo']) && $_FILES['assessment_photo']['error'] === UPLOAD_ERR_OK) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['assessment_photo']['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($mime, $allowedMimes)) {
        if ($_FILES['assessment_photo']['size'] <= 2 * 1024 * 1024) {
            $fileExt = strtolower(pathinfo($_FILES['assessment_photo']['name'], PATHINFO_EXTENSION));
            $newFileName = 'assessment_' . uniqid('', true) . '.' . $fileExt;
            $destPath = '../upload/assessment/' . $newFileName;
            if (!is_dir(dirname($destPath))) { mkdir(dirname($destPath), 0755, true); }
            if (move_uploaded_file($_FILES['assessment_photo']['tmp_name'], $destPath)) {
                $data['assessment_photo'] = $newFileName;
            } else { $errors[] = 'Failed to move uploaded file.'; }
        } else { $errors[] = 'File is too large (max 2MB).'; }
    } else { $errors[] = 'Invalid file type. Only JPG, PNG, WEBP are allowed.'; }
} else {
    $errors[] = 'Assessment photo is required.';
}

// === SECTION 10: Redirect on Validation Failure ===
if (!empty($errors)) {
    error_log("Elderly assessment validation failed: " . implode(", ", $errors));
    header('Location: ' . $_SESSION['last_page'] . '&error=validation');
    exit();
}

// === SECTION 11: Database Transaction ===
$conn->begin_transaction();
try {
    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $column_list = '`' . implode('`, `', $columns) . '`';
    
    $sql = "INSERT INTO `elderly_assessment` ($column_list) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);

    $types = '';
    foreach ($data as $value) {
        if (is_int($value)) $types .= 'i';
        elseif (is_float($value)) $types .= 'd';
        else $types .= 's';
    }

    $stmt->bind_param($types, ...array_values($data));

    if (!$stmt->execute()) {
        throw new Exception("Execute failed (assessment): " . $stmt->error);
    }
    $stmt->close();

    // Also update the patient's main record with the latest status and ADL score
    $updateStmt = $conn->prepare("UPDATE patient SET status = ?, adl = ? WHERE id = ?");
    $updateStmt->bind_param(
        "sii", 
        $data['patient_status_at_assessment'], 
        $data['adl_total_score'], 
        $data['patient_id']
    );
    if (!$updateStmt->execute()) {
        throw new Exception("Execute failed (patient update): " . $updateStmt->error);
    }
    $updateStmt->close();

    log_audit($conn, $_SESSION['s_id'], 'add_elderly_assessment', 'บันทึกการประเมินสุขภาพผู้ป่วย ID: ' . $data['patient_id']);
    
    $conn->commit();
    
    $_SESSION['toast_message'] = [
        'type' => 'success',
        'message' => 'บันทึกการประเมินสุขภาพเรียบร้อยแล้ว'
    ];
    header("Location: ../elderly_history.php?patient_id=" . $data['patient_id']);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    header('Location: ' . $_SESSION['last_page'] . '&error=db_error');
    exit();
}
?>