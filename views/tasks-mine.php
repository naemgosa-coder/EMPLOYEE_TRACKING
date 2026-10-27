<!-- public/tasks-mine.php -->
<?php

require_login();
$user = current_user();

// boss ไปใช้หน้าเดิม
if (is_boss()) { header('Location: tasks'); exit; }

$pageTitle = 'งานที่ฉันมอบหมาย';
$pageJs  = ['assets/js/pages/tasks-mine.page.js'];
include BASE_PATH . '/views/partials/header.php';
?>

<script>
  window.__CURRENT_USER_ID = <?= (int)($user['id'] ?? 0) ?>;
</script>

<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">งานที่ฉันมอบหมาย</h1>
      <div class="page-subtitle">แสดงเฉพาะงานที่คุณเป็นผู้มอบหมาย (สร้างงานไว้)</div>
    </div>

    <div class="d-flex gap-2">
      <a href="tasks/add" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มงาน
      </a>
    </div>
  </div>
</div>

<div id="tasksError" class="alert alert-danger d-none" role="alert"></div>

<!-- Filters -->
<div class="card app-card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form id="taskSearchForm" class="row g-2 align-items-end">
      <div class="col-12 col-md-6">
        <label class="form-label mb-1">ค้นหา</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
          <input id="keyword" class="form-control border-start-0" placeholder="ค้นหาชื่องาน...">
        </div>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label mb-1">สถานะ</label>
        <select id="status" class="form-select">
          <option value="">ทั้งหมด</option>
          <option value="pending">รอดำเนินการ</option>
          <option value="in_progress">กำลังดำเนินการ</option>
          <option value="completed">เสร็จแล้ว</option>
          <option value="overdue">เกินกำหนด</option>
        </select>
      </div>

      <div class="col-12 col-md-3 d-flex gap-2">
        <button id="btnSearch" class="btn btn-outline-primary w-100" type="submit">
          <i class="bi bi-funnel me-1"></i>ค้นหา/กรอง
        </button>
        <a class="btn btn-outline-secondary" href="tasks-mine" title="รีเซ็ตตัวกรอง">
          <i class="bi bi-arrow-counterclockwise"></i>
        </a>
      </div>
    </form>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <span class="badge badge-soft-primary">รอดำเนินการ</span>
      <span class="badge badge-soft-secondary">กำลังดำเนินการ</span>
      <span class="badge badge-soft-success">เสร็จแล้ว</span>
      <span class="badge badge-soft-danger">เกินกำหนด</span>
    </div>
  </div>
</div>

<!-- Table -->
<div class="card app-card shadow-sm border-0">
  <div class="card-header app-card__header">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">รายการงานของฉัน</div>
        <div class="text-muted small">เฉพาะงานที่คุณเป็นผู้มอบหมาย</div>
      </div>
      <div class="text-muted small">
        ทั้งหมด: <span id="taskTotal">-</span> รายการ
      </div>
    </div>
  </div>

  <div class="card-body pt-2">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 table-app">
        <thead>
          <tr>
            <th style="width:120px;">รหัส</th>
            <th>หัวข้อ</th>
            <th style="width:240px;">ผู้รับมอบหมาย</th>
            <th style="width:120px;">สถานะ</th>
            <th style="width:120px;">ไฟล์แนบ</th>
            <th style="width:140px;">กำหนดส่ง</th>
            <th style="width:160px;" class="text-end">การจัดการ</th>
          </tr>
        </thead>

        <tbody id="taskTableBody">
          <tr>
            <td colspan="7" class="py-4">
              <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                <div>กำลังโหลดรายการงาน...</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div id="taskEmpty" class="empty-state d-none">
      <div class="empty-state__icon"><i class="bi bi-inbox"></i></div>
      <div class="fw-semibold">ยังไม่มีงานที่คุณมอบหมาย</div>
      <div class="text-muted small">กด “เพิ่มงาน” เพื่อสร้างงานใหม่</div>
      <div class="d-flex justify-content-center gap-2 mt-2">
        <a href="tasks/add" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>เพิ่มงาน
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Files (เพิ่มเหมือนหน้า boss) -->
<div class="modal fade" id="filesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-paperclip me-1"></i>ไฟล์แนบ</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2" id="filesMeta">-</div>
        <div id="filesEmpty" class="text-muted small d-none">ไม่มีไฟล์แนบ</div>
        <div class="list-group" id="filesList"></div>
      </div>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/views/partials/footer.php'; ?>
