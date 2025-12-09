<?php

class Exam
{
  private $conn;
  private $table = "exams";

  public $id;
  public $title;
  public $description;
  public $teacher_id;
  public $duration_minutes;
  public $group_id;
  public $groups;
  public $module_id;
  public $created_at;
  public $status;
  public $max_attempts;
  public $questions;


  public function __construct($db)
  {
    $this->conn = $db;
  }




  public function getExistingAttempt($examId, $studentId)
  {
    $query = "SELECT id, start_time 
             FROM  exam_attempts 
             WHERE exam_id = :exam_id 
             AND student_id = :student_id 
             AND status = 'in_progress'";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':exam_id', $examId);
    $stmt->bindParam(':student_id', $studentId);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function createNewAttempt($examId, $studentId)
  {
    $query = "INSERT INTO exam_attempts 
             (exam_id, student_id, start_time, status) 
             VALUES (:exam_id, :student_id, NOW(), 'in_progress')";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':exam_id', $examId);
    $stmt->bindParam(':student_id', $studentId);

    if ($stmt->execute()) {
      return $this->conn->lastInsertId();
    }
    return false;
  }

  public function updateAttemptStatus($attemptId, $status)
  {
    $query = "UPDATE " . $this->table . "
             SET status = :status, 
                 end_time = CASE WHEN :status = 'completed' THEN NOW() ELSE NULL END
             WHERE id = :attempt_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':attempt_id', $attemptId);

    return $stmt->execute();
  }

  public function saveCorrection($attemptId, $answers)
  {
    try {
      $this->conn->beginTransaction();

      foreach ($answers as $answer) {
        $query = "UPDATE student_answers 
                     SET points_earned = :points,
                         teacher_comment = :comment,
                         is_corrected = true,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :answer_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':points', $answer['points_earned']);
        $stmt->bindParam(':comment', $answer['teacherComment']);
        $stmt->bindParam(':answer_id', $answer['id']);
        $stmt->execute();
      }

      // Update total score in exam_attempts
      $query = "UPDATE exam_attempts 
                 SET score = (
                     SELECT SUM(points_earned) 
                     FROM student_answers 
                     WHERE attempt_id = :attempt_id
                 )
                 WHERE id = :attempt_id";

      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':attempt_id', $attemptId);
      $stmt->execute();

