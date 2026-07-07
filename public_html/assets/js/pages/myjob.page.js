// public/assets/js/pages/myjob.page.js
import { TaskService } from "../services/task.service.js";

const STATUS_LABELS = {
  pending: "รอดำเนินการ",
  in_progress: "กำลังดำเนินการ",
  completed: "เสร็จแล้ว",
  overdue: "เกินกำหนด", // ใช้สำหรับแสดงผล (คำนวณเอง)
};

const STATUS_ORDER = ["pending", "in_progress", "completed"];
const STATUS_INDEX = Object.fromEntries(STATUS_ORDER.map((s, i) => [s, i]));

function normalizeStatus(st) {
  st = String(st || "pending");
  if (st === "overdue") return "in_progress";
  if (!Object.prototype.hasOwnProperty.call(STATUS_INDEX, st)) return "pending";
  return st;
}

/**
 * จำกัดตัวเลือกใน select: เลือกได้เฉพาะ "สถานะเดิม" หรือ "สถานะถัดไป"
 */
function applyStatusOptions(currentStatus) {
  const sel = document.getElementById("modalStatus");
  if (!sel) return;

  const cur = normalizeStatus(currentStatus);
  const curIdx = STATUS_INDEX[cur] ?? 0;

  const allowed = new Set([cur]);
  if (curIdx + 1 < STATUS_ORDER.length) allowed.add(STATUS_ORDER[curIdx + 1]);

  Array.from(sel.options).forEach((opt) => {
    opt.hidden = !allowed.has(opt.value);
    opt.disabled = !allowed.has(opt.value);
  });

  sel.value = cur;
  sel.disabled = (cur === "completed");
}

const STATUS_BADGE = {
  pending: "badge-soft-primary",
  in_progress: "badge-soft-secondary",
  completed: "badge-soft-success",
  overdue: "badge-soft-danger",
};

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

