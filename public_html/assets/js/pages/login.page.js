// public/assets/js/pages/login.page.js
(() => {
    const wrapper = document.getElementById("authWrapper");
    if (!wrapper) return;

    const btnClose = document.getElementById("btnAuthClose");
    const registerLink = wrapper.querySelector(".register-link");
    const loginLink = wrapper.querySelector(".login-link");
    const btnRegisterFake = document.getElementById("btnRegisterFake");

    registerLink?.addEventListener("click", (e) => {
        e.preventDefault();
        wrapper.classList.add("active");
    });

    loginLink?.addEventListener("click", (e) => {
        e.preventDefault();
        wrapper.classList.remove("active");
    });

    btnClose?.addEventListener("click", () => {
        // ปรับปลายทางได้ เช่น "index" หรือ "/" ตาม routing ของคุณ
        window.location.href = "./";
    });

    btnRegisterFake?.addEventListener("click", () => {
        alert("หน้านี้เป็น UI ตัวอย่าง ยังไม่ได้เชื่อมระบบสมัครสมาชิก");
    });
})();
