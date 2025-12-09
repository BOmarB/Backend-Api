<?php
class Question
{
    private $conn;
    private $table = "questions";
    private $options_table = "question_options";

    public $id;
    public $exam_id;
    public $question_text;
    public $image_data;
    public $question_type;
    public $points;
    public $options;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function create($data)
    {
        $query = "INSERT INTO " . $this->table . " 
                (exam_id, question_text, question_type, points, image_data) 
                VALUES 
                (:exam_id, :question_text, :question_type, :points, :image_data)";

        $stmt = $this->conn->prepare($query);

        // Process image data if it exists
        $imageData = null;
        if (!empty($data['image']) && preg_match('/^data:image\/(\w+);base64,/', $data['image'], $type)) {
            $imageData = substr($data['image'], strpos($data['image'], ',') + 1);
            $imageData = base64_decode($imageData);
        }

        $stmt->bindParam(':exam_id', $data['exam_id']);
        $stmt->bindParam(':question_text', $data['question_text']);
        $stmt->bindParam(':question_type', $data['question_type']);
        $stmt->bindParam(':points', $data['points']);
        $stmt->bindParam(':image_data', $imageData, PDO::PARAM_LOB);

        if ($stmt->execute()) {
            $question_id = $this->conn->lastInsertId();

            if ($data['question_type'] === 'mcq' && !empty($data['options'])) {
                $this->createOptions($question_id, $data['options']);
            }

            return $question_id;
        }
        return false;
    }

    public function getQuestionsByExam($exam_id)
    {
        $query = "SELECT q.*, e.duration_minutes,
                  CASE 
                    WHEN q.image_data IS NOT NULL 
                    THEN CONCAT('data:image/jpeg;base64,', TO_BASE64(q.image_data))
                    ELSE NULL 
                  END as image_data,
                  (SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                      'id', o.id,
                      'text', o.option_text,
                      'isCorrect', o.is_correct
                    )
                  ) FROM " . $this->options_table . " o 
                  WHERE o.question_id = q.id) as options 
                FROM " . $this->table . " q 
                JOIN exams e ON q.exam_id = e.id
                WHERE q.exam_id = :exam_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function createOptions($question_id, $options)
    {
        try {
            $query = "INSERT INTO " . $this->options_table . " 
                    (question_id, option_text, is_correct) 
                    VALUES 
                    (:question_id, :option_text, :is_correct)";

            $stmt = $this->conn->prepare($query);

            foreach ($options as $option) {
                $stmt->bindParam(':question_id', $question_id);
                $stmt->bindParam(':option_text', $option['text']);
                $isCorrect = $option['isCorrect'] ? 1 : 0;  // Convert boolean to integer
                $stmt->bindParam(':is_correct', $isCorrect, PDO::PARAM_INT);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert option");
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Error creating options: " . $e->getMessage());
            return false;
        }
    }

    public function getQuestionOptionsByIds($questionId)
    {
        $query = "SELECT id, option_text, is_correct 
            FROM question_options 
            WHERE question_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$questionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
