<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User

{

  private $conn;
  private $table = "users";

  public $id;
  public $full_name;
  public $email;
  public $password;
  public $role;
  public $created_at;
  public $updated_at;
  public $status;
  public $cin;


  public function __construct($db)
  {
    $this->conn = $db;
  }


  public function loginUser()
  {
    $query = "SELECT full_name, id, email, password, role, status FROM " . $this->table . " WHERE email=:email LIMIT 1;";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":email", $this->email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($this->password, $user["password"])) {
      return $user;
    } else {
      return false;
    }
  }

  public function generateJWTToken()
  {
    $secretKey = JWTConfig::getSecretKey();
    $refreshKey = JWTConfig::getRefreshKey();

    $issuedAt = time();
    $accessExpiration = $issuedAt + (15 * 60); // 15 minutes
    $refreshExpiration = $issuedAt + (7 * 24 * 60 * 60); // 7 days

    $accessToken = JWT::encode([
      'iss' => 'examly-api', // issuer
      'aud' => 'examly-web-client', // audience
      'iat' => $issuedAt, // issued at
      // 'nbf' => $issuedAt, // not before
      'exp' => $accessExpiration,
      'user_id' => $this->id,
      'email' => $this->email,
      'role' => $this->role,
      'jti' => bin2hex(random_bytes(16)) // unique identifier
    ], $secretKey, 'HS256');

    $refreshToken = JWT::encode([
      'iss' => 'examly-api',
      'sub' => $this->id,
      'iat' => $issuedAt,
      'exp' => $refreshExpiration,
      'jti' => bin2hex(random_bytes(16))
    ], $refreshKey, 'HS256');

    return [
      'access_token' => $accessToken,
      'refresh_token' => $refreshToken,
      'expires_in' => 900 // 15 minutes in seconds
    ];
  }


  public function getUserById()
  {
    $query = "SELECT id, full_name, email, role, status FROM " . $this->table . " WHERE id = :id LIMIT 1";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":id", $this->id);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  public function getProfile($userId)
  {
    try {
      $query = "SELECT 
                    id,
                    email,
                    full_name,
                    role,
                    profile_picture_url,
                    phone,
                    address,
                    bio,
                    date_of_birth,
                    gender,
                    social_links,
                    status,
                    created_at,
                    updated_at
                FROM " . $this->table . "
                WHERE id = :userId";

      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":userId", $userId);
      $stmt->execute();

      if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
      }
      return false;
    } catch (PDOException $e) {
      return false;
    }
  }

  public function updateProfile($userId, $data)
  {
    try {
      $allowedFields = [
        'full_name',
        'phone',
        'address',
        'bio',
        'date_of_birth',
        'gender',
        'social_links'
      ];

      $updateFields = [];
      $values = [];

      foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
          $updateFields[] = "$field = :$field";
          $values[":$field"] = $data[$field];
        }
      }

      if (empty($updateFields)) {
        return false;
      }

      $values[":userId"] = $userId;

      $query = "UPDATE " . $this->table . " 
                SET " . implode(', ', $updateFields) . ", 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :userId";

      $stmt = $this->conn->prepare($query);

      return $stmt->execute($values);
    } catch (PDOException $e) {
      return false;
    }
  }



  public function validateUser($userId)
  {
    try {
      $query = "SELECT id FROM " . $this->table . " WHERE id = :userId";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(":userId", $userId);
      $stmt->execute();

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      return false;
    }
  }

  public function addUser()
  {

    $query = "INSERT INTO " . $this->table . " (full_name, email, password, role, status, cin) 
          VALUES (:full_name, :email, :password, :role, :status, :cin)";
    $stmt = $this->conn->prepare($query);

    $this->full_name = htmlspecialchars(strip_tags($this->full_name));
    $this->email = htmlspecialchars(strip_tags($this->email));
    $this->role = htmlspecialchars(strip_tags($this->role));
    $this->status = htmlspecialchars(strip_tags($this->status));
    $this->cin = htmlspecialchars(strip_tags($this->cin));
    // Hash password
    $this->password = password_hash($this->password, PASSWORD_BCRYPT);

    // Binding parameters

    $stmt->bindParam(':full_name', $this->full_name);
    $stmt->bindParam(':email', $this->email);
    $stmt->bindParam(':password', $this->password);
    $stmt->bindParam(':role', $this->role);
    $stmt->bindParam(':cin', $this->cin);
    $stmt->bindParam(':status', $this->status);



    // Executing query
    return $stmt->execute();
  }
  public function updatePermissions($studentIds, $groupId, $action)
  {
    $ids = implode(',', $studentIds);
    if ($action === 'permit') {
      $query = "UPDATE users SET status = 'Permissioned', group_id = ? WHERE id IN ($ids)";
      $stmt = $this->conn->prepare($query);
      return $stmt->execute([$groupId]);
    } else {
      $query = "UPDATE users SET status = 'Unpermissioned', group_id = NULL WHERE id IN ($ids)";
      $stmt = $this->conn->prepare($query);
      return $stmt->execute();
    }
  }



  public function getUserStatus($userId)
  {
    $query = "SELECT status FROM users WHERE id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['status'];
  }

  public function getUser()
  {
    $query = "SELECT id, full_name, email, role, group_id, status ,created_at FROM " . $this->table . " ORDER BY created_at DESC";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $user = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $user;
  }

  public function getUserById2($id)
  {
    $query = "SELECT id, full_name, email, role, group_id, status, phone, address, bio, date_of_birth, gender 
                FROM " . $this->table . " WHERE id = :id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user;
  }

  public function updateUser($id, $userData)
  {
    try {
      // Start building query
      $query = "UPDATE " . $this->table . " SET ";
      $params = [];

      // Add fields to update
      if (isset($userData['email'])) {
        $params[] = "email = :email";
      }

      if (isset($userData['password'])) {
        $params[] = "password = :password";
      }

      if (isset($userData['full_name'])) {
        $params[] = "full_name = :full_name";
      }

      if (isset($userData['role'])) {
        $params[] = "role = :role";
      }

      if (isset($userData['group_id'])) {
        if ($userData['group_id'] === '') {
          $params[] = "group_id = NULL";
        } else {
          $params[] = "group_id = :group_id";
        }
      }

      if (isset($userData['status'])) {
        $params[] = "status = :status";
      }

      if (isset($userData['phone'])) {
        if ($userData['phone'] === '') {
          $params[] = "phone = NULL";
        } else {
          $params[] = "phone = :phone";
        }
      }

      if (isset($userData['address'])) {
        if ($userData['address'] === '') {
          $params[] = "address = NULL";
        } else {
          $params[] = "address = :address";
        }
      }

      if (isset($userData['bio'])) {
        if ($userData['bio'] === '') {
          $params[] = "bio = NULL";
        } else {
          $params[] = "bio = :bio";
        }
      }

      if (isset($userData['date_of_birth'])) {
        if ($userData['date_of_birth'] === '') {
          $params[] = "date_of_birth = NULL";
        } else {
          $params[] = "date_of_birth = :date_of_birth";
        }
      }

      if (isset($userData['gender'])) {
        if ($userData['gender'] === '') {
          $params[] = "gender = NULL";
        } else {
          $params[] = "gender = :gender";
        }
      }

      // Finalize query
      $query .= implode(', ', $params);
      $query .= " WHERE id = :id";

      // No fields to update
      if (empty($params)) {
        return [
          'status' => 'warning',
          'message' => 'No fields to update'
        ];
      }

      // Prepare statement
      $stmt = $this->conn->prepare($query);

      // Bind ID parameter
      $stmt->bindParam(':id', $id);

      // Bind other parameters
      if (isset($userData['email'])) {
        $stmt->bindParam(':email', $userData['email']);
      }

      if (isset($userData['password'])) {
        $stmt->bindParam(':password', $userData['password']);
      }

      if (isset($userData['full_name'])) {
        $stmt->bindParam(':full_name', $userData['full_name']);
      }

      if (isset($userData['role'])) {
        $stmt->bindParam(':role', $userData['role']);
      }

      if (isset($userData['group_id']) && $userData['group_id'] !== '') {
        $stmt->bindParam(':group_id', $userData['group_id']);
      }

      if (isset($userData['status'])) {
        $stmt->bindParam(':status', $userData['status']);
      }

      if (isset($userData['phone']) && $userData['phone'] !== '') {
        $stmt->bindParam(':phone', $userData['phone']);
      }

      if (isset($userData['address']) && $userData['address'] !== '') {
        $stmt->bindParam(':address', $userData['address']);
      }

      if (isset($userData['bio']) && $userData['bio'] !== '') {
        $stmt->bindParam(':bio', $userData['bio']);
      }

      if (isset($userData['date_of_birth']) && $userData['date_of_birth'] !== '') {
        $stmt->bindParam(':date_of_birth', $userData['date_of_birth']);
      }

      if (isset($userData['gender']) && $userData['gender'] !== '') {
        $stmt->bindParam(':gender', $userData['gender']);
      }

      // Execute statement
      if ($stmt->execute()) {
        return [
          'status' => 'success',
          'message' => 'User updated successfully'
        ];
      }

      return [
        'status' => 'error',
        'message' => 'Failed to update user'
      ];
    } catch (PDOException $e) {
      return [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
      ];
    }
  }

  public function deleteUser($id)
  {
    try {
      $query = "DELETE FROM " . $this->table . " WHERE id = :id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':id', $id);

      if ($stmt->execute()) {
        return [
          'status' => 'success',
          'message' => 'User deleted successfully'
        ];
      }

      return [
        'status' => 'error',
        'message' => 'Failed to delete user'
      ];
    } catch (PDOException $e) {
      return [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
      ];
    }
  }
}
