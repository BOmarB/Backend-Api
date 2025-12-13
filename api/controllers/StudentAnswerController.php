<?php

include_once __DIR__ . '/../models/StudentAnswer.php';


class StudentAnswerController
{
  private $studentAnswer;
  private $exam;

  public function __construct($db)
  {
    $this->exam = new Exam($db);
    $this->studentAnswer = new StudentAnswer($db);
  }

  public function updateAttemptScore($attemptId)
  {
    $stmt = $this->studentAnswer->updateAttemptScore($attemptId);
    if ($stmt) {
      return ['success' => true];
    } else {
      return ['success' => false, 'error' => "error"];
    }
  }

  public function getAttemptDetails($attemptId)
  {
    try {
      // Auto-correct MCQ questions
      $this->studentAnswer->autoCorrectMCQ($attemptId);

      // Update total score


      // Get corrected answers
      $answers = $this->studentAnswer->getAnswersWithCorrection($attemptId);
      $details = $this->exam->getAttemptDetails($attemptId);

      return [
        'success' => true,
        'answers' => array_map(function ($answer) {
          if ($answer['options_data']) {
            $answer['optionDetails'] = json_decode($answer['options_data'], true);
            unset($answer['options_data']);
          }
          return $answer;
        }, $answers),
        'exam' => $details['exam']
      ];
    } catch (Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }
}
