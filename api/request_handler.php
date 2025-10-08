<?php
header('Content-Type: application/json; charset=utf-8');
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

// 1. Security & Authorization
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit(json_encode(['error' => 'Method Not Allowed']));
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403); exit(json_encode(['error' => 'Invalid CSRF token']));
}
if (!isset($_SESSION['s_id'])) {
    http_response_code(403); exit(json_encode(['error' => 'Unauthorized']));
}

$action = $_POST['action'] ?? '';
$request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$handler_id = $_SESSION['s_id'];

if (!$request_id || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400); exit(json_encode(['error' => 'Invalid action or request ID.']));
}

$conn->begin_transaction();
try {
    // Get request details
    $req_stmt = $conn->prepare("
        SELECT r.*, p.hospital_id 
        FROM equipment_requests r
        JOIN patient p ON r.patient_id = p.id
        WHERE r.id = ? AND r.status = 'pending' FOR UPDATE
    ");
    $req_stmt->bind_param("i", $request_id);
    $req_stmt->execute();
    $request = $req_stmt->get_result()->fetch_assoc();
    $req_stmt->close();

    if (!$request) {
        throw new Exception("Request not found or already handled.", 404);
    }

    if ($action === 'approve') {
        // Find an available item of the requested type
        $patient_hospital_id = $request['hospital_id'];
        if (!$patient_hospital_id) {
            throw new Exception("Could not determine the patient's hospital.", 400);
        }

        // Find an available item of the requested type AT THE CORRECT HOSPITAL
        $item_stmt = $conn->prepare("
            SELECT id FROM equipment_items 
            WHERE type_id = ? AND hospital_id = ? AND status = 'พร้อมใช้งาน' 
            LIMIT 1 FOR UPDATE
        ");
        $item_stmt->bind_param("ii", $request['equipment_type_id'], $patient_hospital_id);
        $item_stmt->execute();
        $item = $item_stmt->get_result()->fetch_assoc();
        $item_stmt->close();

        if (!$item) {
            throw new Exception("No available items of this type.", 409); // 409 Conflict
        }
        $item_id = $item['id'];

        // 1. Update item status to 'In Use'
        $update_item_stmt = $conn->prepare("UPDATE equipment_items SET status = 'กำลังใช้งาน' WHERE id = ?");
        $update_item_stmt->bind_param("i", $item_id);
        $update_item_stmt->execute();
        $update_item_stmt->close();
        
        // 2. Create a new loan record
        $loan_stmt = $conn->prepare("INSERT INTO equipment_loans (item_id, patient_id, loan_date, loan_status) VALUES (?, ?, ?, 'Active')");
        $loan_stmt->bind_param("iis", $item_id, $request['patient_id'], $request['request_date']);
        $loan_stmt->execute();
        $loan_stmt->close();
        
        // 3. Update request status to 'approved'
        $update_req_stmt = $conn->prepare("UPDATE equipment_requests SET status = 'approved', handler_id = ?, handled_at = NOW() WHERE id = ?");
        $update_req_stmt->bind_param("ii", $handler_id, $request_id);
        $update_req_stmt->execute();
        $update_req_stmt->close();
        
        log_audit($conn, $handler_id, 'approve_request', "Approved request ID $request_id. Assigned item ID $item_id to patient ID " . $request['patient_id']);
        echo json_encode(['success' => true, 'message' => 'Request approved and item assigned.']);

    } elseif ($action === 'reject') {
        $update_req_stmt = $conn->prepare("UPDATE equipment_requests SET status = 'rejected', handler_id = ?, handled_at = NOW() WHERE id = ?");
        $update_req_stmt->bind_param("ii", $handler_id, $request_id);
        $update_req_stmt->execute();
        $update_req_stmt->close();

        log_audit($conn, $handler_id, 'reject_request', "Rejected request ID $request_id.");
        echo json_encode(['success' => true, 'message' => 'Request rejected.']);
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $code = $e->getCode() > 0 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>