<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
require_once('./connect/connect.php');
require_once('./connect/functions.php');

// Security & Authorization
if (!isset($_SESSION['s_username'])) { header("Location: ./index.php"); exit(); }
$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$current_user_role = $_SESSION['s_role'];
$current_user_hospital_id = $_SESSION['s_hospital_id'] ?? null;

// --- Query 1: Fetch PENDING requests (with available_count) ---
$requests_sql = "
    SELECT 
        r.id as request_id, r.request_date, r.notes,
        p.prefix, p.first_name, p.last_name, p.hospital_id,
        et.id as equipment_type_id, et.name as equipment_type_name, et.category,
        u.s_name as requester_name, u.s_surname as requester_surname,
        (SELECT COUNT(*) 
         FROM equipment_items 
         WHERE type_id = r.equipment_type_id 
           AND status = 'พร้อมใช้งาน' 
           AND hospital_id = p.hospital_id
        ) as available_count
    FROM equipment_requests r
    JOIN patient p ON r.patient_id = p.id
    JOIN equipment_types et ON r.equipment_type_id = et.id
    JOIN user u ON r.requester_id = u.s_id
    WHERE r.status = 'pending'
";

if ($current_user_role !== 'admin') {
    $requests_sql .= " AND p.hospital_id = ?";
}
$requests_sql .= " ORDER BY r.request_date ASC";
$stmt_req = $conn->prepare($requests_sql);
if ($current_user_role !== 'admin') {
    $stmt_req->bind_param("i", $current_user_hospital_id);
}
$stmt_req->execute();
$requests = $stmt_req->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_req->close();

// --- Query 2: Fetch ACTIVE loans ---
$loans_sql = "
    SELECT 
        l.id as loan_id, l.loan_date,
        i.serial_number, i.image_url,
        t.name as equipment_type_name,
        p.prefix, p.first_name, p.last_name,
        h.name as hospital_name
    FROM equipment_loans l
    JOIN equipment_items i ON l.item_id = i.id
    JOIN equipment_types t ON i.type_id = t.id
    JOIN patient p ON l.patient_id = p.id
    JOIN hospital h ON i.hospital_id = h.id
    WHERE l.loan_status = 'Active'
