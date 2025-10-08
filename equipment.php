<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
require_once('./connect/connect.php');
require_once('./connect/functions.php');

if (!isset($_SESSION['s_username'])) {
    header("Location: ./index.php");
    exit();
}
$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $itemsPerPage;
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

// --- Base Query: Joins to get all necessary display info ---
$base_query = "
    FROM equipment_items ei
    JOIN equipment_types et ON ei.type_id = et.id
    JOIN hospital h ON ei.hospital_id = h.id
    LEFT JOIN equipment_loans el ON ei.id = el.item_id AND el.loan_status = 'Active'
    LEFT JOIN patient p ON el.patient_id = p.id
";

// --- Filtering Logic ---
$current_user_role = $_SESSION['s_role'];
$current_user_hospital_id = $_SESSION['s_hospital_id'] ?? null;
$where_clauses = [];
$params = [];
$types = "";

if ($current_user_role !== 'admin' && !empty($current_user_hospital_id)) {
    $where_clauses[] = "ei.hospital_id = ?";
    $params[] = $current_user_hospital_id;
    $types .= "i";
}
if ($search !== '') {
    $where_clauses[] = "(et.name LIKE ? OR ei.serial_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}
if ($status_filter !== '') {
    $where_clauses[] = "ei.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// --- Count Total Items for Pagination ---
$count_sql = "SELECT COUNT(DISTINCT ei.id) AS total " . $base_query . $where_sql;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$totalItems = $count_stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);
$count_stmt->close();

// --- Fetch Equipment for the Current Page ---
$equipment_sql = "
    SELECT 
        ei.id, 
        CASE 
            WHEN el.loan_status = 'Active' THEN 'กำลังใช้งาน' 
            ELSE ei.status 
        END AS calculated_status, 
        ei.serial_number, ei.notes, ei.image_url,
        et.name AS type_name, et.category,
        h.name AS hospital_name,
        CONCAT_WS(' ', p.prefix, p.first_name, p.last_name) AS patient_fullname,
        el.loan_date
    " . $base_query . $where_sql . "
    ORDER BY et.name ASC, ei.id ASC
    LIMIT ? OFFSET ?
";
$final_params = array_merge($params, [$itemsPerPage, $offset]);
$final_types = $types . "ii";
$eq_stmt = $conn->prepare($equipment_sql);
$eq_stmt->bind_param($final_types, ...$final_params);
$eq_stmt->execute();
$equipment = $eq_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eq_stmt->close();

// --- KPI Stats Query ---
$kpi_where_sql = ''; $kpi_params = []; $kpi_types = '';
if ($current_user_role !== 'admin' && !empty($current_user_hospital_id)) {
    $kpi_where_sql = "WHERE ei.hospital_id = ?";
    $kpi_params[] = $current_user_hospital_id;
    $kpi_types = "i";
}
$kpi_sql = "SELECT COUNT(ei.id) AS total, SUM(CASE WHEN ei.status = 'พร้อมใช้งาน' THEN 1 ELSE 0 END) AS available, SUM(CASE WHEN ei.status = 'กำลังใช้งาน' THEN 1 ELSE 0 END) AS in_use, SUM(CASE WHEN ei.status IN ('ชำรุด', 'ส่งซ่อม') THEN 1 ELSE 0 END) AS maintenance FROM equipment_items ei " . $kpi_where_sql;
$kpi_stmt = $conn->prepare($kpi_sql);
if (!empty($kpi_params)) { $kpi_stmt->bind_param($kpi_types, ...$kpi_params); }
$kpi_stmt->execute();
$kpi = $kpi_stmt->get_result()->fetch_assoc();
$kpi_stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SmartHealth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-equipment">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="home.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="ย้อนกลับ"><i class="bi bi-arrow-left fs-2"></i></a>
            <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fs-5">ทะเบียนอุปกรณ์ทางการแพทย์</span></div>
            <div class="d-flex align-items-center">
                <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="โปรไฟล์" class="rounded-circle me-2" width="40" height="40">
                <span class="fs-5 me-2 d-none d-md-inline"><?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
                <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <section class="row g-4 mb-4">
            <?php
            $stats = [['label'=>'อุปกรณ์ทั้งหมด','value'=>$kpi['total'],'icon'=>'bi-boxes','color'=>'primary'],['label'=>'พร้อมใช้งาน','value'=>$kpi['available'],'icon'=>'bi-check-circle-fill','color'=>'success'],['label'=>'กำลังใช้งาน','value'=>$kpi['in_use'],'icon'=>'bi-person-check-fill','color'=>'warning'],['label'=>'ชำรุด/ซ่อม','value'=>$kpi['maintenance'],'icon'=>'bi-tools','color'=>'danger']];
            foreach ($stats as $stat): ?>
                <div class="col-6 col-md-3"><div class="stat-card"><div class="icon-wrapper bg-<?= htmlspecialchars($stat['color']) ?>-subtle text-<?= htmlspecialchars($stat['color']) ?>"><i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i></div><div class="stat-value"><?= htmlspecialchars($stat['value'] ?? 0) ?></div><div class="stat-label"><?= htmlspecialchars($stat['label']) ?></div></div></div>
            <?php endforeach; ?>
        </section>

        <div class="card">
            <div class="card-header bg-white py-4">
                <form id="equipmentFilterForm" method="get" class="row gx-2 gy-2 align-items-center" role="search" aria-label="ฟอร์มกรองอุปกรณ์">
                    <div class="col-md-5">
                        <input type="text" name="search" id="equipmentSearchInput" class="form-control" placeholder="ค้นหาชื่ออุปกรณ์หรือ S/N..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="status" id="equipmentStatusFilter" class="form-control">
                            <option value="">สถานะทั้งหมด</option>
                            <option value="พร้อมใช้งาน" <?= $status_filter == 'พร้อมใช้งาน' ? 'selected' : '' ?>>พร้อมใช้งาน</option>
                            <option value="กำลังใช้งาน" <?= $status_filter == 'กำลังใช้งาน' ? 'selected' : '' ?>>กำลังใช้งาน</option>
                            <option value="ชำรุด" <?= $status_filter == 'ชำรุด' ? 'selected' : '' ?>>ชำรุด</option>
                            <option value="ส่งซ่อม" <?= $status_filter == 'ส่งซ่อม' ? 'selected' : '' ?>>ส่งซ่อม</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <a href="equipment.php" class="btn btn-outline-dark w-100">ล้างตัวกรอง</a>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-4">อุปกรณ์</th>
                            <th scope="col">รพ.สต. เจ้าของ</th>
                            <th scope="col">สถานะ</th>
                            <th scope="col">ผู้ใช้งานปัจจุบัน</th>
                            <th scope="col" class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($equipment)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5">ไม่พบข้อมูลอุปกรณ์ที่ตรงกับเงื่อนไข</td></tr>
                        <?php else: ?>
                            <?php foreach ($equipment as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex flex-column align-items-center flex-md-row align-items-md-center text-center text-md-start">
                                            <img src="upload/equipment/<?= htmlspecialchars($item['image_url'] ?? 'default_equipment.png') ?>" class="equipment-img mb-2 mb-md-0 me-md-3" alt="<?= htmlspecialchars($item['type_name']) ?>" onerror="this.src='upload/equipment/default_equipment.png'">
                                            <div>
                                                <div class="equipment-name"><?= htmlspecialchars($item['type_name']) ?></div>
                                                <div class="text-muted small">หมวดหมู่: <?= htmlspecialchars($item['category'] ?? 'N/A') ?></div>
                                                <div class="text-muted small">S/N: <?= htmlspecialchars($item['serial_number'] ?? 'N/A') ?></div>
                                                
                                                <?php if (!empty($item['notes'])): ?>
                                                    <div class="text-warning small fst-italic mt-1" title="Note">
                                                        <i class="bi bi-info-circle-fill"></i> <?= htmlspecialchars($item['notes']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['hospital_name']) ?></td>
                                    <td data-label="สถานะ">
                                        <span class="badge rounded-pill status-<?= strtolower(str_replace(' ', '-', $item['calculated_status'])) ?>">
                                            <?= htmlspecialchars($item['calculated_status']) ?>
                                        </span>
                                        <?php if($item['status'] === 'กำลังใช้งาน' && !empty($item['queue_count'])): ?>
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-people-fill"></i> คิวรอ: <?= htmlspecialchars($item['queue_count']) ?> คน
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="ผู้ใช้งานปัจจุบัน"><?php if ($item['patient_fullname'] && trim($item['patient_fullname']) !== ''): ?><span class="patient-name"><?= htmlspecialchars($item['patient_fullname']) ?></span><div class="small text-muted">ยืมเมื่อ: <?= toThaiDate($item['loan_date']) ?></div><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                    <td data-label="จัดการ" class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary action-btn" data-bs-toggle="modal" data-bs-target="#actionModal" data-item-id="<?= $item['id'] ?>">
                                            <i class="bi bi-pencil-square me-1"></i>จัดการ
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Equipment page navigation" class="mt-4 mb-5">
                <div class="d-flex justify-content-center">
                    <ul class="pagination">
                        <?php
                            $queryParams = http_build_query(['search' => $search, 'status' => $status_filter]);
                        ?>
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link bg-dark text-white" href="?page=<?= $page - 1 ?>&<?= $queryParams ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link <?= $i == $page ? 'bg-dark text-white border-dark' : '' ?>" href="?page=<?= $i ?>&<?= $queryParams ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link bg-dark text-white" href="?page=<?= $page + 1 ?>&<?= $queryParams ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
        <?php endif; ?>
        <?php if ($totalPages <= 1): ?>
            <div class="my-5"></div>
        <?php endif; ?>
    </div>

    <div class="page-equipment-fab"><a href="equipment_add.php" class="btn btn-dark shadow-lg" role="button" aria-label="เพิ่มอุปกรณ์ใหม่"><i class="bi bi-plus-lg"></i></a></div>
    
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title w-100 text-center" id="actionModalLabel">จัดการอุปกรณ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="modal-content-placeholder">
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>