<?php
header('Content-Type: application/json; charset=utf-8');
require_once('../connect/security_headers.php');
require_once('../connect/session.php');
require_once('../connect/connect.php');

// 1. Security & Authorization
if (!isset($_SESSION['s_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Authentication required.']));
}

// 2. Input Validation
$hospital_id = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT);
if (!$hospital_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid or missing hospital ID.']));
}

// 3. The robust SQL query
$sql = "
    SELECT 
        et.id, 
        et.name, 
        et.category,
        (SELECT COUNT(*) 
         FROM equipment_items 
         WHERE type_id = et.id 
           AND status = 'พร้อมใช้งาน' 
           AND hospital_id = ?
        ) as available_count
    FROM equipment_types et
    ORDER BY et.category, et.name
";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();

    // THIS IS THE NEW, MORE COMPATIBLE WAY TO FETCH DATA
    $stmt->store_result();
    $stmt->bind_result($id, $name, $category, $available_count);

    $equipmentData = [];
    while ($stmt->fetch()) {
        $equipmentData[] = [
            'id' => $id,
            'name' => $name,
            'category' => $category,
            'available_count' => $available_count
        ];
    }
    // END OF NEW DATA FETCHING METHOD

    $stmt->close();
    $conn->close();

    echo json_encode($equipmentData);

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error in get_equipment_by_hospital.php: " . $e->getMessage());
    echo json_encode(['error' => 'A server error occurred while fetching equipment data.']);
}
?>