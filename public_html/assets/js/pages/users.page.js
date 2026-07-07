// public/assets/js/pages/users.page.js
import { UserService } from "../services/user.service.js";

const ROLE_LABELS = {
  boss: "ผู้บริหาร",
  admin: "พนักงาน",
  abroad: "พนักงานต่างประเทศ",
  technician: "ช่างเทคนิค",
  salesperson: "พนักงานขาย",
};

const ROLE_BADGE = {
  boss: "badge-soft-primary",
  admin: "badge-soft-secondary",
};

// ---- sort state (ID) ----
let __USERS_ROWS__ = [];
let __SORT_ID_DIR__ = "desc"; // ค่าเริ่มต้น: มาก -> น้อย

function sortUsersById(list, dir) {
  const sign = dir === "asc" ? 1 : -1;
  return [...(Array.isArray(list) ? list : [])].sort((a, b) => {
    const ai = Number(a?.id ?? 0);
    const bi = Number(b?.id ?? 0);
    return (ai - bi) * sign;
  });
}

function updateSortIdIcon() {
  const icon = document.getElementById("sortIdIcon");
  const th = document.getElementById("thSortId");
  if (!icon) return;

  if (__SORT_ID_DIR__ === "asc") {
    icon.className = "bi bi-caret-up-fill ms-1 small text-muted";
    if (th) th.title = "เรียง ID: น้อย → มาก (กดเพื่อสลับ)";
  } else {
    icon.className = "bi bi-caret-down-fill ms-1 small text-muted";
    if (th) th.title = "เรียง ID: มาก → น้อย (กดเพื่อสลับ)";
  }
}

function applySortAndRender() {
  const sorted = sortUsersById(__USERS_ROWS__, __SORT_ID_DIR__);
  render(sorted);
  updateSortIdIcon();
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = String(text ?? "");
}

function show(el, yes) {
  if (!el) return;
  el.classList.toggle("d-none", !yes);
}

function showError(msg) {
  const box = document.getElementById("usersError");
  if (!box) return;
  box.classList.remove("d-none");
  box.textContent = msg || "เกิดข้อผิดพลาด";
}

function hideError() {
  const box = document.getElementById("usersError");
  if (!box) return;
  box.classList.add("d-none");
  box.textContent = "";
}

function setLoading(tbody) {
  if (!tbody) return;
  tbody.innerHTML = `
    <tr>
      <td colspan="6" class="py-4">
        <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
          <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
          <div>กำลังโหลดรายชื่อพนักงาน...</div>
        </div>
      </td>
    </tr>
  `;
}

function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function canManage(user) {
  // ถ้าหน้าคุณมี current user ส่งมาทาง window (แนะนำ)
  // window.__CURRENT_USER_ROLE = "boss"
  // window.__CURRENT_USER_ID = 1
  const meRole = String(window.__CURRENT_USER_ROLE || "").toLowerCase();
  const meId = Number(window.__CURRENT_USER_ID || 0);

  const id = Number(user?.id || 0);
  const role = String(user?.role || "").toLowerCase();

  if (meRole !== "boss") return { ok: false, reason: "ต้องเป็นผู้บริหารเท่านั้น" };
  if (id === meId) return { ok: false, reason: "ไม่สามารถจัดการบัญชีของตัวเองได้" };
  if (role === "boss") return { ok: false, reason: "ไม่สามารถจัดการบัญชีผู้บริหารได้" };

  return { ok: true, reason: "" };
}

