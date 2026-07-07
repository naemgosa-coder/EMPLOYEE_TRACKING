// public/assets/js/pages/dashboard.page.js
import { DashboardService } from "../services/dashboard.service.js";
import { TaskService } from "../services/task.service.js";

const STATUS_LABELS = {
  pending: "รอดำเนินการ",
  in_progress: "กำลังดำเนินการ",
  completed: "เสร็จแล้ว",
  overdue: "เกินกำหนด",
};

const STATUS_COLORS = {
  pending: "rgba(13,110,253,0.65)",
  in_progress: "rgba(255,193,7,0.65)",
  completed: "rgba(25,135,84,0.65)",
  overdue: "rgba(220,53,69,0.65)",
};

function statusColorArray() {
  return [
    STATUS_COLORS.pending,
    STATUS_COLORS.in_progress,
    STATUS_COLORS.completed,
    STATUS_COLORS.overdue,
  ];
}

const STATUS_BADGE = {
  pending: "badge-soft-primary",
  in_progress: "badge-soft-secondary",
  completed: "badge-soft-success",
  overdue: "badge-soft-danger",
};

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = String(text ?? "");
}

function escapeHtml(v) {
  const s = String(v ?? "");
  return s
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

/* =========================
   Filters (department + year)
========================= */
const FILTER_STORE_KEY = "dashboardFilters:v1";
const filters = { department: "", year: "" };

function getFilterEls() {
  return {
    deptEl: document.getElementById("filterDept"),
    yearEl: document.getElementById("filterYear"),
  };
}

function loadFiltersFromStorage() {
  try {
    const raw = localStorage.getItem(FILTER_STORE_KEY);
    if (!raw) return;
    const obj = JSON.parse(raw);
    if (obj && typeof obj === "object") {
      filters.department = obj.department ?? "";
      filters.year = obj.year ?? "";
    }
  } catch (_) { }
}

function saveFiltersToStorage() {
  try { localStorage.setItem(FILTER_STORE_KEY, JSON.stringify(filters)); } catch (_) { }
}

function syncFilterUIFromState() {
  const { deptEl, yearEl } = getFilterEls();
  if (deptEl) deptEl.value = String(filters.department ?? "");
  if (yearEl) yearEl.value = String(filters.year ?? "");
}

function readFiltersFromUI() {
  const { deptEl, yearEl } = getFilterEls();
  filters.department = deptEl ? String(deptEl.value ?? "") : "";
  filters.year = yearEl ? String(yearEl.value ?? "") : "";
  saveFiltersToStorage();
}

function buildFilterParams() {
  const p = {};
  if (window.__IS_BOSS__ && filters.department) p.department = filters.department;
  if (filters.year) p.year = filters.year;
  return p;
}

function currentFilterLabel() {
  const { deptEl, yearEl } = getFilterEls();

  const deptTxt =
    deptEl && deptEl.value
      ? (deptEl.options[deptEl.selectedIndex]?.text || deptEl.value)
      : "ทุกแผนก";

  const yearTxt =
    yearEl && yearEl.value
      ? (yearEl.options[yearEl.selectedIndex]?.text || `ปี ${yearEl.value}`)
      : "ทุกปี";

  return `${deptTxt} • ${yearTxt}`;
}

/* =========================
   Charts
========================= */
let barChart = null;
let donutChart = null;

function renderBarChart(counts) {
  const canvas = document.getElementById("statusChart");
  if (!canvas || !window.Chart) return;

  const labels = [
    STATUS_LABELS.pending,
    STATUS_LABELS.in_progress,
    STATUS_LABELS.completed,
    STATUS_LABELS.overdue,
  ];

  const values = [
    counts?.pending ?? 0,
    counts?.in_progress ?? 0,
    counts?.completed ?? 0,
    counts?.overdue ?? 0,
  ];

  const colors = statusColorArray();

  if (barChart) barChart.destroy();

  barChart = new window.Chart(canvas, {
    type: "bar",
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderWidth: 0,
        borderRadius: 10,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: "y",
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  });
}

function renderDonutChart(counts) {
  const canvas = document.getElementById("statusDonutChart");
  if (!canvas || !window.Chart) return;

  const labels = [
    STATUS_LABELS.pending,
    STATUS_LABELS.in_progress,
    STATUS_LABELS.completed,
    STATUS_LABELS.overdue,
  ];

  const values = [
    counts?.pending ?? 0,
    counts?.in_progress ?? 0,
    counts?.completed ?? 0,
    counts?.overdue ?? 0,
  ];

  const colors = statusColorArray();

  if (donutChart) donutChart.destroy();

  donutChart = new window.Chart(canvas, {
    type: "doughnut",
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderColor: "rgba(255,255,255,.9)",
        borderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "62%",
      plugins: { legend: { position: "bottom" }, tooltip: { enabled: true } },
    },
  });
}

