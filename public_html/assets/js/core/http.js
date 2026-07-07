// public/assets/js/core/http.js
import { AppConfig } from "../config.js";

function buildUrl(path) {
  const base = AppConfig.apiBase.replace(/\/+$/, "");
  const p = String(path || "").replace(/^\/+/, "");
  return `${base}/${p}`;
}

function extractErrorMessage(payload, status) {
  if (!payload) return `HTTP ${status}`;

  if (typeof payload === "string") return payload || `HTTP ${status}`;

  return (
    payload.message ||
    payload.error ||
    payload.msg ||
    payload.detail ||
    (payload.errors &&
      (Array.isArray(payload.errors) ? payload.errors.join(", ") : String(payload.errors))) ||
    `HTTP ${status}`
  );
}

async function parseResponse(res) {
  const contentType = res.headers.get("content-type") || "";
  const isJson = contentType.includes("application/json");

  const payload = isJson
    ? await res.json().catch(() => null)
    : await res.text().catch(() => "");

  if (!res.ok) {
    const msg = extractErrorMessage(payload, res.status);
    const err = new Error(msg);
    err.status = res.status;
    err.payload = payload;
    throw err;
  }

  if (payload && typeof payload === "object" && "ok" in payload && payload.ok === false) {
    const msg = extractErrorMessage(payload, res.status);
    const err = new Error(msg);
    err.status = res.status;
    err.payload = payload;
    throw err;
  }

  return payload;
}

/**
 * Overlay controller (ใช้ของที่คุณประกาศไว้ใน footer.php)
 * - เปิดเฉพาะ request ที่ไม่ใช่ GET/HEAD
 * - ปิดใน finally เสมอ
 */
function shouldShowOverlay(method, opt) {
  const m = String(method || "GET").toUpperCase();
  if (m === "GET" || m === "HEAD") return false;

  // ถ้าบาง request ไม่อยากให้ขึ้น overlay ส่ง noOverlay:true มาได้
  if (opt && opt.noOverlay === true) return false;

  return true;
}

function openOverlay(text) {
  try {
    window.showGlobalOverlay?.(text || "กำลังบันทึก...");
  } catch (_) {}
}

function closeOverlay() {
  try {
    window.hideGlobalOverlay?.();
  } catch (_) {}
}

export async function request(
  path,
  {
    method = "GET",
    params = null,
    body = null,
    headers = {},
    // ✅ options เพิ่มเติมสำหรับ overlay
    overlayText = "",
    noOverlay = false,
  } = {}
) {
  let url = buildUrl(path);

  if (params && typeof params === "object") {
    const qs = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v === undefined || v === null || v === "") return;
      qs.append(k, String(v));
    });
    const q = qs.toString();
    if (q) url += `?${q}`;
  }

  const opts = {
    method,
    credentials: "same-origin",
    headers: { ...headers },
  };

  if (body !== null && body !== undefined) {
    const isFormData = typeof FormData !== "undefined" && body instanceof FormData;
    const isBlob = typeof Blob !== "undefined" && body instanceof Blob;

    if (isFormData || isBlob) {
      opts.body = body;
    } else if (typeof body === "string") {
      opts.headers["Content-Type"] =
        opts.headers["Content-Type"] || "text/plain;charset=UTF-8";
      opts.body = body;
    } else {
      opts.headers["Content-Type"] =
        opts.headers["Content-Type"] || "application/json";
      opts.body = JSON.stringify(body);
    }
  }

  // ✅ เปิด overlay อัตโนมัติสำหรับ POST/PATCH/PUT/DELETE
  const show = shouldShowOverlay(method, { noOverlay });
  if (show) {
    // ตั้งข้อความเริ่มต้นให้ดูดีขึ้น (ถ้าไม่ได้ส่ง overlayText มา)
    const m = String(method || "GET").toUpperCase();
    const fallback =
      m === "POST"
        ? "กำลังบันทึก..."
        : m === "PATCH" || m === "PUT"
        ? "กำลังอัปเดต..."
        : m === "DELETE"
        ? "กำลังลบ..."
        : "กำลังดำเนินการ...";
    openOverlay(overlayText || fallback);
  }

  try {
    const res = await fetch(url, opts);
    return await parseResponse(res);
  } finally {
    if (show) closeOverlay();
  }
}