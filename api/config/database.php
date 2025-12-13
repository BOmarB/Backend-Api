<?php
class database
{
  private static $conn = null;

  public static function connect()    
  {
     if(self::$conn !== null){
         return self::$conn;
     } 
     
    try {

      $host = $_ENV['MYSQL_HOST'] ?? $_SERVER['MYSQL_HOST'] ?? getenv('MYSQL_HOST');
      $dbname = $_ENV['MYSQL_DATABASE'] ?? $_SERVER['MYSQL_DATABASE'] ?? getenv('MYSQL_DATABASE');
      $user = $_ENV['MYSQL_USER'] ?? $_SERVER['MYSQL_USER'] ?? getenv('MYSQL_USER');
      $pass = $_ENV['MYSQL_PASSWORD'] ?? $_SERVER['MYSQL_PASSWORD'] ?? getenv('MYSQL_PASSWORD');
      $port = $_ENV['MYSQL_PORT'] ?? $_SERVER['MYSQL_PORT'] ?? getenv('MYSQL_PORT') ?? '3306';
      
      // Debug: Log what we found (remove this after testing)
      error_log("DB Host: " . ($host ?: 'NOT FOUND'));
      error_log("DB Name: " . ($dbname ?: 'NOT FOUND'));
      error_log("DB User: " . ($user ?: 'NOT FOUND'));
      error_log("DB Port: " . ($port ?: 'NOT FOUND'));
      
     
      if (!$host || !$dbname || !$user) {
        throw new Exception("Database credentials not found. Host: " . ($host ? 'OK' : 'MISSING') . 
                           ", DB: " . ($dbname ? 'OK' : 'MISSING') . 
                           ", User: " . ($user ? 'OK' : 'MISSING'));
      }
      
      $dsn = "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4";
      
      self::$conn = new PDO($dsn, $user, $pass);
      self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      self::$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      
      error_log("Database connected successfully!");
      return self::$conn;
    } catch (PDOException $e) {
      error_log("Connection error: " . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
      return null;
    } catch (Exception $e) {
      error_log("Config error: " . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Database configuration missing', 'details' => $e->getMessage()]);
      return null;
    }
  }
}
?>