<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/functions.php'); // For toThaiDate function

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['s_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

$assessment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$assessment_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid assessment ID.']);
    exit();
}

// Authorization check: Ensure the user can view the patient this assessment belongs to.
$auth_stmt = $conn->prepare("
    SELECT p.hospital_id 
    FROM elderly_assessment ea
    JOIN patient p ON ea.patient_id = p.id 
    WHERE ea.id = ?
");
$auth_stmt->bind_param("i", $assessment_id);
$auth_stmt->execute();
$auth_result = $auth_stmt->get_result();

if ($auth_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Assessment not found.']);
    exit();
}

$patient_hospital_id = $auth_result->fetch_assoc()['hospital_id'];
if ($_SESSION['s_role'] !== 'admin' && $patient_hospital_id != $_SESSION['s_hospital_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not authorized to view this record.']);
    exit();
}
$auth_stmt->close();

// Fetch the full assessment details
$stmt = $conn->prepare("
    SELECT ea.*, CONCAT(u.s_prefix, ' ', u.s_name, ' ', u.s_surname) as creator_fullname
    FROM elderly_assessment ea
    LEFT JOIN user u ON ea.user_id = u.s_id
    WHERE ea.id = ?
");
$stmt->bind_param("i", $assessment_id);
$stmt->execute();
$result = $stmt->get_result();
$assessment_data = $result->fetch_assoc();

if ($assessment_data) {
    // Add formatted date for convenience
    $assessment_data['assessment_date_thai'] = toThaiDate($assessment_data['assessment_date']);
    echo json_encode($assessment_data);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Assessment data not found.']);
}

$stmt->close();
$conn->close();
?>