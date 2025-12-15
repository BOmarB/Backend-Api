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
      // Railway injects variables differently - try all methods
      $host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: $_ENV['MYSQLHOST'] ?? null;
      $dbname = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: $_ENV['MYSQLDATABASE'] ?? null;
      $user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: $_ENV['MYSQLUSER'] ?? null;
      $pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: $_ENV['MYSQLPASSWORD'] ?? null;
      $port = getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: $_ENV['MYSQLPORT'] ?? '3306';
      
      // Debug ALL environment variables (REMOVE AFTER TESTING)
      error_log("=== ALL ENV VARS ===");
      error_log(print_r(getenv(), true));
      error_log("=== CHECKING VARS ===");
      error_log("MYSQLHOST: " . (getenv('MYSQLHOST') ?: 'NOT FOUND'));
      error_log("MYSQL_HOST: " . (getenv('MYSQL_HOST') ?: 'NOT FOUND'));
      error_log("Host result: " . ($host ?: 'MISSING'));
      error_log("DB result: " . ($dbname ?: 'MISSING'));
      error_log("User result: " . ($user ?: 'MISSING'));
      
      if (!$host || !$dbname || !$user) {
        throw new Exception("Database credentials not found. Host: " . ($host ?: 'MISSING') . 
                           ", DB: " . ($dbname ?: 'MISSING') . 
                           ", User: " . ($user ?: 'MISSING'));
      }
      
      $dsn = "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4";
      
      self::$conn = new PDO($dsn, $user, $pass);
      self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      self::$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      
      error_log("✅ Database connected successfully!");
      return self::$conn;
      
    } catch (PDOException $e) {
      error_log("❌ Connection error: " . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
      return null;
    } catch (Exception $e) {
      error_log("❌ Config error: " . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Database configuration missing', 'details' => $e->getMessage()]);
      return null;
    }
  }
}
?>