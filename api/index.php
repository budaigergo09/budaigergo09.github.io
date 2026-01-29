<?php
/**
 * PDC Darts Simulator - Backend API
 * Version: 1.2.1
 * 
 * Place this file in your server's /api/ directory
 * Make sure to create a 'data' folder with write permissions
 * 
 * Database: SQLite (file-based, no setup required)
 */

// Enable CORS for cross-origin requests (adjust as needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('DB_PATH', __DIR__ . '/data/pdc_darts.db');
define('JWT_SECRET', 'your-secret-key-change-this-in-production'); // CHANGE THIS!
define('TOKEN_EXPIRY', 60 * 60 * 24 * 30); // 30 days

// Ensure data directory exists
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Initialize SQLite database
function getDB() {
    $db = new SQLite3(DB_PATH);
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS saves (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        data TEXT NOT NULL,
        saved_at INTEGER NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');
    return $db;
}

// Simple JWT-like token functions
function generateToken($userId, $username) {
    $payload = [
        'userId' => $userId,
        'username' => $username,
        'exp' => time() + TOKEN_EXPIRY
    ];
    $encoded = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $encoded, JWT_SECRET);
    return $encoded . '.' . $signature;
}

function verifyToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;
    
    $encoded = $parts[0];
    $signature = $parts[1];
    
    $expectedSignature = hash_hmac('sha256', $encoded, JWT_SECRET);
    if (!hash_equals($expectedSignature, $signature)) return null;
    
    $payload = json_decode(base64_decode($encoded), true);
    if (!$payload || $payload['exp'] < time()) return null;
    
    return $payload;
}

function getAuthUser() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        return verifyToken($matches[1]);
    }
    return null;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function getInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// Route handling
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('/^.*\/api/', '', $uri); // Get path after /api
$method = $_SERVER['REQUEST_METHOD'];

// API Routes
switch ($uri) {
    
    // Register new user
    case '/register':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $input = getInput();
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        
        if (strlen($username) < 3) {
            jsonResponse(['success' => false, 'error' => 'Username must be at least 3 characters']);
        }
        if (strlen($password) < 6) {
            jsonResponse(['success' => false, 'error' => 'Password must be at least 6 characters']);
        }
        
        $db = getDB();
        
        // Check if user exists
        $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(:username)');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray();
        
        if ($result) {
            jsonResponse(['success' => false, 'error' => 'Username already exists']);
        }
        
        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users (username, password, created_at) VALUES (:username, :password, :created_at)');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
        $stmt->execute();
        
        $userId = $db->lastInsertRowID();
        $token = generateToken($userId, $username);
        
        jsonResponse([
            'success' => true,
            'userId' => $userId,
            'username' => $username,
            'token' => $token
        ]);
        break;
    
    // Login
    case '/login':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $input = getInput();
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        
        if (!$username || !$password) {
            jsonResponse(['success' => false, 'error' => 'Username and password required']);
        }
        
        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, password FROM users WHERE LOWER(username) = LOWER(:username)');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(['success' => false, 'error' => 'Invalid username or password']);
        }
        
        // Get last sync time
        $stmt = $db->prepare('SELECT saved_at FROM saves WHERE user_id = :user_id ORDER BY saved_at DESC LIMIT 1');
        $stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
        $save = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $lastSync = $save ? date('Y-m-d H:i:s', $save['saved_at']) : null;
        
        $token = generateToken($user['id'], $user['username']);
        
        jsonResponse([
            'success' => true,
            'userId' => $user['id'],
            'username' => $user['username'],
            'token' => $token,
            'lastSync' => $lastSync
        ]);
        break;
    
    // Verify token
    case '/verify':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $user = getAuthUser();
        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'Invalid or expired token']);
        }
        
        jsonResponse(['success' => true, 'userId' => $user['userId'], 'username' => $user['username']]);
        break;
    
    // Save game data
    case '/save':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $user = getAuthUser();
        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }
        
        $input = getInput();
        $data = $input['data'] ?? '';
        
        if (!$data) {
            jsonResponse(['success' => false, 'error' => 'No data provided']);
        }
        
        $db = getDB();
        
        // Delete old saves for this user (keep only latest)
        $stmt = $db->prepare('DELETE FROM saves WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
        $stmt->execute();
        
        // Insert new save
        $stmt = $db->prepare('INSERT INTO saves (user_id, data, saved_at) VALUES (:user_id, :data, :saved_at)');
        $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
        $stmt->bindValue(':data', $data, SQLITE3_TEXT);
        $stmt->bindValue(':saved_at', time(), SQLITE3_INTEGER);
        $stmt->execute();
        
        jsonResponse(['success' => true, 'savedAt' => time()]);
        break;
    
    // Load game data
    case '/load':
        if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $user = getAuthUser();
        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }
        
        $db = getDB();
        $stmt = $db->prepare('SELECT data, saved_at FROM saves WHERE user_id = :user_id ORDER BY saved_at DESC LIMIT 1');
        $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
        $save = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if ($save) {
            jsonResponse([
                'success' => true,
                'data' => $save['data'],
                'savedAt' => $save['saved_at']
            ]);
        } else {
            jsonResponse(['success' => true, 'data' => null]);
        }
        break;
    
    // Default - API info
    default:
        jsonResponse([
            'name' => 'PDC Darts Simulator API',
            'version' => '1.2.1',
            'endpoints' => [
                'POST /api/register' => 'Register new user',
                'POST /api/login' => 'Login and get token',
                'POST /api/verify' => 'Verify auth token',
                'POST /api/save' => 'Save game data (requires auth)',
                'GET /api/load' => 'Load game data (requires auth)'
            ]
        ]);
}
