<?php
require_once('./connect/security_headers.php');
require_once('./connect/session.php');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SmartHealth</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Kanit&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
  <link href="css/style.css" rel="stylesheet">
</head>
<body class="page-index">
  <div class="container-fluid">
    <div class="image-box"></div>
    <div class="login-panel">
      <div class="text-center mb-4">
        <img src="img/logo.png" alt="SmartHealth Logo" class="login-logo">
      </div>

      <h4 class="fw-bold mb-5 text-center">ลงชื่อเข้าใช้<span class="text-brand">&nbsp;&nbsp;Smart Health</span></h4>
      
      <form action="./process/login_process.php" method="post" class="mx-auto login-form needs-validation" novalidate>
        <div class="mb-3">
          <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้" required>
        </div>
        <div>
          <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน" required>
        </div>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" name="submit" class="btn btn-login mt-4">เข้าสู่ระบบ</button>
        <div class="d-flex justify-content-center mt-4 mb-2">
          <a href="#" class="text-link">ติดต่อผู้ดูแลระบบ</a>
        </div>
        <div class="d-flex justify-content-center">
          <p class="text-link">© 2025 UDIH - Gismans Asia</p>
        </div>
      </form>
    </div>
  </div>

  <?php include './components/toast.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="js/script.js"></script>
</body>
</body>
</html>