/* =========================
   Overdue compute (from due_date)
========================= */
function ymdToLocalDate(ymd) {
  const s = String(ymd || "").trim();
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return null;
  const y = Number(m[1]);
  const mo = Number(m[2]) - 1;
  const d = Number(m[3]);
  const dt = new Date(y, mo, d, 0, 0, 0, 0);
  return Number.isNaN(dt.getTime()) ? null : dt;
}

function daysOverdue(dueYmd) {
  const due = ymdToLocalDate(dueYmd);
  if (!due) return 0;

  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);

  const diffMs = today.getTime() - due.getTime();
  if (diffMs <= 0) return 0;
  return Math.floor(diffMs / (24 * 60 * 60 * 1000));
}

function normalizeBaseStatus(s) {
  const x = String(s || "pending");
  if (x === "overdue") return "pending";
  return ["pending", "in_progress", "completed"].includes(x) ? x : "pending";
}

function getOverdueDays(task) {
  const base = normalizeBaseStatus(task?.status);
  if (base === "completed") return 0;
  return daysOverdue(task?.due_date);
}

function fmtDate(d) {
  if (!d) return "-";
  const dt = ymdToLocalDate(d);
  return dt ? dt.toLocaleDateString("th-TH") : String(d);
}

/* =========================
   Modal list
========================= */
let lastCounts = { pending: 0, in_progress: 0, completed: 0, overdue: 0 };

function modalEls() {
  return {
    modalEl: document.getElementById("statusModal"),
    titleEl: document.getElementById("statusModalTitle"),
    subEl: document.getElementById("statusModalSub"),
    errEl: document.getElementById("statusModalError"),
    emptyEl: document.getElementById("statusModalEmpty"),
    bodyEl: document.getElementById("statusModalBody"),
  };
}

function setModalLoading() {
  const { bodyEl, emptyEl, errEl } = modalEls();
  if (!bodyEl) return;
  if (errEl) errEl.classList.add("d-none");
  if (emptyEl) emptyEl.classList.add("d-none");
  bodyEl.innerHTML = `
    <tr>
      <td colspan="5" class="py-4">
        <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
          <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
          <div>กำลังโหลด...</div>
        </div>
      </td>
    </tr>
  `;
}

function setModalError(msg) {
  const { errEl } = modalEls();
  if (!errEl) return;
  errEl.textContent = String(msg || "โหลดข้อมูลไม่สำเร็จ");
  errEl.classList.remove("d-none");
}

