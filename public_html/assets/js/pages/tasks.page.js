// public/assets/js/pages/tasks.page.js
import { TaskService } from "../services/task.service.js";

const STATUS_LABELS = {
  pending: "รอดำเนินการ",
  in_progress: "กำลังดำเนินการ",
  completed: "เสร็จแล้ว",
  overdue: "เกินกำหนด", // แสดงผลจากการคำนวณ
};

const STATUS_BADGE = {
  pending: "badge-soft-primary",
  in_progress: "badge-soft-secondary",
  completed: "badge-soft-success",
  overdue: "badge-soft-danger",
};

const ROLE_LABELS = {
  boss: "ผู้บริหาร",
  admin: "พนักงาน",
  abroad: "ต่างประเทศ",
  technician: "ช่าง",
  salesperson: "พนักงานขาย",
};

function fmtRole(role) {
  const r = String(role || "").trim();
  if (!r) return "";
  return ROLE_LABELS[r] || r; // ถ้าไม่รู้จักก็แสดงค่าดิบ
}

// ✅ แผนกสำหรับหน้า "รายละเอียดงาน"
function fmtDepartment(taskDepartment, assignees = []) {
  const dep = String(taskDepartment || "").trim();

  // รองรับข้อมูลเก่าใน DB
  if (dep === "multi") return "หลายแผนก";
  if (dep) return fmtRole(dep);

  // ✅ ถ้า dep ว่าง/NULL -> derive จากผู้รับมอบหมาย (ให้ตรงกับ logic ฝั่ง PHP)
  const roles = Array.from(
    new Set(
      (Array.isArray(assignees) ? assignees : [])
        .map((u) => String(u?.role || "").trim())
        .filter(Boolean)
    )
  );

  if (roles.length === 0) return "-";
  if (roles.length === 1) return fmtRole(roles[0]);

  const rolesNoBoss = roles.filter((r) => r !== "boss");
  if (rolesNoBoss.length === 0) return fmtRole("boss");
  if (rolesNoBoss.length === 1) return fmtRole(rolesNoBoss[0]);

  return "หลายแผนก";
}

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

