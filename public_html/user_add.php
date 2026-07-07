<?php
require_once '../app/db.php';
require_once '../app/auth.php';
require_once '../app/helpers.php';

require_login();
if (!is_boss()) {
  header('Location: ../myjob');
  exit;
}

$error = '';
$form = [
  'username'  => '',
  'full_name' => '',
  'email'     => '',
  'role'      => 'admin',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form['username']  = trim((string)($_POST['username'] ?? ''));
  $form['full_name'] = trim((string)($_POST['full_name'] ?? ''));
  $form['email']     = trim((string)($_POST['email'] ?? ''));
  $form['role']      = trim((string)($_POST['role'] ?? 'admin'));
  $password          = (string)($_POST['password'] ?? '');

  // validate
  if ($form['username'] === '' || $form['full_name'] === '' || $form['email'] === '' || $password === '') {
    $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
  } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
    $error = 'รูปแบบอีเมลไม่ถูกต้อง';
  } else {
    try {
      $hash = password_hash($password, PASSWORD_BCRYPT);

      $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, full_name, email, role, is_active)
        VALUES (:u, :p, :f, :e, :r, 1)
      ");
      $stmt->execute([
        'u' => $form['username'],
        'p' => $hash,
        'f' => $form['full_name'],
        'e' => $form['email'],
        'r' => $form['role'],
      ]);

      header('Location: ../users');
      exit;

    } catch (PDOException $e) {
      // 1062 = Duplicate entry (unique)
      if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        // เดาได้ว่าอาจซ้ำ username หรือ email
        $error = 'บันทึกไม่สำเร็จ: Username หรือ Email ซ้ำในระบบ';
      } else {
        $error = 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
      }
    } catch (Exception $e) {
      $error = 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
    }
  }
}

$pageTitle = 'เพิ่มพนักงาน';
include 'assets/partials/header.php';
?>

<!-- Page Head -->
<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">เพิ่มพนักงาน</h1>
      <div class="page-subtitle">สร้างบัญชีผู้ใช้งานสำหรับพนักงานในระบบ</div>
    </div>

    <div class="d-flex gap-2">
      <a href="users" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>กลับ
      </a>
    </div>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?>
  </div>
<?php endif; ?>

<form method="post" class="card app-card shadow-sm border-0">
  <div class="card-header app-card__header">
    <div class="fw-semibold">ข้อมูลผู้ใช้งาน</div>
    <div class="text-muted small">ช่องที่มี * จำเป็นต้องกรอก</div>
  </div>

  <div class="card-body">
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label">Username <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-person"></i></span>
          <input name="username" class="form-control border-start-0" required
            placeholder="เช่น admin01" value="<?= e($form['username']) ?>">
        </div>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-card-heading"></i></span>
          <input name="full_name" class="form-control border-start-0" required
            placeholder="เช่น สมชาย ใจดี" value="<?= e($form['full_name']) ?>">
        </div>
      </div>

      <!-- เพิ่ม Email -->
      <div class="col-12 col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope"></i></span>
          <input name="email" type="email" class="form-control border-start-0" required
            placeholder="เช่น name@example.com" value="<?= e($form['email']) ?>">
        </div>
        <div class="form-text">ใช้อีเมลนี้สำหรับรับแจ้งเตือนจากระบบ</div>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-shield-lock"></i></span>
          <input name="password" type="password" class="form-control border-start-0" required
            placeholder="ตั้งรหัสผ่าน">
        </div>
        <div class="form-text">แนะนำอย่างน้อย 8 ตัวอักษร</div>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Role <span class="text-danger">*</span></label>
        <select name="role" class="form-select" required>
          <option value="boss" <?= (($form['role'] ?? 'admin') === 'boss') ? 'selected' : '' ?>>ผู้บริหาร</option>
          <option value="admin" <?= (($form['role'] ?? 'admin') === 'admin') ? 'selected' : '' ?>>พนักงาน</option>
          <option value="abroad" <?= (($form['role'] ?? 'admin') === 'abroad') ? 'selected' : '' ?>>ต่างประเทศ</option>
          <option value="technician" <?= (($form['role'] ?? 'admin') === 'technician') ? 'selected' : '' ?>>ช่าง</option>
          <option value="salesperson" <?= (($form['role'] ?? 'admin') === 'salesperson') ? 'selected' : '' ?>>พนักงานขาย</option>
        </select>
        <div class="form-text">กำหนดสิทธิ์การเข้าถึงเมนูและข้อมูล</div>
      </div>

      <div class="col-12">
        <div class="p-3 rounded-3" style="border: 1px dashed rgba(15,23,42,.14); background: rgba(255,255,255,.7);">
          <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>ข้อแนะนำ</div>
          <div class="text-muted small">
            Username ไม่ควรซ้ำ และควรเป็นตัวอักษร/ตัวเลขเพื่อให้ง่ายต่อการเข้าสู่ระบบ
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <a href="users" class="btn btn-outline-secondary">ยกเลิก</a>
    <button class="btn btn-primary" type="submit">
      <i class="bi bi-check2-circle me-1"></i>บันทึก
    </button>
  </div>
</form>

<?php include 'assets/partials/footer.php'; ?>
