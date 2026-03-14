<?php
ob_start(); // Buffer all output to prevent warnings/notices from breaking JSON responses
// ============================================================
//  VidhyaSetu — Database Configuration & Helpers
//  config/db.php
//  ⚠️ Update DB_USER, DB_PASS, DB_NAME before going live.
define('DB_HOST', 'db.dblqxmnyozsfmtwkjbti.supabase.co');
define('DB_USER', 'postgres');
define('DB_PASS', 'Chandu@910054');
define('DB_NAME', 'postgres');


$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn) {
    echo "✅ Database Connected Successfully!";
} else {
    echo "❌ Connection Failed: " . mysqli_connect_error();
}
?>



// ── CORS Headers ─────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Get a Database connection (MySQL local or PostgreSQL/Supabase via PDO).
 * Terminates with JSON error if connection fails.
 */
function getDB(): PDO
{
    // Check for environment variables (Vercel/Supabase)
    $db_url = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL');

    if ($db_url) {
        // PostgreSQL (Supabase/Vercel)
        try {
            $conn = new PDO($db_url);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $conn;
        }
        catch (PDOException $e) {
            respond(['success' => false, 'message' => 'Cloud DB connection failed: ' . $e->getMessage()], 500);
        }
    }

    // Local XAMPP MySQL
    $host = DB_HOST;
    $db = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    }
    catch (PDOException $e) {
        respond(['success' => false, 'message' => 'Local database connection failed: ' . $e->getMessage()], 500);
    }
    exit;
}

/**
 * Send a JSON response and exit.
 * Clears all output buffers to ensure no PHP warnings leak into the response.
 */
function respond(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Parse JSON body from the request.
 */
function getBody(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? $_POST;
}

/**
 * Generate a secure random internal UID (hex string).
 */
function generateUID(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Generate the next public-facing User ID (e.g. STU2025001).
 * Queries the DB to find the current count for that role.
 */
function getNextUserID(string $role): string
{
    $conn = getDB();
    $prefix = strtoupper($role === 'tutor' ? 'TUT' : 'STU');
    $year = date('Y');

    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE role = ?");
    $stmt->execute([$role]);
    $count = (int)($stmt->fetch()['cnt'] ?? 0);

    // Zero-pad to 3 digits: STU2025001
    return $prefix . $year . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Validate the Bearer token from Authorization header.
 * Returns the user's public UID (display_uid) or null if invalid / expired.
 */
function getAuthUID(): ?string
{
    $token = null;
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        $token = trim($m[1]);
    }
    else {
        // Fallback to custom header or query param
        $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? $_GET['token'] ?? null;
    }

    if (!$token)
        return null;

    $conn = getDB();
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "SELECT uid FROM user_sessions WHERE token = ? AND expires_at > ? LIMIT 1"
    );
    $stmt->execute([$token, $now]);
    $row = $stmt->fetch();

    return $row['uid'] ?? null;
}

/**
 * Require authentication; terminate with 401 if not authenticated.
 */
function requireAuth(): string
{
    $uid = getAuthUID();
    if (!$uid)
        respond(['success' => false, 'message' => 'Unauthorized — please log in again'], 401);
    return $uid;
}
