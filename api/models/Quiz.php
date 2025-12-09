<?php
class Quiz
{
    private $conn;
    private $table = "exams";
    private $question_table = "questions";
    private $options_table = "question_options";

    public $id;
    public $title;
    public $description;
    public $teacher_id;
    public $duration_minutes;
    public $module_id;
    public $status;
    public $max_attempts;
    public $is_aa_quiz;
    public $groups;
    public $questions;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function createQuiz()
    {
        try {
            $this->conn->beginTransaction();

            // Create the quiz entry in exams table
            $query = "INSERT INTO " . $this->table . " 
                    (title, description, teacher_id, duration_minutes, module_id, status, max_attempts, is_aa_quiz) 
                    VALUES 
                    (:title, :description, :teacher_id, :duration_minutes, :module_id, :status, :max_attempts, :is_aa_quiz)";

            $stmt = $this->conn->prepare($query);

            // Bind the quiz data
            $stmt->bindParam(':title', $this->title);
            $stmt->bindParam(':description', $this->description);
            $stmt->bindParam(':teacher_id', $this->teacher_id);
            $stmt->bindParam(':duration_minutes', $this->duration_minutes);
            $stmt->bindParam(':module_id', $this->module_id);
            $stmt->bindValue(':status', 'draft');
            $stmt->bindValue(':max_attempts', $this->max_attempts);
            $stmt->bindValue(':is_aa_quiz', 1);

            if (!$stmt->execute()) {
                throw new Exception("Failed to create quiz");
            }

            $quizId = $this->conn->lastInsertId();

            // Create group associations
            foreach ($this->groups as $groupId) {
                $query = "INSERT INTO exam_groups (exam_id, group_id) VALUES (:exam_id, :group_id)";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':exam_id', $quizId);
                $stmt->bindParam(':group_id', $groupId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create group association");
                }
            }

            // Create questions and options
            foreach ($this->questions as $question) {
                $query = "INSERT INTO " . $this->question_table . "
                        (exam_id, question_text, question_type, points)
                        VALUES 
                        (:exam_id, :question_text, :question_type, :points)";

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':exam_id', $quizId);
                $stmt->bindParam(':question_text', $question['question_text']);
                $stmt->bindValue(':question_type', 'mcq');
                $stmt->bindParam(':points', $question['points']);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to create question");
                }

                $questionId = $this->conn->lastInsertId();

                // Create options for the question
                foreach ($question['options'] as $option) {
                    $query = "INSERT INTO " . $this->options_table . "
                            (question_id, option_text, is_correct)
                            VALUES 
                            (:question_id, :option_text, :is_correct)";

                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':question_id', $questionId);
                    $stmt->bindParam(':option_text', $option['text']);
                    $stmt->bindParam(':is_correct', $option['isCorrect'], PDO::PARAM_BOOL);

                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create option");
                    }
                }
            }

            $this->conn->commit();
            return ['success' => true, 'quiz_id' => $quizId];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getQuizById($id)
    {
        $query = "SELECT e.*, GROUP_CONCAT(g.id) as group_ids, GROUP_CONCAT(g.name) as group_names
                FROM " . $this->table . " e
                LEFT JOIN exam_groups eg ON e.id = eg.exam_id
                LEFT JOIN groupsss g ON eg.group_id = g.id
                WHERE e.id = :id AND e.is_aa_quiz = 1
                GROUP BY e.id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getQuizzesByTeacher($teacherId)
    {
        $query = "SELECT e.*, GROUP_CONCAT(g.name) as group_names,
                    m.name as module_name,
                    (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id) as attempt_count
                FROM " . $this->table . " e
                LEFT JOIN exam_groups eg ON e.id = eg.exam_id
                LEFT JOIN groupsss g ON eg.group_id = g.id
                LEFT JOIN modules m ON e.module_id = m.id
                WHERE e.teacher_id = :teacher_id AND e.is_aa_quiz = 1
                GROUP BY e.id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>