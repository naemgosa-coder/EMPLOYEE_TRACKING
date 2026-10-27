<?php
// views/partials/footer.php
$pageJs = $pageJs ?? [];
?>

</main>
</div> <!-- .container -->
</div> <!-- .page-wrapper -->

<script src="assets/js/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script type="module" src="assets/js/app.js"></script>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>


<?php foreach ($pageJs as $js): ?>
  <script type="module" src="<?= e($js) ?>"></script>
<?php endforeach; ?>

<!-- Global Overlay -->
<div id="globalOverlay" class="global-overlay d-none" aria-hidden="true">
  <div class="global-overlay__box">
    <div class="spinner-border" role="status" aria-hidden="true"></div>
    <div class="global-overlay__text" id="globalOverlayText">กำลังบันทึก...</div>
  </div>
</div>

<script>
  window.showGlobalOverlay = function(text) {
    const el = document.getElementById("globalOverlay");
    const tx = document.getElementById("globalOverlayText");
    if (!el) return;
    if (tx) tx.textContent = text || "กำลังบันทึก...";
    el.classList.remove("d-none");
    el.setAttribute("aria-hidden", "false");
  };

  window.hideGlobalOverlay = function() {
    const el = document.getElementById("globalOverlay");
    if (!el) return;
    el.classList.add("d-none");
    el.setAttribute("aria-hidden", "true");
  };

    // Auto overlay on form submit (เฉพาะฟอร์มที่ติด data-overlay)
  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute("data-overlay")) return;

    const text = form.getAttribute("data-overlay-text") || "กำลังบันทึก...";
    window.showGlobalOverlay?.(text);

    // disable submit button + spinner
    const btn = e.submitter || form.querySelector("button[type='submit'], input[type='submit']");
    if (btn && !btn.dataset.__loading) {
      btn.dataset.__loading = "1";
      btn.dataset.__oldHtml = btn.innerHTML || btn.value || "";
      btn.disabled = true;

      if (btn.tagName === "BUTTON") {
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${text}`;
      } else {
        btn.value = text;
      }
    }
  }, true);
</script>

</body>

</html>
