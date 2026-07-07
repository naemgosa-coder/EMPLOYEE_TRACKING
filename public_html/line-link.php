<?php
// public/line-link.php
require_once '../app/db.php';
require_once '../app/auth.php';
require_once '../app/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) {
  header('Location: login');
  exit;
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
  header('Location: login');
  exit;
}

/**
 * ✅ ใส่ของจริงของ OA คุณตรงนี้
 */
$LINE_ADD_FRIEND_URL = 'https://lin.ee/oTZsRnZ';
$LINE_QR_IMG_URL     = 'https://qr-official.line.me/gs/M_025mxhzp_GW.png?oat_content=qr';

// ✅ ดึงสถานะจริงจาก DB (ให้ตรงแม้ผูกผ่านบอทแล้ว)
$stmt = $pdo->prepare("SELECT full_name, email, line_user_id FROM users WHERE id=:id LIMIT 1");
$stmt->execute(['id' => $userId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$lineUserId = (string)($me['line_user_id'] ?? '');
$lineLinked = $lineUserId !== '';

// ✅ sync session ให้ header badge ตรง
$_SESSION['user']['line_user_id'] = $lineUserId;

$pageTitle = 'ผูก LINE แจ้งเตือน';
include 'assets/partials/header.php';
?>

<div class="card app-card shadow-sm border-0 mb-3 overview-card mx-auto">
  <div class="row justify-content-center">
      <div class="bcard p-4">
        <div class="d-flex align-items-start justify-content-between gap-3">
          <div>
            <h4 class="mb-1">ผูก LINE เพื่อรับแจ้งเตือนงาน</h4>
            <div class="text-muted small">
              หลังผูกแล้ว ระบบจะส่งแจ้งเตือนงานใหม่/อัปเดตสถานะ/ใกล้ครบกำหนด/เกินกำหนด ผ่าน LINE ให้คุณอัตโนมัติ
            </div>
          </div>

          <?php if ($lineLinked): ?>
            <span class="badge text-bg-success align-self-start">ผูกแล้ว</span>
          <?php else: ?>
            <span class="badge text-bg-secondary align-self-start">ยังไม่ผูก</span>
          <?php endif; ?>
        </div>

        <hr class="my-3">

        <div class="row g-3">
          <!-- ซ้าย: ขั้นตอน + ปุ่ม -->
          <div class="col-12 col-lg-7">

            <?php if ($lineLinked): ?>
              <div class="alert alert-success mb-3">
                ✅ ผูก LINE แล้วเรียบร้อย<br>
                <span class="small text-muted">
                  ถ้าต้องการย้ายไป LINE เครื่องอื่น สามารถกด “ยกเลิกการผูก” แล้วค่อยผูกใหม่ได้
                  (หรือพิมพ์ <b>unlink</b> ในแชทบอท)
                </span>
              </div>
            <?php else: ?>
              <div class="alert alert-info mb-3">
                ℹ️ ยังไม่ได้ผูก LINE<br>
                <span class="small text-muted">ทำตามขั้นตอนด้านล่างเพื่อผูกบัญชี</span>
              </div>
            <?php endif; ?>

            <div class="mb-3">
              <div class="fw-semibold mb-2">ขั้นตอน</div>
              <ol class="mb-0">
                <li>กดปุ่ม <b>เพิ่มเพื่อน LINE</b> (ถ้ายังไม่ได้เพิ่ม)</li>
                <li>กดปุ่ม <b>สร้างโค้ด 6 ตัว</b></li>
                <li>ไปที่แชท LINE ของบอท แล้วส่งโค้ด 6 ตัว</li>
              </ol>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
              <a class="btn btn-success"
                 href="<?= htmlspecialchars($LINE_ADD_FRIEND_URL) ?>"
                 target="_blank" rel="noopener">
                <i class="bi bi-line me-1"></i>เพิ่มเพื่อน LINE
              </a>

              <button class="btn btn-primary" id="btnGen" <?= $lineLinked ? 'disabled' : '' ?>>
                <i class="bi bi-key me-1"></i>สร้างโค้ด 6 ตัว
              </button>

              <button class="btn btn-outline-secondary" id="btnCopy" disabled>
                <i class="bi bi-clipboard me-1"></i>คัดลอกโค้ด
              </button>

              <?php if ($lineLinked): ?>
                <button class="btn btn-outline-danger" id="btnUnlink">
                  <i class="bi bi-link-45deg me-1"></i>ยกเลิกการผูก LINE
                </button>
              <?php endif; ?>

              <a class="btn btn-light" href="dashboard">
                <i class="bi bi-house me-1"></i>กลับหน้าแรก
              </a>
            </div>

            <div class="mt-4">
              <div class="text-muted small mb-1">โค้ดของคุณ</div>
              <div class="border rounded-3 p-3 d-flex align-items-center justify-content-between">
                <div>
                  <div class="display-6 fw-bold mb-0" id="codeText" style="letter-spacing:.25em;">------</div>
                  <div class="text-muted small mt-1" id="expText">
                    <?= $lineLinked ? 'คุณผูก LINE แล้ว (ไม่ต้องสร้างโค้ดใหม่)' : 'กด “สร้างโค้ด 6 ตัว” เพื่อเริ่มผูก' ?>
                  </div>
                </div>
                <i class="bi bi-chat-dots text-muted" style="font-size:28px;"></i>
              </div>

              <div class="text-muted small mt-3">
                ใน LINE ส่งได้ 2 แบบ:<br>
                • <b>ABC123</b> (พิมพ์แค่โค้ด 6 ตัว)<br>
                • <b>link ABC123</b>
              </div>
            </div>

          </div>

          <!-- ขวา: QR แบบ official -->
          <div class="col-12 col-lg-5">
            <div class="border rounded-3 p-3 h-100">
              <div class="fw-semibold mb-2">สแกน QR เพื่อเพิ่มเพื่อน</div>
              <div class="text-muted small mb-3">
                เปิด LINE → เพิ่มเพื่อน → สแกน QR (หรือกดปุ่มเปิดลิงก์)
              </div>

              <div class="text-center">
                <img
                  src="<?= htmlspecialchars($LINE_QR_IMG_URL) ?>"
                  alt="LINE Official QR"
                  class="img-fluid rounded-3 border"
                  style="max-width: 280px"
                  referrerpolicy="no-referrer"
                  loading="lazy"
                >
              </div>

              <div class="d-grid gap-2 mt-3">
                <a class="btn btn-outline-success"
                   href="<?= htmlspecialchars($LINE_ADD_FRIEND_URL) ?>"
                   target="_blank" rel="noopener">
                  <i class="bi bi-person-plus me-1"></i>เปิดลิงก์เพิ่มเพื่อน
                </a>

                <a class="btn btn-outline-secondary"
                   href="<?= htmlspecialchars($LINE_QR_IMG_URL) ?>"
                   target="_blank" rel="noopener">
                  <i class="bi bi-qr-code me-1"></i>เปิด QR เต็มจอ
                </a>
              </div>

              <div class="text-muted small mt-3">
                ถ้ารูป QR ไม่ขึ้น ให้กด “เปิดลิงก์เพิ่มเพื่อน”
              </div>
            </div>
          </div>
        </div>

    </div>
  </div>
</div>

<script>
(function () {
  const btnGen = document.getElementById('btnGen');
  const btnCopy = document.getElementById('btnCopy');
  const btnUnlink = document.getElementById('btnUnlink');

  const codeText = document.getElementById('codeText');
  const expText = document.getElementById('expText');

  let lastCode = '';

  function setGenLoading(on) {
    if (!btnGen) return;
    btnGen.disabled = on;
    btnGen.innerHTML = on
      ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังสร้าง...'
      : '<i class="bi bi-key me-1"></i>สร้างโค้ด 6 ตัว';
  }

  // ===== สร้างโค้ด =====
  if (btnGen && !btnGen.disabled) {
    btnGen.addEventListener('click', async () => {
      setGenLoading(true);
      try {
        const r = await fetch('api/line/create-link-code.php', { method: 'POST' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'สร้างโค้ดไม่สำเร็จ');

        lastCode = j.code || '';
        codeText.textContent = lastCode || '------';
        expText.textContent = j.expires_at ? ('หมดอายุ: ' + j.expires_at) : '';

        btnCopy.disabled = !lastCode;
      } catch (e) {
        alert(e.message || 'เกิดข้อผิดพลาด');
      } finally {
        setGenLoading(false);
      }
    });
  }

  // ===== คัดลอกโค้ด =====
  if (btnCopy) {
    btnCopy.addEventListener('click', async () => {
      if (!lastCode) return;
      try {
        await navigator.clipboard.writeText(lastCode);
        btnCopy.innerHTML = '<i class="bi bi-check2 me-1"></i>คัดลอกแล้ว';
        setTimeout(() => {
          btnCopy.innerHTML = '<i class="bi bi-clipboard me-1"></i>คัดลอกโค้ด';
        }, 1000);
      } catch (e) {
        alert('คัดลอกไม่สำเร็จ (ลองเลือกข้อความแล้วคัดลอกเอง)');
      }
    });
  }

  // ===== ยกเลิกการผูก =====
  if (btnUnlink) {
    btnUnlink.addEventListener('click', async () => {
      const ok = confirm(
        'ต้องการยกเลิกการผูก LINE ใช่ไหม?\n\n' +
        'หลังยกเลิก คุณจะไม่ได้รับแจ้งเตือนผ่าน LINE จนกว่าจะผูกใหม่'
      );
      if (!ok) return;

      btnUnlink.disabled = true;
      btnUnlink.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังยกเลิก...';

      try {
        const r = await fetch('api/line/unlink.php', { method: 'POST' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'ยกเลิกไม่สำเร็จ');

        if (j.notified) {
          alert('✅ ยกเลิกการผูกเรียบร้อยแล้ว\n(แจ้งเตือนใน LINE แล้ว)');
        } else {
          alert('✅ ยกเลิกการผูกเรียบร้อยแล้ว\n(ยังไม่สามารถแจ้งเตือนใน LINE ได้)');
        }
        location.reload();
      } catch (e) {
        alert(e.message || 'เกิดข้อผิดพลาด');
        btnUnlink.disabled = false;
        btnUnlink.innerHTML = '<i class="bi bi-link-45deg me-1"></i>ยกเลิกการผูก LINE';
      }
    });
  }
})();
</script>

<?php include 'assets/partials/footer.php'; ?>