<?php
class Group
{
  private $conn;
  private $table = "groupsss";

  public $id;
  public $name;

  public function __construct($db)
  {
    $this->conn = $db;
  }


  
  public function create()
  {
    $query = "INSERT INTO " . $this->table . " (name) VALUES (:name)";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':name', $this->name);
    return $stmt->execute();
  }

  public function getAll()
  {
    $query = "SELECT * FROM " . $this->table;
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
