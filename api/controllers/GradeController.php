<?php


include_once '../models/Grade.php';
class GradeController
{
  private $grade;

  public function __construct($db)
  {
    $this->grade = new Grade($db);
  }

  public function getAttemptDetails()
  {
    $attempts = $this->grade->getAllAttempts();
    if ($attempts) {
      return ['success' => true, 'attempts' => $attempts];
    }
    return ['success' => false, 'message' => 'No attempts found'];
  }

  public function updateScore($attemptId, $score)
  {
    if ($this->grade->updateScore($attemptId, $score)) {
      return ['success' => true];
    }
    return ['success' => false, 'message' => 'Failed to update score'];
  }

  public function getStudentExamsGrade($studentId)
  {
    if (!$studentId) {
      return [
        'success' => false,
        'message' => 'Student ID is required'
      ];
    }

    // Validate that studentId is numeric
    if (!is_numeric($studentId)) {
      return [
        'success' => false,
        'message' => 'Invalid student ID format'
      ];
    }
    return $this->grade->getStudentExamsGrade($studentId);
  }
}
