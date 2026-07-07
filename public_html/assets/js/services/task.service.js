// public/assets/js/services/task.service.js
import { request } from "../core/http.js";

export const TaskService = {
  list: (params = {}) => request("tasks", { params }),

  listAssignedByMe: (params = {}) =>
    request("tasks", { params: { ...params, scope: "assigned_by" } }),

  get: (id) => request(`tasks/${id}`),
  logs: (id) => request(`tasks/${id}/logs`),
  updateStatus: (id, status, note="") => request(`tasks/${id}/status`, { method:"PATCH", body:{ status, note } }),
  create: (data) => request("tasks", { method:"POST", body:data }),
  update: (id, data) => request(`tasks/${id}`, { method:"PATCH", body:data }),
  remove: (id) => request(`tasks/${id}`, { method:"DELETE" }),
};
