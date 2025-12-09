<?php
include_once '../models/Exam.php';
class ExamController
{
  private $exam;

  public function __construct($db)
  {
    $this->exam = new Exam($db);
  }

  public function submitCorrection($attemptId, $answers)
  {
    $success = $this->exam->saveCorrection($attemptId, $answers);
    return ['success' => $success];
  }
  public function getExamAttempts($examId)
  {
    $attempts = $this->exam->getAttemptsByExam($examId);
    $questions = $this->exam->getQuestionsByExamWithNumbers($examId);
    return ['success' => true, 'attempts' => $attempts, 'questions' => $questions];
  }

  public function getAttemptDetails($attemptId)
  {
    $details = $this->exam->getAttemptDetails($attemptId);
    return ['success' => true, 'exam' => $details['exam'], 'answers' => $details['answers']];
  }


  public function updateScore($answerId, $score)
  {
    $success = $this->exam->updateAnswerScore($answerId, $score);
    return ['success' => $success];
  }
  public function createAttempt($data)
  {
    // Validate input
    if (!isset($data['exam_id']) || !isset($data['student_id'])) {
      return [
        'success' => false,
        'message' => 'Missing required fields'
      ];
    }

    try {
      // Check for existing attempt
      $existingAttempt = $this->exam->getExistingAttempt(
        $data['exam_id'],
        $data['student_id']
      );

      if ($existingAttempt) {
        return [
          'success' => true,
          'attempt_id' => $existingAttempt['id'],
          'start_time' => $existingAttempt['start_time'],
          'message' => 'Existing attempt found'
        ];
      }

      // Create new attempt
      $newAttemptId = $this->exam->createNewAttempt(
        $data['exam_id'],
        $data['student_id']
      );

      if ($newAttemptId) {
        return [
          'success' => true,
          'attempt_id' => $newAttemptId,
          'message' => 'New attempt created successfully'
        ];
      }

      return [
        'success' => false,
        'message' => 'Failed to create attempt'
      ];
    } catch (PDOException $e) {
      return [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
      ];
    }
  }

  public function submitExam($data)
  {
    if (!isset($data['attempt_id'])) {
      return [
        'success' => false,
        'message' => 'Invalid attempt ID'
      ];
    }

    // Validate answers array - allow empty answers array
    if (!isset($data['answers']) || !is_array($data['answers'])) {
      // If no answers, just set to empty array
      $data['answers'] = [];
    }

    // If answers exist, validate each answer
    if (!empty($data['answers'])) {
      foreach ($data['answers'] as $answer) {
        if (!isset($answer['question_id'])) {
          return [
            'success' => false,
            'message' => 'Missing question ID in answers'
          ];
        }
      }
    }

    return $this->exam->submitAnswers($data['attempt_id'], $data['answers']);
  }

  public function verifyAttemptStatus($attemptId)
  {
    try {
      $result = $this->exam->verifyAttemptStatus($attemptId);

      return [
        'success' => true,
        'exists' => !!$result,
        'status' => $result ? $result['status'] : null
      ];
    } catch (PDOException $e) {
      return [
        'success' => false,
        'message' => 'Error verifying attempt: ' . $e->getMessage()
      ];
    }
  }

  public function verifyExamAttempt($attemptId)
  {
    try {


      $result = $this->exam->verifyExamAttempt($attemptId);

      if ($result) {
        return [
          'success' => true,
          'valid' => true,
          'status' => $result['status']
        ];
      } else {
        return [
          'success' => true,
          'valid' => false
        ];
      }
    } catch (PDOException $e) {
      return [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
      ];
    }
  }
  public function getStudentExams($studentId)
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

