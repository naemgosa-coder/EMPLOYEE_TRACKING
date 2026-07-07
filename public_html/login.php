<?php
// public/login.php

require_once '../app/db.php';
require_once '../app/auth.php';
require_once '../app/helpers.php';

if (isset($_SESSION['user'])) {
  if ($_SESSION['user']['role'] === 'boss') {
    header('Location: dashboard');
  } else {
    header('Location: myjob');
  }
  exit;
}

$errors = [];
$username = '';
$rememberChecked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $rememberChecked = isset($_POST['remember']);

  if ($username === '' || $password === '') {
    $errors[] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
  } else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
      $errors[] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } else {
      $_SESSION['user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'avatar'    => $user['avatar_url'] ?? null,
      ];

      if ($rememberChecked) {
        $cookieParams = session_get_cookie_params();
        setcookie(
          session_name(),
          session_id(),
          time() + (60 * 60 * 24 * 7),
          $cookieParams['path'],
          $cookieParams['domain'],
          $cookieParams['secure'],
          $cookieParams['httponly']
        );
      }

      if ($user['role'] === 'boss') {
        header('Location: dashboard');
      } else {
        header('Location: myjob');
      }
      exit;
    }
  }
}
?>

<?php
$pageTitle = 'เข้าสู่ระบบ';
$pageCss = ['assets/css/pages/login.css'];
$hideNav = true;
include 'assets/partials/header.php';
?>

<div class="login-page">
  <div class="wrapper active-popup">
    <!-- ปุ่มปิด: ปรับลิงก์ตามที่คุณต้องการ (กลับหน้าแรก/landing) -->
    <a class="icon-close" href="./"><ion-icon name="close"></ion-icon></a>

    <div class="form-box login">
      <div class="text-center mb-2">
        <img src="assets/img/logo1.png" alt="Logo" class="login-logo">
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <ul class="error-list">
            <?php foreach ($errors as $msg): ?>
              <li><?= e($msg) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- ถ้าไม่ได้ใช้ routing (login) ให้เปลี่ยน action="login.php" -->
      <form method="post" action="login" novalidate>
        <div class="input-box">
          <span class="icon"><ion-icon name="person"></ion-icon></span>
          <input type="text"
                 name="username"
                 value="<?= e($username) ?>"
                 autocomplete="username"
                 required />
          <label>Username</label>
        </div>

        <div class="input-box">
          <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
          <input type="password"
                 name="password"
                 autocomplete="current-password"
                 required />
          <label>Password</label>
        </div>

        <div class="remember-forgot">
          <label>
            <input type="checkbox" name="remember" <?= $rememberChecked ? 'checked' : '' ?>>
            Remember me
          </label>
        </div>

        <button type="submit" class="btn">Login</button>
      </form>
    </div>
  </div>
</div>

<!-- Ionicons (ถ้า footer ของคุณไม่ได้ include ไว้แล้ว ให้ใส่ตรงนี้) -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

<?php include 'assets/partials/footer.php'; ?>