function fmtDate(d) {
  if (!d) return "-";
  const dt = ymdToLocalDate(d);
  return dt ? dt.toLocaleDateString("th-TH") : String(d);
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

/**
 * แยก "สถานะจริง" กับ "overdue flag"
 * - baseStatus = pending/in_progress/completed (หรือค่าอื่นที่ไม่รู้จัก -> pending)
 * - overdueDays = จำนวนวันเกินกำหนด (0 ถ้าไม่เกิน หรือ completed)
 */
function computeDisplayStatus(task) {
  let base = String(task?.status || "pending");
  if (base === "overdue") base = "pending"; // กันข้อมูลเก่าใน DB
  if (!["pending", "in_progress", "completed"].includes(base)) base = "pending";

  if (base === "completed") {
    return { baseStatus: "completed", overdueDays: 0 };
  }

  const od = daysOverdue(task?.due_date);
  return { baseStatus: base, overdueDays: od };
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = String(text ?? "");
}

function show(el, yes) {
  if (!el) return;
  el.classList.toggle("d-none", !yes);
}

function setLoading(tbody) {
  if (!tbody) return;
  tbody.innerHTML = `
    <tr>
      <td colspan="8" class="py-4">
        <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
          <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
          <div>กำลังโหลดรายการงาน...</div>
        </div>
      </td>
    </tr>
  `;
}

function fmtBytes(n) {
  const x = Number(n || 0);
  if (!x) return "-";
  if (x < 1024) return `${x} B`;
  if (x < 1024 * 1024) return `${Math.round((x / 1024) * 10) / 10} KB`;
  return `${Math.round((x / 1024 / 1024) * 10) / 10} MB`;
}

async function openFilesModal(taskId, taskCode, title) {
  const meta = document.getElementById("filesMeta");
  const list = document.getElementById("filesList");
  const empty = document.getElementById("filesEmpty");

  if (meta) meta.textContent = `${taskCode || ""} • ${title || ""}`;
  if (list) list.innerHTML = "";
  if (empty) empty.classList.add("d-none");

  const url = new URL("api/task_files.php", document.baseURI);
  url.searchParams.set("task_id", String(taskId));

  const res = await fetch(url.toString(), { credentials: "same-origin" });
  const data = await res.json().catch(() => null);

  if (!res.ok || !data?.ok) throw new Error(data?.error || `HTTP_${res.status}`);

  const rows = Array.isArray(data.data) ? data.data : [];
  if (!rows.length) {
    if (empty) empty.classList.remove("d-none");
  } else {
    for (const f of rows) {
      const durl = new URL("api/task_file_download.php", document.baseURI);
      durl.searchParams.set("id", String(f.id));

      const a = document.createElement("a");
      a.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center";
      a.href = durl.toString();
      a.target = "_blank";
      a.rel = "noopener";

      const left = document.createElement("div");
      left.innerHTML = `
        <div class="fw-semibold"><i class="bi bi-file-earmark me-1"></i>${escapeHtml(f.original_name || "-")}</div>
        <div class="small text-muted">${escapeHtml(f.created_at || "")}</div>
      `;

      const right = document.createElement("div");
      right.className = "small text-muted";
      right.textContent = fmtBytes(f.file_size);

      a.appendChild(left);
      a.appendChild(right);
      list.appendChild(a);
    }
  }

  new bootstrap.Modal(document.getElementById("filesModal")).show();
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

function render(rows, selectedStatus = "") {
  const tbody = document.getElementById("taskTableBody");
  const empty = document.getElementById("taskEmpty");
  if (!tbody) return;

  const list = Array.isArray(rows) ? rows : [];
  setText("taskTotal", list.length);

  tbody.innerHTML = "";

  if (list.length === 0) {
    show(empty, true);
    tbody.innerHTML = `<tr><td colspan="8" class="py-3"></td></tr>`;
    return;
  }
  show(empty, false);

  for (const t of list) {
    const { baseStatus, overdueDays } = computeDisplayStatus(t);

    const badge = STATUS_BADGE[baseStatus] || "badge-soft-secondary";
    const statusText = STATUS_LABELS[baseStatus] || baseStatus || "-";

    const overdueLine =
      overdueDays > 0
        ? `<div class="small text-danger mt-1">เกินกำหนด ${overdueDays} วัน</div>`
        : ``;

    const assignedBy = t.assigned_by_name || "-";
    const assignees = t.assigned_to_names || t.assigned_to_name || "-";

    const tr = document.createElement("tr");

    const fileCount = Number(t.files_count || 0);
    const filesCell = fileCount > 0
      ? `<button class="btn btn-sm btn-outline-secondary"
            data-action="files"
            data-id="${escapeHtml(t.id)}"
            data-code="${escapeHtml(t.task_code || "")}"
            data-title="${escapeHtml(t.title || "")}">
        <i class="bi bi-paperclip me-1"></i>${fileCount}
     </button>`
      : `<span class="text-muted">-</span>`;


    tr.innerHTML = `
      <td class="text-nowrap fw-semibold">${escapeHtml(t.task_code || "")}</td>

      <td>
        <div class="fw-semibold">${escapeHtml(t.title || "")}</div>
        ${t.description
        ? `<div class="small text-muted text-truncate">${escapeHtml(String(t.description).slice(0, 100))}</div>`
        : `<div class="small text-muted">-</div>`
      }
      </td>

      <td class="text-nowrap">${escapeHtml(assignedBy)}</td>

      <td>
        <div class="small">${escapeHtml(assignees)}</div>
      </td>

     <td class="text-nowrap">
      <span class="badge ${badge}">${escapeHtml(statusText)}</span>
      ${overdueLine}
    </td>

    <td class="text-nowrap">${filesCell}</td>

      <td class="text-nowrap">${escapeHtml(fmtDate(t.due_date))}</td>
            <td class="text-end text-nowrap">
        <div class="d-inline-flex gap-2">

          <button class="btn btn-sm btn-outline-secondary"
                  data-action="detail"
                  data-id="${escapeHtml(t.id)}">
            <i class="bi bi-eye me-1"></i>ดูรายละเอียด
          </button>

          <a class="btn btn-sm btn-outline-primary" href="tasks/edit?id=${encodeURIComponent(t.id)}">
            <i class="bi bi-pencil-square me-1"></i>แก้ไข
          </a>

          <a class="btn btn-sm btn-outline-danger"
             href="tasks/delete?id=${encodeURIComponent(t.id)}"
             onclick="return confirm('ต้องการลบงานนี้หรือไม่?')">
            <i class="bi bi-trash me-1"></i>ลบ
          </a>
        </div>
      </td>

    `;
    tbody.appendChild(tr);
  }
}

function fmtDateTime(dtStr) {
  const s = String(dtStr || "").trim();
  if (!s) return "-";
  // MySQL: "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM:SS"
  const isoLike = s.includes(" ") ? s.replace(" ", "T") : s;
  const dt = new Date(isoLike);
  return Number.isNaN(dt.getTime()) ? s : dt.toLocaleString("th-TH");
}

function nl2brEscaped(text) {
  const safe = escapeHtml(text || "");
  return safe.replaceAll("\n", "<br>");
}

async function openTaskDetailModal(taskId) {
  const modalEl = document.getElementById("taskDetailModal");
  if (!modalEl) throw new Error("ไม่พบ taskDetailModal");

  const el = (id) => document.getElementById(id);

  // reset UI
  el("detailError")?.classList.add("d-none");
  el("detailBody")?.classList.add("d-none");
  el("detailLoading")?.classList.remove("d-none");

  // clear lists
  if (el("detailAssignees")) el("detailAssignees").innerHTML = "";
  if (el("detailFilesList")) el("detailFilesList").innerHTML = "";
  if (el("detailLogsList")) el("detailLogsList").innerHTML = "";
  el("detailFilesEmpty")?.classList.add("d-none");
  el("detailLogsEmpty")?.classList.add("d-none");

  // open modal first (ให้ UX ไหลลื่น)
  const modal = new bootstrap.Modal(modalEl);
  modal.show();

  try {
    const url = new URL("api/task_detail.php", document.baseURI);
    url.searchParams.set("id", String(taskId));

    const res = await fetch(url.toString(), { credentials: "same-origin" });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json?.ok) throw new Error(json?.error || `HTTP_${res.status}`);

    const data = json.data || {};
    const t = data.task || {};
    const assignees = Array.isArray(data.assignees) ? data.assignees : [];
    const files = Array.isArray(data.files) ? data.files : [];
    const logs = Array.isArray(data.logs) ? data.logs : [];

    // header
    const taskCode = t.task_code || "";
    const title = t.title || "";
    if (el("detailTitle")) el("detailTitle").textContent = "รายละเอียดงาน";
    if (el("detailSub")) el("detailSub").textContent = `${taskCode} • ${title}`;

    // compute display status
    const { baseStatus, overdueDays } = computeDisplayStatus(t);
    const badgeClass = STATUS_BADGE[baseStatus] || "badge-soft-secondary";
    const statusText = STATUS_LABELS[baseStatus] || baseStatus || "-";

    const badgeEl = el("detailStatusBadge");
    if (badgeEl) {
      badgeEl.className = `badge ${badgeClass}`;
      badgeEl.textContent = statusText;
    }

    const odEl = el("detailOverdue");
    if (odEl) {
      if (overdueDays > 0) {
        odEl.classList.remove("d-none");
        odEl.textContent = `เกินกำหนด ${overdueDays} วัน`;
      } else {
        odEl.classList.add("d-none");
        odEl.textContent = "";
      }
    }

    // main fields
    el("detailTaskCode").textContent = taskCode || "-";
    el("detailTaskTitle").textContent = title || "-";
    el("detailAssignedBy").textContent = t.assigned_by_name || "-";
    el("detailDepartment").textContent = fmtDepartment(t.department, assignees);
    el("detailDueDate").textContent = fmtDate(t.due_date);
    el("detailCreatedAt").textContent = fmtDateTime(t.created_at);
    el("detailUpdatedAt").textContent = fmtDateTime(t.updated_at);

    const descEl = el("detailDescription");
    if (descEl) {
      const desc = (t.description ?? "").toString();
      descEl.innerHTML = desc ? nl2brEscaped(desc) : `<span class="text-muted">-</span>`;
    }

    // assignees chips
    const assWrap = el("detailAssignees");
    if (assWrap) {
      if (!assignees.length) {
        assWrap.innerHTML = `<span class="text-muted small">-</span>`;
      } else {
        for (let i = 0; i < assignees.length; i++) {
          const u = assignees[i];

          const chip = document.createElement("span");
          chip.className = "badge bg-light text-dark border";
          chip.style.borderRadius = "999px";
          chip.style.padding = "8px 10px";

          const name = u.full_name || "-";
          const uname = u.username ? ` (${u.username})` : "";
          const role = u.role ? ` • ${fmtRole(u.role)}` : "";
          const main = i === 0 ? ` <span class="text-primary">(หลัก)</span>` : "";

          // ใช้ innerHTML เพราะมี span สีฟ้า
          chip.innerHTML = `${escapeHtml(name)}${escapeHtml(uname)}${escapeHtml(role)}${main}`;

          assWrap.appendChild(chip);
        }
      }
    }

    // files
    const filesList = el("detailFilesList");
    const filesEmpty = el("detailFilesEmpty");
    if (filesList) filesList.innerHTML = "";
    if (!files.length) {
      filesEmpty?.classList.remove("d-none");
    } else {
      filesEmpty?.classList.add("d-none");
      for (const f of files) {
        const durl = new URL("api/task_file_download.php", document.baseURI);
        durl.searchParams.set("id", String(f.id));

        const a = document.createElement("a");
        a.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-center";
        a.href = durl.toString();
        a.target = "_blank";
        a.rel = "noopener";

        const left = document.createElement("div");
        left.innerHTML = `
          <div class="fw-semibold"><i class="bi bi-file-earmark me-1"></i>${escapeHtml(f.original_name || "-")}</div>
          <div class="small text-muted">${escapeHtml(f.created_at || "")}</div>
        `;

        const right = document.createElement("div");
        right.className = "small text-muted";
        right.textContent = fmtBytes(f.file_size);

        a.appendChild(left);
        a.appendChild(right);
        filesList.appendChild(a);
      }
    }

    // logs
    const logsList = el("detailLogsList");
    const logsEmpty = el("detailLogsEmpty");
    if (logsList) logsList.innerHTML = "";
    if (!logs.length) {
      logsEmpty?.classList.remove("d-none");
    } else {
      logsEmpty?.classList.add("d-none");
      for (const lg of logs) {
        const item = document.createElement("div");
        item.className = "list-group-item";

        const who = lg.user_name || "-";
        const oldSt = STATUS_LABELS[String(lg.old_status || "")] || (lg.old_status || "-");
        const newSt = STATUS_LABELS[String(lg.new_status || "")] || (lg.new_status || "-");
        const note = lg.note ? String(lg.note) : "";
        const when = fmtDateTime(lg.created_at);

        item.innerHTML = `
          <div class="d-flex justify-content-between gap-2">
            <div class="fw-semibold">${escapeHtml(who)}</div>
            <div class="small text-muted">${escapeHtml(when)}</div>
          </div>
          <div class="small">สถานะ: <span class="text-muted">${escapeHtml(oldSt)}</span> → <span class="fw-semibold">${escapeHtml(newSt)}</span></div>
          ${note ? `<div class="small text-muted mt-1">${nl2brEscaped(note)}</div>` : ""}
        `;
        logsList.appendChild(item);
      }
    }

    // edit btn
    const editBtn = el("detailEditBtn");
    if (editBtn) editBtn.href = `tasks/edit?id=${encodeURIComponent(taskId)}`;

    // show body
    el("detailLoading")?.classList.add("d-none");
    el("detailBody")?.classList.remove("d-none");
  } catch (err) {
    console.error(err);
    el("detailLoading")?.classList.add("d-none");
    el("detailBody")?.classList.add("d-none");
    const box = el("detailError");
    if (box) {
      box.classList.remove("d-none");
      box.textContent = err?.message || "โหลดรายละเอียดไม่สำเร็จ";
    }
  }
}

async function load() {
  const box = document.getElementById("tasksError");
  const tbody = document.getElementById("taskTableBody");
  if (box) box.classList.add("d-none");
  setLoading(tbody);

  const keyword = document.getElementById("keyword")?.value?.trim() || "";
  const statusSel = document.getElementById("status")?.value || ""; // pending/in_progress/completed/overdue/''

  // ✅ ถ้าเลือก overdue ให้ดึงแบบ "ไม่กรอง status ที่ฝั่ง API" แล้วค่อยกรองเองจาก due_date
  const apiStatus =
    statusSel === "pending" || statusSel === "in_progress" || statusSel === "completed"
      ? statusSel
      : ""; // overdue => ส่งว่าง เพื่อให้ได้รายการทั้งหมด

  try {
    const res = await TaskService.list({ keyword, status: apiStatus });
    let list = Array.isArray(res?.data) ? res.data : [];

    // ✅ กรอง overdue แบบคำนวณ (รวมกรณี DB เคยเป็น overdue)
    if (statusSel === "overdue") {
      list = list.filter((t) => computeDisplayStatus(t).overdueDays > 0);
    }

    render(list, statusSel);
  } catch (err) {
    console.error(err);
    render([], statusSel);
    if (box) {
      box.classList.remove("d-none");
      box.textContent = err?.message || "โหลดข้อมูลไม่สำเร็จ";
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  // submit form
  document.getElementById("taskSearchForm")?.addEventListener("submit", (e) => {
    e.preventDefault();
    load();
  });

  // กดค้นหา
  document.getElementById("btnSearch")?.addEventListener("click", (e) => {
    e.preventDefault();
    load();
  });

  document.getElementById("taskTableBody")?.addEventListener("click", async (e) => {
    // 1) files
    const btnFiles = e.target.closest("button[data-action='files']");
    if (btnFiles) {
      const id = btnFiles.getAttribute("data-id");
      const code = btnFiles.getAttribute("data-code") || "";
      const title = btnFiles.getAttribute("data-title") || "";
      try {
        await openFilesModal(id, code, title);
      } catch (err) {
        alert(err?.message || "โหลดไฟล์แนบไม่สำเร็จ");
      }
      return;
    }

    // 2) detail
    const btnDetail = e.target.closest("button[data-action='detail']");
    if (btnDetail) {
      const id = btnDetail.getAttribute("data-id");
      if (!id) return;
      await openTaskDetailModal(id);
      return;
    }
  });

  load();
});
