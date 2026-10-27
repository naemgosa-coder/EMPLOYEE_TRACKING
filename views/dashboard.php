<?php

require_login();
$user = current_user();

$departments = [];
$years = [];

try {
  $departments = $pdo->query("
    SELECT DISTINCT department
    FROM tasks
    WHERE department IS NOT NULL AND department <> ''
    ORDER BY department
  ")->fetchAll(PDO::FETCH_COLUMN);

  $years = $pdo->query("
    SELECT DISTINCT YEAR(created_at) AS y
    FROM tasks
    ORDER BY y DESC
  ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  $departments = [];
  $years = [];
}

$pageTitle = 'แดชบอร์ด';
$pageCss = ['assets/css/pages/dashboard.css'];
$pageJs  = ['assets/js/pages/dashboard.page.js'];

include BASE_PATH . '/views/partials/header.php';
?>

<!-- Overview Card (รวมกรอบภาพรวมงาน + 4 กล่องสถานะไว้ในการ์ดเดียว) -->
<div class="card app-card shadow-sm border-0 mb-3 overview-card">
  <div class="card-header app-card__header">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
         <h1 class="page-title mb-1">ภาพรวมงาน</h1>
        <div class="page-subtitle">สรุปสถานะงานในระบบ</div>
      </div>

      <div class="d-flex flex-wrap gap-2 align-items-end">

        <?php if (is_boss()): ?>
          <!-- Filter: Department (เฉพาะ boss) -->
          <div>
            <div class="form-label small mb-1">แผนก</div>
            <select id="filterDept" class="form-select form-select-sm">
              <option value="">ทั้งหมด</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars($dept) ?>">
                  <?= htmlspecialchars($dept) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <!-- Filter: Year (จะให้ทุกคนใช้ก็ได้) -->
        <div>
          <div class="form-label small mb-1">ปี</div>
          <select id="filterYear" class="form-select form-select-sm">
            <option value="">ทั้งหมด</option>
            <?php foreach ($years as $y): ?>
              <option value="<?= (int)$y ?>"><?= (int)$y + 543 ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefresh">
          <i class="bi bi-arrow-clockwise me-1"></i>รีเฟรช
        </button>
      </div>
    </div>
  </div>

  <div class="card-body">
    <div class="row g-3">
      <!-- pending (ฟ้า) -->
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-card--primary shadow-sm border-0 js-status-card"
          data-status="pending" role="button" tabindex="0" style="cursor:pointer;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">รอดำเนินการ</div>
                <div class="stat-value" id="statPending">0</div>
              </div>
              <div class="stat-ico bg-primary-subtle text-primary">
                <i class="bi bi-hourglass-split"></i>
              </div>
            </div>
            <div class="stat-foot text-muted small mt-2">
              <i class="bi bi-dot me-1 text-primary"></i>งานที่ยังไม่เริ่ม
            </div>
          </div>
        </div>
      </div>

      <!-- in_progress (เหลือง) -->
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-card--warning shadow-sm border-0 js-status-card"
          data-status="in_progress" role="button" tabindex="0" style="cursor:pointer;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">กำลังดำเนินการ</div>
                <div class="stat-value" id="statInProgress">0</div>
              </div>
              <div class="stat-ico bg-warning-subtle text-warning">
                <i class="bi bi-play-circle"></i>
              </div>
            </div>
            <div class="stat-foot text-muted small mt-2">
              <i class="bi bi-dot me-1 text-warning"></i>งานที่กำลังทำ
            </div>
          </div>
        </div>
      </div>

      <!-- completed (เขียว) -->
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-card--success shadow-sm border-0 js-status-card"
          data-status="completed" role="button" tabindex="0" style="cursor:pointer;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">เสร็จแล้ว</div>
                <div class="stat-value" id="statCompleted">0</div>
              </div>
              <div class="stat-ico bg-success-subtle text-success">
                <i class="bi bi-check-circle"></i>
              </div>
            </div>
            <div class="stat-foot text-muted small mt-2">
              <i class="bi bi-dot me-1 text-success"></i>ปิดงานเรียบร้อย
            </div>
          </div>
        </div>
      </div>

      <!-- overdue (แดง) -->
      <div class="col-6 col-lg-3">
        <div class="card stat-card stat-card--danger shadow-sm border-0 js-status-card"
          data-status="overdue" role="button" tabindex="0" style="cursor:pointer;">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">เกินกำหนด</div>
                <div class="stat-value" id="statOverdue">0</div>
              </div>
              <div class="stat-ico bg-danger-subtle text-danger">
                <i class="bi bi-exclamation-triangle"></i>
              </div>
            </div>
            <div class="stat-foot text-muted small mt-2">
              <i class="bi bi-dot me-1 text-danger"></i>ควรรีบติดตาม
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- Main (เปลี่ยนงานล่าสุดเป็นกราฟวงกลม) -->
<div class="row g-3">
  <!-- กราฟ 1 -->
  <div class="col-12 col-lg-6">
    <div class="card app-card shadow-sm border-0 h-100">
      <div class="card-header app-card__header">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold">สัดส่วนสถานะงาน</div>
            <div class="text-muted small">ภาพรวมแบบกราฟ</div>
          </div>
          <span class="badge badge-soft-secondary">
            <i class="bi bi-pie-chart me-1"></i>Chart
          </span>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-box">
          <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- กราฟ 2 (วงกลม: สถานะในระบบ) -->
  <div class="col-12 col-lg-6">
    <div class="card app-card shadow-sm border-0 h-100">
      <div class="card-header app-card__header">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold">สัดส่วนสถานะงานในระบบ</div>
            <div class="text-muted small">รอดำเนินการ • กำลังดำเนินการ • เสร็จแล้ว • เกินกำหนด</div>
          </div>
          <span class="badge badge-soft-secondary">
            <i class="bi bi-pie-chart me-1"></i>Donut
          </span>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-box">
          <canvas id="statusDonutChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: งานตามสถานะ -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border-radius: 16px;">
      <div class="modal-header">
        <h6 class="modal-title" id="statusModalTitle">รายการงาน</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="small text-muted mb-2" id="statusModalSub">-</div>

        <div id="statusModalError" class="alert alert-danger d-none" role="alert"></div>
        <div id="statusModalEmpty" class="text-muted small d-none">ไม่มีงานในสถานะนี้</div>

        <div class="table-responsive mt-2">
          <table class="table table-hover align-middle mb-0 table-app">
            <thead>
              <tr>
                <th style="width:120px;">รหัส</th>
                <th>หัวข้อ</th>
                <th style="width:240px;">ผู้รับมอบหมาย</th>
                <th style="width:140px;">สถานะ</th>
                <th style="width:140px;">กำหนดส่ง</th>
              </tr>
            </thead>
            <tbody id="statusModalBody">
              <tr>
                <td colspan="5" class="py-4">
                  <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
                    <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                    <div>กำลังโหลด...</div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.__IS_BOSS__ = <?= json_encode(is_boss(), JSON_UNESCAPED_UNICODE) ?>;
  window.__DASH_DEFAULT_YEAR__ = <?= json_encode((int)date('Y')) ?>;
</script>


<?php include BASE_PATH . '/views/partials/footer.php'; ?>