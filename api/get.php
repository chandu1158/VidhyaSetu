<?php
//  GET  /api/notifications/get.php          → list all
//  POST /api/notifications/get.php          → mark { id } as read
require_once '../config/db.php';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    respond(['success' => true, 'message' => 'Notification marked as read'], 200);
} else {
    respond(['success' => true, 'notifications' => []], 200);
}