function renderModalRows(tasks, targetStatus) {
  const { bodyEl, emptyEl } = modalEls();
  if (!bodyEl) return;

  bodyEl.innerHTML = "";

  const list = Array.isArray(tasks) ? tasks : [];
  if (!list.length) {
    if (emptyEl) emptyEl.classList.remove("d-none");
    bodyEl.innerHTML = `<tr><td colspan="5" class="py-3"></td></tr>`;
    return;
  }
  if (emptyEl) emptyEl.classList.add("d-none");

  for (const t of list) {
    const base = normalizeBaseStatus(t?.status);
    const od = getOverdueDays(t);
    const isOver = od > 0;

    const mainStatus = targetStatus === "overdue" ? "overdue" : base;
    const badge = STATUS_BADGE[mainStatus] || "badge-soft-secondary";
    const statusText = STATUS_LABELS[mainStatus] || mainStatus;

    let extra = "";
    if (targetStatus === "overdue") {
      extra = ` <span class="badge badge-soft-secondary ms-2">${escapeHtml(STATUS_LABELS[base] || base)}</span>`;
    } else {
      extra = isOver ? ` <span class="badge badge-soft-danger ms-2">เกินกำหนด</span>` : "";
    }

    const overdueLine =
      isOver ? `<div class="small text-danger mt-1">เกินกำหนด ${od} วัน</div>` : "";

    const assignees = t.assigned_to_names || t.assigned_to_name || "-";

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="text-nowrap fw-semibold">${escapeHtml(t.task_code || "")}</td>
      <td>
        <div class="fw-semibold">${escapeHtml(t.title || "")}</div>
        ${t.description
        ? `<div class="small text-muted text-truncate">${escapeHtml(String(t.description).slice(0, 100))}</div>`
        : `<div class="small text-muted">-</div>`
      }
      </td>
      <td><div class="small">${escapeHtml(assignees)}</div></td>
      <td class="text-nowrap">
        <span class="badge ${badge}">${escapeHtml(statusText)}</span>${extra}
        ${overdueLine}
      </td>
      <td class="text-nowrap">${escapeHtml(fmtDate(t.due_date))}</td>
    `;
    bodyEl.appendChild(tr);
  }
}

async function openStatusModal(statusKey) {
  const { modalEl, titleEl, subEl } = modalEls();
  if (!modalEl) return;

  const label = STATUS_LABELS[statusKey] || statusKey;
  const count = Number(lastCounts?.[statusKey] ?? 0);

  if (titleEl) titleEl.textContent = `รายการงาน: ${label}`;
  if (subEl) subEl.textContent = `ทั้งหมด ${count} รายการ • ${currentFilterLabel()}`;

  setModalLoading();
  bootstrap.Modal.getOrCreateInstance(modalEl).show();

  try {
    const filterParams = buildFilterParams();
    let rows = []; // ✅ สำคัญ: ต้องประกาศ

    if (statusKey === "overdue") {
      const res = await TaskService.list({ ...filterParams });

      // กันรูปแบบที่อาจเป็น {ok:true,data:[...]} หรือ {data:[...]} หรือ [...]
      const raw = res?.data?.data ?? res?.data ?? res ?? [];
      rows = Array.isArray(raw) ? raw : [];

      rows = rows.filter((t) => getOverdueDays(t) > 0);
    } else {
      const res = await TaskService.list({ ...filterParams, status: statusKey });

      const raw = res?.data?.data ?? res?.data ?? res ?? [];
      rows = Array.isArray(raw) ? raw : [];
    }

    renderModalRows(rows, statusKey);
  } catch (e) {
    console.error(e);
    renderModalRows([], statusKey);
    setModalError(e?.message || "โหลดรายการงานไม่สำเร็จ");
  }
}

/* =========================
   Load Dashboard
========================= */
let loading = false;

function normalizeCounts(x) {
  const o = x && typeof x === "object" ? x : {};
  const toNum = (v) => {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  };
  return {
    pending: toNum(o.pending),
    in_progress: toNum(o.in_progress),
    completed: toNum(o.completed),
    overdue: toNum(o.overdue),
  };
}

async function loadDashboard() {
  if (loading) return;
  loading = true;

  const alertBox = document.getElementById("dashboardError");
  const alertText = document.getElementById("dashboardErrorText");
  if (alertBox) alertBox.classList.add("d-none");
  if (alertText) alertText.textContent = "";

  try {
    const res = await DashboardService.get(buildFilterParams());
    const payload = res?.data?.data ?? res?.data ?? res ?? {};

    const counts = normalizeCounts(payload.counts || {});

    // เก็บไว้ให้ modal header ใช้ด้วย
    lastCounts = counts;

    // การ์ด
    setText("statPending", counts.pending);
    setText("statInProgress", counts.in_progress);
    setText("statCompleted", counts.completed);
    setText("statOverdue", counts.overdue);

    renderBarChart(counts);
    renderDonutChart(counts);

  } catch (err) {
    console.error(err);
    if (alertBox) alertBox.classList.remove("d-none");
    if (alertText) alertText.textContent = err?.message || "โหลดข้อมูลไม่สำเร็จ";
  } finally {
    loading = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  // โหลดค่าตัวกรองเดิม
  loadFiltersFromStorage();

  // (optional) ถ้าอยาก default ปีปัจจุบันเมื่อยังไม่เคยเลือก
  if (!filters.year && window.__DASH_DEFAULT_YEAR__) {
    // ถ้าอยากให้ default เป็น "ปีปัจจุบัน" ให้เปิดบรรทัดนี้
    // filters.year = String(window.__DASH_DEFAULT_YEAR__);
  }
  if (!window.__IS_BOSS__) {
    filters.department = "";
    saveFiltersToStorage();
  }

  syncFilterUIFromState();
  loadDashboard();

  const btn = document.getElementById("btnRefresh");
  if (btn) btn.addEventListener("click", loadDashboard);

  const { deptEl, yearEl } = getFilterEls();
  if (deptEl) deptEl.addEventListener("change", () => { readFiltersFromUI(); loadDashboard(); });
  if (yearEl) yearEl.addEventListener("change", () => { readFiltersFromUI(); loadDashboard(); });

  document.addEventListener("click", (e) => {
    const card = e.target.closest(".js-status-card");
    if (!card) return;
    const st = card.getAttribute("data-status") || "";
    if (st) openStatusModal(st);
  });

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Enter" && e.key !== " ") return;
    const card = document.activeElement?.closest?.(".js-status-card");
    if (!card) return;
    e.preventDefault();
    const st = card.getAttribute("data-status") || "";
    if (st) openStatusModal(st);
  });
});
