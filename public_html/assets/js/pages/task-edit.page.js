// public/assets/js/pages/task-edit.page.js
document.addEventListener("DOMContentLoaded", () => {
    const deptFilter = document.getElementById("assigneeDeptFilter");
    const searchBox = document.getElementById("assigneeSearch");

    const assigneeSelect = document.getElementById("assigneeSelect");
    const btnAdd = document.getElementById("btnAddAssignee");
    const listEl = document.getElementById("assigneeList");
    const inputsEl = document.getElementById("assigneeInputs");
    const deptBadge = document.getElementById("autoDeptBadge");

    const TASK_ID = Number(window.__TASK_ID__ || 0);
    const INIT_ASSIGNEES = Array.isArray(window.__INIT_ASSIGNEES__) ? window.__INIT_ASSIGNEES__ : [];

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

    if (!assigneeSelect || !btnAdd || !listEl || !inputsEl) return;

    // ===== staff map from <option> =====
    function buildStaffMap() {
        const staffMap = new Map();
        Array.from(assigneeSelect.options || []).forEach((opt) => {
            const id = toInt(opt.value);
            if (!id) return;
            staffMap.set(id, {
                id,
                role: opt.getAttribute("data-role") || "",
                label: opt.textContent || "",
            });
        });
        return staffMap;
    }

    let staffMap = buildStaffMap();

    // state (รักษาลำดับ)
    let selected = [];

    function rebuildHiddenInputs() {
        inputsEl.innerHTML = "";
        for (const id of selected) {
            const inp = document.createElement("input");
            inp.type = "hidden";
            inp.name = "assignees[]";
            inp.value = String(id);
            inputsEl.appendChild(inp);
        }
    }

    function computeDeptText() {
        const roles = new Set();
        for (const id of selected) {
            const s = staffMap.get(id);
            if (s?.role) roles.add(s.role);
        }
        if (roles.size === 1) return Array.from(roles)[0];
        if (roles.size > 1) return "multi";
        return "";
    }

    function renderDeptAuto() {
        if (!deptBadge) return;
        const v = computeDeptText();
        deptBadge.textContent = v === "multi" ? "หลายแผนก" : (v || "-");
    }

    function renderChips() {
        listEl.innerHTML = "";

        if (!selected.length) {
            listEl.innerHTML = `<span class="text-muted small">ยังไม่ได้เลือกผู้รับมอบหมาย</span>`;
            rebuildHiddenInputs();
            renderDeptAuto();
            return;
        }

        for (let i = 0; i < selected.length; i++) {
            const id = selected[i];
            const s = staffMap.get(id);

            // ✅ ถ้าไม่เจอใน staffMap ให้โชว์เป็น ID แทน (กันเคส option ยังไม่มา)
            const label = s?.label || `ID ${id}`;
            const role = s?.role || "";

            const chip = document.createElement("span");
            chip.className = "badge bg-light text-dark border d-inline-flex align-items-center gap-2";
            chip.style.borderRadius = "999px";
            chip.style.padding = "8px 10px";

            chip.innerHTML = `
        <span>${escapeHtml(label)}${i === 0 ? ` <span class="text-primary">(หลัก)</span>` : ""}</span>
        ${role ? `<span class="badge bg-secondary">${escapeHtml(role)}</span>` : ""}
        <button type="button" class="btn btn-sm p-0 border-0" data-remove="${id}" title="ลบ">
          <i class="bi bi-x-circle"></i>
        </button>
      `;

            listEl.appendChild(chip);
        }

        rebuildHiddenInputs();
        renderDeptAuto();
    }

    function applyFilters() {
        const role = String(deptFilter?.value ?? "");
        const q = String(searchBox?.value ?? "").trim().toLowerCase();

        for (const opt of Array.from(assigneeSelect.options)) {
            if (!opt.value) continue;
            const optRole = opt.getAttribute("data-role") || "";
            const text = (opt.textContent || "").toLowerCase();

            const okRole = !role || optRole === role;
            const okText = !q || text.includes(q);
            opt.hidden = !(okRole && okText);
        }

        const cur = assigneeSelect.value;
        if (cur && assigneeSelect.selectedOptions[0]?.hidden) {
            assigneeSelect.value = "";
        }
    }

    function addSelectedAssignee() {
        const id = toInt(assigneeSelect.value);
        if (!id) return;

        if (selected.includes(id)) {
            assigneeSelect.value = "";
            return;
        }

        selected.push(id);
        assigneeSelect.value = "";
        renderChips();
    }

    function setSelectedFromInit(arr) {
        selected = (Array.isArray(arr) ? arr : []).map(toInt).filter((x) => x > 0);
        const seen = new Set();
        selected = selected.filter((id) => (seen.has(id) ? false : (seen.add(id), true)));
    }

    async function fallbackLoadAssigneesFromApi() {
        if (!TASK_ID) return false;
        try {
            const url = new URL("api/task_detail.php", document.baseURI);
            url.searchParams.set("id", String(TASK_ID));

            const res = await fetch(url.toString(), { credentials: "same-origin" });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json?.ok) return false;

            const ass = Array.isArray(json.data?.assignees) ? json.data.assignees : [];
            const ids = ass.map((u) => toInt(u.id)).filter((x) => x > 0);

            setSelectedFromInit(ids);
            return selected.length > 0;
        } catch (_) {
            return false;
        }
    }

    // ===== init (สำคัญ) =====
    setSelectedFromInit(INIT_ASSIGNEES);

    // ถ้า init ว่าง (หรือ staffMap ไม่เจอจน render ไม่มีอะไร) -> fallback ดึงจาก API
    renderChips();
    applyFilters();

    if (!selected.length) {
        fallbackLoadAssigneesFromApi().then(() => {
            // rebuild staffMap เผื่อ option ถูก render ทีหลัง
            staffMap = buildStaffMap();
            renderChips();
            applyFilters();
        });
    }

    // ===== events =====
    btnAdd.addEventListener("click", (e) => {
        e.preventDefault();
        addSelectedAssignee();
    });

    listEl.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-remove]");
        if (!btn) return;
        const id = toInt(btn.getAttribute("data-remove"));
        selected = selected.filter((x) => x !== id);
        renderChips();
    });

    deptFilter?.addEventListener("change", applyFilters);
    searchBox?.addEventListener("input", applyFilters);
});
