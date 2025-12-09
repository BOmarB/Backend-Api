<?php
class Grade
{
  private $conn;
  private $table = 'exam_attempts';

  public function __construct($db)
  {
    $this->conn = $db;
  }

  public function getAllAttempts()
  {
    $query = "SELECT 
            ea.id,
            ea.score,
            ea.start_time,
            e.title as exam_title,
            e.duration_minutes,
            u.full_name as student_name,
            g.name as group_name,
            g.id as group_id,
            m.name as module_name,
            m.id as module_id
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN users u ON ea.student_id = u.id
        LEFT JOIN groupsss g ON u.group_id = g.id
        LEFT JOIN modules m ON e.module_id = m.id
        WHERE ea.status = 'completed'
        ORDER BY ea.start_time DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function updateScore($attemptId, $score)
  {
    $query = "UPDATE " . $this->table . " 
                 SET score = :score 
                 WHERE id = :id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':score', $score);
    $stmt->bindParam(':id', $attemptId);

    return $stmt->execute();
  }

  public function getStudentExamsGrade($studentId)
  {
    $query = " SELECT e.*, ea.*, m.name as module_name from exam_attempts ea join exams e  on  e.id = ea.exam_id
    JOIN modules m ON m.id = e.module_id
     where student_id=:student_id and score is not null ORDER BY e.created_at DESC";
    $stmt = $this->conn->prepare($query);

    $studentId = htmlspecialchars(strip_tags($studentId));

    $stmt->bindParam(':student_id', $studentId);

    try {
      $stmt->execute();
      $exams = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $examItem = [
          'id' => $row['id'],
          'title' => $row['title'],
          'duration_minutes' => $row['duration_minutes'],
          'created_at' => $row['created_at'],
          'module_name' => $row['module_name'],
          'score' => $row['score'],
        ];
        $exams[] = $examItem;
      }
      return [
        'success' => true,
        'exams' => $exams,
      ];
    } catch (PDOException $e) {
      return [
        'success' => false,
        'message' => 'Error fetching exams: ' . $e->getMessage()
      ];
    };
  }
}
