<?php
$hash = '$2y$10$dvQNOlZHPLRV0B/BmTXKaOHRUP1jXC.Jjha7ltT3RI63B7jjWXO4C';

var_dump(password_verify('123456', $hash)); // ต้องได้ bool(true)
