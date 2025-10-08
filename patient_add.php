<?php
require_once('./connect/session.php');
require_once('./connect/security_headers.php');
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
$cgUsers = $conn->query("SELECT s_id, s_prefix, s_name, s_surname FROM user WHERE s_role = 'cg' ORDER BY s_name ASC")->fetch_all(MYSQLI_ASSOC);
$cmUsers = $conn->query("SELECT s_id, s_prefix, s_name, s_surname FROM user WHERE s_role = 'cm' ORDER BY s_name ASC")->fetch_all(MYSQLI_ASSOC);

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
<body class="page-patient-add">
  <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3 sticky-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="patient_info.php" class="d-flex align-items-center text-dark text-decoration-none" aria-label="กลับไปหน้าข้อมูลผู้ป่วย"><i class="bi bi-arrow-left fs-2"></i></a>
      <div class="text-center position-absolute top-50 start-50 translate-middle"><span class="fw-bold fs-5">เพิ่มผู้สูงอายุและผู้มีภาวะพึ่งพิง</span></div>
      <div class="d-flex align-items-center">
        <img src="./upload/profile/<?= htmlspecialchars($_SESSION['s_profile']); ?>" alt="Profile" class="rounded-circle me-2" width="40" height="40">
        <span class="fw-bold fs-5 me-2 d-none d-md-inline"><?= htmlspecialchars($_SESSION['s_role'] . ' ' . $_SESSION['s_name']); ?></span>
        <a href="./process/logout.php" class="text-danger fs-4 ms-1" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </nav>

  <div class="container py-4 my-4">
    <form action="process/patient_add_process.php" method="POST" class="p-4 p-md-5 mx-auto needs-validation" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
      
      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger mb-4">
          <h5 class="alert-heading">พบข้อผิดพลาด!</h5>
          <ul class="mb-0">
              <?php foreach ($errors as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
          </ul>
      </div>
      <?php endif; ?>

      <div class="position-relative text-center mb-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">ข้อมูลผู้มีภาวะพึ่งพิง</span></div>
      <div class="row g-3 mb-3">
        <div class="col-md-12">
          <select name="hospital_id" class="form-control" required>
            <option value="" disabled <?= empty($formData['hospital_id']) ? 'selected' : '' ?>>เลือก รพ.สต.*</option>
            <?php foreach ($hospitals as $h): ?>
              <option value="<?= $h['id'] ?>" <?= ($formData['hospital_id'] ?? '') == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือก รพ.สต.</div>
        </div>
        <div class="col-md-3">
          <select name="prefix" class="form-control" required>
            <option value="" disabled <?= empty($formData['prefix']) ? 'selected' : '' ?>>คำนำหน้า*</option>
            <?php $prefixes = ['นาย', 'นาง', 'นางสาว', 'เด็กหญิง', 'เด็กชาย', 'พระ']; ?>
            <?php foreach($prefixes as $p): ?>
              <option value="<?= $p ?>" <?= ($formData['prefix'] ?? '') == $p ? 'selected' : '' ?>><?= $p ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือกคำนำหน้า</div>
        </div>
        <div class="col-md-4"><input type="text" name="first_name" class="form-control" placeholder="ชื่อ*" value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุชื่อ</div></div>
        <div class="col-md-5"><input type="text" name="last_name" class="form-control" placeholder="นามสกุล*" value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุนามสกุล</div></div>
        <div class="col-md-4"><input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($formData['birthdate'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุวันเกิด</div></div>
        <div class="col-md-2"><input type="number" name="age" class="form-control" placeholder="อายุ*" min="0" max="120" value="<?= htmlspecialchars($formData['age'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุอายุ</div></div>
        <div class="col-md-6"><input type="text" name="citizen_id" class="form-control" placeholder="เลขที่บัตรประชาชน*" minlength="13" maxlength="13" pattern="\d{13}" value="<?= htmlspecialchars($formData['citizen_id'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุเลขบัตรประชาชน 13 หลัก</div></div>
        <div class="col-md-12">
          <select name="medical_rights" class="form-control" required>
            <option value="" disabled <?= empty($formData['medical_rights']) ? 'selected' : '' ?>>สิทธิการรักษาพยาบาล*</option>
            <?php $rights = ['สิทธิสวัสดิการการรักษาพยาบาลของข้าราชการ', 'สิทธิประกันสังคม', 'สิทธิหลักประกันสุขภาพ 30 บาท']; ?>
            <?php foreach($rights as $r): ?>
              <option value="<?= $r ?>" <?= ($formData['medical_rights'] ?? '') == $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือกสิทธิการรักษาพยาบาล</div>
        </div>
        <div class="col-md-3"><input type="text" name="house_no" class="form-control" placeholder="บ้านเลขที่*" value="<?= htmlspecialchars($formData['house_no'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุบ้านเลขที่</div></div>
        <div class="col-md-3"><input type="text" name="village_no" class="form-control" placeholder="หมู่ที่*" value="<?= htmlspecialchars($formData['village_no'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุหมู่</div></div>
        <div class="col-md-3"><input type="text" name="subdistrict" class="form-control" placeholder="ตำบล*" value="<?= htmlspecialchars($formData['subdistrict'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุตำบล</div></div>
        <div class="col-md-3"><input type="text" name="district" class="form-control" placeholder="อำเภอ*" value="<?= htmlspecialchars($formData['district'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุอำเภอ</div></div>
        <div class="col-md-6"><input type="text" name="province" class="form-control" placeholder="จังหวัด*" value="<?= htmlspecialchars($formData['province'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุจังหวัด</div></div>
        <div class="col-md-6"><input type="text" name="location" id="locationInput" class="form-control" placeholder="พิกัด (คลิกเพื่อเลือกจากแผนที่)*" value="<?= htmlspecialchars($formData['location'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#mapModal" readonly required><div class="invalid-feedback">กรุณาระบุพิกัดจากแผนที่</div></div>
        <div class="col-md-6"><input type="text" name="disease" class="form-control" placeholder="โรคประจำตัว*" value="<?= htmlspecialchars($formData['disease'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุโรคประจำตัว</div></div>
        <div class="col-md-6"><input type="text" name="disability_type" class="form-control" placeholder="ประเภทความพิการ*" value="<?= htmlspecialchars($formData['disability_type'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุประเภทความพิการ</div></div>
        <div class="col-md-6"><input type="text" name="allergy" class="form-control" placeholder="ประวัติการแพ้ยา/อาหาร*" value="<?= htmlspecialchars($formData['allergy'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุประวัติการแพ้ยา/อาหาร</div></div>
        <div class="col-md-6"><input type="tel" name="phone" class="form-control" placeholder="เบอร์โทรติดต่อ*" pattern="[0-9]{9,15}" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุเบอร์โทรติดต่อ</div></div>
        <div class="col-md-12 mb-3"><label for="photo" class="form-label">ภาพถ่ายผู้ป่วย*</label><input type="file" name="photo" id="photo" class="form-control" accept="image/*" required><div class="invalid-feedback">กรุณาอัพโหลดภาพถ่าย</div></div>
        <div class="col-md-6">
          <select name="status" class="form-control" required>
            <option value="" disabled <?= empty($formData['status']) ? 'selected' : '' ?>>เลือกสถานะ*</option>
            <?php $statuses = ['ผู้สูงอายุ', 'ผู้พิการ', 'ผู้มีภาวะพึ่งพิง', 'ผู้มีภาวะพึ่งพิงในโครงการ LTC']; ?>
            <?php foreach($statuses as $s): ?>
              <option value="<?= $s ?>" <?= ($formData['status'] ?? '') == $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือกสถานะ</div>
        </div>
        <div class="col-md-6">
          <select name="life_status" class="form-control" required>
            <option value="" disabled <?= empty($formData['life_status']) ? 'selected' : '' ?>>สถานะการมีชีวิต*</option>
            <option value="มีชีวิตอยู่" <?= ($formData['life_status'] ?? '') == 'มีชีวิตอยู่' ? 'selected' : '' ?>>มีชีวิตอยู่</option>
            <option value="เสียชีวิต" <?= ($formData['life_status'] ?? '') == 'เสียชีวิต' ? 'selected' : '' ?>>เสียชีวิต</option>
          </select>
          <div class="invalid-feedback">กรุณาเลือกสถานะการมีชีวิต</div>
        </div>
      </div>

      <div class="position-relative text-center my-5"><hr class="section-divider"><span class="h5 bg-white px-4 position-relative z-2 fw-bold">ข้อมูลด้าน LTC</span></div>
      <div class="row g-3">
        <div class="col-md-4">
          <select name="group" class="form-control" required>
            <option value="" disabled <?= empty($formData['group']) ? 'selected' : '' ?>>กลุ่ม*</option>
            <?php for($i=1; $i<=4; $i++): ?>
              <option value="<?= $i ?>" <?= ($formData['group'] ?? '') == $i ? 'selected' : '' ?>>กลุ่ม <?= $i ?></option>
            <?php endfor; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือกกลุ่ม</div>
        </div>
        <div class="col-md-4"><input type="number" name="adl" class="form-control" placeholder="ADL*" value="<?= htmlspecialchars($formData['adl'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุ ADL</div></div>
        <div class="col-md-4">
          <select name="tai" class="form-control" required>
            <option value="" disabled <?= empty($formData['tai']) ? 'selected' : '' ?>>TAI*</option>
            <?php $tais = ['B3','B4','B5','C2','C3','C4','I1','I2','I3']; ?>
            <?php foreach($tais as $t): ?>
              <option value="<?= $t ?>" <?= ($formData['tai'] ?? '') == $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือก TAI</div>
        </div>
        <div class="col-md-12"><textarea name="needs" class="form-control" placeholder="ความต้องการช่วยเหลือ (ประเมินปัญหา/ความต้องการ)*" required><?= htmlspecialchars($formData['needs'] ?? '') ?></textarea><div class="invalid-feedback">กรุณาระบุความต้องการช่วยเหลือ</div></div>
        <div class="col-md-4">
          <select name="cm_id" class="form-control" required>
            <option value="" disabled <?= empty($formData['cm_id']) ? 'selected' : '' ?>>CM ที่ดูแล*</option>
            <?php foreach ($cmUsers as $cm): ?>
              <option value="<?= $cm['s_id'] ?>" <?= ($formData['cm_id'] ?? '') == $cm['s_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cm['s_prefix'].$cm['s_name'].' '.$cm['s_surname']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือก CM ที่ดูแล</div>
        </div>
        <div class="col-md-4">
          <select name="cg_id" class="form-control" required>
            <option value="" disabled <?= empty($formData['cg_id']) ? 'selected' : '' ?>>CG ที่ดูแล*</option>
            <?php foreach ($cgUsers as $cg): ?>
              <option value="<?= $cg['s_id'] ?>" <?= ($formData['cg_id'] ?? '') == $cg['s_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cg['s_prefix'].$cg['s_name'].' '.$cg['s_surname']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือก CG ที่ดูแล</div>
        </div>
        <div class="col-md-4">
          <select name="project_year" class="form-control" required>
            <option value="" disabled <?= empty($formData['project_year']) ? 'selected' : '' ?>>ปีที่เข้าร่วมโครงการ*</option>
            <?php $currentYear = date("Y") + 543; for ($year = $currentYear; $year >= $currentYear - 10; $year--): ?>
              <option value="<?= $year ?>" <?= ($formData['project_year'] ?? '') == $year ? 'selected' : '' ?>><?= $year ?></option>
            <?php endfor; ?>
          </select>
          <div class="invalid-feedback">กรุณาเลือกปีที่เข้าร่วมโครงการ</div>
        </div>
        <div class="col-md-6"><input type="text" name="relative_name" class="form-control" placeholder="ญาติผู้ดูแล*" value="<?= htmlspecialchars($formData['relative_name'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุญาติผู้ดูแล</div></div>
        <div class="col-md-6"><input type="tel" name="relative_phone" class="form-control" placeholder="เบอร์โทรศัพท์ญาติผู้ดูแล*" pattern="[0-9]{9,15}" value="<?= htmlspecialchars($formData['relative_phone'] ?? '') ?>" required><div class="invalid-feedback">กรุณาระบุเบอร์โทรศัพท์ญาติผู้ดูแล</div></div>
        <div class="col-md-12"><textarea name="precaution" class="form-control" placeholder="ข้อควรระวังในการให้บริการ*" required><?= htmlspecialchars($formData['precaution'] ?? '') ?></textarea><div class="invalid-feedback">กรุณาระบุข้อควรระวังในการให้บริการ</div></div>
        <div class="col-md-12"><textarea name="care_expectation" class="form-control" placeholder="ความคาดหวังในการดูแล (ระยะสั้น/ระยะยาว)*" required><?= htmlspecialchars($formData['care_expectation'] ?? '') ?></textarea><div class="invalid-feedback">กรุณาระบุความคาดหวังในการดูแล</div></div>
      </div>

      <div class="text-center mt-5"><button type="submit" class="btn btn-dark px-5 py-2">บันทึกข้อมูล</button></div>
    </form>
    <div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title fw-bold" id="mapModalLabel">เลือกตำแหน่งบนแผนที่</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body map-modal-body"><div id="map" class="map-container-full"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button><button type="button" class="btn btn-dark" id="confirmLocation">ยืนยันตำแหน่ง</button></div></div></div></div>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/script.js"></script>
</body>
</html>