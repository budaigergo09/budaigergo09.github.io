<?php
/**
 * PDC Darts Simulator - Backend API
 * Version: 1.2.6
 * 
 * Place this file in your server's /api/ directory
 * Make sure to create a 'data' folder with write permissions
 * 
 * Database: SQLite (file-based, no setup required)
 */

// Enable CORS for cross-origin requests (adjust as needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
    
    // Users table
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )');
    
    // Saves table
    $db->exec('CREATE TABLE IF NOT EXISTS saves (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        data TEXT NOT NULL,
        saved_at INTEGER NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');
    
    // Tournaments table
    $db->exec('CREATE TABLE IF NOT EXISTS tournaments (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        date TEXT NOT NULL,
        format TEXT NOT NULL,
        players INTEGER NOT NULL,
        type TEXT NOT NULL,
        cardRequired INTEGER NOT NULL DEFAULT 0,
        location TEXT,
        region TEXT,
        targetEvent INTEGER,
        slots INTEGER,
        night INTEGER,
        eligibleRegions TEXT
    )');
    
    // Players table
    $db->exec('CREATE TABLE IF NOT EXISTS players (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        country TEXT NOT NULL,
        avg REAL NOT NULL DEFAULT 85.0,
        co REAL NOT NULL DEFAULT 35.0,
        fav INTEGER NOT NULL DEFAULT 20,
        money INTEGER NOT NULL DEFAULT 0,
        tourCard INTEGER NOT NULL DEFAULT 0
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
    
    // ==================== PUBLIC DATA ENDPOINTS ====================
    
    // Get all tournaments (for game)
    case '/tournaments':
        if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $db = getDB();
        $result = $db->query('SELECT * FROM tournaments ORDER BY id');
        $tournaments = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['cardRequired'] = (bool)$row['cardRequired'];
            $row['players'] = (int)$row['players'];
            if ($row['targetEvent']) $row['targetEvent'] = (int)$row['targetEvent'];
            if ($row['slots']) $row['slots'] = (int)$row['slots'];
            if ($row['night']) $row['night'] = (int)$row['night'];
            if ($row['eligibleRegions']) {
                $row['eligibleRegions'] = json_decode($row['eligibleRegions'], true);
            }
            $tournaments[] = $row;
        }
        jsonResponse(['success' => true, 'tournaments' => $tournaments]);
        break;
    
    // Get all players (for game)
    case '/players':
        if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $db = getDB();
        $result = $db->query('SELECT * FROM players ORDER BY money DESC, avg DESC');
        $players = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['tourCard'] = (bool)$row['tourCard'];
            $row['avg'] = (float)$row['avg'];
            $row['co'] = (float)$row['co'];
            $row['fav'] = (int)$row['fav'];
            $row['money'] = (int)$row['money'];
            unset($row['id']); // Game doesn't need database ID
            $players[] = $row;
        }
        jsonResponse(['success' => true, 'players' => $players]);
        break;
    
    // ==================== AUTH ENDPOINTS ====================
    
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
    
    // Default - API info and admin routes
    default:
        // Admin routes
        if (preg_match('/^\/admin\/(.+)/', $uri, $matches)) {
            $adminRoute = $matches[1];
            
            // Admin stats
            if ($adminRoute === 'stats') {
                $db = getDB();
                $users = $db->querySingle('SELECT COUNT(*) FROM users');
                $saves = $db->querySingle('SELECT COUNT(*) FROM saves');
                $tournaments = $db->querySingle('SELECT COUNT(*) FROM tournaments');
                $players = $db->querySingle('SELECT COUNT(*) FROM players');
                jsonResponse([
                    'success' => true,
                    'users' => $users,
                    'saves' => $saves,
                    'tournaments' => $tournaments,
                    'players' => $players
                ]);
            }
            
            // ==================== ADMIN USER ROUTES ====================
            
            if ($adminRoute === 'users' && $method === 'GET') {
                $db = getDB();
                $result = $db->query('SELECT u.id, u.username, u.created_at, s.saved_at as last_sync 
                    FROM users u LEFT JOIN saves s ON u.id = s.user_id 
                    GROUP BY u.id ORDER BY u.id DESC');
                $users = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $users[] = $row;
                }
                jsonResponse(['success' => true, 'users' => $users]);
            }
            
            if (preg_match('/^users\/(\d+)$/', $adminRoute, $m) && $method === 'DELETE') {
                $db = getDB();
                $userId = $m[1];
                $db->exec("DELETE FROM saves WHERE user_id = $userId");
                $db->exec("DELETE FROM users WHERE id = $userId");
                jsonResponse(['success' => true]);
            }
            
            // ==================== ADMIN TOURNAMENT ROUTES ====================
            
            if ($adminRoute === 'tournaments' && $method === 'GET') {
                $db = getDB();
                $result = $db->query('SELECT * FROM tournaments ORDER BY id');
                $tournaments = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $row['cardRequired'] = (bool)$row['cardRequired'];
                    if ($row['eligibleRegions']) {
                        $row['eligibleRegions'] = json_decode($row['eligibleRegions'], true);
                    }
                    $tournaments[] = $row;
                }
                jsonResponse(['success' => true, 'tournaments' => $tournaments]);
            }
            
            if ($adminRoute === 'tournaments' && $method === 'POST') {
                $input = getInput();
                $db = getDB();
                
                $eligibleRegions = isset($input['eligibleRegions']) ? json_encode($input['eligibleRegions']) : null;
                
                $stmt = $db->prepare('INSERT OR REPLACE INTO tournaments 
                    (id, name, date, format, players, type, cardRequired, location, region, targetEvent, slots, night, eligibleRegions) 
                    VALUES (:id, :name, :date, :format, :players, :type, :cardRequired, :location, :region, :targetEvent, :slots, :night, :eligibleRegions)');
                $stmt->bindValue(':id', $input['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':name', $input['name'], SQLITE3_TEXT);
                $stmt->bindValue(':date', $input['date'], SQLITE3_TEXT);
                $stmt->bindValue(':format', $input['format'], SQLITE3_TEXT);
                $stmt->bindValue(':players', $input['players'], SQLITE3_INTEGER);
                $stmt->bindValue(':type', $input['type'], SQLITE3_TEXT);
                $stmt->bindValue(':cardRequired', $input['cardRequired'] ? 1 : 0, SQLITE3_INTEGER);
                $stmt->bindValue(':location', $input['location'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':region', $input['region'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':targetEvent', $input['targetEvent'] ?? null, SQLITE3_INTEGER);
                $stmt->bindValue(':slots', $input['slots'] ?? null, SQLITE3_INTEGER);
                $stmt->bindValue(':night', $input['night'] ?? null, SQLITE3_INTEGER);
                $stmt->bindValue(':eligibleRegions', $eligibleRegions, SQLITE3_TEXT);
                $stmt->execute();
                jsonResponse(['success' => true]);
            }
            
            if (preg_match('/^tournaments\/(\d+)$/', $adminRoute, $m) && $method === 'PUT') {
                $input = getInput();
                $db = getDB();
                
                $eligibleRegions = isset($input['eligibleRegions']) ? json_encode($input['eligibleRegions']) : null;
                
                $stmt = $db->prepare('UPDATE tournaments SET 
                    name=:name, date=:date, format=:format, players=:players, type=:type, 
                    cardRequired=:cardRequired, location=:location, region=:region, 
                    targetEvent=:targetEvent, slots=:slots, night=:night, eligibleRegions=:eligibleRegions 
                    WHERE id=:id');
                $stmt->bindValue(':id', $m[1], SQLITE3_INTEGER);
                $stmt->bindValue(':name', $input['name'], SQLITE3_TEXT);
                $stmt->bindValue(':date', $input['date'], SQLITE3_TEXT);
                $stmt->bindValue(':format', $input['format'], SQLITE3_TEXT);
                $stmt->bindValue(':players', $input['players'], SQLITE3_INTEGER);
                $stmt->bindValue(':type', $input['type'], SQLITE3_TEXT);
                $stmt->bindValue(':cardRequired', $input['cardRequired'] ? 1 : 0, SQLITE3_INTEGER);
                $stmt->bindValue(':location', $input['location'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':region', $input['region'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':targetEvent', $input['targetEvent'] ?? null, SQLITE3_INTEGER);
                $stmt->bindValue(':slots', $input['slots'] ?? null, SQLITE3_INTEGER);
                $stmt->bindValue(':night', $input['night'] ?? null, SQLITE3_INTEGER);
                $stmt->bindValue(':eligibleRegions', $eligibleRegions, SQLITE3_TEXT);
                $stmt->execute();
                jsonResponse(['success' => true]);
            }
            
            if (preg_match('/^tournaments\/(\d+)$/', $adminRoute, $m) && $method === 'DELETE') {
                $db = getDB();
                $db->exec("DELETE FROM tournaments WHERE id = " . $m[1]);
                jsonResponse(['success' => true]);
            }
            
            // Bulk import tournaments
            if ($adminRoute === 'tournaments/import' && $method === 'POST') {
                $input = getInput();
                $tournaments = $input['tournaments'] ?? [];
                $db = getDB();
                
                $imported = 0;
                foreach ($tournaments as $t) {
                    $eligibleRegions = isset($t['eligibleRegions']) ? json_encode($t['eligibleRegions']) : null;
                    
                    $stmt = $db->prepare('INSERT OR REPLACE INTO tournaments 
                        (id, name, date, format, players, type, cardRequired, location, region, targetEvent, slots, night, eligibleRegions) 
                        VALUES (:id, :name, :date, :format, :players, :type, :cardRequired, :location, :region, :targetEvent, :slots, :night, :eligibleRegions)');
                    $stmt->bindValue(':id', $t['id'], SQLITE3_INTEGER);
                    $stmt->bindValue(':name', $t['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':date', $t['date'], SQLITE3_TEXT);
                    $stmt->bindValue(':format', $t['format'], SQLITE3_TEXT);
                    $stmt->bindValue(':players', $t['players'], SQLITE3_INTEGER);
                    $stmt->bindValue(':type', $t['type'], SQLITE3_TEXT);
                    $stmt->bindValue(':cardRequired', ($t['cardRequired'] ?? false) ? 1 : 0, SQLITE3_INTEGER);
                    $stmt->bindValue(':location', $t['location'] ?? null, SQLITE3_TEXT);
                    $stmt->bindValue(':region', $t['region'] ?? null, SQLITE3_TEXT);
                    $stmt->bindValue(':targetEvent', $t['targetEvent'] ?? null, SQLITE3_INTEGER);
                    $stmt->bindValue(':slots', $t['slots'] ?? null, SQLITE3_INTEGER);
                    $stmt->bindValue(':night', $t['night'] ?? null, SQLITE3_INTEGER);
                    $stmt->bindValue(':eligibleRegions', $eligibleRegions, SQLITE3_TEXT);
                    $stmt->execute();
                    $imported++;
                }
                jsonResponse(['success' => true, 'imported' => $imported]);
            }
            
            // ==================== ADMIN PLAYER ROUTES ====================
            
            if ($adminRoute === 'players' && $method === 'GET') {
                $db = getDB();
                $result = $db->query('SELECT * FROM players ORDER BY money DESC, avg DESC');
                $players = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $row['tourCard'] = (bool)$row['tourCard'];
                    $players[] = $row;
                }
                jsonResponse(['success' => true, 'players' => $players]);
            }
            
            if ($adminRoute === 'players' && $method === 'POST') {
                $input = getInput();
                $db = getDB();
                
                $stmt = $db->prepare('INSERT INTO players (name, country, avg, co, fav, money, tourCard) 
                    VALUES (:name, :country, :avg, :co, :fav, :money, :tourCard)');
                $stmt->bindValue(':name', $input['name'], SQLITE3_TEXT);
                $stmt->bindValue(':country', $input['country'], SQLITE3_TEXT);
                $stmt->bindValue(':avg', $input['avg'] ?? 85.0, SQLITE3_FLOAT);
                $stmt->bindValue(':co', $input['co'] ?? 35.0, SQLITE3_FLOAT);
                $stmt->bindValue(':fav', $input['fav'] ?? 20, SQLITE3_INTEGER);
                $stmt->bindValue(':money', $input['money'] ?? 0, SQLITE3_INTEGER);
                $stmt->bindValue(':tourCard', ($input['tourCard'] ?? false) ? 1 : 0, SQLITE3_INTEGER);
                $stmt->execute();
                jsonResponse(['success' => true, 'id' => $db->lastInsertRowID()]);
            }
            
            if (preg_match('/^players\/(\d+)$/', $adminRoute, $m) && $method === 'PUT') {
                $input = getInput();
                $db = getDB();
                
                $stmt = $db->prepare('UPDATE players SET 
                    name=:name, country=:country, avg=:avg, co=:co, fav=:fav, money=:money, tourCard=:tourCard 
                    WHERE id=:id');
                $stmt->bindValue(':id', $m[1], SQLITE3_INTEGER);
                $stmt->bindValue(':name', $input['name'], SQLITE3_TEXT);
                $stmt->bindValue(':country', $input['country'], SQLITE3_TEXT);
                $stmt->bindValue(':avg', $input['avg'] ?? 85.0, SQLITE3_FLOAT);
                $stmt->bindValue(':co', $input['co'] ?? 35.0, SQLITE3_FLOAT);
                $stmt->bindValue(':fav', $input['fav'] ?? 20, SQLITE3_INTEGER);
                $stmt->bindValue(':money', $input['money'] ?? 0, SQLITE3_INTEGER);
                $stmt->bindValue(':tourCard', ($input['tourCard'] ?? false) ? 1 : 0, SQLITE3_INTEGER);
                $stmt->execute();
                jsonResponse(['success' => true]);
            }
            
            if (preg_match('/^players\/(\d+)$/', $adminRoute, $m) && $method === 'DELETE') {
                $db = getDB();
                $db->exec("DELETE FROM players WHERE id = " . $m[1]);
                jsonResponse(['success' => true]);
            }
            
            // Bulk import players
            if ($adminRoute === 'players/import' && $method === 'POST') {
                $input = getInput();
                $players = $input['players'] ?? [];
                $db = getDB();
                
                $imported = 0;
                foreach ($players as $p) {
                    $stmt = $db->prepare('INSERT OR REPLACE INTO players (name, country, avg, co, fav, money, tourCard) 
                        VALUES (:name, :country, :avg, :co, :fav, :money, :tourCard)');
                    $stmt->bindValue(':name', $p['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':country', $p['country'], SQLITE3_TEXT);
                    $stmt->bindValue(':avg', $p['avg'] ?? 85.0, SQLITE3_FLOAT);
                    $stmt->bindValue(':co', $p['co'] ?? 35.0, SQLITE3_FLOAT);
                    $stmt->bindValue(':fav', $p['fav'] ?? 20, SQLITE3_INTEGER);
                    $stmt->bindValue(':money', $p['money'] ?? 0, SQLITE3_INTEGER);
                    $stmt->bindValue(':tourCard', ($p['tourCard'] ?? false) ? 1 : 0, SQLITE3_INTEGER);
                    $stmt->execute();
                    $imported++;
                }
                jsonResponse(['success' => true, 'imported' => $imported]);
            }
            
            // Clear all data (for reimport)
            if ($adminRoute === 'tournaments/clear' && $method === 'DELETE') {
                $db = getDB();
                $db->exec("DELETE FROM tournaments");
                jsonResponse(['success' => true]);
            }
            
            if ($adminRoute === 'players/clear' && $method === 'DELETE') {
                $db = getDB();
                $db->exec("DELETE FROM players");
                jsonResponse(['success' => true]);
            }
        }
        
        jsonResponse([
            'name' => 'PDC Darts Simulator API',
            'version' => '1.2.6',
            'endpoints' => [
                'GET /api/tournaments' => 'Get all tournaments',
                'GET /api/players' => 'Get all players',
                'POST /api/register' => 'Register new user',
                'POST /api/login' => 'Login and get token',
                'POST /api/verify' => 'Verify auth token',
                'POST /api/save' => 'Save game data (requires auth)',
                'GET /api/load' => 'Load game data (requires auth)',
                'GET /api/admin/stats' => 'Get admin statistics',
                'GET /api/admin/users' => 'List all users',
                'DELETE /api/admin/users/:id' => 'Delete a user',
                'GET /api/admin/tournaments' => 'List all tournaments',
                'POST /api/admin/tournaments' => 'Add tournament',
                'POST /api/admin/tournaments/import' => 'Bulk import tournaments',
                'DELETE /api/admin/tournaments/clear' => 'Clear all tournaments',
                'PUT /api/admin/tournaments/:id' => 'Update tournament',
                'DELETE /api/admin/tournaments/:id' => 'Delete tournament',
                'GET /api/admin/players' => 'List all players',
                'POST /api/admin/players' => 'Add player',
                'POST /api/admin/players/import' => 'Bulk import players',
                'DELETE /api/admin/players/clear' => 'Clear all players',
                'PUT /api/admin/players/:id' => 'Update player',
                'DELETE /api/admin/players/:id' => 'Delete player'
            ]
        ]);
}