function computeDisplayStatus(task) {
  let base = String(task?.status || "pending");
  if (base === "overdue") base = "pending";
  if (!["pending", "in_progress", "completed"].includes(base)) base = "pending";

  if (base === "completed") return { baseStatus: "completed", overdueDays: 0 };

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
          <div>กำลังโหลดงานของคุณ...</div>
        </div>
      </td>
    </tr>
  `;
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

function fmtBytes(n) {
  const x = Number(n || 0);
  if (!x) return "-";
  if (x < 1024) return `${x} B`;
  if (x < 1024 * 1024) return `${Math.round((x / 1024) * 10) / 10} KB`;
  return `${Math.round((x / 1024 / 1024) * 10) / 10} MB`;
}

// ✅ เพิ่มเหมือนหน้า boss
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
      a.className =
        "list-group-item list-group-item-action d-flex justify-content-between align-items-center";
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

  const modalEl = document.getElementById("filesModal");
  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function fmtDateTime(dtStr) {
  const s = String(dtStr || "").trim();
  if (!s) return "-";
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

  // reset
  el("detailError")?.classList.add("d-none");
  el("detailBody")?.classList.add("d-none");
  el("detailLoading")?.classList.remove("d-none");

  if (el("detailAssignees")) el("detailAssignees").innerHTML = "";
  if (el("detailFilesList")) el("detailFilesList").innerHTML = "";
  if (el("detailLogsList")) el("detailLogsList").innerHTML = "";
  el("detailFilesEmpty")?.classList.add("d-none");
  el("detailLogsEmpty")?.classList.add("d-none");

  bootstrap.Modal.getOrCreateInstance(modalEl).show();

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
    if (el("detailSub")) el("detailSub").textContent = `${taskCode} • ${title}`;

    // status + overdue
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

    // fields
    el("detailTaskCode").textContent = taskCode || "-";
    el("detailTaskTitle").textContent = title || "-";
    el("detailAssignedBy").textContent = t.assigned_by_name || "-";
    el("detailDepartment").textContent = t.department || "-";
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
        for (const u of assignees) {
          const chip = document.createElement("span");
          chip.className = "badge bg-light text-dark border";
          chip.style.borderRadius = "999px";
          chip.style.padding = "8px 10px";
          const name = u.full_name || "-";
          const uname = u.username ? ` (${u.username})` : "";
          const role = u.role ? ` • ${u.role}` : "";
          chip.textContent = `${name}${uname}${role}`;
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

function render(rows) {
  const tbody = document.getElementById("myJobBody");
  const empty = document.getElementById("myJobEmpty");
  if (!tbody) return;

  const list = Array.isArray(rows) ? rows : [];
  setText("myJobTotal", list.length);

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

    const tr = document.createElement("tr");

    const fileCount = Number(t.files_count || 0);
    const filesCell =
      fileCount > 0
        ? `<button type="button" class="btn btn-link p-0 small"
              data-action="files"
              data-id="${escapeHtml(t.id)}"
              data-code="${escapeHtml(t.task_code || "")}"
              data-title="${escapeHtml(t.title || "")}">
            <i class="bi bi-paperclip me-1"></i>${fileCount} ไฟล์แนบ
           </button>`
        : `<span class="text-muted">-</span>`;

    const assignedBy =
      t.assigned_by_name ||
      t.assigned_by_full_name ||
      t.assigned_by_username ||
      "-";

    tr.innerHTML = `
          <td class="text-nowrap fw-semibold">${escapeHtml(t.task_code || "")}</td>

          <td>
            <div class="fw-semibold">${escapeHtml(t.title || "")}</div>
            ${t.description
        ? `<div class="small text-muted text-truncate">${escapeHtml(String(t.description).slice(0, 100))}</div>`
        : `<div class="small text-muted">-</div>`
      }
          </td>

          <td class="text-nowrap">
            <div class="fw-semibold">${escapeHtml(assignedBy)}</div>
          </td>

          <td class="text-nowrap">
            <span class="badge ${badge}">${escapeHtml(statusText)}</span>
            ${overdueLine}
          </td>

          <td class="text-nowrap">${escapeHtml(fmtDate(t.due_date))}</td>

          <td>
            <div class="small text-muted text-truncate">${escapeHtml(t.remark ? String(t.remark) : "-")}</div>
          </td>

          <td class="text-nowrap">${filesCell}</td>

          <td class="text-end text-nowrap">
            <div class="d-inline-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary"
                      data-action="detail"
                      data-id="${escapeHtml(t.id)}">
                <i class="bi bi-eye me-1"></i>ดู
              </button>

              <button type="button" class="btn btn-sm btn-outline-primary"
                      data-action="change"
                      data-id="${escapeHtml(t.id)}"
                      data-status="${escapeHtml(t.status)}">
                <i class="bi bi-arrow-repeat me-1"></i>อัปเดต
              </button>
            </div>
          </td>
        `;
    tbody.appendChild(tr);
  }
}

async function load() {
  const box = document.getElementById("myjobError");
  const tbody = document.getElementById("myJobBody");
  if (box) box.classList.add("d-none");
  setLoading(tbody);

  try {
    const res = await TaskService.list({ mine: 1 });
    render(res?.data || []);
  } catch (err) {
    console.error(err);
    render([]);
    if (box) {
      box.classList.remove("d-none");
      box.textContent = err?.message || "โหลดข้อมูลไม่สำเร็จ";
    }
  }
}

/**
 * เรียก API myjob_update_status.php (multipart)
 */
async function updateStatusViaEmailApi(taskId, status, note, files) {
  const url = new URL("api/myjob_update_status.php", document.baseURI);

  const fd = new FormData();
  fd.append("task_id", String(Number(taskId)));
  fd.append("status", String(status || "pending"));
  fd.append("note", String(note || ""));

  const arr = Array.from(files || []);
  for (const f of arr) fd.append("files[]", f);

  const res = await fetch(url.toString(), {
    method: "POST",
    credentials: "same-origin",
    body: fd,
  });

  let data = null;
  try { data = await res.json(); } catch (_) { }

  if (!res.ok || !data?.ok) {
    throw new Error(data?.error || `HTTP_${res.status}`);
  }
  return data;
}

function setBtnLoading(btn, on, text = "กำลังบันทึก...") {
  if (!btn) return;
  if (on) {
    btn.dataset.__oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `
      <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
      ${text}
    `;
  } else {
    btn.disabled = false;
    if (btn.dataset.__oldHtml) btn.innerHTML = btn.dataset.__oldHtml;
    delete btn.dataset.__oldHtml;
  }
}

async function saveStatus(taskId) {
  let status = document.getElementById("modalStatus")?.value || "pending";
  const noteEl = document.getElementById("modalNote");
  const note = noteEl?.value || "";
  const fileInput = document.getElementById("modalFiles");
  const files = fileInput?.files;
  const btn = document.getElementById("modalSaveBtn");

  if (status === "overdue") status = "in_progress";

  if (status === "completed" && String(note).trim() === "") {
    alert("ถ้าปรับเป็น “เสร็จแล้ว” กรุณาใส่บันทึกสรุปผลลัพธ์ก่อนบันทึก");
    noteEl?.focus();
    return;
  }

  showGlobalOverlay("กำลังบันทึกสถานะ...");

  try {
    await updateStatusViaEmailApi(taskId, status, note, files);
    bootstrap.Modal.getInstance(document.getElementById("statusModal"))?.hide();

    if (fileInput) fileInput.value = "";
    if (noteEl) noteEl.value = "";

    await load();
  } catch (err) {
    alert(err?.message || "อัปเดตไม่สำเร็จ");
  } finally {
    hideGlobalOverlay();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  load();
  document.getElementById("btnRefreshMyJob")?.addEventListener("click", load);

  // ✅ รวม event delegation ตัวเดียว จัดการทั้ง change และ files
  document.getElementById("myJobBody")?.addEventListener("click", async (e) => {
    // detail
    const dbtn = e.target.closest("button[data-action='detail']");
    if (dbtn) {
      const id = dbtn.getAttribute("data-id");
      if (id) await openTaskDetailModal(id);
      return;
    }

    // files
    const fbtn = e.target.closest("button[data-action='files']");
    if (fbtn) {
      const id = fbtn.getAttribute("data-id");
      const code = fbtn.getAttribute("data-code") || "";
      const title = fbtn.getAttribute("data-title") || "";
      try {
        await openFilesModal(id, code, title);
      } catch (err) {
        alert(err?.message || "โหลดไฟล์แนบไม่สำเร็จ");
      }
      return;
    }

    // change status
    const btn = e.target.closest("button[data-action='change']");
    if (!btn) return;

    const id = btn.getAttribute("data-id") || "";
    let st = btn.getAttribute("data-status") || "pending";
    st = normalizeStatus(st);

    document.getElementById("modalTaskId").value = id;
    document.getElementById("modalNote").value = "";
    const hiddenOld = document.getElementById("modalOldStatus");
    if (hiddenOld) hiddenOld.value = st;

    applyStatusOptions(st);

    bootstrap.Modal.getOrCreateInstance(document.getElementById("statusModal")).show();
  });

  document.getElementById("modalSaveBtn")?.addEventListener("click", () => {
    const id = document.getElementById("modalTaskId")?.value;
    if (id) saveStatus(id);
  });

  const getOpenId = () => {
    const w = Number(window.__OPEN_TASK_ID__ || 0);
    if (Number.isFinite(w) && w > 0) return w;

    try {
      const url = new URL(window.location.href);
      const q = Number(url.searchParams.get("open") || 0);
      return Number.isFinite(q) ? q : 0;
    } catch (_) {
      return 0;
    }
  };

  const openId = getOpenId();
  if (openId > 0) {
    setTimeout(() => {
      openTaskDetailModal(openId);
    }, 100);

    window.__OPEN_TASK_ID__ = 0;
    try {
      const url = new URL(window.location.href);
      url.searchParams.delete("open");
      url.searchParams.delete("from");
      const newUrl = url.pathname + (url.search ? url.search : "") + (url.hash ? url.hash : "");
      history.replaceState({}, "", newUrl);
    } catch (_) { }
  }
});
