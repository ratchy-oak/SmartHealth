<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('Error: This page only accepts POST requests.');
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: ../patient_form.php?error=csrf_invalid'); exit();
}

$errors = [];
$validated_data = [];
function validate_required_string($post_key) {
    global $errors;
    $value = trim($_POST[$post_key] ?? '');
    if (empty($value)) { $errors[] = "Field is required: " . htmlspecialchars($post_key); return null; }
    return $value;
}

// All validation logic remains the same...
$validated_data['patient_id'] = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$validated_data['cg_id'] = filter_input(INPUT_POST, 'cg_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$validated_data['cm_id'] = filter_input(INPUT_POST, 'cm_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$validated_data['created_by_user_id'] = filter_input(INPUT_POST, 'created_by_user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$validated_data['visit_date'] = validate_required_string('visit_date');
$validated_data['start_time'] = validate_required_string('start_time');
$validated_data['end_time'] = validate_required_string('end_time');
$validated_data['total_duration'] = trim($_POST['total_duration'] ?? 'N/A');
$validated_data['next_visit_date'] = validate_required_string('next_visit_date');
$validated_data['weight_kg'] = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT);
$validated_data['height_cm'] = filter_input(INPUT_POST, 'height_cm', FILTER_VALIDATE_INT);
$validated_data['bmi'] = filter_input(INPUT_POST, 'bmi', FILTER_VALIDATE_FLOAT);
$validated_data['bp_systolic'] = filter_input(INPUT_POST, 'bp_systolic', FILTER_VALIDATE_INT);
$validated_data['bp_diastolic'] = filter_input(INPUT_POST, 'bp_diastolic', FILTER_VALIDATE_INT);
$validated_data['pulse_rate'] = filter_input(INPUT_POST, 'pulse_rate', FILTER_VALIDATE_INT);
$validated_data['respiratory_rate'] = filter_input(INPUT_POST, 'respiratory_rate', FILTER_VALIDATE_INT);
$validated_data['body_temp'] = filter_input(INPUT_POST, 'body_temp', FILTER_VALIDATE_FLOAT);
$validated_data['symptoms_found'] = validate_required_string('symptoms_found');
$validated_data['care_provided'] = validate_required_string('care_provided');
$validated_data['relative_relationship'] = validate_required_string('relative_relationship');
$assessment_result = $_POST['assessment_result'] ?? '';
if (in_array($assessment_result, ['ดีขึ้น', 'เท่าเดิม', 'แย่ลง'])) { $validated_data['assessment_result'] = $assessment_result; } else { $errors[] = "Invalid assessment result."; }
$validated_data['q2_depressed'] = filter_input(INPUT_POST, 'q2_depressed', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
$validated_data['q2_anhedonia'] = filter_input(INPUT_POST, 'q2_anhedonia', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
$validated_data['q2_self_harm'] = filter_input(INPUT_POST, 'q2_self_harm', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);
$adl_total = 0;
$adl_keys = ["feeding", "grooming", "transfer", "toilet", "mobility", "dressing", "stairs", "bathing", "bowels", "bladder"];
foreach ($adl_keys as $key) {
    $adl_score = filter_input(INPUT_POST, 'adl_' . $key, FILTER_VALIDATE_INT);
    if ($adl_score === false || $adl_score < 0) { $errors[] = "Invalid ADL score: " . htmlspecialchars($key); $validated_data['adl_' . $key] = null;
    } else { $validated_data['adl_' . $key] = $adl_score; $adl_total += $adl_score; }
}
$validated_data['adl_total'] = $adl_total;

function save_base64_image($base64_string, $output_dir) {
    if (empty($base64_string) || !preg_match('/^data:image\/(png);base64,/', $base64_string)) return null;
    list(, $data) = explode(',', $base64_string);
    $decoded_data = base64_decode($data);
    if ($decoded_data === false) return null;
    $finfo = finfo_open(); $mime_type = finfo_buffer($finfo, $decoded_data, FILEINFO_MIME_TYPE); finfo_close($finfo);
    if ($mime_type !== 'image/png') return null;
    $file_name = 'sig_' . uniqid('', true) . '.png';
    if (!is_dir($output_dir)) { mkdir($output_dir, 0755, true); }
    if (file_put_contents($output_dir . $file_name, $decoded_data)) return $file_name;
    return null;
}
$signature_dir = '../upload/signature/';
$validated_data['relative_signature'] = save_base64_image($_POST['relative_signature'] ?? '', $signature_dir);
$validated_data['cm_signature'] = save_base64_image($_POST['cm_signature'] ?? '', $signature_dir);
if ($validated_data['relative_signature'] === null) $errors[] = "Relative signature is required.";
if ($validated_data['cm_signature'] === null) $errors[] = "CM signature is required.";
if (isset($_FILES['visit_photo']) && $_FILES['visit_photo']['error'] === UPLOAD_ERR_OK) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $_FILES['visit_photo']['tmp_name']); finfo_close($finfo);
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
        if ($_FILES['visit_photo']['size'] <= 2 * 1024 * 1024) {
            $fileExt = strtolower(pathinfo($_FILES['visit_photo']['name'], PATHINFO_EXTENSION));
            $newFileName = 'visit_' . uniqid('', true) . '.' . $fileExt;
            $destPath = '../upload/visit/';
            if (!is_dir($destPath)) { mkdir($destPath, 0755, true); }
            if (move_uploaded_file($_FILES['visit_photo']['tmp_name'], $destPath . $newFileName)) { $validated_data['visit_photo'] = $newFileName; } else { $errors[] = 'Failed to move uploaded photo.'; }
        } else { $errors[] = 'Photo is too large (max 2MB).'; }
    } else { $errors[] = 'Invalid photo file type.'; }
} else { $errors[] = 'Visit photo is required.'; }

