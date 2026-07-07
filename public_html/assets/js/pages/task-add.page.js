// public/assets/js/pages/task-add.page.js
(() => {
  const deptSelect = document.getElementById("departmentSelect");

  const deptFilter = document.getElementById("assigneeDeptFilter");
  const searchBox = document.getElementById("assigneeSearch");

  const assigneeSelect = document.getElementById("assigneeSelect");
  const btnAdd = document.getElementById("btnAddAssignee");
  const listEl = document.getElementById("assigneeList");
  const inputsEl = document.getElementById("assigneeInputs");

  // ===== Submit Loading (กันกดซ้ำ + โชว์ loader ตอนรอ backend ส่งเมล) =====
  const formEl = document.getElementById("taskAddForm") || document.querySelector('form[method="post"]');
  const btnSubmit = document.getElementById("btnSubmit");
  const pageLoading = document.getElementById("pageLoading");

  if (formEl && btnSubmit && pageLoading) {
    let locked = false;

    formEl.addEventListener(
      "submit",
      () => {
        if (locked) return;
        locked = true;

        btnSubmit.disabled = true;
        btnSubmit.innerHTML =
          '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>กำลังบันทึก...';
        pageLoading.style.display = "flex";
      },
      { once: true }
    );
  }

  const INIT_ASSIGNEES = Array.isArray(window.__INIT_ASSIGNEES__) ? window.__INIT_ASSIGNEES__ : [];
  const INIT_DEPT = String(window.__INIT_DEPARTMENT__ ?? "");

  const toInt = (v) => {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  };

  const escapeHtml = (v) =>
    String(v ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  // เก็บข้อมูลพนักงานจาก option
  const staffMap = new Map(); // id -> { id, role, label }
  Array.from(assigneeSelect?.options || []).forEach((opt) => {
    const id = toInt(opt.value);
    const role = opt.getAttribute("data-role") || "";
    const label = opt.textContent || "";
    if (id > 0) staffMap.set(id, { id, role, label });
  });

  // state (รักษาลำดับการเพิ่ม)
  let selected = [];

  function rebuildHiddenInputs() {
    if (!inputsEl) return;
    inputsEl.innerHTML = "";
    for (const id of selected) {
      const inp = document.createElement("input");
      inp.type = "hidden";
      inp.name = "assignees[]";
      inp.value = String(id);
      inputsEl.appendChild(inp);
    }
  }

  function syncDepartment() {
    if (!deptSelect) return;

    // นับ role ของผู้รับมอบหมายที่เลือก
    const roles = new Set();
    for (const id of selected) {
      const s = staffMap.get(id);
      if (s?.role) roles.add(s.role);
    }

    if (roles.size === 0) return;

    if (roles.size === 1) {
      const only = Array.from(roles)[0];

      // ถ้า dept ว่าง หรือเป็น multi ให้ตั้งเป็น role เดียวนี้
      if (deptSelect.value === "" || deptSelect.value === "multi") {
        const has = Array.from(deptSelect.options).some((o) => o.value === only);
        if (has) deptSelect.value = only;
      }
      return;
    }

    // roles > 1 => ตั้งเป็น multi เสมอ
    const hasMulti = Array.from(deptSelect.options).some((o) => o.value === "multi");
    if (hasMulti) deptSelect.value = "multi";
  }

  function renderChips() {
    if (!listEl) return;
    listEl.innerHTML = "";

    for (const id of selected) {
      const s = staffMap.get(id);
      if (!s) continue;

      const chip = document.createElement("span");
      chip.className = "badge bg-light text-dark border d-inline-flex align-items-center gap-2";
      chip.style.borderRadius = "999px";
      chip.style.padding = "8px 10px";

      chip.innerHTML = `
        <span>${escapeHtml(s.label)}</span>
        ${s.role ? `<span class="badge bg-secondary">${escapeHtml(s.role)}</span>` : ""}
        <button type="button" class="btn btn-sm p-0 border-0" data-remove="${id}" title="ลบ">
          <i class="bi bi-x-circle"></i>
        </button>
      `;

      listEl.appendChild(chip);
    }

    rebuildHiddenInputs();
    syncDepartment();
  }

  function applyFilters() {
    if (!assigneeSelect) return;

    const role = String(deptFilter?.value ?? "");
    const q = String(searchBox?.value ?? "").trim().toLowerCase();

    for (const opt of Array.from(assigneeSelect.options)) {
      if (!opt.value) continue; // placeholder

      const optRole = opt.getAttribute("data-role") || "";
      const text = (opt.textContent || "").toLowerCase();

      const okRole = !role || optRole === role;
      const okText = !q || text.includes(q);

      opt.hidden = !(okRole && okText);
    }

    // ถ้าค่าที่เลือกถูกซ่อนไว้ ให้รีเซ็ต
    const cur = assigneeSelect.value;
    if (cur && assigneeSelect.selectedOptions[0]?.hidden) {
      assigneeSelect.value = "";
    }
  }

  function addSelectedAssignee() {
    const id = toInt(assigneeSelect?.value);
    if (!id) return;

    if (selected.includes(id)) {
      assigneeSelect.value = "";
      return;
    }

    selected.push(id);
    assigneeSelect.value = "";
    renderChips();
  }

  // ===== Debounce สำหรับค้นหา (ถ้ารายชื่อเยอะจะลื่นขึ้น) =====
  function debounce(fn, wait = 200) {
    let t = null;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  }
  const applyFiltersDebounced = debounce(applyFilters, 200);

  // init
  if (deptSelect && INIT_DEPT) deptSelect.value = INIT_DEPT;

  selected = (Array.isArray(INIT_ASSIGNEES) ? INIT_ASSIGNEES : [])
    .map((x) => toInt(x))
    .filter((x) => x > 0);

  // กันซ้ำและคงลำดับ
  const seen = new Set();
  selected = selected.filter((id) => (seen.has(id) ? false : (seen.add(id), true)));

  renderChips();
  applyFilters();

  // events
  btnAdd?.addEventListener("click", (e) => {
    e.preventDefault();
    addSelectedAssignee();
  });

  listEl?.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-remove]");
    if (!btn) return;
    const id = toInt(btn.getAttribute("data-remove"));
    selected = selected.filter((x) => x !== id);
    renderChips();
  });

  deptFilter?.addEventListener("change", () => applyFilters());
  searchBox?.addEventListener("input", () => applyFiltersDebounced());
})();
