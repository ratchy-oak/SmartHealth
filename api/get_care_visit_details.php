<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/functions.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['s_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

$visit_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$visit_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid visit ID.']);
    exit();
}

$auth_stmt = $conn->prepare("SELECT p.hospital_id FROM care_visits cv JOIN patient p ON cv.patient_id = p.id WHERE cv.id = ?");
$auth_stmt->bind_param("i", $visit_id);
$auth_stmt->execute();
$auth_result = $auth_stmt->get_result();

if ($auth_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Visit not found.']);
    exit();
}

$patient_hospital_id = $auth_result->fetch_assoc()['hospital_id'];
if ($_SESSION['s_role'] !== 'admin' && $patient_hospital_id != $_SESSION['s_hospital_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not authorized to view this record.']);
    exit();
}
$auth_stmt->close();

$stmt = $conn->prepare("
    SELECT 
        cv.*,
        CONCAT_WS(' ', cm.s_prefix, cm.s_name, cm.s_surname) as cm_fullname,
        CONCAT_WS(' ', cg.s_prefix, cg.s_name, cg.s_surname) as cg_fullname
    FROM care_visits cv
    LEFT JOIN user cm ON cv.cm_id = cm.s_id
    LEFT JOIN user cg ON cv.cg_id = cg.s_id
    WHERE cv.id = ?
");
$stmt->bind_param("i", $visit_id);
$stmt->execute();
$result = $stmt->get_result();
$visit_data = $result->fetch_assoc();

if ($visit_data) {
    $visit_data['visit_date_thai'] = toThaiDate($visit_data['visit_date']);
    $visit_data['next_visit_date_thai'] = toThaiDate($visit_data['next_visit_date']);
}

$stmt->close();
$conn->close();

echo json_encode($visit_data);
?>