";
if ($current_user_role !== 'admin') {
    $loans_sql .= " AND i.hospital_id = ?";
}
$loans_sql .= " ORDER BY l.loan_date ASC";
$stmt_loan = $conn->prepare($loans_sql);
if ($current_user_role !== 'admin') {
    $stmt_loan->bind_param("i", $current_user_hospital_id);
}
$stmt_loan->execute();
$active_loans = $stmt_loan->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_loan->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SmartHealth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="page-approve-requests">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="request_equipment.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับ"><i class="bi bi-arrow-left fs-2"></i></a>
            <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fs-5 fw-bold">อนุมัติคำขอยืม</span></div>
            <div class="d-flex align-items-center">
                <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์" class="rounded-circle me-2" width="40" height="40">
                <span class="fs-5 me-2 d-none d-md-inline"><?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
                <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </nav>
    <div class="container my-5">
        <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <ul class="nav nav-pills nav-fill mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-tab-pane" type="button" role="tab" aria-controls="pending-tab-pane" aria-selected="true">
                    คำขอยืมที่รออนุมัติ <span class="badge bg-danger ms-2"><?= count($requests) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-tab-pane" type="button" role="tab" aria-controls="active-tab-pane" aria-selected="false">
                    อุปกรณ์ที่กำลังถูกยืม <span class="badge bg-secondary ms-2"><?= count($active_loans) ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="pending-tab-pane" role="tabpanel" aria-labelledby="pending-tab" tabindex="0">
                <div class="row g-4" id="request-card-container">
                    <?php if (empty($requests)): ?>
                        <div class="col-12" id="no-requests-row">
                            <div class="text-center p-5 bg-light rounded-3">
                                <i class="bi bi-check2-circle display-4 text-success"></i>
                                <h4 class="mt-3 fw-light">ไม่มีคำขอยืมที่รอการอนุมัติ</h4>
                                <p class="text-muted">ทุกอย่างเรียบร้อยดี!</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <?php $is_available = $req['available_count'] > 0; ?>
                            <div class="col-md-6 col-lg-4" id="request-row-<?= $req['request_id'] ?>">
                                <div class="card request-card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <small class="text-muted">วันที่ขอ: <?= toThaiDate($req['request_date']) ?></small>
                                                <h5 class="card-title mt-1 mb-0"><?= htmlspecialchars($req['equipment_type_name']) ?></h5>
                                                <div class="small text-muted"><?= htmlspecialchars($req['category']) ?></div>
                                            </div>
                                            <span class="badge rounded-pill availability-badge <?= $is_available ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>">
                                                <?= $is_available ? 'มีของ' : 'ของหมด' ?>
                                            </span>
                                        </div>
                                        <hr>
                                        <div class="request-info"><i class="bi bi-person-circle"></i><div><div class="info-label">สำหรับผู้ป่วย</div><div class="info-data"><?= htmlspecialchars($req['prefix'] . $req['first_name'] . ' ' . $req['last_name']) ?></div></div></div>
                                        <div class="request-info"><i class="bi bi-person-workspace"></i><div><div class="info-label">ผู้ขอ</div><div class="info-data"><?= htmlspecialchars($req['requester_name'] . ' ' . $req['requester_surname']) ?></div></div></div>
                                        <?php if(!empty($req['notes'])): ?><div class="request-info mt-2"><i class="bi bi-chat-left-text"></i><div><div class="info-label">หมายเหตุ</div><div class="info-data"><?= htmlspecialchars($req['notes']) ?></div></div></div><?php endif; ?>
                                    </div>
                                    <div class="card-footer bg-white border-0 mt-auto">
                                        <div class="d-grid gap-2">
                                            <?php if ($is_available): ?>
                                                <button type="button" class="btn btn-success approve-btn" data-request-id="<?= $req['request_id'] ?>" aria-label="อนุมัติคำขอของ <?= htmlspecialchars($req['prefix'] . $req['first_name']) ?>"><i class="bi bi-check-lg"></i> อนุมัติ</button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary" disabled><i class="bi bi-x-circle-fill"></i> ของหมด (อนุมัติไม่ได้)</button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger reject-btn" data-request-id="<?= $req['request_id'] ?>" aria-label="ปฏิเสธคำขอของ <?= htmlspecialchars($req['prefix'] . $req['first_name']) ?>"><i class="bi bi-x-lg"></i> ไม่อนุมัติ</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="active-tab-pane" role="tabpanel" aria-labelledby="active-tab" tabindex="0">
                <div class="row g-4" id="loan-card-container">
                    <?php if (empty($active_loans)): ?>
                        <div class="col-12" id="no-loans-row">
                             <div class="text-center p-5 bg-light rounded-3">
                                <i class="bi bi-check2-circle display-4 text-success"></i>
                                <h4 class="mt-3 fw-light">ไม่มีอุปกรณ์ที่กำลังถูกยืม</h4>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_loans as $loan): ?>
                            <div class="col-md-6 col-lg-4" id="loan-row-<?= $loan['loan_id'] ?>">
                                <div class="card loan-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <img src="upload/equipment/<?= htmlspecialchars($loan['image_url'] ?? 'default_equipment.png') ?>" class="loan-card-img me-3" alt="<?= htmlspecialchars($loan['equipment_type_name']) ?>" onerror="this.src='upload/equipment/default_equipment.png'">
                                            <div>
                                                <h5 class="card-title mb-0"><?= htmlspecialchars($loan['equipment_type_name']) ?></h5>
                                                <small class="text-muted">S/N: <?= htmlspecialchars($loan['serial_number'] ?? 'N/A') ?></small>
                                            </div>
                                        </div>
                                        <div class="request-info"><i class="bi bi-person-circle"></i><div><div class="info-label">ผู้ยืม</div><div class="info-data"><?= htmlspecialchars($loan['prefix'] . $loan['first_name'] . ' ' . $loan['last_name']) ?></div></div></div>
                                        <div class="request-info"><i class="bi bi-hospital"></i><div><div class="info-label">รพ.สต.</div><div class="info-data"><?= htmlspecialchars($loan['hospital_name']) ?></div></div></div>
                                        <div class="request-info mb-0"><i class="bi bi-calendar-check"></i><div><div class="info-label">วันที่ยืม</div><div class="info-data"><?= toThaiDate($loan['loan_date']) ?></div></div></div>
                                    </div>
                                    <div class="card-footer bg-white border-0 pt-3">
                                        <button class="btn btn-primary w-100 return-btn" data-loan-id="<?= $loan['loan_id'] ?>" aria-label="บันทึกการรับคืนอุปกรณ์ <?= htmlspecialchars($loan['equipment_type_name']) ?>">
                                            <i class="bi bi-box-arrow-in-left me-1"></i> บันทึกการรับคืน
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">ยืนยันการดำเนินการ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmationModalBody">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn" id="confirmModalButton">ยืนยัน</button>
                </div>
            </div>
        </div>
    </div>

    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>