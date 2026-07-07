// public/assets/js/services/user.service.js
import { request } from "../core/http.js";

export const UserService = {
  list: () => request("users"),
  create: (data) => request("users", { method: "POST", body: data }),

  update: (id, payload) =>
    request("users/update", {
      method: "POST",
      body: {
        id: Number(id),
        full_name: String(payload?.full_name ?? ""),
      },
    }),

  toggle: (id, to) =>
    request("users/toggle", { method: "POST", body: { id: Number(id), to: Number(to) } }),

  delete: (id) =>
    request("users/delete", { method: "POST", body: { id: Number(id) } }),

  setPassword: (id, password) =>
    request("users/password", { method: "POST", body: { id: Number(id), password: String(password) } }),
};
