// public/assets/js/services/dashboard.service.js
import { request } from "../core/http.js";

const cleanParams = (p = {}) =>
    Object.fromEntries(
        Object.entries(p).filter(([, v]) => v !== "" && v !== null && v !== undefined)
    );

export const DashboardService = {
    get: (params = {}) => request("dashboard", { params: cleanParams(params) }),
};
