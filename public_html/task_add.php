<?php
require_once '../app/db.php';
require_once '../app/auth.php';
require_once '../app/helpers.php';
require_once '../app/models/TaskModel.php';
require_once '../app/models/TaskLogModel.php';
require_once '../app/models/TaskFileModel.php';
require_once '../app/NotificationService.php';

require_login();
$user = current_user();
$isBoss = is_boss();

$ROLE_LABELS = [
  'boss'        => 'ผู้บริหาร',
  'admin'       => 'พนักงาน',
  'abroad'      => 'ต่างประเทศ',
  'technician'  => 'ช่าง',
  'salesperson' => 'พนักงานขาย',
];

$ALLOWED_ROLES = array_keys($ROLE_LABELS);
// non-boss ห้ามเห็น boss
$FILTER_ROLES = $isBoss ? $ALLOWED_ROLES : array_values(array_diff($ALLOWED_ROLES, ['boss']));

$backUrl   = $isBoss ? 'tasks' : 'tasks-mine';
$afterSave = $isBoss ? '../tasks' : '../tasks-mine';

$taskModel = new TaskModel($pdo);
$logModel  = new TaskLogModel($pdo);
$noti      = new NotificationService($pdo);

$error = '';
$form = [
  'title'       => '',
  'description' => '',
  'status'      => 'pending',
  'due_date'    => '',
];

$assignees = [];

