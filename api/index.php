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
    
    // Saves table (for season mode)
    $db->exec('CREATE TABLE IF NOT EXISTS saves (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        data TEXT NOT NULL,
        saved_at INTEGER NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');
    
    // Career saves table (separate from season saves)
    $db->exec('CREATE TABLE IF NOT EXISTS career_saves (
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
        eligibleRegions TEXT,
        themePreset TEXT,
        theme TEXT,
        color1 TEXT,
        color2 TEXT,
        logo TEXT,
        rounds TEXT,
        prizes TEXT
    )');
    
    // Add columns if they don't exist (for existing databases)
    $db->exec('ALTER TABLE tournaments ADD COLUMN themePreset TEXT');
    $db->exec('ALTER TABLE tournaments ADD COLUMN theme TEXT');
    $db->exec('ALTER TABLE tournaments ADD COLUMN color1 TEXT');
    $db->exec('ALTER TABLE tournaments ADD COLUMN color2 TEXT');
    $db->exec('ALTER TABLE tournaments ADD COLUMN logo TEXT');
    $db->exec('ALTER TABLE tournaments ADD COLUMN rounds TEXT');
    $db->exec('ALTER TABLE tournaments ADD COLUMN prizes TEXT');
    
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
    
    // Themes table - stores custom themes that override $EVENT_THEMES defaults
    $db->exec('CREATE TABLE IF NOT EXISTS themes (
        id TEXT PRIMARY KEY,
        color1 TEXT NOT NULL,
        color2 TEXT NOT NULL,
        logo TEXT,
        theme TEXT,
        activeGradient1 TEXT,
        activeGradient2 TEXT,
        legNameColor TEXT,
        is_deleted INTEGER NOT NULL DEFAULT 0
    )');
    
    // Add is_deleted column if it doesn't exist
    $db->exec('ALTER TABLE themes ADD COLUMN is_deleted INTEGER NOT NULL DEFAULT 0');
    
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

// Event themes data - EXACT copy from admin.html DEFAULT_APPEARANCE
// Keys match tournament types, values include colors, logo, and CSS class
$EVENT_THEMES = [
    'matchplay' => [
        'color1' => '#8B0000', 'color2' => '#B22222', 
        'logo' => 'worldmatchplay.png', 'theme' => 'theme-matchplay',
        'activeGradient' => ['#B22222', '#8B0000'], 'legNameColor' => 'white'
    ],
    'uk-open' => [
        'color1' => '#C8102E', 'color2' => '#FFD100', 
        'logo' => 'ukopen.png', 'theme' => 'theme-uk-open',
        'activeGradient' => ['#8402db', '#C8102E'], 'legNameColor' => 'white'
    ],
    'playersf' => [
        'color1' => '#111111', 'color2' => '#C8102E', 
        'logo' => 'playersc.png', 'theme' => 'theme-players-championship',
        'activeGradient' => ['#C8102E', '#111111'], 'legNameColor' => 'white'
    ],
    'grandslam' => [
        'color1' => '#FF8C00', 'color2' => '#111111', 
        'logo' => 'mrvegas.png', 'theme' => 'theme-grand-slam',
        'activeGradient' => ['#FF8C00', '#D97706'], 'legNameColor' => 'white'
    ],
    'masters-final' => [
        'color1' => '#7A0000', 'color2' => '#000000', 
        'logo' => 'worldseriesofdarts.png', 'theme' => 'theme-world-series',
        'activeGradient' => ['#000000', '#7A0000'], 'legNameColor' => 'white'
    ],
    'grandprix' => [
        'color1' => '#0033A0', 'color2' => '#00AEEF', 
        'logo' => 'grandprix.png', 'theme' => 'theme-world-grandprix',
        'activeGradient' => ['#fd0202', '#000000'], 'legNameColor' => 'white'
    ],
    'winmaudm' => [
        'color1' => '#000000', 'color2' => '#FFFFFF', 
        'logo' => 'worldmasters.png', 'theme' => 'theme-winmau',
        'activeGradient' => ['#FFFFFF', '#000000'], 'legNameColor' => '#000'
    ],
    'europeanf' => [
        'color1' => '#5B2D8B', 'color2' => '#C0C0C0', 
        'logo' => 'europeanchampionship.png', 'theme' => 'theme-european',
        'activeGradient' => ['#C0C0C0', '#5B2D8B'], 'legNameColor' => '#000'
    ],
    'world' => [
        'color1' => '#0C3B2E', 'color2' => '#1E7F43', 
        'logo' => 'worldchampionship.png', 'theme' => 'theme-world-championship',
        'activeGradient' => ['#1E7F43', '#0C3B2E'], 'legNameColor' => 'white'
    ],
    'players' => [
        'color1' => '#111111', 'color2' => '#C8102E', 
        'logo' => 'playersc.png', 'theme' => 'theme-players-championship',
        'activeGradient' => ['#C8102E', '#111111'], 'legNameColor' => 'white'
    ],
    'premier-league' => [
        'color1' => '#0A1AFF', 'color2' => '#001B5E', 
        'logo' => 'premierleague.png', 'theme' => 'theme-premier-league',
        'activeGradient' => ['#FFD100', '#796d02'], 'legNameColor' => 'white'
    ],
    'premier-league-playoff' => [
        'color1' => '#0A1AFF', 'color2' => '#001B5E', 
        'logo' => 'premierleague.png', 'theme' => 'theme-premier-league',
        'activeGradient' => ['#FFD100', '#796d02'], 'legNameColor' => 'white'
    ],
    'masters' => [
        'color1' => '#000000', 'color2' => '#D4AF37', 
        'logo' => 'worldseries.png', 'theme' => 'theme-masters',
        'activeGradient' => ['#D4AF37', '#000000'], 'legNameColor' => '#000'
    ],
    'minor' => [
        'color1' => '#1A1A1A', 'color2' => '#2E2E2E', 
        'logo' => '', 'theme' => 'theme-minor',
        'activeGradient' => ['#2E2E2E', '#1A1A1A'], 'legNameColor' => 'white'
    ],
    'challenge' => [
        'color1' => '#1A1A1A', 'color2' => '#2E2E2E', 
        'logo' => '', 'theme' => 'theme-minor',
        'activeGradient' => ['#2E2E2E', '#1A1A1A'], 'legNameColor' => 'white'
    ],
    'q-school' => [
        'color1' => '#1A1A1A', 'color2' => '#2E2E2E', 
        'logo' => '', 'theme' => 'theme-minor',
        'activeGradient' => ['#2E2E2E', '#1A1A1A'], 'legNameColor' => 'white'
    ],
    'et-qual-tc' => [
        'color1' => '#1A1A1A', 'color2' => '#2E2E2E', 
        'logo' => '', 'theme' => 'theme-minor',
        'activeGradient' => ['#2E2E2E', '#1A1A1A'], 'legNameColor' => 'white'
    ],
    'et-host-nation' => [
        'color1' => '#1A1A1A', 'color2' => '#2E2E2E', 
        'logo' => '', 'theme' => 'theme-minor',
        'activeGradient' => ['#2E2E2E', '#1A1A1A'], 'legNameColor' => 'white'
    ],
    'et-east-european' => [
        'color1' => '#1A1A1A', 'color2' => '#2E2E2E', 
        'logo' => '', 'theme' => 'theme-minor',
        'activeGradient' => ['#2E2E2E', '#1A1A1A'], 'legNameColor' => 'white'
    ],
    'et-nordic-baltic' => [
        'color1' => '#1A1A1A', 'color2' => '#2E2E2E', 
        'logo' => '', 'theme' => 'theme-minor',
        'activeGradient' => ['#2E2E2E', '#1A1A1A'], 'legNameColor' => 'white'
    ],
    'ws-qual-tc' => [
        'color1' => '#7A0000', 'color2' => '#000000', 
        'logo' => 'worldseries.png', 'theme' => 'theme-world-series',
        'activeGradient' => ['#000000', '#7A0000'], 'legNameColor' => 'white'
    ],
    'gs-qual-tc' => [
        'color1' => '#FF8C00', 'color2' => '#111111', 
        'logo' => 'mrvegas.png', 'theme' => 'theme-grand-slam',
        'activeGradient' => ['#FF8C00', '#D97706'], 'legNameColor' => 'white'
    ],
    'wmasters-qual' => [
        'color1' => '#000000', 'color2' => '#FFFFFF', 
        'logo' => 'worldmasters.png', 'theme' => 'theme-winmau',
        'activeGradient' => ['#FFFFFF', '#000000'], 'legNameColor' => '#000'
    ],
    'wc-qual-intl' => [
        'color1' => '#0C3B2E', 'color2' => '#1E7F43', 
        'logo' => 'worldchampionship.png', 'theme' => 'theme-world-championship',
        'activeGradient' => ['#1E7F43', '#0C3B2E'], 'legNameColor' => 'white'
    ],
    'wc-qual-tc' => [
        'color1' => '#0C3B2E', 'color2' => '#1E7F43', 
        'logo' => 'worldchampionship.png', 'theme' => 'theme-world-championship',
        'activeGradient' => ['#1E7F43', '#0C3B2E'], 'legNameColor' => 'white'
    ]
];

// Helper to get themes with database overrides
function getThemesWithOverrides($eventThemes) {
    $db = getDB();
    $result = $db->query('SELECT * FROM themes');
    $themes = $eventThemes; // Start with defaults
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $id = $row['id'];
        $themes[$id] = [
            'color1' => $row['color1'],
            'color2' => $row['color2'],
            'logo' => $row['logo'] ?: '',
            'theme' => $row['theme'] ?: '',
            'activeGradient' => $row['activeGradient1'] && $row['activeGradient2'] 
                ? [$row['activeGradient1'], $row['activeGradient2']] 
                : null,
            'legNameColor' => $row['legNameColor'] ?: 'white',
            'is_deleted' => (bool)$row['is_deleted']
        ];
        // Remove null activeGradient
        if (!$themes[$id]['activeGradient']) {
            unset($themes[$id]['activeGradient']);
        }
    }
    
    // Filter out deleted themes
    return array_filter($themes, function($t) {
        return !isset($t['is_deleted']) || !$t['is_deleted'];
    });
}