    return $this->exam->getStudentExams($studentId);
  }

  public function validateExamAccess($examId, $studentId)
  {
    if (!$examId || !$studentId) {
      return [
        'success' => false,
        'message' => 'Both exam ID and student ID are required'
      ];
    }

    // Validate that both IDs are numeric
    if (!is_numeric($examId) || !is_numeric($studentId)) {
      return [
        'success' => false,
        'message' => 'Invalid ID format'
      ];
    }

    return $this->exam->validateStudentEligibility($examId, $studentId);
  }

  public function toggleExamStatus($exam_id, $status)
  {
    if ($this->exam->toggleExamStatus($exam_id, $status)) {
      return [
        'success' => true,
        'message' => 'Exam status updated successfully'
      ];
    }
    return [
      'success' => false,
      'message' => 'Failed to update exam status'
    ];
  }

  public function deleteExam($exam_id)
  {
    if ($this->exam->deleteExam($exam_id)) {
      return [
        'success' => true,
        'message' => 'Exam deleted successfully'
      ];
    }
    return [
      'success' => false,
      'message' => 'Failed to delete exam'
    ];
  }


  public function createExam($data)
  {
    $this->exam->title = $data['title'];
    $this->exam->description = $data['description'];
    $this->exam->teacher_id = $data['teacher_id'];
    $this->exam->duration_minutes = $data['duration_minutes'];
    $this->exam->groups = $data['groups'];
    $this->exam->module_id = $data['module_id'];

    $result = $this->exam->createExam();

    // Return the result directly as it's already in the correct format
    return $result;
  }



  public function getExamsByTeacher($teacherId)
  {
    $exams = $this->exam->getExamsByTeacher($teacherId);
    return [
      'success' => true,
      'exams' => array_map(function ($exam) {
        // Ensure questions is properly decoded JSON
        if (isset($exam['questions']) && is_string($exam['questions'])) {
          $exam['questions'] = json_decode($exam['questions'], true);
        }
        return $exam;
      }, $exams)
    ];
  }




  public function startExam($data)
  {
    if (!isset($data['exam_id']) || !isset($data['student_id'])) {
      return [
        'success' => false,
        'message' => 'Missing required fields'
      ];
    }

    $attempt_id = $this->exam->createAttempt($data['exam_id'], $data['student_id']);
    if ($attempt_id) {
      return [
        'success' => true,
        'attempt_id' => $attempt_id
      ];
    }
    return [
      'success' => false,
      'message' => 'Failed to start exam'
    ];
  }






  public function getExams()
  {
    return $this->exam->getExams();
  }


  public function updateExam($data)
  {
    if (!isset($data['id']) || !isset($data['teacher_id'])) {
      return [
        'success' => false,
        'message' => 'Missing required fields'
      ];
    }

    $this->exam->id = $data['id'];
    $this->exam->title = $data['title'];
    $this->exam->description = $data['description'];
    $this->exam->teacher_id = $data['teacher_id'];
    $this->exam->duration_minutes = $data['duration_minutes'];

    // Handle questions data if provided
    if (isset($data['questions'])) {
      $this->exam->questions = $data['questions'];
    }

    try {
      if ($this->exam->updateExam()) {
        // Update was successful
        return [
          'success' => true,
          'message' => 'Exam updated successfully'
        ];
      } else {
        return [
          'success' => false,
          'message' => 'Failed to update exam'
        ];
      }
    } catch (Exception $e) {
      return [
        'success' => false,
        'message' => 'Error updating exam: ' . $e->getMessage()
      ];
    }
  }

  public function getExamById($exam_id, $teacher_id)
  {
    $exam = $this->exam->getExamById($exam_id, $teacher_id);
    if ($exam) {
      return [
        'success' => true,
        'exam' => $exam
      ];
    }
    return [
      'success' => false,
      'message' => 'Exam not found'
    ];
  }
}


 //  public function submitExam($data)
  // {
  //   if (!isset($data['attempt_id']) || !isset($data['answers'])) {
  //     return [
  //       'success' => false,
  //       'message' => 'Missing required fields'
  //     ];
  //   }

  //   $score = $this->calculateScore($data['attempt_id'], $data['answers']);
  //   $success = $this->exam->submitAttempt($data['attempt_id'], $score, $data['time_spent']);

  //   if ($success) {
  //     return [
  //       'success' => true,
  //       'score' => $score
  //     ];
  //   }
  //   return [
  //     'success' => false,
  //     'message' => 'Failed to submit exam'
  //   ];
  // }
  // private function calculateScore($attempt_id, $answers)
  // {
  //   $questions = $this->exam->getQuestionsWithAnswers($attempt_id);
  //   $totalPoints = 0;
  //   $earnedPoints = 0;

  //   foreach ($questions as $question) {
  //     $totalPoints += $question['points'];
  //     $studentAnswers = $answers[$question['id']] ?? [];

  //     // Get correct and incorrect answers selected by student
  //     $correctSelected = 0;
  //     $incorrectSelected = 0;
  //     $totalCorrect = 0;

  //     foreach ($question['options'] as $option) {
  //       if ($option['is_correct']) {
  //         $totalCorrect++;
  //         if (in_array($option['id'], $studentAnswers)) {
  //           $correctSelected++;
  //         }
  //       } else if (in_array($option['id'], $studentAnswers)) {
  //         $incorrectSelected++;
  //       }
  //     }

  //     // Calculate points for this question
  //     if ($incorrectSelected === 0 && $correctSelected === $totalCorrect) {
  //       // All correct answers selected and no incorrect ones
  //       $earnedPoints += $question['points'];
  //     } else if ($correctSelected > 0 && $incorrectSelected === 0) {
  //       // Some correct answers but missing some, and no incorrect ones
  //       $earnedPoints += ($question['points'] * $correctSelected) / $totalCorrect;
  //     } else if ($correctSelected > 0 && $incorrectSelected > 0) {
  //       // Mixed correct and incorrect answers
  //       $earnedPoints += ($question['points'] * $correctSelected) / ($totalCorrect + $incorrectSelected);
  //     }
  //   }
  // }
  // public function getExamResult($attempt_id)
  // {
  //   $result = $this->exam->getAttemptResult($attempt_id);
  //   if ($result) {
  //     return [
  //       'success' => true,
  //       'result' => $result
  //     ];
  //   }
  //   return [
  //     'success' => false,
  //     'message' => 'Result not found'
  //   ];
  // }