function derive_department_from_assignees(PDO $pdo, array $assignees): ?string
{
  $assignees = array_values(array_unique(array_filter(array_map('intval', $assignees), fn($v) => $v > 0)));
  if (!$assignees) return null;

  $placeholders = implode(',', array_fill(0, count($assignees), '?'));
  $stmt = $pdo->prepare("SELECT DISTINCT role FROM users WHERE id IN ($placeholders) AND is_active = 1");
  $stmt->execute($assignees);

  $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $roles = array_values(array_unique(array_filter(array_map('strval', $roles))));
  if (!$roles) return null;

  if (count($roles) === 1) return $roles[0];

  $rolesNoBoss = array_values(array_diff($roles, ['boss']));

  if (!$rolesNoBoss) return 'boss';

  if (count($rolesNoBoss) === 1) return $rolesNoBoss[0];

  return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($form as $k => $_) {
    $form[$k] = trim((string)($_POST[$k] ?? $form[$k]));
  }

  $assignees = $_POST['assignees'] ?? [];
  if (!is_array($assignees)) $assignees = [];
  $assignees = array_values(array_unique(array_filter(array_map('intval', $assignees), fn($v) => $v > 0)));

  // ===== required fields =====
  if ($form['title'] === '' || empty($assignees) || $form['due_date'] === '') {
    $error = 'กรุณากรอกข้อมูลให้ครบถ้วน (หัวข้อ / ผู้รับมอบหมาย / กำหนดส่ง)';
  }

  // ✅ Server-side rule: พนักงานห้ามมอบหมายงานให้ผู้บริหาร (กันยิง POST ตรงๆ)
  if (!$error && !$isBoss && !empty($assignees)) {
    $placeholders = implode(',', array_fill(0, count($assignees), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id IN ($placeholders) AND is_active=1 AND role='boss'");
    $stmt->execute($assignees);
    $bossCount = (int)$stmt->fetchColumn();

    if ($bossCount > 0) {
      $error = 'ไม่สามารถมอบหมายงานให้ผู้บริหารได้';
    }
  }

  if (!$error) {
    $deptAuto = derive_department_from_assignees($pdo, $assignees);

    $data = [
      'title'       => $form['title'],
      'description' => $form['description'],
      'department'  => $deptAuto, // อาจเป็น null ได้ตาม DDT
      'assigned_by' => (int)($_SESSION['user']['id'] ?? 0),
      'status'      => 'pending',
      'due_date'    => $form['due_date'],
    ];

    try {
      $result = $taskModel->createAuto($data, $assignees);
      $taskId = (int)($result['id'] ?? 0);

      $filesModel = new TaskFileModel($pdo);
      try {
        if (!empty($_FILES['task_files']['name'][0])) {
          $filesModel->saveUploads(
            $taskId,
            null,
            (int)($_SESSION['user']['id'] ?? 0),
            $_FILES['task_files']
          );
        }
      } catch (Throwable $e) {
        // เก็บเป็นข้อความธรรมดา (แสดงแบบปลอดภัย)
        $error = ($error ? $error . "\n" : "") . "แนบไฟล์ไม่สำเร็จ: " . $e->getMessage();
      }

      // log การสร้าง
      $logModel->addLog($taskId, (int)($_SESSION['user']['id'] ?? 0), null, 'pending', 'สร้างงานใหม่');

      // แจ้งเตือนงานใหม่ถูกมอบหมาย
      try {
        $noti->sendTaskAssigned($taskId);
      } catch (Throwable $e) {
        // ไม่ให้ทำให้บันทึกงานพัง
      }

      if (!$error) {
        header('Location: ' . $afterSave);
        exit;
      }

    } catch (Exception $e) {
      $error = 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
    }
  }
}

$roles = $FILTER_ROLES;

$ph = implode(',', array_fill(0, count($FILTER_ROLES), '?'));
$stmt = $pdo->prepare("
  SELECT id, username, full_name, role
  FROM users
  WHERE is_active = 1
    AND role IN ($ph)
  ORDER BY role, full_name
");
$stmt->execute($FILTER_ROLES);
$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'เพิ่มงาน';
$pageJs = ['assets/js/pages/task-add.page.js'];
include 'assets/partials/header.php';
?>

<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">เพิ่มงาน</h1>
      <div class="page-subtitle">กำหนดรายละเอียด มอบหมาย และวันครบกำหนด</div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>กลับ
      </a>
    </div>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-1"></i><?= nl2br(e($error)) ?>
  </div>
<?php endif; ?>

<form id="taskAddForm" method="post" enctype="multipart/form-data" class="card app-card shadow-sm border-0" data-overlay data-overlay-text="กำลังสร้างงาน...">
  <div class="card-header app-card__header">
    <div class="fw-semibold">ข้อมูลงาน</div>
    <div class="text-muted small">ช่องที่มี * จำเป็นต้องกรอก</div>
  </div>

  <div class="card-body">
    <div class="row g-3">

      <div class="col-12 col-md-4">
        <label class="form-label">รหัสงาน</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-hash"></i></span>
          <input class="form-control border-start-0" value="สร้างอัตโนมัติหลังบันทึก" readonly>
        </div>
        <div class="form-text">ระบบจะสร้างให้ เช่น TF-0001</div>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">กำหนดส่ง <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar-event"></i></span>
          <input type="date" name="due_date" class="form-control border-start-0" required value="<?= e($form['due_date']) ?>">
        </div>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">สถานะ <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-flag"></i></span>
          <input class="form-control border-start-0 form-control-sm" value="รอดำเนินการ" readonly>
        </div>
        <input type="hidden" name="status" value="pending">
        <div class="form-text">ล็อกเป็นค่าเริ่มต้นตอนสร้างงาน (แก้ได้ในหน้าแก้ไข)</div>
      </div>

      <div class="col-12">
        <label class="form-label">ผู้รับมอบหมาย <span class="text-danger">*</span></label>
        <div class="row g-2 mb-2">
          <div class="col-12 col-md-4">
            <select id="assigneeDeptFilter" class="form-select">
              <option value="">ทั้งหมดทุกแผนก</option>
             <?php foreach ($roles as $r): ?>
              <option value="<?= e($r) ?>"><?= e($ROLE_LABELS[$r] ?? $r) ?></option>
            <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-8">
            <input id="assigneeSearch" class="form-control" placeholder="ค้นหาชื่อ/username...">
          </div>
        </div>

        <div class="d-flex gap-2">
          <select id="assigneeSelect" class="form-select">
            <option value="">-- เลือกผู้รับมอบหมาย --</option>
            <?php foreach ($staffs as $u): ?>
              <?php
                $roleLabel = $ROLE_LABELS[$u['role']] ?? $u['role'];
                $nameLabel = $u['full_name'] . ' (' . $u['username'] . ')';
              ?>
              <option value="<?= (int)$u['id'] ?>" data-role="<?= e($u['role']) ?>">
                <?= e($nameLabel . ($u['role'] ? ' • ' . $roleLabel : '')) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <button type="button" id="btnAddAssignee" class="btn btn-outline-primary">
            <i class="bi bi-plus-lg me-1"></i>เพิ่ม
          </button>
        </div>

        <div id="assigneeHint" class="form-text">
          เลือกแผนกจากตัวกรองได้ตลอด เพื่อเพิ่มผู้รับมอบหมายหลายแผนก • คนแรกที่เพิ่ม = ผู้รับผิดชอบหลัก
        </div>

        <div id="assigneeList" class="mt-2 d-flex flex-wrap gap-2"></div>
        <div id="assigneeInputs"></div>
      </div>

      <div class="col-12">
        <label class="form-label">หัวข้อ <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-card-text"></i></span>
          <input name="title" class="form-control border-start-0" required
            placeholder="เช่น อัปเดตหน้าแดชบอร์ด" value="<?= e($form['title']) ?>">
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">รายละเอียด</label>
        <textarea name="description" class="form-control" rows="4"
          placeholder="อธิบายรายละเอียดงาน/เงื่อนไข/สิ่งที่ต้องส่งมอบ"><?= e($form['description']) ?></textarea>
      </div>

      <div class="col-12">
        <label class="form-label">แนบไฟล์ (ถ้ามี)</label>
        <input type="file" name="task_files[]" class="form-control" multiple>
        <div class="form-text">รองรับ pdf, รูป, office, zip (แนะนำไม่เกิน 10MB/ไฟล์)</div>
      </div>

    </div>
  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button id="btnSubmit" class="btn btn-primary" type="submit">
      <i class="bi bi-check2-circle me-1"></i>บันทึก
    </button>
  </div>
</form>

<script>
  window.__INIT_ASSIGNEES__ = <?= json_encode(array_values($assignees), JSON_UNESCAPED_UNICODE) ?>;
  window.__INIT_DEPARTMENT__ = "";
</script>

<?php include 'assets/partials/footer.php'; ?>
