<?php
require_once __DIR__ . '/../config/jwt_config.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class JWTMiddleware {
     private static function getAllHeaders() {
      
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$header] = $value;
                }
            }
            return $headers;
        }
        return getallheaders();
    }
    
    public static function verifyToken() {
        $headers = self::getAllHeaders();
        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(['message' => 'No token provided']);
            exit();
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);

        try {
            $decoded = JWT::decode($token, new Key(JWTConfig::getSecretKey(), 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid token']);
            exit();
        }
    }
}