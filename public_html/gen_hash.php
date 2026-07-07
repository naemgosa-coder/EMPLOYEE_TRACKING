<?php
// public/gen_hash.php

$password = '123456'; // รหัสผ่านที่คุณอยากใช้จริง ๆ
$hash = password_hash($password, PASSWORD_DEFAULT);

echo 'Plain: ' . $password . "<br>";
echo 'Hash: ' . $hash . "<br>";