// API Routes
switch ($uri) {
    
    // ==================== PUBLIC DATA ENDPOINTS ====================
    
    // Get all event themes (merges database themes with defaults)
    case '/themes':
        if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
        $themes = getThemesWithOverrides($EVENT_THEMES);
        jsonResponse(['success' => true, 'themes' => $themes]);
        break;
    
    // Assign themes to all tournaments based on their type (which matches admin.html DEFAULT_APPEARANCE keys)
    case '/assign-themes':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $db = getDB();
        $result = $db->query('SELECT id, name, type FROM tournaments');
        $updated = 0;
        $assignments = [];
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tournamentType = $row['type'];
            
            // The tournament type directly maps to admin.html DEFAULT_APPEARANCE keys
            // So just use the type as the theme
            $theme = $tournamentType;
            
            if ($theme) {
                $stmt = $db->prepare('UPDATE tournaments SET theme = :theme WHERE id = :id');
                $stmt->bindValue(':theme', $theme, SQLITE3_TEXT);
                $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $stmt->execute();
                $updated++;
                $assignments[] = ['id' => $row['id'], 'name' => $row['name'], 'type' => $tournamentType, 'theme' => $theme];
            }
        }
        
        jsonResponse(['success' => true, 'updated' => $updated, 'assignments' => $assignments]);
        break;
    
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
            if (isset($row['rounds']) && $row['rounds']) {
                $row['rounds'] = json_decode($row['rounds'], true);
            }
            if (isset($row['prizes']) && $row['prizes']) {
                $row['prizes'] = json_decode($row['prizes'], true);
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
        $saveType = $input['saveType'] ?? 'season'; // 'season' or 'career_v2'

        if (!$data) {
            jsonResponse(['success' => false, 'error' => 'No data provided']);
        }

        $targetTable = ($saveType === 'career_v2') ? 'career_saves' : 'saves';

        try {
            $db = getDB();

            // Delete old saves for this user (keep only latest of this type)
            $stmt = $db->prepare("DELETE FROM $targetTable WHERE user_id = :user_id");
            $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
            $stmt->execute();

            // Insert new save
            $stmt = $db->prepare("INSERT INTO $targetTable (user_id, data, saved_at) VALUES (:user_id, :data, :saved_at)");
            $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
            $stmt->bindValue(':data', $data, SQLITE3_TEXT);
            $stmt->bindValue(':saved_at', time(), SQLITE3_INTEGER);
            $stmt->execute();

            jsonResponse(['success' => true, 'savedAt' => time(), 'type' => $saveType]);
        } catch (Exception $e) {
            error_log("Save failed for user {$user['userId']}: " . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Database error'], 500);
        }
        break;

    // Load game data
    case '/load':
        if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $user = getAuthUser();
        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }
        
        $saveType = $_GET['type'] ?? 'season';
        $targetTable = ($saveType === 'career_v2') ? 'career_saves' : 'saves';
        
        $db = getDB();
        $stmt = $db->prepare("SELECT data, saved_at FROM $targetTable WHERE user_id = :user_id ORDER BY saved_at DESC LIMIT 1");
        $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
        $save = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if ($save) {
            jsonResponse([
                'success' => true,
                'data' => $save['data'],
                'savedAt' => $save['saved_at'],
                'type' => $saveType
            ]);
        } else {
            jsonResponse(['success' => true, 'data' => null, 'type' => $saveType]);
        }
        break;
    
    // Save career data (separate from season saves)
    case '/save-career':
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
        
        // Delete old career saves for this user (keep only latest)
        $stmt = $db->prepare('DELETE FROM career_saves WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
        $stmt->execute();
        
        // Insert new career save
        $stmt = $db->prepare('INSERT INTO career_saves (user_id, data, saved_at) VALUES (:user_id, :data, :saved_at)');
        $stmt->bindValue(':user_id', $user['userId'], SQLITE3_INTEGER);
        $stmt->bindValue(':data', $data, SQLITE3_TEXT);
        $stmt->bindValue(':saved_at', time(), SQLITE3_INTEGER);
        $stmt->execute();
        
        jsonResponse(['success' => true, 'savedAt' => time()]);
        break;
    
    // Load career data
    case '/load-career':
        if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $user = getAuthUser();
        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
        }
        
        $db = getDB();
        $stmt = $db->prepare('SELECT data, saved_at FROM career_saves WHERE user_id = :user_id ORDER BY saved_at DESC LIMIT 1');
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
            
            // Admin saves monitoring
            if ($adminRoute === 'saves' && $method === 'GET') {
                $db = getDB();
                $type = $_GET['type'] ?? 'season';
                $table = ($type === 'career_v2') ? 'career_saves' : 'saves';
                
                $result = $db->query("SELECT s.id, u.username, s.data, s.saved_at 
                    FROM $table s JOIN users u ON s.user_id = u.id 
                    ORDER BY s.saved_at DESC");
                
                $saves = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $data = json_decode($row['data'], true);
                    
                    // Extract player info for the admin table
                    $playerName = 'Unknown';
                    $rank = '-';
                    $avg = 0;
                    $money = 0;
                    
                    if ($type === 'career_v2' && isset($data['season']['career'])) {
                        $career = $data['season']['career'];
                        $playerName = $career['playerName'] ?? 'Unknown';
                        $money = $career['money'] ?? 0;
                        
                        // Find this player in rankings if available
                        if (isset($data['season']['playerRankings'])) {
                            foreach ($data['season']['playerRankings'] as $r => $pName) {
                                if ($pName === $playerName) {
                                    $rank = $r;
                                    break;
                                }
                            }
                        }
                        
                        // Find in players array for average
                        if (isset($data['players'])) {
                            foreach ($data['players'] as $p) {
                                if ($p['n'] === $playerName) {
                                    $avg = $p['avg'] ?? ($p['avg_running'] ?? 0);
                                    break;
                                }
                            }
                        }
                    }
                    
                    $saves[] = [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'playerName' => $playerName,
                        'rank' => $rank,
                        'avg' => round($avg, 1),
                        'money' => $money,
                        'updatedAt' => date('Y-m-d H:i:s', $row['saved_at']),
                        'data' => $row['data'] // For "View Data" button
                    ];
                }
                jsonResponse(['success' => true, 'saves' => $saves]);
            }

            // Admin stats
            if ($adminRoute === 'stats') {
                $db = getDB();
                $users = $db->querySingle('SELECT COUNT(*) FROM users');
                $saves = $db->querySingle('SELECT COUNT(*) FROM saves');
                $careerSaves = $db->querySingle('SELECT COUNT(*) FROM career_saves');
                $tournaments = $db->querySingle('SELECT COUNT(*) FROM tournaments');
                $players = $db->querySingle('SELECT COUNT(*) FROM players');
                $themes = $db->querySingle('SELECT COUNT(*) FROM themes');
                jsonResponse([
                    'success' => true,
                    'users' => $users,
                    'saves' => $saves,
                    'careerSaves' => $careerSaves,
                    'tournaments' => $tournaments,
                    'players' => $players,
                    'themes' => $themes
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
                $db->exec("DELETE FROM career_saves WHERE user_id = $userId");
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
                    if (isset($row['rounds']) && $row['rounds']) {
                        $row['rounds'] = json_decode($row['rounds'], true);
                    }
                    if (isset($row['prizes']) && $row['prizes']) {
                        $row['prizes'] = json_decode($row['prizes'], true);
                    }
                    $tournaments[] = $row;
                }
                jsonResponse(['success' => true, 'tournaments' => $tournaments]);
            }
            
            if ($adminRoute === 'tournaments' && $method === 'POST') {
                $input = getInput();
                $db = getDB();
                
                $eligibleRegions = isset($input['eligibleRegions']) ? json_encode($input['eligibleRegions']) : null;
                $rounds = isset($input['rounds']) ? json_encode($input['rounds']) : null;
                $prizes = isset($input['prizes']) ? json_encode($input['prizes']) : null;
                
                $stmt = $db->prepare('INSERT OR REPLACE INTO tournaments 
                    (id, name, date, format, players, type, cardRequired, location, region, targetEvent, slots, night, eligibleRegions, themePreset, logo, rounds, prizes) 
                    VALUES (:id, :name, :date, :format, :players, :type, :cardRequired, :location, :region, :targetEvent, :slots, :night, :eligibleRegions, :themePreset, :logo, :rounds, :prizes)');
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
                $stmt->bindValue(':themePreset', $input['themePreset'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':logo', $input['logo'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':rounds', $rounds, SQLITE3_TEXT);
                $stmt->bindValue(':prizes', $prizes, SQLITE3_TEXT);
                $stmt->execute();
                jsonResponse(['success' => true]);
            }
            
            if (preg_match('/^tournaments\/(\d+)$/', $adminRoute, $m) && $method === 'PUT') {
                $input = getInput();
                $db = getDB();
                
                $eligibleRegions = isset($input['eligibleRegions']) ? json_encode($input['eligibleRegions']) : null;
                $rounds = isset($input['rounds']) ? json_encode($input['rounds']) : null;
                $prizes = isset($input['prizes']) ? json_encode($input['prizes']) : null;
                
                $stmt = $db->prepare('UPDATE tournaments SET 
                    name=:name, date=:date, format=:format, players=:players, type=:type, 
                    cardRequired=:cardRequired, location=:location, region=:region, 
                    targetEvent=:targetEvent, slots=:slots, night=:night, eligibleRegions=:eligibleRegions,
                    themePreset=:themePreset, logo=:logo, rounds=:rounds, prizes=:prizes 
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
                $stmt->bindValue(':themePreset', $input['themePreset'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':logo', $input['logo'] ?? null, SQLITE3_TEXT);
                $stmt->bindValue(':rounds', $rounds, SQLITE3_TEXT);
                $stmt->bindValue(':prizes', $prizes, SQLITE3_TEXT);
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
                    $rounds = isset($t['rounds']) ? json_encode($t['rounds']) : null;
                    $prizes = isset($t['prizes']) ? json_encode($t['prizes']) : null;
                    
                    $stmt = $db->prepare('INSERT OR REPLACE INTO tournaments 
                        (id, name, date, format, players, type, cardRequired, location, region, targetEvent, slots, night, eligibleRegions, themePreset, logo, rounds, prizes) 
                        VALUES (:id, :name, :date, :format, :players, :type, :cardRequired, :location, :region, :targetEvent, :slots, :night, :eligibleRegions, :themePreset, :logo, :rounds, :prizes)');
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
                    $stmt->bindValue(':themePreset', $t['themePreset'] ?? null, SQLITE3_TEXT);
                    $stmt->bindValue(':logo', $t['logo'] ?? null, SQLITE3_TEXT);
                    $stmt->bindValue(':rounds', $rounds, SQLITE3_TEXT);
                    $stmt->bindValue(':prizes', $prizes, SQLITE3_TEXT);
                    $stmt->execute();
                    $imported++;
                }
                jsonResponse(['success' => true, 'imported' => $imported]);
            }
            
            // ==================== ADMIN THEME ROUTES ====================
            
            // Get all themes (defaults + custom from DB)
            if ($adminRoute === 'themes' && $method === 'GET') {
                $db = getDB();
                
                // Get all custom themes from database
                $result = $db->query('SELECT * FROM themes');
                $customThemes = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $customThemes[$row['id']] = $row;
                }
                
                // Merge with defaults - mark which are custom
                $allThemes = [];
                foreach ($EVENT_THEMES as $id => $theme) {
                    $themeData = [
                        'id' => $id,
                        'color1' => $theme['color1'],
                        'color2' => $theme['color2'],
                        'logo' => $theme['logo'] ?? '',
                        'theme' => $theme['theme'] ?? '',
                        'activeGradient1' => isset($theme['activeGradient']) ? $theme['activeGradient'][0] : '',
                        'activeGradient2' => isset($theme['activeGradient']) ? $theme['activeGradient'][1] : '',
                        'legNameColor' => $theme['legNameColor'] ?? 'white',
                        'isCustom' => false,
                        'isDefault' => true
                    ];
                    
                    // Override with custom if exists
                    if (isset($customThemes[$id])) {
                        $custom = $customThemes[$id];
                        $themeData['color1'] = $custom['color1'];
                        $themeData['color2'] = $custom['color2'];
                        $themeData['logo'] = $custom['logo'] ?: $themeData['logo'];
                        $themeData['theme'] = $custom['theme'] ?: $themeData['theme'];
                        $themeData['activeGradient1'] = $custom['activeGradient1'] ?: $themeData['activeGradient1'];
                        $themeData['activeGradient2'] = $custom['activeGradient2'] ?: $themeData['activeGradient2'];
                        $themeData['legNameColor'] = $custom['legNameColor'] ?: $themeData['legNameColor'];
                        $themeData['isCustom'] = true;
                        unset($customThemes[$id]);
                    }
                    
                    $allThemes[] = $themeData;
                }
                
                // Add any custom themes not in defaults
                foreach ($customThemes as $id => $custom) {
                    $allThemes[] = [
                        'id' => $id,
                        'color1' => $custom['color1'],
                        'color2' => $custom['color2'],
                        'logo' => $custom['logo'] ?: '',
                        'theme' => $custom['theme'] ?: '',
                        'activeGradient1' => $custom['activeGradient1'] ?: '',
                        'activeGradient2' => $custom['activeGradient2'] ?: '',
                        'legNameColor' => $custom['legNameColor'] ?: 'white',
                        'isCustom' => true,
                        'isDefault' => false,
                        'isDeleted' => (bool)($custom['is_deleted'] ?? 0)
                    ];
                }
                
                // Final filtering: If it's custom and deleted, hide it completely. 
                // If it's default and deleted, hide it from the list unless we want to allow management.
                // For now, let's keep all in the admin list but mark them, OR just hide them.
                // User wants to DELETE, so let's hide them from the standard grid.
                $adminThemes = array_filter($allThemes, function($t) {
                    return !($t['isDeleted'] ?? false);
                });
                
                jsonResponse(['success' => true, 'themes' => array_values($adminThemes)]);
            }
            
            // Save/Update a theme
            if ($adminRoute === 'themes' && $method === 'POST') {
                $input = getInput();
                $db = getDB();
                
                if (empty($input['id'])) {
                    jsonResponse(['error' => 'Theme ID is required'], 400);
                }
                
                $stmt = $db->prepare('INSERT OR REPLACE INTO themes 
                    (id, color1, color2, logo, theme, activeGradient1, activeGradient2, legNameColor, is_deleted) 
                    VALUES (:id, :color1, :color2, :logo, :theme, :ag1, :ag2, :legNameColor, 0)');
                $stmt->bindValue(':id', $input['id'], SQLITE3_TEXT);
                $stmt->bindValue(':color1', $input['color1'], SQLITE3_TEXT);
                $stmt->bindValue(':color2', $input['color2'], SQLITE3_TEXT);
                $stmt->bindValue(':logo', $input['logo'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':theme', $input['theme'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':ag1', $input['activeGradient1'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':ag2', $input['activeGradient2'] ?? '', SQLITE3_TEXT);
                $stmt->bindValue(':legNameColor', $input['legNameColor'] ?? 'white', SQLITE3_TEXT);
                $stmt->execute();
                
                jsonResponse(['success' => true]);
            }
            
            // Delete a theme (soft-delete defaults, hard-delete customs)
            if (preg_match('/^themes\/(.+)$/', $adminRoute, $m) && $method === 'DELETE') {
                $themeId = $m[1];
                $db = getDB();
                
                // Check if it's a default theme
                global $EVENT_THEMES;
                if (isset($EVENT_THEMES[$themeId])) {
                    // It's a default theme, mark as deleted in overrides table
                    $stmt = $db->prepare('INSERT OR REPLACE INTO themes 
                        (id, color1, color2, logo, theme, activeGradient1, activeGradient2, legNameColor, is_deleted) 
                        SELECT id, color1, color2, logo, theme, activeGradient1, activeGradient2, legNameColor, 1 
                        FROM themes WHERE id = :id 
                        UNION ALL 
                        SELECT :id2, :c1, :c2, :l, :t, :ag1, :ag2, :lnc, 1 
                        WHERE NOT EXISTS (SELECT 1 FROM themes WHERE id = :id3)');
                    
                    // This is complex because we need to insert a default if it doesn't exist yet
                    $def = $EVENT_THEMES[$themeId];
                    $stmt->bindValue(':id', $themeId, SQLITE3_TEXT);
                    $stmt->bindValue(':id2', $themeId, SQLITE3_TEXT);
                    $stmt->bindValue(':id3', $themeId, SQLITE3_TEXT);
                    $stmt->bindValue(':c1', $def['color1'], SQLITE3_TEXT);
                    $stmt->bindValue(':c2', $def['color2'], SQLITE3_TEXT);
                    $stmt->bindValue(':l', $def['logo'] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(':t', $def['theme'] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(':ag1', $def['activeGradient'][0] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(':ag2', $def['activeGradient'][1] ?? '', SQLITE3_TEXT);
                    $stmt->bindValue(':lnc', $def['legNameColor'] ?? 'white', SQLITE3_TEXT);
                    $stmt->execute();
                    jsonResponse(['success' => true, 'message' => 'Default theme hidden']);
                } else {
                    // It's a custom theme, permanent delete
                    $stmt = $db->prepare('DELETE FROM themes WHERE id = :id');
                    $stmt->bindValue(':id', $themeId, SQLITE3_TEXT);
                    $stmt->execute();
                    jsonResponse(['success' => true, 'message' => 'Custom theme deleted']);
                }
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
            
            // Remove duplicate players (handles "First Last" vs "Last, First" format)
            if ($adminRoute === 'players/deduplicate' && $method === 'POST') {
                $db = getDB();
                $beforeCount = $db->querySingle('SELECT COUNT(*) FROM players');
                
                // Get all players
                $result = $db->query('SELECT id, name FROM players ORDER BY id');
                $players = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $players[] = $row;
                }
                
                // Normalize name: "Last, First" -> "First Last"
                function normalizeName($name) {
                    $name = trim($name);
                    if (strpos($name, ',') !== false) {
                        $parts = explode(',', $name, 2);
                        return trim($parts[1]) . ' ' . trim($parts[0]);
                    }
                    return $name;
                }
                
                // Find duplicates
                $seen = [];
                $toDelete = [];
                foreach ($players as $p) {
                    $normalized = strtolower(normalizeName($p['name']));
                    if (isset($seen[$normalized])) {
                        $toDelete[] = $p['id'];
                    } else {
                        $seen[$normalized] = $p['id'];
                    }
                }
                
                // Delete duplicates
                if (!empty($toDelete)) {
                    $ids = implode(',', $toDelete);
                    $db->exec("DELETE FROM players WHERE id IN ($ids)");
                }
                
                $afterCount = $db->querySingle('SELECT COUNT(*) FROM players');
                $removed = $beforeCount - $afterCount;
                jsonResponse(['success' => true, 'removed' => $removed, 'remaining' => $afterCount]);
            }
        }
        
        jsonResponse([
            'name' => 'PDC Darts Simulator API',
            'version' => '1.2.7',
            'endpoints' => [
                'GET /api/tournaments' => 'Get all tournaments',
                'GET /api/players' => 'Get all players',
                'POST /api/register' => 'Register new user',
                'POST /api/login' => 'Login and get token',
                'POST /api/verify' => 'Verify auth token',
                'POST /api/save' => 'Save season game data (requires auth)',
                'GET /api/load' => 'Load season game data (requires auth)',
                'POST /api/save-career' => 'Save career mode data (requires auth)',
                'GET /api/load-career' => 'Load career mode data (requires auth)',
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
#MUKODDJE
