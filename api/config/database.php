
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
      // Get credentials from environment variables ONLY
      $host = getenv('DB_HOST') ?: getenv('MYSQL_HOST');
      $dbname = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE');
      $user = getenv('DB_USER') ?: getenv('MYSQL_USER');
      $pass = getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD');
      $port = getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: '3306';
      
      // Validate that we have credentials
      if (!$host || !$dbname || !$user) {
        throw new Exception("Database credentials not found in environment variables");
      }
      
      $dsn = "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4";
      
      self::$conn = new PDO($dsn, $user, $pass);
      self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      self::$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      
      return self::$conn;
    } catch (PDOException $e) {
      error_log("Connection error: " . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Database connection failed']);
      return null;
    } catch (Exception $e) {
      error_log("Config error: " . $e->getMessage());
      http_response_code(500);
      echo json_encode(['error' => 'Database configuration missing']);
      return null;
    }
  }
}
?>

