<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
require_once('./connect/connect.php');
require_once('./connect/functions.php');

// 1. === SECURITY & AUTHORIZATION ===
if (!isset($_SESSION['s_id'])) {
    header("Location: ./index.php");
    exit();
}

if ($_SESSION['s_role'] !== 'admin') {
  $goBack = $_SESSION['last_page'] ?? 'home.php';
  header("Location: $goBack");
  exit();
}

// 2. === DATA FETCHING ===
$kpi_sql = "SELECT COUNT(id) as total_patients, SUM(CASE WHEN prefix IN ('นาย', 'เด็กชาย') THEN 1 ELSE 0 END) as gender_male, SUM(CASE WHEN prefix IN ('นาง', 'นางสาว', 'เด็กหญิง') THEN 1 ELSE 0 END) as gender_female, SUM(CASE WHEN adl BETWEEN 5 AND 11 THEN 1 ELSE 0 END) as group_homebound, SUM(CASE WHEN adl BETWEEN 0 AND 4 THEN 1 ELSE 0 END) as group_bedridden, SUM(CASE WHEN life_status = 'มีชีวิตอยู่' THEN 1 ELSE 0 END) as status_alive, SUM(CASE WHEN life_status = 'เสียชีวิต' THEN 1 ELSE 0 END) as status_deceased FROM patient";
$kpi_result = $conn->query($kpi_sql);
$kpi = $kpi_result->fetch_assoc();

$patients_by_hospital_sql = "SELECT h.name AS hospital_name, p.prefix, p.first_name, p.last_name FROM patient p JOIN hospital h ON p.hospital_id = h.id ORDER BY h.name ASC, p.first_name ASC";
$patients_result = $conn->query($patients_by_hospital_sql);
$patientsByHospital = [];
while ($row = $patients_result->fetch_assoc()) {
    $patientsByHospital[$row['hospital_name']][] = $row;
}

$equipment_sql = "SELECT et.name, COUNT(ei.id) as item_count FROM equipment_items ei JOIN equipment_types et ON ei.type_id = et.id GROUP BY et.id ORDER BY item_count DESC";
$equipment_result = $conn->query($equipment_sql);

$adl_group_sql = "SELECT `group` as adl_group, COUNT(id) as patient_count FROM patient WHERE `group` IS NOT NULL AND `group` != '' GROUP BY `group` ORDER BY `group` ASC";
$adl_group_result = $conn->query($adl_group_sql);
$adl_data = ['labels' => [], 'data' => []];
while ($row = $adl_group_result->fetch_assoc()) {
    $adl_data['labels'][] = 'กลุ่ม ' . $row['adl_group'];
    $adl_data['data'][] = $row['patient_count'];
}

$all_diseases_sql = "SELECT disease FROM patient WHERE disease IS NOT NULL AND disease != '' AND disease != 'ไม่มี'";
$all_diseases_result = $conn->query($all_diseases_sql);
$disease_counts = [];
while($row = $all_diseases_result->fetch_assoc()) {
    $diseases = explode(',', $row['disease']);
    foreach($diseases as $disease) {
        $trimmed_disease = trim($disease);
        if (!empty($trimmed_disease)) {
            if (!isset($disease_counts[$trimmed_disease])) { $disease_counts[$trimmed_disease] = 0; }
            $disease_counts[$trimmed_disease]++;
        }
    }
}
arsort($disease_counts);
$top_diseases = array_slice($disease_counts, 0, 7);
$disease_data = ['labels' => array_keys($top_diseases), 'data' => array_values($top_diseases)];

