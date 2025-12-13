<?php
include_once __DIR__ . '/../models/Quiz.php';

class QuizController
{
    private $quiz;
    private $module;
    private $group;

    public function __construct($db)
    {
        $this->quiz = new Quiz($db);
        $this->module = new Module($db);
        $this->group = new Group($db);
    }

    public function createQuiz($data)
    {
        // Validate required fields
        if (
            !isset($data['title']) || !isset($data['description']) ||
            !isset($data['teacher_id']) || !isset($data['duration_minutes'])
        ) {
            return [
                'success' => false,
                'message' => 'Missing required fields'
            ];
        }

        // Set quiz properties
        $this->quiz->title = $data['title'];
        $this->quiz->description = $data['description'];
        $this->quiz->teacher_id = $data['teacher_id'];
        $this->quiz->duration_minutes = $data['duration_minutes'];
        $this->quiz->module_id = $data['module_id'];
        $this->quiz->groups = $data['groups'];
        $this->quiz->max_attempts = 3; // As per requirement
        $this->quiz->questions = $data['questions'];

        // Create the quiz
        $result = $this->quiz->createQuiz();

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Quiz created successfully',
                'quiz_id' => $result['quiz_id']
            ];
        }

        return [
            'success' => false,
            'message' => $result['message'] ?? 'Failed to create quiz'
        ];
    }

    public function getQuizzesByTeacher($teacherId)
    {
        try {
            $quizzes = $this->quiz->getQuizzesByTeacher($teacherId);
            return [
                'success' => true,
                'quizzes' => $quizzes
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch quizzes'
            ];
        }
    }

    public function getQuizById($id)
    {
        try {
            $quiz = $this->quiz->getQuizById($id);
            if ($quiz) {
                return [
                    'success' => true,
                    'quiz' => $quiz
                ];
            }
            return [
                'success' => false,
                'message' => 'Quiz not found'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch quiz'
            ];
        }
    }
}
