<?php
require_once __DIR__ . '/../config/jwt_config.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class JWTMiddleware {
    public static function verifyToken() {
        $headers = getallheaders();
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