$map_sql = "SELECT id, first_name, last_name, status, location FROM patient WHERE location IS NOT NULL AND location != ''";
$map_result = $conn->query($map_sql);
$locations = [];
while ($row = $map_result->fetch_assoc()) {
    $coords = explode(',', $row['location']);
    if (count($coords) === 2 && is_numeric(trim($coords[0])) && is_numeric(trim($coords[1]))) {
        $locations[] = ['id' => $row['id'], 'name' => $row['first_name'].' '.$row['last_name'], 'status' => $row['status'], 'lat' => trim($coords[0]), 'lng' => trim($coords[1])];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0"/><title>SmartHealth</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600&display=swap" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link rel="stylesheet" href="css/style.css">
</head>
<body class="page-dashboard-ltc"
      data-locations='<?= htmlspecialchars(json_encode($locations), ENT_QUOTES, 'UTF-8') ?>'
      data-adl-groups='<?= htmlspecialchars(json_encode($adl_data), ENT_QUOTES, 'UTF-8') ?>'
      data-disease-counts='<?= htmlspecialchars(json_encode($disease_data), ENT_QUOTES, 'UTF-8') ?>'
>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top"><div class="container d-flex align-items-center justify-content-between"><a href="home.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="กลับสู่หน้าหลัก"><i class="bi bi-arrow-left fs-2"></i></a><div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fs-5">Dashboard</span></div><div class="d-flex align-items-center"><img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40"><span class="fs-5 me-2 d-none d-md-inline"><?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span><a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a></div></div></nav>
    <main class="container py-4 my-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="row g-4">
                    <div class="col-md-6"><div class="dashboard-card h-100"><div class="card-header">จำนวนผู้ป่วยทั้งหมด</div><div class="card-body"><div class="stat-main"><span>ทั้งหมด</span> <span class="stat-value" data-count="<?= htmlspecialchars($kpi['total_patients']) ?>">0</span> <span>คน</span></div><hr class="my-2"><div class="stat-sub mt-4"><div><span>ชาย</span> <span class="fw-bold"><?= htmlspecialchars($kpi['gender_male']) ?></span></div><div><span>หญิง</span> <span class="fw-bold"><?= htmlspecialchars($kpi['gender_female']) ?></span></div></div></div></div></div>
                    <div class="col-md-6"><div class="dashboard-card h-100"><div class="card-header">ผู้สูงอายุตามกลุ่ม</div><div class="card-body"><div class="stat-main"><span>ติดบ้าน</span> <span class="stat-value" data-count="<?= htmlspecialchars($kpi['group_homebound']) ?>">0</span> <span>คน</span></div><hr class="my-2"><div class="stat-main"><span>ติดเตียง</span> <span class="stat-value" data-count="<?= htmlspecialchars($kpi['group_bedridden']) ?>">0</span> <span>คน</span></div></div></div></div>
                    <div class="col-md-6"><div class="dashboard-card h-100"><div class="card-header">ผู้ป่วยแยกตาม รพ.สต.</div><div class="card-body scroll-list p-2"><?php foreach ($patientsByHospital as $hospitalName => $patients): ?><?php $collapseId = 'hosp_'.md5($hospitalName); ?><div class="dashboard-hospital-card"><button class="district-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"><span><?= htmlspecialchars($hospitalName) ?></span><div class="d-flex align-items-center"><span class="badge badge-rounded me-2"><?= count($patients) ?></span><i class="bi bi-chevron-down collapse-icon"></i></div></button><div class="collapse" id="<?= $collapseId ?>"><?php foreach ($patients as $patient): ?><div class="hospital-item"><i class="bi bi-person-fill text-muted"></i><span class="hospital-name ms-2"><?= htmlspecialchars($patient['prefix'].$patient['first_name'].' '.$patient['last_name']) ?></span></div><?php endforeach; ?></div></div><?php endforeach; ?></div></div></div>
                    <div class="col-md-6"><div class="dashboard-card h-100"><div class="card-header">ทะเบียนอุปกรณ์</div><div class="card-body scroll-list"><ul class="list-unstyled"><?php while($row = $equipment_result->fetch_assoc()): ?><li class="info-list-item"><span><i class="bi bi-heart-pulse text-muted"></i> <?= htmlspecialchars($row['name']) ?></span> <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= htmlspecialchars($row['item_count']) ?> เครื่อง</span></li><?php endwhile; ?></ul></div></div></div>
                    <div class="col-12"><div class="dashboard-card dashboard-card-map"><div class="card-header">แผนที่แสดงตำแหน่งที่อยู่ผู้ป่วย</div><div class="card-body p-0"><div id="ltcDashboardMap"></div></div></div></div>
                </div>
            </div>
            <div class="col-lg-4"><div class="row g-4"><div class="col-12"><div class="dashboard-card"><div class="card-header">สถานะการมีชีวิต</div><div class="card-body"><div class="stat-main"><span>มีชีวิต</span> <span class="stat-value" data-count="<?= htmlspecialchars($kpi['status_alive']) ?>">0</span> <span>คน</span></div><hr class="my-2"><div class="stat-main"><span>เสียชีวิต</span> <span class="stat-value" data-count="<?= htmlspecialchars($kpi['status_deceased']) ?>">0</span> <span>คน</span></div></div></div></div><div class="col-12"><div class="dashboard-card"><div class="card-header">กลุ่ม ADL</div><div class="card-body chart-container"><canvas id="adlGroupChart"></canvas></div></div></div><div class="col-12"><div class="dashboard-card"><div class="card-header">โรคที่พบมากที่สุด</div><div class="card-body chart-container"><canvas id="diseaseChart"></canvas></div></div></div></div></div>
        </div>
    </main>

    <div id="map-tooltip" class="custom-map-tooltip"></div>

    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>