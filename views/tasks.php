<?php

require_login();

if (!is_boss()) {
  require __DIR__ . '/tasks-mine.php';
  exit;
}

// ===== boss เหมือนเดิมทั้งหมดต่อจากนี้ =====
$user = current_user();

$pageTitle = 'รายการงาน';
$pageJs  = ['assets/js/pages/tasks.page.js'];
include BASE_PATH . '/views/partials/header.php';
?>

<!-- Page Head -->
<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">งานที่มอบหมาย</h1>
      <div class="page-subtitle">ค้นหา กรอง และจัดการงานในระบบ</div>
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
      <div class="col-12 col-md-5">
        <label class="form-label mb-1">ค้นหา</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0">
            <i class="bi bi-search"></i>
          </span>
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
          <option value="overdue">เกินกำหนด (คำนวณอัตโนมัติ)</option>
        </select>
      </div>

      <div class="col-12 col-md-4 d-flex gap-2">
        <button id="btnSearch" class="btn btn-outline-primary w-100" type="submit">
          <i class="bi bi-funnel me-1"></i>ค้นหา/กรอง
        </button>
        <a class="btn btn-outline-secondary" href="tasks" title="รีเซ็ตตัวกรอง">
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
        <div class="fw-semibold">รายการงาน</div>
        <div class="text-muted small">แสดงผลตามตัวกรองที่เลือก</div>
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
            <th style="width:200px;">ผู้มอบหมาย</th>
            <th style="width:220px;">ผู้รับมอบหมาย</th>
            <th style="width:120px;">สถานะ</th>
            <th style="width:120px;">ไฟล์แนบ</th>
            <th style="width:140px;">กำหนดส่ง</th>
            <th style="width:180px;" class="text-end">การจัดการ</th>
          </tr>
        </thead>

        <tbody id="taskTableBody">
          <tr>
            <td colspan="8" class="py-4">
              <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                <div>กำลังโหลดรายการงาน...</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty state (ให้ JS toggle class d-none) -->
    <div id="taskEmpty" class="empty-state d-none">
      <div class="empty-state__icon">
        <i class="bi bi-inbox"></i>
      </div>
      <div class="fw-semibold">ไม่พบงานตามเงื่อนไขที่เลือก</div>
      <div class="text-muted small">ลองเปลี่ยนคำค้นหาหรือรีเซ็ตตัวกรอง</div>
      <div class="d-flex justify-content-center gap-2 mt-2">
        <a href="tasks" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-counterclockwise me-1"></i>รีเซ็ต
        </a>
        <a href="tasks/add" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>เพิ่มงาน
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Files -->
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

<!-- Modal: Task Detail -->
<div class="modal fade" id="taskDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <div>
          <h6 class="modal-title mb-0" id="detailTitle">รายละเอียดงาน</h6>
          <div class="small text-muted" id="detailSub">-</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="detailError" class="alert alert-danger d-none" role="alert"></div>

        <div id="detailLoading" class="d-flex align-items-center gap-2 text-muted">
          <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
          <div>กำลังโหลดรายละเอียด...</div>
        </div>

        <div id="detailBody" class="d-none">
          <div class="row g-3">
            <!-- left -->
            <div class="col-12 col-lg-7">
              <div class="card border-0 bg-light">
                <div class="card-body">
                  <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span id="detailStatusBadge" class="badge"></span>
                    <span id="detailOverdue" class="small text-danger d-none"></span>
                  </div>

                  <div class="mb-2">
                    <div class="text-muted small">หัวข้อ</div>
                    <div class="fw-semibold" id="detailTaskTitle">-</div>
                  </div>

                  <div>
                    <div class="text-muted small">รายละเอียด</div>
                    <div class="small" id="detailDescription">-</div>
                  </div>
                </div>
              </div>

              <div class="mt-3">
                <div class="fw-semibold mb-1"><i class="bi bi-people me-1"></i>ผู้รับมอบหมาย</div>
                <div id="detailAssignees" class="d-flex flex-wrap gap-2"></div>
                <div class="form-text">* คนแรก = ผู้รับผิดชอบหลัก</div>
              </div>

              <div class="mt-3">
                <div class="fw-semibold mb-1"><i class="bi bi-paperclip me-1"></i>ไฟล์แนบ</div>
                <div id="detailFilesEmpty" class="text-muted small d-none">ไม่มีไฟล์แนบ</div>
                <div class="list-group" id="detailFilesList"></div>
              </div>
            </div>

            <!-- right -->
            <div class="col-12 col-lg-5">
              <div class="card border-0">
                <div class="card-body p-0">
                  <table class="table table-sm mb-0">
                    <tbody>
                      <tr><th style="width:160px;">รหัสงาน</th><td id="detailTaskCode">-</td></tr>
                      <tr><th>ผู้มอบหมาย</th><td id="detailAssignedBy">-</td></tr>
                      <tr><th>แผนก</th><td id="detailDepartment">-</td></tr>
                      <tr><th>กำหนดส่ง</th><td id="detailDueDate">-</td></tr>
                      <tr><th>สร้างเมื่อ</th><td id="detailCreatedAt">-</td></tr>
                      <tr><th>อัปเดตล่าสุด</th><td id="detailUpdatedAt">-</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="mt-3">
                <div class="fw-semibold mb-1"><i class="bi bi-clock-history me-1"></i>ประวัติการเปลี่ยนสถานะ</div>
                <div id="detailLogsEmpty" class="text-muted small d-none">ไม่มีประวัติ</div>
                <div class="list-group" id="detailLogsList"></div>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /modal-body -->

      <div class="modal-footer">
        <a id="detailEditBtn" class="btn btn-outline-primary" href="#">
          <i class="bi bi-pencil-square me-1"></i>แก้ไข
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/views/partials/footer.php'; ?>