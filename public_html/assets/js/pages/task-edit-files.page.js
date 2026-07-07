// public/assets/js/pages/task-edit-files.page.js
(() => {
  const taskId = Number(window.__TASK_ID__ || 0);
  if (!taskId) return;

  const tbody = document.getElementById("taskFilesTbody");
  const empty = document.getElementById("taskFilesEmpty");
  const errBox = document.getElementById("taskFilesError");
  const btnReload = document.getElementById("btnReloadFiles");

  const esc = (v) =>
    String(v ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  function show(el, yes) {
    if (!el) return;
    el.classList.toggle("d-none", !yes);
  }

  function fmtBytes(n) {
    const x = Number(n || 0);
    if (!x) return "-";
    if (x < 1024) return `${x} B`;
    if (x < 1024 * 1024) return `${Math.round((x / 1024) * 10) / 10} KB`;
    return `${Math.round((x / 1024 / 1024) * 10) / 10} MB`;
  }

  function setError(msg) {
    if (!errBox) return;
    errBox.textContent = String(msg || "เกิดข้อผิดพลาด");
    show(errBox, true);
  }

  function clearError() {
    show(errBox, false);
    if (errBox) errBox.textContent = "";
  }

  async function fetchFiles() {
    const url = new URL("api/task_files.php", document.baseURI);
    url.searchParams.set("task_id", String(taskId));

    const res = await fetch(url.toString(), { credentials: "same-origin" });
    const data = await res.json().catch(() => null);

    if (!res.ok || !data?.ok) throw new Error(data?.error || `HTTP_${res.status}`);
    return Array.isArray(data.data) ? data.data : [];
  }

  async function deleteFile(fileId) {
    const url = new URL("api/task_file_delete.php", document.baseURI);
    url.searchParams.set("id", String(fileId));

    const res = await fetch(url.toString(), {
      method: "POST",
      credentials: "same-origin",
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) throw new Error(data?.error || `HTTP_${res.status}`);
    return true;
  }

  async function renameFile(fileId, newName) {
    const url = new URL("api/task_file_rename.php", document.baseURI);

    const res = await fetch(url.toString(), {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: fileId, original_name: newName }),
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) throw new Error(data?.error || `HTTP_${res.status}`);
    return true;
  }

  function render(files) {
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!files.length) {
      show(empty, true);
      tbody.innerHTML = `<tr><td colspan="4" class="py-3"></td></tr>`;
      return;
    }
    show(empty, false);

    for (const f of files) {
      const downloadUrl = new URL("api/task_file_download.php", document.baseURI);
      downloadUrl.searchParams.set("id", String(f.id));

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>
          <div class="fw-semibold text-truncate" style="max-width: 520px;">
            <a href="${esc(downloadUrl.toString())}" target="_blank" rel="noopener">
              <i class="bi bi-file-earmark me-1"></i>${esc(f.original_name || "-")}
            </a>
          </div>
          <div class="small text-muted">${esc(f.stored_name || f.stored_path || "")}</div>
        </td>

        <td class="text-nowrap">${esc(fmtBytes(f.file_size))}</td>
        <td class="text-nowrap">${esc(f.created_at || "")}</td>

        <td class="text-end text-nowrap">
          <div class="d-inline-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary"
              data-action="rename" data-id="${esc(f.id)}" data-name="${esc(f.original_name || "")}">
              <i class="bi bi-pencil me-1"></i>เปลี่ยนชื่อ
            </button>

            <button type="button" class="btn btn-sm btn-outline-danger"
              data-action="delete" data-id="${esc(f.id)}" data-name="${esc(f.original_name || "")}">
              <i class="bi bi-trash me-1"></i>ลบ
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    }
  }

  async function load() {
    clearError();
    try {
      const files = await fetchFiles();
      render(files);
    } catch (e) {
      render([]);
      setError(e?.message || "โหลดไฟล์แนบไม่สำเร็จ");
    }
  }

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;

    const action = btn.getAttribute("data-action");
    const fileId = Number(btn.getAttribute("data-id") || 0);
    const name = btn.getAttribute("data-name") || "";

    if (!fileId) return;

    try {
      if (action === "delete") {
        const ok = confirm(`ต้องการลบไฟล์นี้หรือไม่?\n\n${name}`);
        if (!ok) return;

        btn.disabled = true;
        await deleteFile(fileId);
        await load();
        return;
      }

      if (action === "rename") {
        const newName = prompt("เปลี่ยนชื่อไฟล์", name);
        if (newName == null) return;

        const trimmed = String(newName).trim();
        if (!trimmed) {
          alert("ชื่อไฟล์ห้ามว่าง");
          return;
        }

        btn.disabled = true;
        await renameFile(fileId, trimmed);
        await load();
        return;
      }
    } catch (err) {
      alert(err?.message || "ทำรายการไม่สำเร็จ");
    } finally {
      btn.disabled = false;
    }
  });

  btnReload?.addEventListener("click", (e) => {
    e.preventDefault();
    load();
  });

  load();
})();
