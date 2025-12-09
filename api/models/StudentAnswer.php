<?php
class StudentAnswer
{
    private $conn;


    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAnswersWithCorrection($attemptId)
    {
        $query = "SELECT 
            sa.id, sa.question_id, sa.answer_text, sa.selected_options,
            q.question_type,
            sa.points_earned, q.points as max_points, q.question_text,
            CASE 
                WHEN q.image_data IS NOT NULL 
                THEN CONCAT('data:image/jpeg;base64,', TO_BASE64(q.image_data))
                ELSE NULL 
            END as image_data,
            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', qo.id,
                        'option_text', qo.option_text,
                        'is_correct', qo.is_correct,
                        'is_selected', JSON_CONTAINS(sa.selected_options, CAST(qo.id AS JSON))
                    )
                )
                FROM question_options qo
                WHERE qo.question_id = sa.question_id
            ) as options_data
            FROM student_answers sa
            JOIN questions q ON sa.question_id = q.id
            WHERE sa.attempt_id = :attempt_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':attempt_id', $attemptId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function autoCorrectMCQ($attemptId)
    {
        $query = "UPDATE student_answers sa
            SET points_earned = (
                SELECT 
                    CASE 
                        WHEN COUNT(DISTINCT CASE WHEN qo.is_correct != JSON_CONTAINS(sa.selected_options, CAST(qo.id AS JSON)) THEN 1 END) = 0 
                        THEN q.points 
                        ELSE 0 
                    END
                FROM question_options qo
                JOIN questions q ON sa.question_id = q.id
                WHERE qo.question_id = sa.question_id
            ),
            is_corrected = true
            WHERE sa.attempt_id = :attempt_id
            AND sa.selected_options IS NOT NULL";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':attempt_id', $attemptId);
        return $stmt->execute();
    }

    public function updateAttemptScore($attemptId)
    {
        $query = "UPDATE exam_attempts ea
            SET score = (
                SELECT SUM(points_earned)
                FROM student_answers
                WHERE attempt_id = :attempt_id
            )
            WHERE ea.id = :attempt_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':attempt_id', $attemptId);
        return $stmt->execute();
    }
}
