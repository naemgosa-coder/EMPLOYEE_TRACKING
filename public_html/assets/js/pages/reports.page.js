// public/assets/js/pages/reports.page.js
import { ReportService } from "../services/report.service.js";

const STATUS_LABELS = {
  pending: "รอดำเนินการ",
  in_progress: "กำลังดำเนินการ",
  completed: "เสร็จแล้ว",
  overdue: "เกินกำหนด",
};

const STATUS_BADGE = {
  pending: "badge-soft-primary",
  in_progress: "badge-soft-secondary",
  completed: "badge-soft-success",
  overdue: "badge-soft-danger",
};

function fmtDateTime(d) {
  if (!d) return "-";
  const dt = new Date(d);
  return Number.isNaN(dt.getTime()) ? String(d) : dt.toLocaleString("th-TH");
}

function fmtDate(d) {
  if (!d) return "-";
  const dt = new Date(d);
  return Number.isNaN(dt.getTime()) ? String(d) : dt.toLocaleDateString("th-TH");
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = String(text ?? "");
}

function show(el, yes) {
  if (!el) return;
  el.classList.toggle("d-none", !yes);
}

function safeLabel(status) {
  const key = String(status || "").toLowerCase();
  return STATUS_LABELS[key] || status || "-";
}

function setLoading() {
  const wrap = document.getElementById("reportList");
  if (!wrap) return;
  wrap.innerHTML = `
    <div class="d-flex align-items-center justify-content-center gap-2 text-muted py-4">
      <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
      <div>กำลังโหลดรายงาน...</div>
    </div>
  `;
}

function render(tasks, logsByTask) {
  const wrap = document.getElementById("reportList");
  const empty = document.getElementById("reportEmpty");
  if (!wrap) return;

  const list = Array.isArray(tasks) ? tasks : [];
  setText("reportTotal", list.length);

  wrap.innerHTML = "";

  if (list.length === 0) {
    show(empty, true);
    return;
  }
  show(empty, false);

  for (const t of list) {
    const badge = STATUS_BADGE[t.status] || "badge-soft-secondary";
    const logs = logsByTask && logsByTask[t.id] ? logsByTask[t.id] : [];

    const logHtml = logs.length
      ? logs
          .map((l, idx) => {
            const oldS = safeLabel(l.old_status);
            const newS = safeLabel(l.new_status);
            const who = l.full_name || "-";
            const note = l.note ? String(l.note) : "";
            const when = fmtDateTime(l.created_at);
            const isLast = idx === logs.length - 1;

            return `
              <div class="d-flex gap-2 align-items-start py-2 ${isLast ? "" : "border-bottom"}">
                <div class="small text-muted text-nowrap">${when}</div>
                <div class="flex-grow-1">
                  <div class="small">
                    <span class="fw-semibold">${who}</span>
                    ${note ? `<span class="text-muted">: ${note}</span>` : ""}
                  </div>
                  <div class="small text-muted">${oldS} → ${newS}</div>
                </div>
              </div>
            `;
          })
          .join("")
      : `<div class="text-muted small">ยังไม่มีประวัติการเปลี่ยนสถานะ</div>`;

    const due = fmtDate(t.due_date);

    const card = document.createElement("div");
    card.className = "card app-card shadow-sm border-0 mb-3";
    card.innerHTML = `
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
          <div>
            <div class="fw-semibold">${t.title || ""}</div>
            <div class="small text-muted">
              ${t.task_code || ""} • มอบหมายให้: ${t.assigned_to_name || "-"}
            </div>
          </div>

          <div class="text-end">
            <span class="badge ${badge}">${safeLabel(t.status)}</span>
            <div class="small text-muted mt-1">กำหนดส่ง: ${due}</div>
          </div>
        </div>

        <div class="mt-3">
          <button class="btn btn-sm btn-outline-primary"
                  data-bs-toggle="collapse"
                  data-bs-target="#log-${t.id}">
            <i class="bi bi-clock-history me-1"></i>ดูประวัติ
          </button>

          <div class="collapse mt-2" id="log-${t.id}">
            <div class="border rounded-3 p-2" style="border-color: rgba(15,23,42,.10); background: rgba(248,250,252,.7);">
              ${logHtml}
            </div>
          </div>
        </div>
      </div>
    `;
    wrap.appendChild(card);
  }
}

async function load() {
  const status = document.getElementById("reportStatus")?.value || "";
  const assigned_to = document.getElementById("reportAssignedTo")?.value || "";
  const keyword = document.getElementById("reportKeyword")?.value || "";
  const btn = document.getElementById("btnReportSearch");

  const box = document.getElementById("reportsError");
  if (box) box.classList.add("d-none");

  if (btn) btn.disabled = true;
  setLoading();

  try {
    const res = await ReportService.get({ status, assigned_to, keyword });
    render(res?.data?.tasks || [], res?.data?.logs || {});
  } catch (err) {
    console.error(err);
    render([], {});
    if (box) {
      box.classList.remove("d-none");
      box.textContent = err?.message || "โหลดรายงานไม่สำเร็จ";
    }
  } finally {
    if (btn) btn.disabled = false;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("reportForm")?.addEventListener("submit", (e) => {
    e.preventDefault();
    load();
  });

  document.getElementById("btnExportCsv")?.addEventListener("click", () => {
    const status = document.getElementById("reportStatus")?.value || "";
    const assigned_to = document.getElementById("reportAssignedTo")?.value || "";
    const keyword = document.getElementById("reportKeyword")?.value || "";
    const qs = new URLSearchParams({ status, assigned_to, keyword, format: "csv" }).toString();
    window.location.href = `api/reports/export?${qs}`;
  });

  load();
});