if (!empty($errors)) {
    error_log("Care visit validation failed: " . implode(", ", $errors));
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: ' . $_SESSION['last_page']);
    exit();
}

$conn->begin_transaction();

try {
    $stmt_visit = $conn->prepare("INSERT INTO care_visits (patient_id, cg_id, cm_id, visit_date, start_time, end_time, total_duration, weight_kg, height_cm, bmi, bp_systolic, bp_diastolic, pulse_rate, respiratory_rate, body_temp, symptoms_found, care_provided, assessment_result, adl_feeding, adl_grooming, adl_transfer, adl_toilet, adl_mobility, adl_dressing, adl_stairs, adl_bathing, adl_bowels, adl_bladder, adl_total, q2_depressed, q2_anhedonia, q2_self_harm, visit_photo, next_visit_date, relative_relationship, relative_signature, cm_signature, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt_visit) {
        throw new Exception("DB Prepare failed for care_visits: " . $conn->error);
    }
    
    $stmt_visit->bind_param("iiissssdidiiiidsssiiiiiiiiiiiiiisssssi",
        $validated_data['patient_id'], $validated_data['cg_id'], $validated_data['cm_id'], $validated_data['visit_date'], $validated_data['start_time'], $validated_data['end_time'], $validated_data['total_duration'],
        $validated_data['weight_kg'], $validated_data['height_cm'], $validated_data['bmi'], $validated_data['bp_systolic'], $validated_data['bp_diastolic'], $validated_data['pulse_rate'], $validated_data['respiratory_rate'], $validated_data['body_temp'],
        $validated_data['symptoms_found'], $validated_data['care_provided'], $validated_data['assessment_result'],
        $validated_data['adl_feeding'], $validated_data['adl_grooming'], $validated_data['adl_transfer'], $validated_data['adl_toilet'], $validated_data['adl_mobility'],
        $validated_data['adl_dressing'], $validated_data['adl_stairs'], $validated_data['adl_bathing'], $validated_data['adl_bowels'], $validated_data['adl_bladder'], $validated_data['adl_total'],
        $validated_data['q2_depressed'], $validated_data['q2_anhedonia'], $validated_data['q2_self_harm'],
        $validated_data['visit_photo'], $validated_data['next_visit_date'], $validated_data['relative_relationship'], $validated_data['relative_signature'], $validated_data['cm_signature'],
        $validated_data['created_by_user_id']
    );

    if (!$stmt_visit->execute()) {
        throw new Exception("DB Execute failed for care_visits: " . $stmt_visit->error);
    }
    $stmt_visit->close();

    $stmt_patient_adl = $conn->prepare("UPDATE patient SET adl = ? WHERE id = ?");
    if (!$stmt_patient_adl) {
        throw new Exception("DB Prepare failed for patient update: " . $conn->error);
    }
    $stmt_patient_adl->bind_param("ii", $validated_data['adl_total'], $validated_data['patient_id']);

    if (!$stmt_patient_adl->execute()) {
        throw new Exception("DB Execute failed for patient update: " . $stmt_patient_adl->error);
    }
    $stmt_patient_adl->close();

    log_audit($conn, $_SESSION['s_id'], 'add_care_visit', 'บันทึกการเยี่ยมผู้ป่วย ID: ' . $validated_data['patient_id'] . ' และอัปเดต ADL เป็น ' . $validated_data['adl_total']);

    $conn->commit();

    // === THIS IS THE MODIFIED REDIRECT LOGIC ===
    $_SESSION['toast_message'] = [
        'type' => 'success',
        'message' => 'บันทึกการดูแลผู้ป่วยเรียบร้อยแล้ว'
    ];
    // Redirect directly to the patient's history page.
    header('Location: ../patient_care_history.php?patient_id=' . $validated_data['patient_id']);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    header('Location: ' . $_SESSION['last_page'] . '&error=db_transaction_failed');
    exit();
}
?>