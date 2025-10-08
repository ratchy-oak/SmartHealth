<?php
header('Content-Type: application/json; charset=utf-8');
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

// 1. Security & Authorization Checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    exit(json_encode(['error' => 'Method Not Allowed']));
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403); 
    exit(json_encode(['error' => 'Invalid CSRF token']));
}
if (!isset($_SESSION['s_id'])) {
    http_response_code(403); 
    exit(json_encode(['error' => 'Unauthorized']));
}

// 2. Input Validation
$loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
if (!$loan_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid loan ID provided.']));
}

$handler_id = $_SESSION['s_id'];
$conn->begin_transaction();

try {
    // Step 1: Update the loan status to 'Returned' and get the item_id
    $loan_stmt = $conn->prepare(
        "UPDATE equipment_loans SET loan_status = 'Returned', actual_return_date = NOW() WHERE id = ? AND loan_status = 'Active'"
    );
    if (!$loan_stmt) throw new Exception("Database prepare failed for loan update.");
    
    $loan_stmt->bind_param("i", $loan_id);
    $loan_stmt->execute();

    if ($loan_stmt->affected_rows === 0) {
        throw new Exception("Loan not found or already returned.", 404);
    }
    $loan_stmt->close();
    
    // Step 2: Get the item_id from the loan we just updated
    $item_id_stmt = $conn->prepare("SELECT item_id FROM equipment_loans WHERE id = ?");
    if (!$item_id_stmt) throw new Exception("Database prepare failed for fetching item_id.");
    
    $item_id_stmt->bind_param("i", $loan_id);
    $item_id_stmt->execute();
    $result = $item_id_stmt->get_result();
    $item_id = $result->fetch_assoc()['item_id'];
    $item_id_stmt->close();

    if (!$item_id) {
        throw new Exception("Could not retrieve item ID from the loan record.");
    }

    // Step 3: Update the item's status back to 'Available'
    $item_stmt = $conn->prepare("UPDATE equipment_items SET status = 'พร้อมใช้งาน' WHERE id = ?");
    if (!$item_stmt) throw new Exception("Database prepare failed for item update.");

    $item_stmt->bind_param("i", $item_id);
    $item_stmt->execute();
    $item_stmt->close();

    // Step 4: Log the successful action
    log_audit($conn, $handler_id, 'return_equipment', "Recorded return for loan ID $loan_id. Item ID $item_id is now available.");

    // Step 5: Commit the transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Item returned successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    // Use the exception's code for the HTTP response if it's a valid code
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>