function render(rows) {
  const tbody = document.getElementById("usersBody");
  const empty = document.getElementById("usersEmpty");
  if (!tbody) return;

  const list = Array.isArray(rows) ? rows : [];
  setText("userTotal", list.length);

  tbody.innerHTML = "";

  if (list.length === 0) {
    show(empty, true);
    tbody.innerHTML = `<tr><td colspan="6" class="py-3"></td></tr>`;
    return;
  }

  show(empty, false);

  for (const u of list) {
    const isActive = (u.is_active == 1 || u.is_active === true);

    const roleKey = String(u.role || "").toLowerCase();
    const roleText = ROLE_LABELS[roleKey] || (u.role || "-");
    const roleBadge = ROLE_BADGE[roleKey] || "badge-soft-secondary";

    const statusText = isActive ? "ใช้งาน" : "ปิด";
    const statusBadge = isActive ? "badge-soft-success" : "badge-soft-danger";

    const manage = canManage(u);
    const disabledAttr = manage.ok ? "" : "disabled";
    const titleAttr = manage.ok ? "" : `title="${escapeHtml(manage.reason)}"`;

    const toggleText = isActive ? "ปิดสถานะ" : "เปิดสถานะ";
    const toggleIcon = isActive ? "bi-slash-circle" : "bi-check-circle";

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="text-nowrap fw-semibold">${escapeHtml(u.id ?? "")}</td>

      <td>
        <div class="fw-semibold">${escapeHtml(u.username || "")}</div>
        <div class="small text-muted">User</div>
      </td>

      <td>${escapeHtml(u.full_name || "")}</td>

      <td class="text-nowrap">
        <span class="badge ${roleBadge}">${escapeHtml(roleText)}</span>
      </td>

      <td class="text-nowrap">
        <span class="badge ${statusBadge}">${escapeHtml(statusText)}</span>
      </td>

      <td class="text-end text-nowrap">
        <div class="d-inline-flex gap-2">

        <button
          type="button"
          class="btn btn-sm btn-outline-success"
          data-action="edit"
          data-id="${escapeHtml(u.id ?? "")}"
          data-name="${escapeHtml(u.full_name || u.username || "")}"
          data-full_name="${escapeHtml(u.full_name || "")}"
          data-role="${escapeHtml(String(u.role || "").toLowerCase())}"
          ${disabledAttr}
          ${titleAttr}
        >
          <i class="bi bi-pencil me-1"></i>แก้ไข
        </button>

        <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            data-action="password"
            data-id="${escapeHtml(u.id ?? "")}"
            data-name="${escapeHtml(u.full_name || u.username || "")}"
            ${disabledAttr}
            ${titleAttr}
          >
            <i class="bi bi-key me-1"></i>เปลี่ยนรหัส
          </button>

         <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            data-action="toggle"
            data-id="${escapeHtml(u.id ?? "")}"
            data-name="${escapeHtml(u.full_name || u.username || "")}"
            data-active="${isActive ? 1 : 0}"
            ${disabledAttr}
            ${titleAttr}
          >
            <i class="bi ${toggleIcon} me-1"></i>${toggleText}
          </button>

          <button
            type="button"
            class="btn btn-sm btn-outline-danger"
            data-action="delete"
            data-id="${escapeHtml(u.id ?? "")}"
            data-name="${escapeHtml(u.full_name || u.username || "")}"
            ${disabledAttr}
            ${titleAttr}
          >
            <i class="bi bi-trash me-1"></i>ลบ
          </button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  }
}

async function loadUsers() {
  hideError();
  const tbody = document.getElementById("usersBody");
  setLoading(tbody);

  try {
    const res = await UserService.list();
    __USERS_ROWS__ = Array.isArray(res?.data) ? res.data : [];
    applySortAndRender();
  } catch (err) {
    console.error(err);
    __USERS_ROWS__ = [];
    render([]);
    showError(err?.message || "โหลดข้อมูลไม่สำเร็จ");
  }
}

