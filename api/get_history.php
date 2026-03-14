<?php
//  GET /api/payments/get_history.php
require_once '../config/db.php';
respond(['success'=>true, 'history' => []]);
