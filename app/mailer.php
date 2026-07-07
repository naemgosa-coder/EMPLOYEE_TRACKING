<?php
// app/mailer.php
require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Bangkok');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

class Mailer
{
    public static function send(string $to, string $subject, string $html, array $opts = []): bool
    {
        [$ok, $err] = self::sendWithError($to, $subject, $html, $opts);
        return $ok;
    }

    public static function sendWithError(string $to, string $subject, string $html, array $opts = []): array
    {
        $cc  = $opts['cc']  ?? [];
        $bcc = $opts['bcc'] ?? [];

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [false, "invalid recipient email"];
        }

        // ✅ ถ้า PHPMailer มีจริง ให้ใช้ SMTP ได้แน่นอน
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return self::sendViaPHPMailerWithError($to, $subject, $html, $cc, $bcc);
        }

        // ❗ fallback: mail() บน XAMPP/Windows มักส่งไม่ออก (ถ้าไม่ตั้ง sendmail)
        $ok = self::sendViaMailFunction($to, $subject, $html, $cc, $bcc);
        return [$ok, $ok ? null : "mail() failed or not configured (no PHPMailer/vendor)"];
    }

    private static function sendViaPHPMailerWithError(string $to, string $subject, string $html, array $cc, array $bcc): array
    {
        global $MAIL_TRANSPORT, $MAIL_FROM, $MAIL_FROM_NAME;
        global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_SECURE;

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';

            if (($MAIL_TRANSPORT ?? 'mail') === 'smtp') {
                $mail->isSMTP();
                $mail->Host = (string)$SMTP_HOST;
                $mail->Port = (int)$SMTP_PORT;

                // ✅ Office365 ต้อง Auth + STARTTLS (587)
                $mail->SMTPAuth = true;
                $mail->Username = (string)$SMTP_USER;
                $mail->Password = (string)$SMTP_PASS;

                // STARTTLS
                if (!empty($SMTP_SECURE)) {
                    // แนะนำใช้ค่าคงที่ของ PHPMailer จะชัวร์กว่า string
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // tls
                }
                $mail->SMTPAutoTLS = true;

                // (ออปชัน) ตั้ง timeout
                $mail->Timeout = 20;

                // (ดีบักชั่วคราวถ้าต้องการ) 0=ปิด, 2=ละเอียด
                // $mail->SMTPDebug = 2;
            }

            if (empty($MAIL_FROM)) {
                return [false, "MAIL_FROM is empty"];
            }

            $mail->setFrom((string)$MAIL_FROM, (string)$MAIL_FROM_NAME);
            $mail->addAddress($to);

            foreach ($cc as $e)  if (filter_var($e, FILTER_VALIDATE_EMAIL)) $mail->addCC($e);
            foreach ($bcc as $e) if (filter_var($e, FILTER_VALIDATE_EMAIL)) $mail->addBCC($e);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags($html);

            $mail->send();
            return [true, null];
        } catch (\Throwable $e) {
            // ✅ คืน error จริง (รวม ErrorInfo ถ้ามี)
            $info = '';
            if (property_exists($mail, 'ErrorInfo') && !empty($mail->ErrorInfo)) {
                $info = " | " . $mail->ErrorInfo;
            }
            return [false, $e->getMessage() . $info];
        }
    }

    private static function sendViaMailFunction(string $to, string $subject, string $html, array $cc, array $bcc): bool
    {
        global $MAIL_FROM, $MAIL_FROM_NAME;

        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B');

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $fromName = mb_encode_mimeheader($MAIL_FROM_NAME, 'UTF-8', 'B');
        $headers[] = "From: {$fromName} <{$MAIL_FROM}>";

        if (!empty($cc))  $headers[] = "Cc: "  . implode(',', $cc);
        if (!empty($bcc)) $headers[] = "Bcc: " . implode(',', $bcc);

        return mail($to, $encodedSubject, $html, implode("\r\n", $headers));
    }
}