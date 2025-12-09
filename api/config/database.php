<?php
class database
{
  private static $dsn = 'mysql:host=127.0.0.1;dbname=onlineexams2;port=3307';
  private static $user = 'root';
  private static $pass = 'DD102';

  public static function connect()
  {
    try {
      $conn = new PDO(self::$dsn, self::$user, self::$pass);
      $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $conn;
    } catch (PDOException $e) {
      echo "Connection error: " . $e->getMessage();
      return null;
    }
  }
}


