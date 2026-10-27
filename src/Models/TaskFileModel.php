<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use PDO;

class TaskFileModel
{
    private PDO $pdo;
    private string $uploadRootAbs;   // absolute path
    private string $publicPrefix;    // path stored in DB, relative to storage/

    public function __construct(PDO $pdo, ?string $uploadRootAbs = null, string $publicPrefix = 'uploads/task_files')
    {
        $this->pdo = $pdo;
        $this->uploadRootAbs = $uploadRootAbs ?: rtrim(config('uploads_path') . '/task_files', '/\\');
        $this->publicPrefix  = trim($publicPrefix, '/');
    }

    private function ensureTaskDir(int $taskId): string
    {
        $dir = $this->uploadRootAbs . DIRECTORY_SEPARATOR . $taskId;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception("สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ: {$dir}");
            }
        }
        return $dir;
    }

    private function normalizeFiles(array $files): array
    {
        // รองรับทั้ง single / multiple
        $out = [];
        if (!isset($files['name'])) return $out;

        if (is_array($files['name'])) {
            $n = count($files['name']);
            for ($i = 0; $i < $n; $i++) {
                $out[] = [
                    'name'     => (string)$files['name'][$i],
                    'type'     => (string)($files['type'][$i] ?? ''),
                    'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                    'error'    => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int)($files['size'][$i] ?? 0),
                ];
            }
        } else {
            $out[] = [
                'name'     => (string)$files['name'],
                'type'     => (string)($files['type'] ?? ''),
                'tmp_name' => (string)($files['tmp_name'] ?? ''),
                'error'    => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int)($files['size'] ?? 0),
            ];
        }

        return $out;
    }

    private function guessMime(string $tmp): string
    {
        if (!is_file($tmp)) return '';
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $m = finfo_file($f, $tmp) ?: '';
                finfo_close($f);
                return (string)$m;
            }
        }
        return (string)(mime_content_type($tmp) ?: '');
    }

    public function saveUploads(int $taskId, ?int $logId, int $uploadedBy, array $files, array $opt = []): array
    {
        $maxMb = (int)($opt['max_mb'] ?? 10);
        $maxBytes = $maxMb * 1024 * 1024;

        $allowedExt = $opt['allowed_ext'] ?? [
            'pdf',
            'png',
            'jpg',
            'jpeg',
            'webp',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'txt',
            'zip',
            'rar'
        ];

        $rows = $this->normalizeFiles($files);
        if (!$rows) return [];

        // ✅ กันเคสไม่ได้เลือกไฟล์เลย
        $hasAny = false;
        foreach ($rows as $r) {
            if (($r['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $hasAny = true;
                break;
            }
        }
        if (!$hasAny) return [];

        $taskDir = $this->ensureTaskDir($taskId);

        // ✅ อ่าน schema จริง เพื่อ insert เฉพาะคอลัมน์ที่มี
        $cols = $this->pdo->query("DESCRIBE task_files")->fetchAll(PDO::FETCH_ASSOC);
        $has = [];
        foreach ($cols as $c) $has[$c['Field']] = true;

        $saved = [];

        foreach ($rows as $f) {
            $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) continue;

            if ($err !== UPLOAD_ERR_OK) {
                // ✅ โยน error ให้เห็นชัด (ไม่เงียบ)
                throw new Exception("อัปโหลดไฟล์ไม่สำเร็จ (error={$err})");
            }

            $size = (int)($f['size'] ?? 0);
            if ($size <= 0 || $size > $maxBytes) {
                throw new Exception("ไฟล์ใหญ่เกินกำหนด ({$maxMb}MB) หรือไฟล์ไม่ถูกต้อง");
            }

            $orig = trim((string)$f['name']);
            if ($orig === '') $orig = 'file';

            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $allowedExt, true)) {
                throw new Exception("ชนิดไฟล์ไม่อนุญาต: {$orig}");
            }

            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            $abs = $taskDir . DIRECTORY_SEPARATOR . $stored;

            if (!move_uploaded_file($f['tmp_name'], $abs)) {
                throw new Exception("ย้ายไฟล์ไม่สำเร็จ: {$orig}");
            }

            $mime = $this->guessMime($abs) ?: 'application/octet-stream';
            $relPath = $this->publicPrefix . '/' . $taskId . '/' . $stored;

            // ✅ สร้างชุดข้อมูลตามคอลัมน์ที่มี
            $data = [];
            if (!empty($has['task_id']))       $data['task_id'] = $taskId;
            if (!empty($has['log_id']))        $data['log_id'] = $logId;
            if (!empty($has['uploaded_by']))   $data['uploaded_by'] = $uploadedBy;
            if (!empty($has['original_name'])) $data['original_name'] = $orig;
            if (!empty($has['stored_name']))   $data['stored_name'] = $stored;
            if (!empty($has['path']))          $data['path'] = $relPath;
            if (!empty($has['mime_type']))     $data['mime_type'] = $mime;
            if (!empty($has['file_size']))     $data['file_size'] = $size;
            if (!empty($has['created_at']))    $data['created_at'] = date('Y-m-d H:i:s');

            $colsSql = implode(',', array_keys($data));
            $valsSql = implode(',', array_map(fn($k) => ':' . $k, array_keys($data)));

            $ins = $this->pdo->prepare("INSERT INTO task_files ($colsSql) VALUES ($valsSql)");
            $ins->execute($data);

            $saved[] = [
                'id' => (int)$this->pdo->lastInsertId(),
                'name' => $orig,
                'path' => $relPath,
                'abs' => $abs,
            ];
        }

        return $saved;
    }
}
