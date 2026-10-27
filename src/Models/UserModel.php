<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class UserModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    // ดึงข้อมูลผู้ใช้ตาม username
    public function findByUsername($username)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM users WHERE username = :u LIMIT 1
        ");
        $stmt->execute(['u' => $username]);
        return $stmt->fetch();
    }

    // ดึงข้อมูลผู้ใช้ทั้งหมด
    public function getAll()
    {
        return $this->pdo->query("
        SELECT id, username, full_name, role, is_active
        FROM users
        ORDER BY full_name ASC
    ")->fetchAll();
    }


    // ดึงผู้ใช้ตาม ID
    public function find($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM users WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
