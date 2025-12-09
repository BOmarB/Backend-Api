<?php
include_once '../models/User.php';
require_once '../config/jwt_config.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class UserController
{
    private $user;

    public function __construct($db)
    {
        $this->user = new User($db);
    }



    public function addUsers($data)
    {

        $this->user->full_name = $data['full_name'];
        $this->user->email = $data['email'];
        $this->user->password = $data['password'];
        $this->user->role = $data['role'];
        $this->user->cin = $data['role'] !== "student" ? $data['cin'] = "" : $data['cin'];
        $this->user->status = $data['role'] === "student" ? "Unpermissioned" : "permissioned";



        if ($this->user->addUser()) {
            return ['message' => 'User registered successfully'];
        }
        return ['message' => 'Failed to register user'];
    }

    public function updatePermissions($data)
    {
        if ($this->user->updatePermissions($data['studentIds'], $data['groupId'], $data['action'])) {
            return [
                'success' => true,
                'message' => $data['action'] === 'permit' ? 'Permissions granted successfully' : 'Permissions removed successfully'
            ];
        }
        return ['success' => false, 'message' => 'Failed to update permissions'];
    }

    public function refreshToken($data)
    {
        try {
            // Verify the refresh token
            $refresh_token = $data['refresh_token'];
            $decoded = JWT::decode($refresh_token, new Key(JWTConfig::getRefreshKey(), 'HS256'));

            // Get user details
            $this->user->id = $decoded->sub;
            $user = $this->user->getUserById();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Generate new access token
            $secretKey = JWTConfig::getSecretKey();
            $access_token = JWT::encode([
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'exp' => time() + (15 * 60) // 15 minutes
            ], $secretKey, 'HS256');

            return [
                'success' => true,
                'access_token' => $access_token
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid refresh token'
            ];
        }
    }
    public function login($data)
    {
        $this->user->email = $data['email'];
        $this->user->password = $data['password'];
        $user = $this->user->loginUser();
        if ($user) {
            $this->user->id = $user['id'];
            $this->user->email = $user['email'];
            $this->user->role = $user['role'];

            $tokens = $this->user->generateJWTToken();
            return [
                'success' => true,
                'message' => 'User login successful',
                'user' => [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'status' => $user['status']
                ],
                'tokens' => $tokens
            ];
        }
        return ['message' => 'Failed to login user'];
    }

    public function checkUserStatus($userId)
    {
        $status = $this->user->getUserStatus($userId);
        return ['status' => $status];
    }
    public function getUsers()
    {
        return $this->user->getUser();
    }
    public function getUserById($userId)
    {
        return $this->user->getUserById2($userId);
    }
    public function updateUser($userId, $userData)
    {
        // Check if user exists
        $existingUser = $this->user->getUserById2($userId);
        if (!$existingUser || empty($existingUser)) {
            return [
                'status' => 'error',
                'message' => 'User not found'
            ];
        }

        // Handle password if provided
        if (!empty($userData['password'])) {
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        } else {
            // Remove password field if it's empty to avoid overriding with empty value
            unset($userData['password']);
        }

        return $this->user->updateUser($userId, $userData);
    }

    public function deleteUser($userId)
    {
        // Check if user exists
        $existingUser = $this->user->getUserById2($userId);
        if (!$existingUser || empty($existingUser)) {
            return [
                'status' => 'error',
                'message' => 'User not found'
            ];
        }

        // Only allow admin to delete users
        $tokens = apache_request_headers();
        $auth = isset($tokens['Authorization']) ? $tokens['Authorization'] : '';

        if (!$auth) {
            return [
                'status' => 'error',
                'message' => 'Unauthorized access'
            ];
        }

        try {
            $token = str_replace('Bearer ', '', $auth);
            $decoded = JWT::decode($token, new Key(JWTConfig::getSecretKey(), 'HS256'));

            if ($decoded->role !== 'admin') {
                return [
                    'status' => 'error',
                    'message' => 'Only administrators can delete users'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Invalid authentication token'
            ];
        }

        return $this->user->deleteUser($userId);
    }
}
