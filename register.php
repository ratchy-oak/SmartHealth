<?php
require_once('./connect/session.php');
require_once('./connect/security_headers.php');
require_once('./connect/connect.php');

if (!isset($_SESSION['s_username'])) {
    header("Location: ./index.php");
    exit();
}

if ($_SESSION['s_role'] !== 'admin') {
  $goBack = $_SESSION['last_page'] ?? 'home.php';
  header("Location: $goBack");
  exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$hosp_stmt = $conn->prepare("SELECT id, name FROM hospital ORDER BY name ASC");
$hosp_stmt->execute();
$hospitals = $hosp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get errors and old form data from session, then clear them
$errors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SmartHealth</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
</head>
<body class="page-register">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="home.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="กลับไปหน้าหลัก">
        <i class="bi bi-arrow-left fs-2"></i>
      </a>
      <div class="text-center position-absolute top-50 start-50 translate-middle">
        <span class="fw-bold fs-5">สร้างบัญชีผู้ใช้งาน</span>
      </div>
      <div class="d-flex align-items-center">
        <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="Profile"
            class="rounded-circle me-2" width="40" height="40">
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
    <form action="./process/register_process.php" method="post" enctype="multipart/form-data" class="p-4 p-md-5 mx-auto needs-validation" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger mb-4">
          <h5 class="alert-heading">เกิดข้อผิดพลาด!</h5>
          <ul class="mb-0">
              <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
          </ul>
      </div>
      <?php endif; ?>

      <div class="position-relative text-center mb-5">
        <hr class="section-divider">
        <span class="h5 bg-white px-4 position-relative z-2 fw-bold">ข้อมูลผู้ใช้งาน</span>
      </div>

      <div class="mb-3">
        <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้*" pattern="^[a-zA-Z0-9_]{4,20}$" value="<?= htmlspecialchars($formData['username'] ?? '') ?>" required>
        <div class="invalid-feedback">กรุณากรอกชื่อผู้ใช้ (4-20 ตัวอักษร ใช้เฉพาะ a-z, A-Z, 0-9, _)</div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน*" minlength="8" required>
          <div class="invalid-feedback">รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร</div>
        </div>
        <div class="col-md-6">
          <input type="password" name="confirm_password" class="form-control" placeholder="ยืนยันรหัสผ่าน*" minlength="8" required>
          <div class="invalid-feedback">กรุณายืนยันรหัสผ่านให้ตรงกัน</div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <select name="prefix" class="form-control" required>
            <option value="" disabled <?= empty($formData['prefix']) ? 'selected' : '' ?>>คำนำหน้า*</option>
            <option value="นาย" <?= ($formData['prefix'] ?? '') === 'นาย' ? 'selected' : '' ?>>นาย</option>
            <option value="นาง" <?= ($formData['prefix'] ?? '') === 'นาง' ? 'selected' : '' ?>>นาง</option>
            <option value="นางสาว" <?= ($formData['prefix'] ?? '') === 'นางสาว' ? 'selected' : '' ?>>นางสาว</option>
          </select>
          <div class="invalid-feedback">กรุณาเลือกคำนำหน้า</div>
        </div>
        <div class="col-md-4">
          <input type="text" name="name" class="form-control" placeholder="ชื่อจริง*" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
          <div class="invalid-feedback">กรุณาระบุชื่อจริง</div>
        </div>
        <div class="col-md-4">
          <input type="text" name="surname" class="form-control" placeholder="นามสกุล*" value="<?= htmlspecialchars($formData['surname'] ?? '') ?>" required>
          <div class="invalid-feedback">กรุณาระบุนามสกุล</div>
        </div>
      </div>

      <div class="mb-3">
        <input type="text" name="position" class="form-control" placeholder="ตำแหน่ง*" value="<?= htmlspecialchars($formData['position'] ?? '') ?>" required>
        <div class="invalid-feedback">กรุณาระบุตำแหน่ง</div>
      </div>
      <div class="mb-3">
        <input type="tel" name="phone_number" class="form-control" placeholder="เบอร์โทรศัพท์*" pattern="[0-9]{9,15}" value="<?= htmlspecialchars($formData['phone_number'] ?? '') ?>" required>
        <div class="invalid-feedback">กรุณาระบุเบอร์โทรศัพท์ให้ถูกต้อง</div>
      </div>

      <div class="mb-3">
        <label for="profile" class="form-label">ภาพโปรไฟล์*</label>
        <input type="file" name="profile" id="profile" class="form-control" accept="image/*" required>
        <div class="invalid-feedback">กรุณาอัพโหลดภาพโปรไฟล์</div>
      </div>
      
      <div class="mb-3">
        <select name="role" id="roleSelect" class="form-control" required>
          <option value="" disabled <?= empty($formData['role']) ? 'selected' : '' ?>>สิทธิ์ผู้ใช้*</option>
          <option value="admin" <?= ($formData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>ADMIN</option>
          <option value="cm" <?= ($formData['role'] ?? '') === 'cm' ? 'selected' : '' ?>>CM</option>
          <option value="cg" <?= ($formData['role'] ?? '') === 'cg' ? 'selected' : '' ?>>CG</option>
        </select>
        <div class="invalid-feedback">กรุณาเลือกสิทธิ์ผู้ใช้งาน</div>
      </div>

      <div id="affiliationWrapper" class="mb-3" style="display: none;">
          <select name="affiliation" id="affiliationSelect" class="form-control">
              <option value="" disabled <?= empty($formData['affiliation']) ? 'selected' : '' ?>>สังกัด*</option>
              <option value="หน่วยบริการสาธารณสุขระดับปฐมภูมิ" <?= ($formData['affiliation'] ?? '') === 'หน่วยบริการสาธารณสุขระดับปฐมภูมิ' ? 'selected' : '' ?>>หน่วยบริการสาธารณสุขระดับปฐมภูมิ</option>
              <option value="องค์กรปกครองส่วนท้องถิ่น" <?= ($formData['affiliation'] ?? '') === 'องค์กรปกครองส่วนท้องถิ่น' ? 'selected' : '' ?>>องค์กรปกครองส่วนท้องถิ่น</option>
          </select>
          <div class="invalid-feedback">กรุณาเลือกสังกัดสำหรับ Admin</div>
      </div>

      <div class="mb-3">
        <select name="hospital_id" class="form-control" required>
          <option value="" disabled <?= empty($formData['hospital_id']) ? 'selected' : '' ?>>เลือกโรงพยาบาล*</option>
          <?php foreach ($hospitals as $hospital): ?>
            <option value="<?= $hospital['id'] ?>" <?= ($formData['hospital_id'] ?? '') == $hospital['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hospital['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="invalid-feedback">กรุณาเลือกโรงพยาบาล</div>
      </div>

      <div class="text-center mt-5">
        <button type="submit" name="register" class="btn btn-dark px-5 py-2">ลงทะเบียน</button>
      </div>
    </form>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/script.js"></script>
</body>
</html>