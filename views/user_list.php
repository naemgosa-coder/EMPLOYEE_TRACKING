
<?php

require_login();
$user = current_user();

$pageTitle = 'พนักงาน';
$pageCss = ['assets/css/pages/users.css'];
$pageJs  = ['assets/js/pages/users.page.js'];
include BASE_PATH . '/views/partials/header.php';
?>

<!-- ส่งค่า current user ให้ JS -->
<script>
  window.__CURRENT_USER_ID = <?= (int)($user['id'] ?? 0) ?>;
  window.__CURRENT_USER_ROLE = <?= json_encode($user['role'] ?? '') ?>;
</script>

<!-- Page Head -->
<div class="page-head mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <h1 class="page-title mb-1">พนักงาน</h1>
      <div class="page-subtitle">จัดการผู้ใช้งานภายในระบบ และสถานะการใช้งาน</div>
    </div>

    <div class="d-flex gap-2">
      <a href="users/add" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>เพิ่มพนักงาน
      </a>
    </div>
  </div>
</div>

<div id="usersError" class="alert alert-danger d-none" role="alert"></div>

<!-- Table -->
<div class="card app-card shadow-sm border-0">
  <div class="card-header app-card__header">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">รายชื่อพนักงาน</div>
        <div class="text-muted small">แสดงข้อมูลผู้ใช้งานทั้งหมดในระบบ</div>
      </div>
      <div class="text-muted small">
        ทั้งหมด: <span id="userTotal">-</span> คน
      </div>
    </div>
  </div>

  <div class="card-body pt-2">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 table-app">
        <thead>
          <tr>
            <th
              style="width:90px;"
              id="thSortId"
              class="text-nowrap"
              role="button"
              tabindex="0"
              aria-label="เรียงตาม ID"
              title="กดเพื่อสลับเรียง ID"
            >
              ID <i id="sortIdIcon" class="bi bi-caret-down-fill ms-1 small text-muted"></i>
            </th>
            <th style="width:220px;">Username</th>
            <th>ชื่อ-นามสกุล</th>
            <th style="width:140px;">บทบาท</th>
            <th style="width:140px;">สถานะ</th>
            <th style="width:180px;" class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody id="usersBody">
          <tr>
            <td colspan="6" class="py-4">
              <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                <div>กำลังโหลดรายชื่อพนักงาน...</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div id="usersEmpty" class="empty-state d-none">
      <div class="empty-state__icon">
        <i class="bi bi-people"></i>
      </div>
      <div class="fw-semibold">ยังไม่มีพนักงานในระบบ</div>
      <div class="text-muted small">เริ่มต้นด้วยการเพิ่มพนักงานคนแรก</div>
      <a href="users/add" class="btn btn-primary btn-sm mt-2">
        <i class="bi bi-person-plus me-1"></i>เพิ่มพนักงาน
      </a>
    </div>
  </div>
</div>

<!-- Modal ยืนยันลบบัญชี -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-trash me-1 text-danger"></i> ยืนยันการลบบัญชี
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <div class="mb-1">คุณต้องการลบบัญชีนี้ใช่ไหม?</div>
        <div class="text-muted small">
          ผู้ใช้: <span class="fw-semibold" id="delUserName">-</span><br>
          การลบอาจทำไม่ได้ถ้ามีข้อมูลอื่นอ้างอิงผู้ใช้นี้ (เช่น งาน/บันทึก)
        </div>
        <input type="hidden" id="delUserId" value="">
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
        <button class="btn btn-danger" id="btnConfirmDelete">
          <i class="bi bi-trash me-1"></i>ลบเลย
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal เปลี่ยนรหัสผ่าน (Boss ทำให้พนักงาน) -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-key me-1 text-primary"></i> เปลี่ยนรหัสผ่าน
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>

      <div class="modal-body">
        <div class="text-muted small mb-2">
          ผู้ใช้: <span class="fw-semibold" id="pwUserName">-</span>
        </div>

        <input type="hidden" id="pwUserId" value="">

        <div class="mb-2">
          <label class="form-label">รหัสผ่านใหม่</label>
          <input type="password" class="form-control" id="pw1" placeholder="อย่างน้อย 8 ตัวอักษร">
        </div>

        <div class="mb-2">
          <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
          <input type="password" class="form-control" id="pw2" placeholder="พิมพ์ซ้ำอีกครั้ง">
        </div>

        <div class="text-muted small">
          * ระบบจะทับรหัสเดิมทันที (เก็บเป็น password_hash)
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
        <button class="btn btn-primary" id="btnConfirmPassword">
          <i class="bi bi-check2 me-1"></i>บันทึก
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal แก้ไขข้อมูลพนักงาน -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-pencil-square me-1 text-primary"></i> แก้ไขข้อมูลพนักงาน
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>

      <div class="modal-body">
        <div class="text-muted small mb-2">
          ผู้ใช้: <span class="fw-semibold" id="editUserName">-</span>
        </div>

        <input type="hidden" id="editUserId" value="">

        <div class="mb-2">
          <label class="form-label">ชื่อ-นามสกุล</label>
          <input type="text" class="form-control" id="editFullName" placeholder="กรอกชื่อ-นามสกุล">
        </div>

        <div class="mb-2">
          <label class="form-label">บทบาท</label>
          <div class="form-control bg-light" id="editRoleText">-</div>
          <div class="form-text">* บทบาทแก้ไขไม่ได้</div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
        <button class="btn btn-primary" id="btnConfirmEditUser">
          <i class="bi bi-check2 me-1"></i>บันทึก
        </button>
      </div>
    </div>
  </div>
</div>

<?php include BASE_PATH . '/views/partials/footer.php'; ?>