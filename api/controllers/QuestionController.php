<?php
include_once '../models/Question.php';

class QuestionController
{
    private $question;

    public function __construct($db)
    {
        $this->question = new Question($db);
    }




    public function createQuestion($data)
    {
        // Validate image size (5MB limit)
        if (!empty($data['image'])) {
            $size = strlen(base64_decode(substr($data['image'], strpos($data['image'], ',') + 1)));
            if ($size > 5242880) { // 5MB in bytes
                return ['success' => false, 'message' => 'Image size should be less than 5MB'];
            }
        }

        $question_id = $this->question->create($data);

        if ($question_id) {
            return ['success' => true, 'question_id' => $question_id];
        }

        return ['success' => false, 'message' => 'Failed to create question'];
    }

    public function getQuestionsByExam($exam_id)
    {
        $questions = $this->question->getQuestionsByExam($exam_id);
        return [
            'success' => true,
            'questions' => array_map(function ($q) {
                if ($q['options']) {
                    $q['options'] = json_decode($q['options'], true);
                }
                return $q;
            }, $questions)
        ];
    }

    public function getQuestionOptions($questionId, $optionIds)
    {
        try {
            $options = $this->question->getQuestionOptionsByIds($questionId, $optionIds);
            return ['success' => true, 'options' => $options];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
