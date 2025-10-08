<?php
header('Content-Type: application/json; charset=utf-8');
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');
require_once('../connect/audit.php');

// --- CSRF TOKEN VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token. Request blocked.']);
        exit();
    }
}

if (!isset($_SESSION['s_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['s_id'];

// Determine item_id based on request type
if ($action === 'get_details') {
    $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
} else {
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
}

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item ID.']);
    exit();
}

switch ($action) {
    // --- FETCH DETAILS FOR MODAL ---
    case 'get_details':
        $details_sql = "
            SELECT 
                ei.*, 
                et.name as type_name,
                CASE 
                    WHEN el.loan_status = 'Active' THEN 'กำลังใช้งาน' 
                    ELSE ei.status 
                END AS status 
            FROM equipment_items ei
            JOIN equipment_types et ON ei.type_id = et.id
            LEFT JOIN equipment_loans el ON ei.id = el.item_id AND el.loan_status = 'Active'
            WHERE ei.id = ?
        ";
        $stmt = $conn->prepare($details_sql);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $itemDetails = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['details' => $itemDetails]);
        break;

    // --- UPDATE STATUS ---
    case 'update_status':
        // First, check the current status of the item
        $check_stmt = $conn->prepare("SELECT status FROM equipment_items WHERE id = ?");
        $check_stmt->bind_param("i", $item_id);
        $check_stmt->execute();
        $current_status = $check_stmt->get_result()->fetch_assoc()['status'];
        $check_stmt->close();

        // Block the update if the item is currently in use
        if ($current_status === 'กำลังใช้งาน') {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'ไม่สามารถเปลี่ยนสถานะของอุปกรณ์ที่กำลังใช้งานอยู่ได้']);
            exit();
        }
        
        $new_status = $_POST['status'] ?? '';
        $allowed_statuses = ['พร้อมใช้งาน', 'ชำรุด', 'ส่งซ่อม'];
        if (!in_array($new_status, $allowed_statuses)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status provided.']);
            exit();
        }
        
        // If not in use, proceed with the update
        $stmt = $conn->prepare("UPDATE equipment_items SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $item_id);
        if ($stmt->execute()) {
            log_audit($conn, $user_id, 'update_equipment_status', "Updated status of item ID $item_id to '$new_status'");
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database update failed.']);
        }
        break;
        
    // --- DELETE ITEM ---
    case 'delete_item':
        // Check if the item is currently in use before deleting
        $status_stmt = $conn->prepare("SELECT status FROM equipment_items WHERE id = ?");
        $status_stmt->bind_param("i", $item_id);
        $status_stmt->execute();
        $item_status_result = $status_stmt->get_result()->fetch_assoc();
        $status_stmt->close();

        if ($item_status_result && $item_status_result['status'] === 'กำลังใช้งาน') {
            http_response_code(400);
            echo json_encode(['error' => 'ไม่สามารถลบอุปกรณ์ที่กำลังใช้งานอยู่ได้']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM equipment_items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        if ($stmt->execute()) {
            log_audit($conn, $user_id, 'delete_equipment', "Deleted item ID $item_id");
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database delete failed.']);
        }
        break;

    case 'update_note':
        // Get the notes from the POST request.
        $notes = trim($_POST['notes'] ?? '');

        $stmt = $conn->prepare("UPDATE equipment_items SET notes = ? WHERE id = ?");
        $stmt->bind_param("si", $notes, $item_id);

        if ($stmt->execute()) {
            log_audit($conn, $user_id, 'update_equipment_note', "Updated notes for item ID $item_id");
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database update for notes failed.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action specified.']);
        break;
}

$conn->close();
?>