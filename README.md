# EMPLOYEE_TRACKING

ระบบติดตามงานพนักงาน (TaskFlow), PHP 8.4

## โครงสร้างโปรเจค

```
bin/                  สคริปต์ CLI / cron (send-task-reminders.php)
bootstrap/app.php     จุดเริ่มต้นเดียว: autoload, .env, timezone, PDO ($pdo), session
config/app.php        ค่า config (อ่านจาก .env) เรียกใช้ด้วย config('db.host')
public_html/          web root (ไฟล์เดียวที่เปิดสู่ภายนอก)
  index.php           front controller / router
  api/                endpoint ที่ JS เรียกตรง (task_files.php, line/webhook.php, ...)
  assets/             css, js, img
routes/api.php        REST API /api/... (ถูก include จาก index.php)
src/                  PSR-4 namespace App\
  Controllers/  Models/  Services/
  helpers.php, auth.php   ฟังก์ชัน global (โหลดผ่าน composer "files")
views/                หน้าเพจ + partials/header.php, footer.php
storage/uploads/      ไฟล์แนบงาน (อยู่นอก web root, ดาวน์โหลดผ่าน api/task_file_download.php)
storage/logs/
tests/run.php         self-check (composer test)
```

## ติดตั้ง

```bash
composer install
cp .env.example .env      # แล้วใส่ค่า DB / SMTP / LINE
composer test
```

ตั้ง document root ของเว็บไปที่ `public_html/`

Cron (วันละครั้ง):

```
php /path/to/EMPLOYEE_TRACKING/bin/send-task-reminders.php
```

## ย้ายจากเวอร์ชันเก่าบนเซิร์ฟเวอร์

1. สร้าง `.env` บนเซิร์ฟเวอร์ (ไม่อยู่ใน git)
2. ย้ายไฟล์แนบเดิม `public_html/uploads/task_files/*` ไปที่ `storage/uploads/task_files/` (path ใน DB ใช้ได้เหมือนเดิม)
3. แก้ cron จาก `app/cron/send_task_reminders.php` เป็น `bin/send-task-reminders.php`
4. รัน `composer dump-autoload -o`
