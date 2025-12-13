<?php
header("Access-Control-Allow-Origin: https://examlyfront.vercel.app");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Show errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug information - REMOVE AFTER TESTING
//echo json_encode([
//    'REQUEST_URI' => $_SERVER['REQUEST_URI'],
//    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
//    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
//    'message' => 'index.php is loading'
//]);
//exit;
header('Content-Type: application/json');

// Check if files exist
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die(json_encode(['error' => 'vendor/autoload.php not found']));
}

if (!file_exists(__DIR__ . '/api/routes/api.php')) {
    die(json_encode(['error' => 'api/routes/api.php not found']));
}

// Load dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Route to API
require_once __DIR__ . '/api/routes/api.php';