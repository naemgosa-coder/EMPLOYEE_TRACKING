// public/assets/js/config.js
export const AppConfig = (() => {
  const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || "";
  const apiBase = meta("api-base") || "api";
  const baseHref = document.querySelector("base")?.href || "/";
  return { apiBase, baseHref };
})();
