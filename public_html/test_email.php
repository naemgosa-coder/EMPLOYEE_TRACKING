<?php
require_once '../app/auth.php';
require_once '../app/mailer.php';

require_login(); // กันคนอื่นเรียก

$to = $_GET['to'] ?? '';
if (!$to) {
  echo "Usage: test_email.php?to=oliviaofdarkness2204@gmail.com";
  exit;
}

$ok = Mailer::send($to, 'Test SMTP from Task System', '<b>Hello</b> SMTP works ✅');

echo $ok ? "SENT OK" : "FAILED";
