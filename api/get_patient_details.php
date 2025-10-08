<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['s_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patient_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid patient ID.']);
    exit();
}

if ($_SESSION['s_role'] !== 'admin' && !empty($_SESSION['s_hospital_id'])) {
    $auth_stmt = $conn->prepare("SELECT hospital_id FROM patient WHERE id = ?");
    $auth_stmt->bind_param("i", $patient_id);
    $auth_stmt->execute();
    $auth_result = $auth_stmt->get_result();
    if ($auth_result->num_rows > 0) {
        $patient_hospital_id = $auth_result->fetch_assoc()['hospital_id'];
        if ($patient_hospital_id != $_SESSION['s_hospital_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to view this patient.']);
            exit();
        }
    }
}

$stmt = $conn->prepare("
    SELECT p.*, 
           h.name as hospital_name, 
           cm.s_prefix as cm_prefix, cm.s_name as cm_name, cm.s_surname as cm_surname,
           cg.s_prefix as cg_prefix, cg.s_name as cg_name, cg.s_surname as cg_surname
    FROM patient p
    LEFT JOIN hospital h ON p.hospital_id = h.id
    LEFT JOIN user cm ON p.cm_id = cm.s_id
    LEFT JOIN user cg ON p.cg_id = cg.s_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $patient_data = $result->fetch_assoc();
    echo json_encode($patient_data);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Patient not found.']);
}

$stmt->close();
$conn->close();
?>