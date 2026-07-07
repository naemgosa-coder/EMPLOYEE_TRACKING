<?php
require_once '../app/db.php';
require_once '../app/auth.php';
require_once '../app/models/TaskModel.php';

require_login();

if (!is_boss()) {
  header('Location: ../tasks-mine.php');
  exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
  $taskModel = new TaskModel($pdo);
  $taskModel->delete($id);
}

header('Location: ../tasks');
exit;
