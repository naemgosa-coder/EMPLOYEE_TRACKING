<?php

require_login();
$user = current_user();
$openTaskId = (int)($_GET['open'] ?? 0);

$pageTitle = 'งานของฉัน';
$pageCss = ['assets/css/pages/myjob.css']; // ถ้าไม่ใช้ ลบได้
$pageJs  = ['assets/js/pages/myjob.page.js'];
include BASE_PATH . '/views/partials/header.php';
?>

<!-- Page Head -->
<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">งานของฉัน</h1>
      <div class="page-subtitle">งานที่ได้รับมอบหมายให้คุณ</div>
    </div>

    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefreshMyJob">
        <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช
      </button>
    </div>
  </div>
</div>

<div id="myjobError" class="alert alert-danger d-none" role="alert"></div>

<div class="card app-card shadow-sm border-0">
  <div class="card-header app-card__header">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">รายการงาน</div>
        <div class="text-muted small">อัปเดตสถานะได้จากปุ่มด้านขวา</div>
      </div>
      <div class="text-muted small">
        ทั้งหมด: <span id="myJobTotal">-</span> งาน
      </div>
    </div>
  </div>

  <div class="card-body pt-2">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 table-app">
        <thead>
          <tr>
            <th style="width:110px;">รหัส</th>
            <th>งาน</th>
            <th style="width:180px;">ผู้มอบหมาย</th>
            <th style="width:140px;">สถานะ</th>
            <th style="width:140px;">กำหนดส่ง</th>
            <th style="width:220px;">หมายเหตุ</th>
            <th style="width:120px;">ไฟล์แนบ</th>
            <th style="width:160px;" class="text-end">อัปเดต</th>
          </tr>
        </thead>
        <tbody id="myJobBody">
          <tr>
            <td colspan="8" class="py-4">
              <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                <div>กำลังโหลดงานของคุณ...</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div id="myJobEmpty" class="empty-state d-none">
      <div class="empty-state__icon">
        <i class="bi bi-briefcase"></i>
      </div>
      <div class="fw-semibold">ยังไม่มีงานที่ได้รับมอบหมาย</div>
      <div class="text-muted small">หากมีการมอบหมายงาน ระบบจะแสดงที่หน้านี้</div>
    </div>
  </div>
</div>

<!-- Modal: Update Status -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h6 class="modal-title">
          <i class="bi bi-pencil-square me-1"></i>อัปเดตสถานะงาน
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="modalTaskId">
        <input type="hidden" id="modalOldStatus" value="pending">

        <div class="mb-3">
          <label class="form-label">สถานะ</label>
          <select id="modalStatus" class="form-select">
            <option value="pending">รอดำเนินการ</option>
            <option value="in_progress">กำลังดำเนินการ</option>
            <option value="completed">เสร็จแล้ว</option>
          </select>
          <div class="form-text">
            * สถานะ “เกินกำหนด” ระบบคำนวณอัตโนมัติจากวันกำหนดส่ง
          </div>
        </div>

        <div class="mb-0">
          <label class="form-label">บันทึกเพิ่มเติม</label>
          <textarea id="modalNote" class="form-control" rows="3" placeholder="ใส่หมายเหตุ (บังคับ)"></textarea>
        </div>

        <div class="mt-2">
          <label class="form-label">แนบไฟล์</label>
          <input id="modalFiles" type="file" class="form-control" multiple>
          <div class="form-text">แนบได้หลายไฟล์</div>
        </div>

        <div class="mt-3 p-3 rounded-3" style="border:1px dashed rgba(15,23,42,.14); background: rgba(255,255,255,.7);">
          <div class="small text-muted">
            <i class="bi bi-info-circle me-1"></i>แนะนำ: ถ้าปรับเป็น “เสร็จแล้ว” ควรใส่สรุปผลลัพธ์สั้น ๆ
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button id="modalSaveBtn" class="btn btn-primary">
          <i class="bi bi-check2-circle me-1"></i>บันทึก
        </button>
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
  <div class="modal-dialog modal-dialog-centered modal-lg">
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

        <div id="detailLoading" class="py-4">
          <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
            <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
            <div>กำลังโหลดรายละเอียด...</div>
          </div>
        </div>

        <div id="detailBody" class="d-none">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span id="detailStatusBadge" class="badge badge-soft-secondary">-</span>
            <span id="detailOverdue" class="small text-danger d-none"></span>
          </div>

          <div class="row g-2">
            <div class="col-12 col-md-4">
              <div class="text-muted small">รหัสงาน</div>
              <div class="fw-semibold" id="detailTaskCode">-</div>
            </div>
            <div class="col-12 col-md-8">
              <div class="text-muted small">หัวข้อ</div>
              <div class="fw-semibold" id="detailTaskTitle">-</div>
            </div>

            <div class="col-12 col-md-4">
              <div class="text-muted small">ผู้มอบหมาย</div>
              <div id="detailAssignedBy">-</div>
            </div>
            <div class="col-12 col-md-4">
              <div class="text-muted small">แผนก</div>
              <div id="detailDepartment">-</div>
            </div>
            <div class="col-12 col-md-4">
              <div class="text-muted small">กำหนดส่ง</div>
              <div id="detailDueDate">-</div>
            </div>

            <div class="col-12 col-md-6">
              <div class="text-muted small">สร้างเมื่อ</div>
              <div id="detailCreatedAt">-</div>
            </div>
            <div class="col-12 col-md-6">
              <div class="text-muted small">อัปเดตล่าสุด</div>
              <div id="detailUpdatedAt">-</div>
            </div>
          </div>

          <hr class="my-3">

          <div class="mb-2">
            <div class="text-muted small mb-1">ผู้รับมอบหมายทั้งหมด</div>
            <div id="detailAssignees" class="d-flex flex-wrap gap-2"></div>
          </div>

          <div class="mb-2">
            <div class="text-muted small mb-1">รายละเอียด</div>
            <div id="detailDescription" class="small"></div>
          </div>

          <div class="mt-3">
            <div class="fw-semibold mb-1"><i class="bi bi-paperclip me-1"></i>ไฟล์แนบ</div>
            <div id="detailFilesEmpty" class="text-muted small d-none">ไม่มีไฟล์แนบ</div>
            <div class="list-group" id="detailFilesList"></div>
          </div>

          <div class="mt-3">
            <div class="fw-semibold mb-1"><i class="bi bi-clock-history me-1"></i>ประวัติการเปลี่ยนสถานะ</div>
            <div id="detailLogsEmpty" class="text-muted small d-none">ไม่มีประวัติ</div>
            <div class="list-group" id="detailLogsList"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.__OPEN_TASK_ID__ = <?= (int)$openTaskId ?>;
</script>

<?php include BASE_PATH . '/views/partials/footer.php'; ?>