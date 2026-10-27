<?php

require_login();
$user = current_user();

// boss ให้ไปใช้หน้า reports เดิม
if (is_boss()) { header('Location: reports'); exit; }

$pageTitle = 'รายงานของฉัน';
$pageCss = ['assets/css/pages/reports.css']; // ใช้ไฟล์เดิมได้
$pageJs  = ['assets/js/pages/reports-mine.page.js'];

include BASE_PATH . '/views/partials/header.php';
?>

<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">รายงานของฉัน</h1>
      <div class="page-subtitle">แสดงเฉพาะงานที่คุณเป็นผู้รับมอบหมาย</div>
    </div>
  </div>
</div>

<div id="reportsError" class="alert alert-danger d-none" role="alert"></div>

<!-- Filters -->
<div class="card app-card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form id="reportForm" class="row g-2 align-items-end">
      <div class="col-12 col-md-6">
        <label class="form-label mb-1">ค้นหา</label>
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
          <input id="reportKeyword" class="form-control border-start-0" placeholder="หัวข้องาน...">
        </div>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label mb-1">สถานะ</label>
        <select id="reportStatus" class="form-select">
          <option value="">ทั้งหมด</option>
          <option value="pending">รอดำเนินการ</option>
          <option value="in_progress">กำลังดำเนินการ</option>
          <option value="completed">เสร็จแล้ว</option>
          <option value="overdue">เกินกำหนด</option>
        </select>
      </div>

      <div class="col-12 col-md-2 d-flex gap-2">
        <button id="btnReportSearch" class="btn btn-primary w-100" type="submit">
          <i class="bi bi-funnel me-1"></i>ค้นหา
        </button>
        <a class="btn btn-outline-secondary" href="reports-mine" title="รีเซ็ตตัวกรอง">
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

<!-- List -->
<div class="card app-card shadow-sm border-0">
  <div class="card-header app-card__header">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">ผลลัพธ์รายงาน</div>
        <div class="text-muted small">คลิกเพื่อดูประวัติการเปลี่ยนสถานะ</div>
      </div>
      <div class="text-muted small">ทั้งหมด: <span id="reportTotal">-</span> งาน</div>
    </div>
  </div>

  <div class="card-body">
    <div id="reportList">
      <div class="d-flex align-items-center justify-content-center gap-2 text-muted py-4">
        <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
        <div>กำลังโหลดรายงาน...</div>
      </div>
    </div>

    <div id="reportEmpty" class="empty-state d-none">
      <div class="empty-state__icon"><i class="bi bi-clipboard-data"></i></div>
      <div class="fw-semibold">ไม่พบข้อมูลรายงาน</div>
      <div class="text-muted small">ลองเปลี่ยนตัวกรองหรือรีเซ็ต</div>
      <a class="btn btn-outline-secondary btn-sm mt-2" href="reports-mine">
        <i class="bi bi-arrow-counterclockwise me-1"></i>รีเซ็ต
      </a>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/views/partials/footer.php'; ?>