      $this->conn->commit();
      return true;
    } catch (Exception $e) {
      $this->conn->rollBack();
      return false;
    }
  }
  public function getAttemptsByExam($examId)
  {
    // Get all questions for the exam to determine question numbers
    $questionsQuery = "SELECT id FROM questions WHERE exam_id = :exam_id ORDER BY id";
    $stmt = $this->conn->prepare($questionsQuery);
    $stmt->bindParam(':exam_id', $examId);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $questionNumbers = [];
    $questionIndex = 1;
    foreach ($questions as $q) {
      $questionNumbers[$q['id']] = $questionIndex++;
    }

    // Get all attempts and their answers
    $query = "SELECT 
                  ea.*, 
                  u.full_name as student_name, 
                  g.name as group_name,
                  sa.question_id,
                  sa.points_earned
                FROM exam_attempts ea 
                JOIN users u ON ea.student_id = u.id 
                JOIN groupsss g ON u.group_id = g.id 
                LEFT JOIN student_answers sa ON sa.attempt_id = ea.id
                WHERE ea.exam_id = :exam_id 
                ORDER BY ea.end_time DESC, ea.id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':exam_id', $examId);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process rows into attempts with answers
    $attempts = [];
    $currentAttemptId = null;
    $currentAttempt = null;

    foreach ($rows as $row) {
      if ($row['id'] !== $currentAttemptId) {
        if ($currentAttemptId !== null) {
          $currentAttempt['question_scores'] = $this->mapScores($currentAttempt['answers'], $questionNumbers);
          unset($currentAttempt['answers']);
          $attempts[] = $currentAttempt;
        }
        $currentAttemptId = $row['id'];
        $currentAttempt = $row;
        $currentAttempt['answers'] = [];
        if ($row['question_id'] !== null) {
          $currentAttempt['answers'][$row['question_id']] = $row['points_earned'];
        }
      } else {
        if ($row['question_id'] !== null) {
          $currentAttempt['answers'][$row['question_id']] = $row['points_earned'];
        }
      }
    }

    if ($currentAttemptId !== null) {
      $currentAttempt['question_scores'] = $this->mapScores($currentAttempt['answers'], $questionNumbers);
      unset($currentAttempt['answers']);
      $attempts[] = $currentAttempt;
    }

    return $attempts;
  }


  private function mapScores($answers, $questionNumbers)
  {
    $scores = [];
    foreach ($questionNumbers as $qId => $qNum) {
      $scores[$qNum] = isset($answers[$qId]) ? $answers[$qId] : 0;
    }
    ksort($scores);
    return $scores;
  }

  public function getQuestionsByExamWithNumbers($examId)
  {
    $query = "SELECT id FROM questions WHERE exam_id = :exam_id ORDER BY id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':exam_id', $examId);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $numberedQuestions = [];
    $number = 1;
    foreach ($questions as $q) {
      $numberedQuestions[] = [
        'number' => $number++,
        'id' => $q['id']
      ];
    }
    return $numberedQuestions;
  }

  public function getAttemptDetails($attemptId)
  {
    $examQuery = "SELECT e.*, ea.student_id, u.full_name as student_name, g.name as group_name 
                  FROM exam_attempts ea 
                  JOIN exams e ON ea.exam_id = e.id 
                  JOIN users u ON ea.student_id = u.id 
                  JOIN groupsss g ON u.group_id = g.id 
                  WHERE ea.id = :attempt_id";

    $answersQuery = "SELECT sa.*, q.question_text, q.points as max_points, q.image_data 
                     FROM student_answers sa 
                     JOIN questions q ON sa.question_id = q.id 
                     WHERE sa.attempt_id = :attempt_id";

    $stmt = $this->conn->prepare($examQuery);
    $stmt->bindParam(':attempt_id', $attemptId);
    $stmt->execute();
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $this->conn->prepare($answersQuery);
    $stmt->bindParam(':attempt_id', $attemptId);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['exam' => $exam, 'answers' => $answers];
  }

  public function updateAnswerScore($answerId, $score)
  {
    try {
      // Ensure score is a valid float
      $scoreValue = floatval($score);

      // Debug logging (optional)
      error_log("Updating answer ID: $answerId with score: $scoreValue");

      $query = "UPDATE student_answers 
                SET points_earned = :score, 
                    is_corrected = true,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :answer_id";

      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':score', $scoreValue, PDO::PARAM_STR); // Use PDO::PARAM_STR for decimal values
      $stmt->bindParam(':answer_id', $answerId);
      $result = $stmt->execute();

      if ($result) {
        // Update the total score in exam_attempts
        $this->updateTotalScoreForAnswer($answerId);
      }

      return $result;
    } catch (Exception $e) {
      error_log("Error updating answer score: " . $e->getMessage());
      return false;
    }
  }

  private function updateTotalScoreForAnswer($answerId)
  {
    try {
      // First get the attempt_id for this answer
      $query = "SELECT attempt_id FROM student_answers WHERE id = :answer_id";
      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':answer_id', $answerId);
      $stmt->execute();

      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$result) return false;

      $attemptId = $result['attempt_id'];

      // Now update the total score
      $query = "UPDATE exam_attempts 
                SET score = (
                    SELECT SUM(points_earned) 
                    FROM student_answers 
                    WHERE attempt_id = :attempt_id AND points_earned IS NOT NULL
                )
                WHERE id = :attempt_id";

      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':attempt_id', $attemptId);
      return $stmt->execute();
    } catch (Exception $e) {
      error_log("Error updating total score: " . $e->getMessage());
      return false;
    }
  }

  public function createAttempt($examId, $studentId)
  {
    try {
      $query = "INSERT INTO exam_attempts (exam_id, student_id, start_time) 
                  VALUES (:exam_id, :student_id, NOW())";

      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':exam_id', $examId);
      $stmt->bindParam(':student_id', $studentId);

      if ($stmt->execute()) {
        return [
          'success' => true,
          'attempt_id' => $this->conn->lastInsertId()
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

  public function verifyAttemptStatus($attemptId)
  {

    $query = "SELECT status FROM exam_attempts WHERE id = :attempt_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':attempt_id', $attemptId);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result;
  }
  public function verifyExamAttempt($attemptId)
  {
    $query = "SELECT status FROM exam_attempts WHERE id = :attempt_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':attempt_id', $attemptId);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result;
  }

  public function submitAnswers($attemptId, $answers)
  {
    try {
      $this->conn->beginTransaction();

      // Check if attempt exists and get exam_id and student_id
      $checkQuery = "SELECT exam_id, student_id, status 
                        FROM exam_attempts 
                        WHERE id = :attempt_id";
      $checkStmt = $this->conn->prepare($checkQuery);
      $checkStmt->bindParam(':attempt_id', $attemptId);
      $checkStmt->execute();
      $attemptDetails = $checkStmt->fetch(PDO::FETCH_ASSOC);

      if (!$attemptDetails) {
        throw new PDOException("Invalid attempt ID");
      }

      if ($attemptDetails['status'] !== 'in_progress') {
        $this->conn->rollBack();
        return [
          'success' => true,
          'message' => 'This attempt has already been ' . $attemptDetails['status']
        ];
      }

      // Update attempt status and end time
      $updateQuery = "UPDATE exam_attempts 
                         SET status = 'completed', 
                             end_time = NOW()
                         WHERE id = :attempt_id";
      $updateStmt = $this->conn->prepare($updateQuery);
      $updateStmt->bindParam(':attempt_id', $attemptId);
      if (!$updateStmt->execute()) {
        throw new PDOException("Failed to update attempt status");
      }

      // Check for existing answers for this attempt to avoid duplicates
      $checkAnswersQuery = "SELECT COUNT(*) as count FROM student_answers WHERE attempt_id = :attempt_id";
      $checkAnswersStmt = $this->conn->prepare($checkAnswersQuery);
      $checkAnswersStmt->bindParam(':attempt_id', $attemptId);
      $checkAnswersStmt->execute();
      $existingAnswers = $checkAnswersStmt->fetch(PDO::FETCH_ASSOC);

      // Only insert answers if none exist yet AND we have answers to insert
      if ($existingAnswers['count'] == 0 && !empty($answers)) {
        // Insert answers
        $insertQuery = "INSERT INTO student_answers 
                         (attempt_id, question_id, answer_text, selected_options) 
                         VALUES (:attempt_id, :question_id, :answer_text, :selected_options)";
        $insertStmt = $this->conn->prepare($insertQuery);

        foreach ($answers as $answer) {
          $params = [
            ':attempt_id' => $attemptId,
            ':question_id' => $answer['question_id'],
            ':answer_text' => $answer['answer_text'],
            ':selected_options' => $answer['selected_options']
          ];

          try {
            $insertStmt->execute($params);
          } catch (PDOException $e) {
            // Log the error but continue with other answers
            error_log("Error inserting answer for question {$answer['question_id']}: " . $e->getMessage());
            continue;
          }
        }
      }

      $this->conn->commit();
      return [
        'success' => true,
        'message' => 'Exam submitted successfully'
      ];
    } catch (PDOException $e) {
      $this->conn->rollBack();
      error_log("Exam submission error: " . $e->getMessage());
      return [
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
      ];
    }
  }
  public function getStudentExams($studentId)
  {
    $query = "SELECT 
                e.*,
                g.name as group_name,
                m.name as module_name,
                (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) as question_count,
                (SELECT COUNT(*) FROM exam_attempts ea 
                 WHERE ea.exam_id = e.id AND ea.student_id = :student_id) as attempt_count
            FROM " . $this->table . " e
            JOIN exam_groups eg ON e.id = eg.exam_id
            JOIN groupsss g ON g.id = eg.group_id
            JOIN users u ON u.group_id = g.id
            JOIN modules m ON m.id = e.module_id
            WHERE u.id = :student_id 
            AND e.status = 'published'
            ORDER BY e.created_at DESC";

    $stmt = $this->conn->prepare($query);

    // Clean data
    $studentId = htmlspecialchars(strip_tags($studentId));

    // Bind data
    $stmt->bindParam(':student_id', $studentId);

    try {
      $stmt->execute();
      $exams = [];

      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $examItem = [
          'id' => $row['id'],
          'title' => $row['title'],
          'description' => $row['description'],
          'duration_minutes' => $row['duration_minutes'],
          'group_id' => $row['group_id'],
          'is_aa_quiz' => $row['is_aa_quiz'],

          'group_name' => $row['group_name'],
          'module_name' => $row['module_name'],

          'status' => $row['status'],
          'max_attempts' => $row['max_attempts'],
          'question_count' => $row['question_count'],
          'has_attempted' => ($row['attempt_count'] >= $row['max_attempts']),
          'created_at' => $row['created_at']
        ];

        $exams[] = $examItem;
      }

      return [
        'success' => true,
        'exams' => $exams
      ];
    } catch (PDOException $e) {
      return [
        'success' => false,
        'message' => 'Error fetching exams: ' . $e->getMessage()
      ];
    }
  }

  // Helper method to validate student's eligibility for an exam
  public function validateStudentEligibility($examId, $studentId)
  {

    $query = "SELECT 
    e.*,
    (SELECT COUNT(*) 
     FROM exam_attempts ea 
     WHERE ea.exam_id = e.id AND ea.student_id = :student_id) AS attempt_count
FROM exams e
JOIN exam_groups eg ON e.id = eg.exam_id
JOIN users u ON u.group_id = eg.group_id
WHERE e.id = :exam_id
  AND u.id = :student_id
  AND e.status = 'published'";

    $stmt = $this->conn->prepare($query);

    // Clean data
    $examId = htmlspecialchars(strip_tags($examId));
    $studentId = htmlspecialchars(strip_tags($studentId));

    // Bind data
    $stmt->bindParam(':exam_id', $examId);
    $stmt->bindParam(':student_id', $studentId);

    try {
      $stmt->execute();
      $exam = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$exam) {
        return [
          'success' => false,
          'message' => 'Exam not found or not available for this student'
        ];
      }

      if ($exam['attempt_count'] >= $exam['max_attempts']) {
        return [
          'success' => false,
          'message' => 'Maximum attempts reached for this exam'
        ];
      }

      return [
        'success' => true,
        'exam' => $exam
      ];
    } catch (PDOException $e) {
      return [
        'success' => false,
        'message' => 'Error validating eligibility: ' . $e->getMessage()
      ];
    }
  }

  public function createExam()
  {
    try {
      $this->conn->beginTransaction();

      // First, create the exam
      $query = "INSERT INTO " . $this->table . " 
                 (title, description, teacher_id, duration_minutes, module_id, status, max_attempts) 
                 VALUES 
                 (:title, :description, :teacher_id, :duration_minutes, :module_id, :status, :max_attempts)";

      $stmt = $this->conn->prepare($query);

      // Bind the exam data
      $stmt->bindParam(':title', $this->title);
      $stmt->bindParam(
        ':description',
        $this->description
      );
      $stmt->bindParam(':teacher_id', $this->teacher_id);
      $stmt->bindParam(':duration_minutes', $this->duration_minutes);
      $stmt->bindParam(':module_id', $this->module_id);
      $stmt->bindValue(':status', 'draft');
      $stmt->bindValue(':max_attempts', 1);

      $stmt->execute();
      $examId = $this->conn->lastInsertId();

      // Then, create the exam-group relationships
      foreach ($this->groups as $groupId) {
        $query = "INSERT INTO exam_groups (exam_id, group_id) VALUES (:exam_id, :group_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $examId);
        $stmt->bindParam(':group_id', $groupId);
        $stmt->execute();
      }

      $this->conn->commit();
      return ['success' => true, 'exam_id' => $examId];
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return ['success' => false, 'message' => $e->getMessage()];
    }
  }


  public function getExamsByTeacher($teacher_id)
  {
    $query = "SELECT 
          e.*, 
          GROUP_CONCAT(DISTINCT g.name) AS group_names,
          GROUP_CONCAT(DISTINCT g.id) AS group_ids,
          m.name AS module_name,
          (
              SELECT JSON_ARRAYAGG(
                  JSON_OBJECT(
                      'id', q.id,
                      'question_text', q.question_text,
                      'question_type', q.question_type,
                      'points', q.points,
                      'has_image', CASE WHEN q.image_data IS NOT NULL THEN TRUE ELSE FALSE END
                  )
              )
              FROM questions q 
              WHERE q.exam_id = e.id
          ) AS questions,
          COUNT(DISTINCT ea.id) AS attempt_count
      FROM exams e
      LEFT JOIN exam_attempts ea ON e.id = ea.exam_id
      LEFT JOIN exam_groups eg ON e.id = eg.exam_id
      LEFT JOIN groupsss g ON eg.group_id = g.id
      LEFT JOIN modules m ON e.module_id = m.id
      WHERE e.teacher_id = :teacher_id
      GROUP BY e.id, e.created_at, m.name
      ORDER BY e.created_at DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function toggleExamStatus($exam_id, $status)
  {
    $query = "UPDATE " . $this->table . " 
              SET status = :status 
              WHERE id = :exam_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->bindParam(':status', $status);

    return $stmt->execute();
  }

  public function deleteExam($exam_id)
  {
    // First delete related records if you have any foreign key constraints
    // For example, delete exam attempts, questions, etc.

    $query = "DELETE FROM " . $this->table . " WHERE id = :exam_id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':exam_id', $exam_id);

    return $stmt->execute();
  }



  public function getExamById($exam_id, $teacher_id)
  {
    $query = "SELECT e.*, 
                        (SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', q.id,
                                'text', q.question_text,
                                'type', q.question_type,
                                'points', q.points,
                                'options', (
                                    SELECT JSON_ARRAYAGG(
                                        JSON_OBJECT(
                                            'id', o.id,
                                            'text', o.option_text,
                                            'isCorrect', o.is_correct
                                        )
                                    )
                                    FROM question_options o
                                    WHERE o.question_id = q.id
                                )
                            )
                        )
                        FROM questions q
                        WHERE q.exam_id = e.id) as questions
                FROM " . $this->table . " e
                WHERE e.id = :exam_id AND e.teacher_id = :teacher_id";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':exam_id', $exam_id);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function getExams()
  {
    $query = "SELECT * FROM " . $this->table;
    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    return $exam;
  }
  public function updateExam()
  {
    $this->conn->beginTransaction();

    try {
      // Update exam basic information
      $query = "UPDATE " . $this->table . " 
                    SET title = :title, 
                        description = :description, 
                        duration_minutes = :duration_minutes
                    WHERE id = :id AND teacher_id = :teacher_id";

      $stmt = $this->conn->prepare($query);

      $stmt->bindParam(':title', $this->title);
      $stmt->bindParam(':description', $this->description);
      $stmt->bindParam(':duration_minutes', $this->duration_minutes);
      $stmt->bindParam(':id', $this->id);
      $stmt->bindParam(':teacher_id', $this->teacher_id);

      if (!$stmt->execute()) {
        throw new Exception("Failed to update exam");
      }

      // If questions data is provided, update questions
      if (isset($this->questions) && !empty($this->questions)) {
        // First, let's get existing questions for this exam
        $queryGetQuestions = "SELECT id FROM questions WHERE exam_id = :exam_id";
        $stmtGetQuestions = $this->conn->prepare($queryGetQuestions);
        $stmtGetQuestions->bindParam(':exam_id', $this->id);
        $stmtGetQuestions->execute();
        $existingQuestions = $stmtGetQuestions->fetchAll(PDO::FETCH_COLUMN);

        // Parse questions if needed
        $questions = $this->questions;
        if (is_string($questions)) {
          $questions = json_decode($questions, true);
        }

        // Process questions if they're valid
        if (is_array($questions)) {
          foreach ($questions as $question) {
            // Check if question already exists (has an ID)
            if (isset($question['id']) && in_array($question['id'], $existingQuestions)) {
              // Update existing question
              $queryUpdateQuestion = "UPDATE questions 
                                  SET question_text = :question_text,
                                      question_type = :question_type,
                                      points = :points
                                  WHERE id = :id AND exam_id = :exam_id";

              $stmtUpdateQuestion = $this->conn->prepare($queryUpdateQuestion);
              $stmtUpdateQuestion->bindParam(':question_text', $question['text']);
              $stmtUpdateQuestion->bindParam(':question_type', $question['type']);
              $stmtUpdateQuestion->bindParam(':points', $question['points']);
              $stmtUpdateQuestion->bindParam(':id', $question['id']);
              $stmtUpdateQuestion->bindParam(':exam_id', $this->id);

              if (!$stmtUpdateQuestion->execute()) {
                throw new Exception("Failed to update question");
              }

              // Update options for MCQ questions
              if ($question['type'] === 'mcq' && isset($question['options']) && is_array($question['options'])) {
                // Delete existing options
                $queryDeleteOptions = "DELETE FROM question_options WHERE question_id = :question_id";
                $stmtDeleteOptions = $this->conn->prepare($queryDeleteOptions);
                $stmtDeleteOptions->bindParam(':question_id', $question['id']);

                if (!$stmtDeleteOptions->execute()) {
                  throw new Exception("Failed to delete existing options");
                }

                // Insert new options
                $queryInsertOption = "INSERT INTO question_options 
                                 (question_id, option_text, is_correct) 
                                 VALUES 
                                 (:question_id, :option_text, :is_correct)";

                $stmtInsertOption = $this->conn->prepare($queryInsertOption);

                foreach ($question['options'] as $option) {
                  $stmtInsertOption->bindParam(':question_id', $question['id']);
                  $stmtInsertOption->bindParam(':option_text', $option['text']);
                  $isCorrect = $option['isCorrect'] ? 1 : 0;
                  $stmtInsertOption->bindParam(':is_correct', $isCorrect, PDO::PARAM_INT);

                  if (!$stmtInsertOption->execute()) {
                    throw new Exception("Failed to insert option");
                  }
                }
              }

              // Remove this ID from our tracking array
              $key = array_search($question['id'], $existingQuestions);
              if ($key !== false) {
                unset($existingQuestions[$key]);
              }
            } else {
              // This is a new question, insert it
              $queryInsertQuestion = "INSERT INTO questions 
                                  (exam_id, question_text, question_type, points) 
                                  VALUES 
                                  (:exam_id, :question_text, :question_type, :points)";

              $stmtInsertQuestion = $this->conn->prepare($queryInsertQuestion);
              $stmtInsertQuestion->bindParam(':exam_id', $this->id);
              $stmtInsertQuestion->bindParam(':question_text', $question['text']);
              $stmtInsertQuestion->bindParam(':question_type', $question['type']);
              $stmtInsertQuestion->bindParam(':points', $question['points']);

              if (!$stmtInsertQuestion->execute()) {
                throw new Exception("Failed to insert question");
              }

              $newQuestionId = $this->conn->lastInsertId();

              // Insert options for MCQ questions
              if ($question['type'] === 'mcq' && isset($question['options']) && is_array($question['options'])) {
                $queryInsertOption = "INSERT INTO question_options 
                                 (question_id, option_text, is_correct) 
                                 VALUES 
                                 (:question_id, :option_text, :is_correct)";

                $stmtInsertOption = $this->conn->prepare($queryInsertOption);

                foreach ($question['options'] as $option) {
                  $stmtInsertOption->bindParam(':question_id', $newQuestionId);
                  $stmtInsertOption->bindParam(':option_text', $option['text']);
                  $isCorrect = $option['isCorrect'] ? 1 : 0;
                  $stmtInsertOption->bindParam(':is_correct', $isCorrect, PDO::PARAM_INT);

                  if (!$stmtInsertOption->execute()) {
                    throw new Exception("Failed to insert option");
                  }
                }
              }
            }
          }

          // Any IDs left in existingQuestions array should be deleted
          if (!empty($existingQuestions)) {
            $queryDeleteQuestions = "DELETE FROM questions WHERE id IN (" . implode(',', $existingQuestions) . ")";
            $stmtDeleteQuestions = $this->conn->prepare($queryDeleteQuestions);

            if (!$stmtDeleteQuestions->execute()) {
              throw new Exception("Failed to delete old questions");
            }
          }
        }
      }

      $this->conn->commit();
      return true;
    } catch (Exception $e) {
      $this->conn->rollBack();
      error_log("Error updating exam: " . $e->getMessage());
      return false;
    }
  }




  // public function createAttempt($exam_id, $student_id)
  // {
  //   $query = "INSERT INTO exam_attempts 
  //             (exam_id, student_id, start_time, status) 
  //             VALUES (:exam_id, :student_id, NOW(), 'in_progress')";

  //   $stmt = $this->conn->prepare($query);
  //   $stmt->bindParam(':exam_id', $exam_id);
  //   $stmt->bindParam(':student_id', $student_id);

  //   if ($stmt->execute()) {
  //     return $this->conn->lastInsertId();
  //   }
  //   return false;
  // }




  public function submitAttempt($attempt_id, $score, $time_spent)
  {
    $this->conn->beginTransaction();

    try {
      // Update the attempt status and score
      $query = "UPDATE exam_attempts 
                 SET status = 'completed',
                     score = :score,
                     end_time = NOW()
                 WHERE id = :attempt_id";

      $stmt = $this->conn->prepare($query);
      $stmt->bindParam(':score', $score);
      $stmt->bindParam(':attempt_id', $attempt_id);

      if (!$stmt->execute()) {
        throw new Exception("Failed to update attempt");
      }

      $this->conn->commit();
      return true;
    } catch (Exception $e) {
      $this->conn->rollBack();
      return false;
    }
  }
}


 // public function getQuestionsWithAnswers($attempt_id)
  // {
  //   $query = "SELECT q.*, 
  //                   (SELECT JSON_ARRAYAGG(
  //                       JSON_OBJECT(
  //                           'id', o.id,
  //                           'is_correct', o.is_correct
  //                       )
  //                   )
  //                   FROM question_options o
  //                   WHERE o.question_id = q.id) as options
  //             FROM questions q
  //             INNER JOIN exams e ON q.exam_id = e.id
  //             INNER JOIN exam_attempts ea ON e.id = ea.exam_id
  //             WHERE ea.id = :attempt_id";

  //   $stmt = $this->conn->prepare($query);
  //   $stmt->bindParam(':attempt_id', $attempt_id);
  //   $stmt->execute();

  //   $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
  //   foreach ($questions as &$question) {
  //     $question['options'] = json_decode($question['options'], true);
  //   }
  //   return $questions;
  // }