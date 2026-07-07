// public/assets/js/services/report.service.js
import { request } from "../core/http.js";
export const ReportService = { get: (params={}) => request("reports", { params }) };
