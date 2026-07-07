<?php
// public/task_edit.php (หรือ public/tasks/edit.php ตาม routing ของคุณ)
require_once '../app/db.php';
require_once '../app/auth.php';
require_once '../app/helpers.php';
require_once '../app/models/TaskModel.php';
require_once '../app/models/TaskLogModel.php';
require_once '../app/NotificationService.php';
require_once '../app/models/TaskFileModel.php';

require_login();
$user   = current_user();
$isBoss = is_boss();
$uid    = (int)($user['id'] ?? 0);

$backUrl   = $isBoss ? 'tasks' : 'tasks-mine';
$afterSave = $isBoss ? '../tasks' : '../tasks-mine';

$taskModel = new TaskModel($pdo);
$logModel  = new TaskLogModel($pdo);
$noti      = new NotificationService($pdo);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header('Location: ' . $afterSave);
  exit;
}

$task = $taskModel->find($id);
if (!$task) {
  header('Location: ' . $afterSave);
  exit;
}

// พนักงานแก้ได้เฉพาะงานที่ตัวเองเป็นคนมอบหมาย
if (!$isBoss && (int)($task['assigned_by'] ?? 0) !== $uid) {
  header('Location: ../tasks-mine.php');
  exit;
}

// แผนกจาก users.role
$roles = $pdo->query("
  SELECT DISTINCT role
  FROM users
  WHERE is_active = 1 AND role <> 'boss'
  ORDER BY role
")->fetchAll(PDO::FETCH_COLUMN);

// พนักงานทั้งหมด (ไม่รวม boss)
$staffs = $pdo->query("
  SELECT id, username, full_name, role
  FROM users
  WHERE is_active=1 AND role <> 'boss'
  ORDER BY role, full_name
")->fetchAll();

// ผู้รับมอบหมายเดิมจาก task_assignees
$assigneesDb = $pdo->prepare("
  SELECT ta.user_id
  FROM task_assignees ta
  WHERE ta.task_id = :id
  ORDER BY ta.created_at ASC
");
$assigneesDb->execute(['id' => $id]);
$assigneesInit = $assigneesDb->fetchAll(PDO::FETCH_COLUMN);
$assigneesInit = array_values(array_unique(array_map('intval', $assigneesInit)));

if (empty($assigneesInit) && !empty($task['assigned_to'])) {
  $assigneesInit = [(int)$task['assigned_to']];
}

// dept init
$deptInit = derive_department_from_assignees($pdo, $assigneesInit) ?? (string)($task['department'] ?? '');
if ($deptInit === '' && !empty($task['assigned_to'])) {
  $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
  $stmtRole->execute(['id' => (int)$task['assigned_to']]);
  $deptInit = (string)($stmtRole->fetchColumn() ?: '');
}

$error = '';
$statusNote = ''; // note สำหรับ log (บังคับเมื่อ completed)

$form = [
  'task_code'   => $task['task_code'] ?? '',
  'title'       => $task['title'] ?? '',
  'description' => $task['description'] ?? '',
  'department'  => $deptInit,
  'status'      => $task['status'] ?? 'pending',
  'due_date'    => $task['due_date'] ?? '',
  // ✅ ตัด remark ออก
];

/**
 * สถานะที่ "เลือกได้" (ห้าม overdue)
 */
$statusLabelsSelect = [
  'pending'     => 'รอดำเนินการ',
  'in_progress' => 'กำลังดำเนินการ',
  'completed'   => 'เสร็จแล้ว',
];

/**
 * สถานะสำหรับ "แสดงผล" (มี overdue ได้ เพราะคำนวณจาก due_date)
 */
$statusLabelsDisplay = $statusLabelsSelect + [
  'overdue' => 'เกินกำหนด',
];

$statusBadge  = [
  'pending'     => 'badge-soft-primary',
  'in_progress' => 'badge-soft-secondary',
  'completed'   => 'badge-soft-success',
  'overdue'     => 'badge-soft-danger',
];

$assignees = $assigneesInit;

function derive_department_from_assignees(PDO $pdo, array $assignees): ?string
{
  $assignees = array_values(array_unique(array_filter(array_map('intval', $assignees), fn($v) => $v > 0)));
  if (!$assignees) return null;

  $placeholders = implode(',', array_fill(0, count($assignees), '?'));
  $stmt = $pdo->prepare("SELECT DISTINCT role FROM users WHERE id IN ($placeholders) AND role <> 'boss'");
  $stmt->execute($assignees);

  $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $roles = array_values(array_unique(array_filter(array_map('strval', $roles))));

  if (count($roles) === 1) return $roles[0];
  if (count($roles) > 1) return 'multi';
  return null;
}

// ====== POST ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form['title']       = trim((string)($_POST['title'] ?? $form['title']));
  $form['description'] = trim((string)($_POST['description'] ?? $form['description']));
  $form['status']      = trim((string)($_POST['status'] ?? $form['status']));
  $form['due_date']    = trim((string)($_POST['due_date'] ?? $form['due_date']));

  $statusNote = trim((string)($_POST['status_note'] ?? ''));

  $assignees = $_POST['assignees'] ?? [];
  if (!is_array($assignees)) $assignees = [];
  $assignees = array_values(array_unique(array_filter(array_map('intval', $assignees), fn($v) => $v > 0)));

  // validate required
  if ($form['title'] === '' || empty($assignees) || $form['due_date'] === '') {
    $error = 'กรุณากรอกข้อมูลให้ครบถ้วน (หัวข้อ / ผู้รับมอบหมาย / กำหนดส่ง)';
  }

  // validate status (ห้าม overdue)
  if ($error === '') {
    $allowedStatus = array_keys($statusLabelsSelect);
    if (!in_array($form['status'], $allowedStatus, true)) {
      $error = 'ไม่อนุญาตให้ตั้งสถานะ "เกินกำหนด" (ระบบคำนวณอัตโนมัติจากวันกำหนดส่ง)';
    }
  }

  // require note when completed
  if ($error === '') {
    if ($form['status'] === 'completed' && $statusNote === '') {
      $error = 'ถ้าปรับเป็น “เสร็จแล้ว” กรุณาใส่บันทึกสรุปผลลัพธ์ก่อนบันทึก';
    }
  }

  $deptAuto = derive_department_from_assignees($pdo, $assignees);
  if ($error === '') {
    $data = [
      'title'       => $form['title'],
      'description' => ($form['description'] !== '') ? $form['description'] : null,
      'department'  => $deptAuto,
      'status'      => $form['status'],
      'due_date'    => $form['due_date'],
    ];

    try {
      $sessionUid = (int)($_SESSION['user']['id'] ?? 0);
      $oldStatus  = $task['status'] ?? null;

      // ✅ เทียบผู้รับมอบหมายก่อน/หลัง
      $oldAssignees = array_values(array_unique(array_map('intval', $assigneesInit)));
      $newAssignees = array_values(array_unique(array_map('intval', $assignees))); // ✅ ต้องเป็น $assignees (POST)
      $added   = array_values(array_diff($newAssignees, $oldAssignees));
      $removed = array_values(array_diff($oldAssignees, $newAssignees));

      // ✅ เก็บค่าเดิมไว้เทียบว่าอัปเดทอะไรบ้าง
      $oldTitle = trim((string)($task['title'] ?? ''));
      $oldDesc  = trim((string)($task['description'] ?? ''));
      $oldDue   = trim((string)($task['due_date'] ?? ''));

      // update งาน + replace assignees
      $taskModel->update($id, $data, $assignees);

      // อัปโหลดไฟล์ (ไม่ให้พังทั้งฟอร์ม)
      try {
        if (!empty($_FILES['task_files'])) {
          $filesModel = new TaskFileModel($pdo);
          $filesModel->saveUploads((int)$id, null, $sessionUid, $_FILES['task_files']);
        }
      } catch (Throwable $e) {
        error_log('[FILES][TASK_EDIT] ' . $e->getMessage());
      }

      $statusChanged = ($data['status'] !== $oldStatus);

      // ✅ 1) ถ้าเปลี่ยนสถานะ -> log + แจ้งเตือนสถานะ (รวม completed)
      if ($statusChanged) {
        $noteForLog = ($statusNote !== '') ? $statusNote : 'แก้ไขและเปลี่ยนสถานะ';

        $logIdRaw = $logModel->addLog(
          $id,
          (int)($_SESSION['user']['id'] ?? 0),
          $oldStatus,
          $data['status'],
          $noteForLog
        );

        $logId = 0;
        if (is_numeric($logIdRaw)) {
          $logId = (int)$logIdRaw;
        } elseif ($logIdRaw) {
          $logId = (int)$pdo->lastInsertId();
        }

        if ($logId > 0) {
          try {
            $noti->sendTaskStatusChangedByLogId($logId);
          } catch (Throwable $e) {
            error_log('[NOTI][STATUS][TASK_EDIT] ' . $e->getMessage());
          }
        }
      }

      // ✅ 2) ถ้า “อัปเดทงาน” แต่ไม่ได้เปลี่ยนสถานะ -> log + แจ้งเตือน UPDATE (ผู้รับมอบหมาย + ผู้สั่งงาน)
      if (!$statusChanged) {
        $oldTitle = trim((string)($task['title'] ?? ''));
        $oldDesc  = trim((string)($task['description'] ?? ''));
        $oldDue   = trim((string)($task['due_date'] ?? ''));

        $removed = array_values(array_diff($oldAssignees, $newAssignees));

        $hasFiles = !empty($_FILES['task_files'])
          && is_array($_FILES['task_files']['name'] ?? null)
          && count(array_filter($_FILES['task_files']['name'])) > 0;

        $changes = [];
        if ($form['title'] !== $oldTitle) $changes[] = 'แก้ไขหัวข้อ';
        if (trim((string)$form['description']) !== $oldDesc) $changes[] = 'แก้ไขรายละเอียด';
        if ($form['due_date'] !== $oldDue) $changes[] = 'เปลี่ยนกำหนดส่ง';
        if (!empty($added)) $changes[] = 'เพิ่มผู้รับมอบหมาย';
        if (!empty($removed)) $changes[] = 'ลบผู้รับมอบหมาย';
        if ($hasFiles) $changes[] = 'แนบไฟล์';

        if (!empty($changes)) {
          $noteUpdate = 'อัปเดทงาน: ' . implode(', ', $changes);

          $logIdRaw2 = $logModel->addLog($id, $sessionUid, $oldStatus, $oldStatus, $noteUpdate);

          $logId2 = 0;
          if (is_numeric($logIdRaw2)) {
            $logId2 = (int)$logIdRaw2;
          } elseif ($logIdRaw2) {
            $logId2 = (int)$pdo->lastInsertId();
          }

          if ($logId2 > 0) {
            try {
              $noti->sendTaskActionByLogId($logId2);
            } catch (Throwable $e) {
              error_log('[NOTI][ACTION][TASK_EDIT] ' . $e->getMessage());
            }
          }
        }
      }

      // ✅ 3) ถ้ามีเพิ่มผู้รับมอบหมายใหม่ -> ส่งแจ้งงานใหม่ให้ “ผู้รับมอบหมาย”
      // (ส่งซ้ำคนเดิมไม่ได้ เพราะ event_key เดิมจะ INSERT IGNORE)
      if (!empty($added)) {
        try {
          $noti->sendTaskAssigned($id);
        } catch (Throwable $e) {
          error_log('[NOTI][ASSIGNED][TASK_EDIT] ' . $e->getMessage());
        }
      }

      header('Location: ' . $afterSave);
      exit;
    } catch (Exception $e) {
      $error = 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
    }
  }
}

// ====== คำนวณสถานะสำหรับแสดง badge (overdue อัตโนมัติ) ======
$displayStatus = $form['status'] ?? 'pending';
if ($displayStatus !== 'completed' && !empty($form['due_date'])) {
  $due = DateTime::createFromFormat('Y-m-d', (string)$form['due_date']);
  $today = new DateTime('today');
  if ($due && $due < $today) $displayStatus = 'overdue';
}
$currentBadge  = $statusBadge[$displayStatus] ?? 'badge-soft-secondary';
$currentLabel  = $statusLabelsDisplay[$displayStatus] ?? $displayStatus;

$pageTitle = 'แก้ไขงาน';
$pageJs  = ['assets/js/pages/task-edit.page.js', 'assets/js/pages/task-edit-files.page.js'];
include 'assets/partials/header.php';
?>

<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">แก้ไขงาน</h1>
      <div class="page-subtitle">
        ปรับรายละเอียดงาน และเปลี่ยนผู้รับมอบหมาย/สถานะได้
        <span class="ms-2 badge <?= e($currentBadge) ?>">สถานะปัจจุบัน: <?= e($currentLabel) ?></span>
      </div>
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
    <i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data"
      class="card app-card shadow-sm border-0"
      data-overlay
      data-overlay-text="กำลังบันทึกการแก้ไข...">
  <div class="card-header app-card__header">
    <div class="fw-semibold">รายละเอียดงาน</div>
    <div class="text-muted small">ช่องที่มี * จำเป็นต้องกรอก</div>
  </div>

  <div class="card-body">
    <div class="row g-2">

      <div class="col-12 col-md-4">
        <label class="form-label">รหัสงาน</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-hash"></i></span>
          <input class="form-control border-start-0 form-control-sm" value="<?= e($form['task_code']) ?>" readonly>
        </div>
        <div class="form-text">รหัสงานสร้างอัตโนมัติ ไม่สามารถแก้ไขได้</div>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">กำหนดส่ง <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-calendar-event"></i></span>
          <input type="date" name="due_date" class="form-control border-start-0 form-control-sm" required
            value="<?= e($form['due_date']) ?>">
        </div>
        <div class="form-text">* “เกินกำหนด” ระบบคำนวณอัตโนมัติจากวันกำหนดส่ง</div>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">สถานะ <span class="text-danger">*</span></label>
        <select name="status" class="form-select form-select-sm" required>
          <?php
          $st = $form['status'] ?? 'pending';
          foreach ($statusLabelsSelect as $k => $v) {
            $sel = ($st === $k) ? 'selected' : '';
            echo "<option value=\"{$k}\" {$sel}>{$v}</option>";
          }
          ?>
        </select>
        <div class="form-text">ไม่สามารถเลือก “เกินกำหนด” ได้ (ระบบคำนวณอัตโนมัติ)</div>
      </div>

      <div class="col-12">
        <label class="form-label">บันทึกการอัปเดตสถานะ</label>
        <textarea name="status_note" class="form-control form-control-sm" rows="3"
          placeholder="ใส่บันทึก (บังคับเมื่อเลือก “เสร็จแล้ว”)"><?= e($statusNote) ?></textarea>
        <div class="form-text">* ถ้าเลือก “เสร็จแล้ว” จำเป็นต้องใส่สรุปผลลัพธ์</div>
      </div>

      <div class="col-12">
        <label class="form-label">ผู้รับมอบหมาย <span class="text-danger">*</span></label>

        <div class="row g-2 mb-2">
          <div class="col-12 col-md-4">
            <select id="assigneeDeptFilter" class="form-select form-select-sm">
              <option value="">ทั้งหมดทุกแผนก</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= e($r) ?>"><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-8">
            <input id="assigneeSearch" class="form-control form-control-sm" placeholder="ค้นหาชื่อ/username...">
          </div>
        </div>

        <div class="d-flex gap-2">
          <select id="assigneeSelect" class="form-select form-select-sm">
            <option value="">-- เลือกผู้รับมอบหมาย --</option>
            <?php foreach ($staffs as $u): ?>
              <option value="<?= (int)$u['id'] ?>" data-role="<?= e($u['role']) ?>">
                <?= e($u['full_name'] . ' (' . $u['username'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>

          <button type="button" id="btnAddAssignee" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>เพิ่ม
          </button>
        </div>

        <div class="form-text mt-2">
          แผนกงาน (อัตโนมัติจากผู้รับ): <span id="autoDeptBadge" class="badge bg-secondary">-</span>
          • คนแรกที่เพิ่ม = ผู้รับผิดชอบหลัก
        </div>

        <div id="assigneeList" class="mt-2 d-flex flex-wrap gap-2"></div>
        <div id="assigneeInputs"></div>
      </div>

      <div class="col-12">
        <label class="form-label">หัวข้อ <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-card-text"></i></span>
          <input name="title" class="form-control border-start-0 form-control-sm" required
            value="<?= e($form['title']) ?>" placeholder="หัวข้องาน">
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">รายละเอียด</label>
        <textarea name="description" class="form-control form-control-sm" rows="4"
          placeholder="อธิบายรายละเอียดงาน/เงื่อนไข/สิ่งที่ต้องส่งมอบ"><?= e($form['description']) ?></textarea>
      </div>

      <div class="col-12">
        <label class="form-label">แนบไฟล์ (ถ้ามี)</label>
        <input type="file" name="task_files[]" class="form-control" multiple>

        <div class="mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">
              <i class="bi bi-paperclip me-1"></i>ไฟล์แนบทั้งหมดของงานนี้
            </div>
            <button type="button" id="btnReloadFiles" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-clockwise me-1"></i>รีโหลด
            </button>
          </div>

          <div id="taskFilesError" class="alert alert-danger d-none mt-2" role="alert"></div>
          <div id="taskFilesEmpty" class="text-muted small d-none mt-2">ยังไม่มีไฟล์แนบ</div>

          <div class="table-responsive mt-2">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>ชื่อไฟล์</th>
                  <th style="width:120px;">ขนาด</th>
                  <th style="width:160px;">อัปโหลดเมื่อ</th>
                  <th style="width:180px;" class="text-end">จัดการ</th>
                </tr>
              </thead>
              <tbody id="taskFilesTbody"></tbody>
            </table>
          </div>

          <div class="form-text mt-1">
            * อัปโหลดไฟล์ใหม่ได้จากช่อง “แนบไฟล์” แล้วกดบันทึก / ลบ-เปลี่ยนชื่อทำได้ทันทีในตารางด้านบน
          </div>
        </div>

        <div class="form-text">รองรับ pdf, รูป, office, zip (แนะนำไม่เกิน 10MB/ไฟล์)</div>
      </div>

    </div>
  </div>

  <div class="card-footer d-flex justify-content-end gap-2">
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button class="btn btn-primary" type="submit">
      <i class="bi bi-save2 me-1"></i>บันทึกการแก้ไข
    </button>
  </div>
</form>

<script>
  window.__TASK_ID__ = <?= (int)$id ?>;
  window.__INIT_ASSIGNEES__ = <?= json_encode(array_values($assignees), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
  document.querySelector("form")?.addEventListener("submit", () => {
    showGlobalOverlay("กำลังบันทึกการแก้ไข...");
  });
</script>

<?php include 'assets/partials/footer.php'; ?>