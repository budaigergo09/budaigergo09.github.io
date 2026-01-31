<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

if (strpos($path, '/api/') === 0) {
    $_GET['route'] = substr($path, 5);
    include __DIR__ . '/api/index.php';
    exit;
}

$file = __DIR__ . $path;
if ($path === '/' || $path === '') {
    $file = __DIR__ . '/darts.html';
}

if (is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'mp3' => 'audio/mpeg',
        'MP3' => 'audio/mpeg',
        'ttf' => 'font/ttf',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'txt' => 'text/plain',
    ];
    
    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-cache');
    readfile($file);
    exit;
}

http_response_code(404);
echo "404 Not Found";
