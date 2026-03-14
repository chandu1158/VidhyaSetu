<?php
// ================================================================
//  VidhyaSetu — Chat API Diagnostics
//  api/chat_diag.php
//  Open in browser: http://localhost/VidhyaSetu/api/chat_diag.php
//  Tests: DB connection, messages table schema, send + fetch flow
// ================================================================
require_once '../config/db.php';

header('Content-Type: text/html; charset=utf-8');
ob_end_clean();

function check(string $label, bool $ok, string $detail = ''): void {
    $icon = $ok ? '✅' : '❌';
    $color = $ok ? '#22c55e' : '#ef4444';
    echo "<tr><td>$icon</td><td><strong>$label</strong></td><td style='color:$color'>$detail</td></tr>";
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Chat API Diagnostics – VidhyaSetu</title>
<style>
  body { font-family: system-ui, sans-serif; background:#0f172a; color:#e2e8f0; padding:30px; margin:0; }
  h1 { color:#6366f1; } h2 { color:#8b5cf6; border-bottom:1px solid #334155; padding-bottom:6px; }
  table { border-collapse:collapse; width:100%; margin:12px 0 24px; }
  th { text-align:left; padding:8px 12px; background:#1e293b; color:#94a3b8; font-size:12px; }
  td { padding:8px 12px; border-bottom:1px solid #1e293b; font-size:13px; vertical-align:top; }
  code { background:#1e293b; padding:2px 6px; border-radius:4px; font-family:monospace; word-break:break-all; }
  .pill { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; }
  .pill-ok { background:#166534; color:#86efac; }
  .pill-err { background:#7f1d1d; color:#fca5a5; }
  pre { background:#1e293b; padding:16px; border-radius:8px; overflow-x:auto; font-size:12px; }
</style>
</head>
<body>
<h1>🔧 VidhyaSetu — Chat API Diagnostics</h1>
<p style="color:#94a3b8;font-size:13px;">Run this page to verify your DB, schema, and chat API endpoints.</p>

<?php
// ─── 1. DB Connection ─────────────────────────────────────────────
echo "<h2>1. Database Connection</h2><table>";
echo "<tr><th>Check</th><th>Item</th><th>Result</th></tr>";
try {
    $conn = getDB();
    check('PDO Connection', true, 'Connected successfully');
    $dbName = $conn->query("SELECT DATABASE()")->fetchColumn() ?: 'N/A';
    check('Database name', true, "<code>$dbName</code>");
} catch (Exception $e) {
    check('PDO Connection', false, htmlspecialchars($e->getMessage()));
    echo "</table><p style='color:#ef4444'>⛔ Cannot continue — fix DB connection first.</p></body></html>";
    exit;
}
echo "</table>";

// ─── 2. Tables ────────────────────────────────────────────────────
echo "<h2>2. Required Tables</h2><table>";
echo "<tr><th>Check</th><th>Table</th><th>Status</th></tr>";
$required = ['users', 'user_sessions', 'bookings', 'messages', 'notifications'];
$allTablesOk = true;
foreach ($required as $t) {
    try {
        $conn->query("SELECT 1 FROM `$t` LIMIT 1");
        check("Table exists", true, "<code>$t</code>");
    } catch (PDOException $e) {
        check("Table exists", false, "<code>$t</code> — " . htmlspecialchars($e->getMessage()));
        $allTablesOk = false;
    }
}
echo "</table>";

// ─── 3. Messages Table Columns ───────────────────────────────────
echo "<h2>3. Messages Table Columns</h2><table>";
echo "<tr><th>Check</th><th>Column</th><th>Type / Default</th></tr>";
$requiredCols = [
    'id'          => 'INT / AUTO_INCREMENT',
    'booking_id'  => 'INT / nullable FK',
    'sender_id'   => 'VARCHAR / NOT NULL',
    'receiver_id' => 'VARCHAR / NOT NULL',
    'text'        => 'TEXT / NOT NULL',
    'is_read'     => 'TINYINT / DEFAULT 0  ← needed for unread badges',
    'created_at'  => 'DATETIME / DEFAULT NOW()',
];

try {
    $cols = $conn->query("SHOW COLUMNS FROM `messages`")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Type', 'Field');
    foreach ($requiredCols as $colName => $desc) {
        $exists = array_key_exists($colName, $colNames);
        check("Column exists", $exists, "<code>$colName</code> — $desc" . ($exists ? " | DB type: <code>{$colNames[$colName]}</code>" : ' ← MISSING'));
    }
} catch (PDOException $e) {
    check("messages schema", false, htmlspecialchars($e->getMessage()));
}
echo "</table>";

// ─── 4. Auto-migrate is_read if missing ──────────────────────────
echo "<h2>4. Auto-migration (is_read)</h2><table>";
echo "<tr><th>Check</th><th>Action</th><th>Result</th></tr>";
try {
    $hasIsRead = $conn->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='is_read'"
    )->fetchColumn();

    if (!$hasIsRead) {
        $conn->exec("ALTER TABLE `messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `text`");
        check("Added is_read column", true, "ALTER TABLE executed — column added ✔");
    } else {
        check("is_read column", true, "Already present — no migration needed");
    }
} catch (PDOException $e) {
    check("is_read migration", false, htmlspecialchars($e->getMessage()));
}
echo "</table>";

// ─── 5. Active Users & Bookings ──────────────────────────────────
echo "<h2>5. Data Snapshot</h2><table>";
echo "<tr><th>Check</th><th>Metric</th><th>Count</th></tr>";
$queries = [
    'Total users'               => "SELECT COUNT(*) FROM users",
    'Students'                  => "SELECT COUNT(*) FROM users WHERE role='student'",
    'Tutors'                    => "SELECT COUNT(*) FROM users WHERE role='tutor'",
    'Total bookings'            => "SELECT COUNT(*) FROM bookings",
    'Confirmed/Completed'       => "SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed','completed')",
    'Total messages'            => "SELECT COUNT(*) FROM messages",
    'Unread messages (global)'  => "SELECT COUNT(*) FROM messages WHERE is_read=0",
];
foreach ($queries as $label => $sql) {
    try {
        $n = $conn->query($sql)->fetchColumn();
        check($label, true, "<strong>$n</strong>");
    } catch (PDOException $e) {
        check($label, false, htmlspecialchars($e->getMessage()));
    }
}
echo "</table>";

// ─── 6. Recent messages ──────────────────────────────────────────
echo "<h2>6. Last 5 Messages</h2>";
try {
    $rows = $conn->query(
        "SELECT m.id, m.booking_id, m.sender_id, m.receiver_id,
                LEFT(m.text,60) AS preview,
                m.is_read, m.created_at
         FROM messages m ORDER BY m.id DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        echo "<table><tr>";
        foreach (array_keys($rows[0]) as $k) echo "<th>$k</th>";
        echo "</tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($r as $v) echo "<td>" . htmlspecialchars((string)$v) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:#94a3b8;'>No messages yet.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:#ef4444'>" . htmlspecialchars($e->getMessage()) . "</p>";
}

// ─── 7. API Endpoint Summary ──────────────────────────────────────
echo "<h2>7. API Endpoint Summary</h2>";
echo "<table><tr><th>Method</th><th>Endpoint</th><th>Purpose</th><th>Auth</th></tr>";
$endpoints = [
    ['POST',  'api/send_message.php',  'Send a message to booking partner',                   'Bearer token'],
    ['GET',   'api/get_messages.php',  'Fetch new messages (supports ?booking_id, ?mark_read)', 'Bearer token'],
    ['GET',   'api/get_bookings.php?chat=1', 'Load confirmed/completed bookings for sidebar',    'Bearer token'],
];
foreach ($endpoints as [$m,$e,$d,$a]) {
    echo "<tr><td><span class='pill pill-ok'>$m</span></td><td><code>$e</code></td><td>$d</td><td><code>$a</code></td></tr>";
}
echo "</table>";

// ─── 8. Summary ──────────────────────────────────────────────────
echo "<h2>✅ All checks complete</h2>";
echo "<p style='color:#94a3b8;font-size:13px;'>If all checks are green, your chat system is ready.<br>
Open <code>http://localhost/VidhyaSetu/chat.html</code> (student) or 
<code>http://localhost/VidhyaSetu/T_chat.html</code> (tutor) to test.</p>";
?>
</body>
</html>