document.addEventListener("DOMContentLoaded", async () => {
  // ----- Delete Modal wiring -----
  const modalEl = document.getElementById("deleteUserModal");
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

  const delIdEl = document.getElementById("delUserId");
  const delNameEl = document.getElementById("delUserName");
  const btnConfirmDelete = document.getElementById("btnConfirmDelete");
  // confirm password change in modal
  const btnConfirmPassword = document.getElementById("btnConfirmPassword");

  // ----- Edit Modal wiring -----
  const editModalEl = document.getElementById("editUserModal");
  const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

  const editUserIdEl = document.getElementById("editUserId");
  const editUserNameEl = document.getElementById("editUserName");
  const editFullNameEl = document.getElementById("editFullName");
  const editRoleEl = document.getElementById("editRole");
  const btnConfirmEditUser = document.getElementById("btnConfirmEditUser");

  // ---- click to sort by ID ----
  const thSortId = document.getElementById("thSortId");
  if (thSortId) {
    thSortId.style.cursor = "pointer";

    const toggleSort = () => {
      __SORT_ID_DIR__ = __SORT_ID_DIR__ === "desc" ? "asc" : "desc";
      applySortAndRender();
    };

    thSortId.addEventListener("click", toggleSort);
    thSortId.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        toggleSort();
      }
    });
  }

  updateSortIdIcon();

  if (btnConfirmEditUser) {
    btnConfirmEditUser.addEventListener("click", async () => {
      const id = Number(document.getElementById("editUserId")?.value || 0);
      const fullName = String(document.getElementById("editFullName")?.value || "").trim();

      if (!id) return;
      if (!fullName) return showError("กรุณากรอกชื่อ-นามสกุล");

      try {
        hideError();
        btnConfirmEditUser.disabled = true;

        await UserService.update(id, { full_name: fullName });

        editModal?.hide();
        await loadUsers();
        alert("บันทึกสำเร็จ");
      } catch (err) {
        showError(err?.message || "บันทึกไม่สำเร็จ");
      } finally {
        btnConfirmEditUser.disabled = false;
      }
    });
  }

  if (btnConfirmPassword) {
    btnConfirmPassword.addEventListener("click", async () => {
      const id = Number(document.getElementById("pwUserId")?.value || 0);
      const pw1 = document.getElementById("pw1")?.value || "";
      const pw2 = document.getElementById("pw2")?.value || "";

      if (!id) return;
      if (pw1.length < 8) return showError("รหัสผ่านต้องอย่างน้อย 8 ตัวอักษร");
      if (pw1 !== pw2) return showError("รหัสผ่านไม่ตรงกัน");

      try {
        hideError();
        btnConfirmPassword.disabled = true;

        await UserService.setPassword(id, pw1);

        const pwModalEl = document.getElementById("passwordModal");
        bootstrap.Modal.getInstance(pwModalEl)?.hide();

        alert("เปลี่ยนรหัสผ่านสำเร็จ");
      } catch (err) {
        console.error(err);
        showError(err?.message || "เปลี่ยนรหัสผ่านไม่สำเร็จ");
      } finally {
        btnConfirmPassword.disabled = false;
      }
    });
  }


  // event delegation on table
  const tbody = document.getElementById("usersBody");
  if (tbody) {
    tbody.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;

      const action = btn.dataset.action;
      const id = Number(btn.dataset.id || 0);
      const name = btn.dataset.name || "";

      if (!id) return;

      // edit (open modal)
      if (action === "edit") {
        const editRoleTextEl = document.getElementById("editRoleText");
        const roleKey = (btn.dataset.role || "").toLowerCase();
        const roleText = ROLE_LABELS[roleKey] || (btn.dataset.role || "-");
        if (editRoleTextEl) editRoleTextEl.textContent = roleText;

        if (!editModal) {
          showError("ไม่พบหน้าต่างแก้ไขข้อมูล");
          return;
        }

        const fullName = btn.dataset.full_name || "";
        const role = (btn.dataset.role || "admin").toLowerCase();

        if (editUserIdEl) editUserIdEl.value = String(id);
        if (editUserNameEl) editUserNameEl.textContent = name || "-";
        if (editFullNameEl) editFullNameEl.value = fullName;

        // set role ถ้ามี option ตรง
        if (editRoleEl) {
          const has = Array.from(editRoleEl.options).some((o) => o.value === role);
          editRoleEl.value = has ? role : "admin";
        }

        editModal.show();
        return;
      }

      // password (open modal)
      if (action === "password") {
        const pwModalEl = document.getElementById("passwordModal");
        const pwModal = pwModalEl ? new bootstrap.Modal(pwModalEl) : null;

        const pwUserId = document.getElementById("pwUserId");
        const pwUserName = document.getElementById("pwUserName");
        const pw1 = document.getElementById("pw1");
        const pw2 = document.getElementById("pw2");

        if (!pwModal) {
          showError("ไม่พบหน้าต่างเปลี่ยนรหัสผ่าน");
          return;
        }

        if (pwUserId) pwUserId.value = String(id);
        if (pwUserName) pwUserName.textContent = name || "-";
        if (pw1) pw1.value = "";
        if (pw2) pw2.value = "";
        pwModal.show();
      }

      // toggle
      if (action === "toggle") {
        const current = Number(btn.dataset.active || 0);
        const to = current ? 0 : 1; // ค่าใหม่

        const ok = confirm(`ต้องการ${to ? "เปิด" : "ปิด"}สถานะผู้ใช้ "${name}" ใช่ไหม?`);
        if (!ok) return;

        try {
          hideError();
          btn.disabled = true;
          await UserService.toggle(id, to); // ส่งค่าใหม่ไปด้วย
          await loadUsers();
        } catch (err) {
          console.error(err);
          showError(err?.message || "เปลี่ยนสถานะไม่สำเร็จ");
        } finally {
          btn.disabled = false;
        }
      }

      // delete (open modal)
      if (action === "delete") {
        if (!modal) {
          // fallback ถ้าไม่มี modal ใน HTML
          const ok = confirm(`ต้องการลบบัญชี "${name}" ใช่ไหม?`);
          if (!ok) return;

          try {
            hideError();
            btn.disabled = true;
            await UserService.delete(id); // ต้องมีใน service
            await loadUsers();
          } catch (err) {
            console.error(err);
            showError(err?.message || "ลบบัญชีไม่สำเร็จ");
          } finally {
            btn.disabled = false;
          }
          return;
        }

        if (delIdEl) delIdEl.value = String(id);
        if (delNameEl) delNameEl.textContent = name || "-";
        modal.show();
      }
    });
  }

  // confirm delete in modal
  if (btnConfirmDelete) {
    btnConfirmDelete.addEventListener("click", async () => {
      const id = Number(delIdEl?.value || 0);
      if (!id) return;

      try {
        hideError();
        btnConfirmDelete.disabled = true;
        await UserService.delete(id);
        modal?.hide();
        await loadUsers();
      } catch (err) {
        console.error(err);
        showError(err?.message || "ลบบัญชีไม่สำเร็จ");
      } finally {
        btnConfirmDelete.disabled = false;
      }
    });
  }

  await loadUsers();
});
