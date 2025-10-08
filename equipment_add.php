<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');
require_once('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
    header("Location: ./index.php");
    exit();
}

$_SESSION['last_page'] = basename($_SERVER['PHP_SELF']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$hospitals = $conn->query("SELECT id, name FROM hospital ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT DISTINCT category FROM equipment_types WHERE category IS NOT NULL ORDER BY category ASC")->fetch_all(MYSQLI_ASSOC);

$types_stmt = $conn->prepare("SELECT id, name, category FROM equipment_types ORDER BY name ASC");
$types_stmt->execute();
$equipment_types = $types_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$types_stmt->close();

$errors = $_SESSION['form_errors'] ?? [];

$errors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SmartHealth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body class="page-equipment-add">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="equipment.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="กลับไปหน้าทะเบียนอุปกรณ์">
                <i class="bi bi-arrow-left fs-2"></i>
            </a>
            <div class="text-center position-absolute top-50 start-50 translate-middle">
                <span class="fw-bold fs-5">เพิ่มอุปกรณ์ใหม่</span>
            </div>
            <div class="d-flex align-items-center">
                <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
                <span class="fw-bold fs-5 me-2 d-none d-md-inline">
                    <?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?>
                </span>
                <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4 my-4">
        <form action="./process/equipment_add_process.php" method="POST" class="p-4 p-md-5 mx-auto needs-validation" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger" role="alert">
                    <h5 class="alert-heading">เกิดข้อผิดพลาด!</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error) : ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="position-relative text-center mb-5">
                <hr class="section-divider">
                <span class="h5 bg-white px-4 position-relative z-2 fw-bold">ข้อมูลอุปกรณ์</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <select id="type_id_select" name="type_id" class="form-control" required>
                        <option value="" data-category="" disabled <?= empty($formData['type_name']) ? 'selected' : '' ?>>ชื่ออุปกรณ์*</option>
                        <?php foreach ($equipment_types as $type): ?>
                            <option 
                                value="<?= htmlspecialchars($type['id']) ?>" 
                                data-category="<?= htmlspecialchars($type['category']) ?>"
                                <?= (isset($formData['type_name']) && $formData['type_name'] == $type['name']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">กรุณาเลือกชื่อของอุปกรณ์</div>
                </div>
                <div class="col-md-6">
                    <input type="text" id="category" name="category" class="form-control" placeholder="หมวดหมู่ของอุปกรณ์" value="<?= htmlspecialchars($formData['category'] ?? '') ?>" readonly required>
                    <div class="invalid-feedback">หมวดหมู่จะถูกกรอกโดยอัตโนมัติ</div>
                </div>
                <div class="col-md-6">
                    <input placeholder="Serial Number" type="text" id="serial_number" name="serial_number" class="form-control" value="<?= htmlspecialchars($formData['serial_number'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <select id="hospital_id" name="hospital_id" class="form-control" required>
                        <option value="" disabled <?= empty($formData['hospital_id']) ? 'selected' : '' ?>>เลือกโรงพยาบาล*</option>
                        <?php foreach ($hospitals as $hospital) : ?>
                            <option value="<?= $hospital['id'] ?>" <?= ($formData['hospital_id'] ?? '') == $hospital['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($hospital['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">กรุณาเลือกโรงพยาบาลเจ้าของอุปกรณ์</div>
                </div>
                 <div class="col-md-12">
                    <input placeholder="วันที่เพิ่มเข้าคลัง*" type="date" id="added_date" name="added_date" class="form-control" value="<?= htmlspecialchars($formData['added_date'] ?? date('Y-m-d')) ?>" required>
                    <div class="invalid-feedback">กรุณาระบุวันที่เพิ่มอุปกรณ์</div>
                </div>
                <div class="col-md-12">
                    <label for="image_url" class="form-label">ภาพอุปกรณ์*</label>
                    <input type="file" name="image_url" id="image_url" class="form-control" accept="image/png, image/jpeg, image/webp" required>
                    <small id="image_info" class="form-text text-muted"></small>
                    <div class="invalid-feedback">กรุณาอัพโหลดภาพอุปกรณ์</div>
                </div>
                <div class="col-12">
                     <textarea placeholder="หมายเหตุ" id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="text-center mt-5">
                <button type="submit" class="btn btn-dark px-5 py-2">บันทึกข้อมูลอุปกรณ์</button>
            </div>
        </form>
    </div>

    <?php include './components/toast.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>