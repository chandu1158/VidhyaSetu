<?php
//  POST /api/contact/submit.php
require_once '../config/db.php';
$body = getBody();
respond(['success' => true, 'message' => 'Contact message received (stub)'], 200);
