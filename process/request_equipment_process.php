<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

// 1. Security Checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: ../request_equipment.php?error=csrf");
    exit();
}
if (!isset($_SESSION['s_id'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

// 2. Data Validation
$patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
$equipment_type_id = filter_input(INPUT_POST, 'equipment_type_id', FILTER_VALIDATE_INT);
$request_date = $_POST['request_date'] ?? date('Y-m-d');
$notes = trim($_POST['notes'] ?? '');
$requester_id = $_SESSION['s_id'];

if (!$patient_id || !$equipment_type_id) {
    header("Location: ../request_equipment.php?error=validation_failed");
    exit();
}

// 3. Database Insertion
$stmt = $conn->prepare(
    "INSERT INTO equipment_requests (patient_id, equipment_type_id, request_date, requester_id, notes) VALUES (?, ?, ?, ?, ?)"
);
// This line has been corrected from "iiiss" to "iisis"
$stmt->bind_param("iisis", $patient_id, $equipment_type_id, $request_date, $requester_id, $notes);

if ($stmt->execute()) {
    $new_request_id = $stmt->insert_id;
    log_audit($conn, $requester_id, 'request_equipment', "User requested equipment type ID $equipment_type_id for patient ID $patient_id. Request ID: $new_request_id");
    $_SESSION['toast_message'] = [
        'type' => 'success',
        'message' => 'ส่งคำขอยืมอุปกรณ์เรียบร้อยแล้ว'
    ];
    header("Location: ../request_equipment.php");
    exit();
} else {
    error_log("Failed to create equipment request: " . $stmt->error);
    header("Location: ../request_equipment.php?error=db_error");
    exit();
}
?>