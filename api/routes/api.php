<?php

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../vendor/autoload.php';

include_once '../config/database.php';
include_once '../middleware/JWTMiddleware.php';
include_once '../controllers/UserController.php';
include_once '../controllers/ExamController.php';
include_once '../controllers/QuestionController.php';
include_once '../controllers/GroupController.php';
include_once '../controllers/ModuleController.php';
include_once '../controllers/ProfileController.php';
include_once '../controllers/QuizController.php';
include_once '../controllers/StudentAnswerController.php';
include_once '../controllers/GradeController.php';
include_once '../controllers/NotificationController.php';



$db = database::connect();
$userController = new UserController($db);
$examController = new ExamController($db);
$questionController = new QuestionController($db);
$groupController = new GroupController($db);
$moduleController = new ModuleController($db);
$profileController = new ProfileController($db);
$quizController = new QuizController($db);
$studentAnswerController = new StudentAnswerController($db);
$gradeController = new GradeController($db);
$notificationController = new NotificationController($db);



$request_uri = trim($_SERVER['REQUEST_URI'], '/');
$script_name = trim($_SERVER['SCRIPT_NAME'], '/');
$request_path = str_replace($script_name, '', $request_uri);
$request_path = trim($request_path, '/');
$request = explode('/', $request_path);
$method = $_SERVER['REQUEST_METHOD'];

// Define public routes
$publicRoutes = [
    'login' => ['POST'],
    'refresh-token' => ['POST'],
    'add-user' => ['POST'],
];

// Check if the current route requires authentication
$currentRoute = $request[0];
$requiresAuth = true;

if (isset($publicRoutes[$currentRoute]) && in_array($method, $publicRoutes[$currentRoute])) {
    $requiresAuth = false;
}

if ($requiresAuth) {
    $decoded = JWTMiddleware::verifyToken();
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    $userId = $decoded->user_id;
    $userRole = $decoded->role;
}

// die($request[0]  );
switch (true) {
    // Public routes
    case $request[0] === 'add-user' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($userController->addUsers($data));
        break;

    case $request[0] === 'login' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($userController->login($data));
        break;

    case $request[0] === 'refresh-token' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($userController->refreshToken($data));
        break;
    // Protected routes
    case $request[0] === 'attempt-details2' && $method === 'GET' && isset($request[1]):
        // if ($userRole !== 'admin' && $userId !== $request[1]) {
        //     http_response_code(403);
        //     echo json_encode(['message' => 'Unauthorized access']);
        //     break;
        // }
        echo json_encode($studentAnswerController->getAttemptDetails($request[1]));
        break;
    case $request[0] === 'update-attempt' && $method === 'GET' && isset($request[1]):

        echo json_encode($studentAnswerController->updateAttemptScore($request[1]));
        break;
    case $request[0] === 'attempt-details3' && $method === 'GET':
        echo json_encode($gradeController->getAttemptDetails());
        break;

    case $request[0] === 'update-score' && $method === 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($gradeController->updateScore($data['attemptId'], $data['score']));
        break;
    case $request[0] === 'create-quiz' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($quizController->createQuiz($data));
        break;


    case $request[0] === 'create-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->createExam($data));
        break;

    case $request[0] === 'profile' && $method === 'GET' && isset($request[1]):
        echo json_encode($profileController->getProfile($request[1]));
        break;

    case $request[0] === 'profile' && $method === 'PUT' && isset($request[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($profileController->updateProfile($request[1], $data));
        break;

    case $request[0] === 'create-notification' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $notificationController = new NotificationController($db);
        echo json_encode($notificationController->createNotification($data, $userId, $userRole));
        break;

    case $request[0] === 'notifications' && $request[2] === 'publish' && $method === 'PUT':
        $notificationId = $request[1];

        echo json_encode($notificationController->publishNotification($notificationId, $userId));
        break;
    case $request[0] === 'notifications' && $request[2] === 'unpublish' && $method === 'PUT':
        $notificationId = $request[1];
        echo json_encode($notificationController->unpublishNotification($notificationId, $userId));
        break;

    case strpos($request[0], 'update-notification') === 0 && $method === 'PUT':
        $notificationId = end($request);
        $data = json_decode(file_get_contents("php://input"), true);

        echo json_encode($notificationController->updateNotification($notificationId, $data, $userId, $userRole));
        break;

    case strpos($request[0], 'delete-notification') === 0 && $method === 'DELETE':
        $notificationId = end($request);

        echo json_encode($notificationController->deleteNotification($notificationId, $userId, $userRole));
        break;

    case $request[0] === 'user-notifications' && $method === 'GET':
        $type = $request[1] ?? 'received';

        echo json_encode($notificationController->getNotifications($userId, $userRole, $type));
        break;
    case $request[0] === 'mark-notification-read' && $method === 'POST':
        $notificationId = $request[1];

        echo json_encode($notificationController->markAsRead($notificationId, $userId));
        break;


    case $request[0] === 'question-options' && $method === 'POST' && isset($request[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($questionController->getQuestionOptions($request[1], $data['optionIds']));
        break;
    case $request[0] === 'exam-questions' && $method === 'GET' && isset($request[1]):
        echo json_encode($questionController->getQuestionsByExam($request[1]));
        break;


    case $request[0] === 'exam-attempts' && $method === 'GET' && isset($request[1]):
        echo json_encode($examController->getExamAttempts($request[1]));
        break;
    case $request[0] === 'submit-correction' && $method === 'POST' && isset($request[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($examController->submitCorrection($request[1], $data['answers']));
        break;

    case $request[0] === 'attempt-details' && $method === 'GET' && isset($request[1]):
        echo json_encode($examController->getAttemptDetails($request[1]));
        break;

    case $request[0] === 'update-score' && $method === 'PUT' && isset($request[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['score'])) {
            echo json_encode($examController->updateScore($request[1], floatval($data['score'])));
        } else {
            echo json_encode(['success' => false, 'message' => 'Score value is missing']);
        }
        break;

    case preg_match('/^student-exams\/(\d+)$/', $request_path, $matches) && $method === 'GET':
        $studentId = $matches[1];
        echo json_encode($examController->getStudentExams($studentId));
        break;
    case preg_match('/^student-exams-grade\/(\d+)$/', $request_path, $matches) && $method === 'GET':
        $studentId = $matches[1];
        echo json_encode($gradeController->getStudentExamsGrade($studentId));
        break;

    case $request[0] === 'validate-exam-access' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->validateExamAccess($data['exam_id'], $data['student_id']));
        break;
    case $request[0] === 'get-modules' && $method === 'GET':
        echo json_encode($moduleController->getAllModules());
        break;

    case $request[0] === 'create-module' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($moduleController->createModule($data));
        break;
    case $request[0] === 'v-attempt' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->verifyExamAttempt($data['attemptId']));
        break;
    // case $request[0] === 'v-attempt-status' && $method === 'POST':
    //     $data = json_decode(file_get_contents("php://input"), true);
    //     echo json_encode($examController->verifyAttemptStatus($attamptId));
    //     break;

    case $request[0] === 'create-attempt' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->createAttempt($data));
        break;

    case $request[0] === 'submit-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->submitExam($data));
        break;





    case $request[0] === 'groups' && $method === 'GET':
        echo json_encode($groupController->getAllGroups());
        break;

    case $request[0] === 'groups' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($groupController->createGroup($data));
        break;


    case $request[0] === 'users' && $method === 'GET':
        echo json_encode($userController->getUsers());
        break;

    case $request[0] === 'users2' && $method === 'GET' && count($request) === 2:
        $userId = $request[1];
        echo json_encode($userController->getUserById($userId));
        break;

    case $request[0] === 'users3' && $method === 'PUT' && count($request) === 2:
        $userId = $request[1];
        $userData = json_decode(file_get_contents('php://input'), true);
        echo json_encode($userController->updateUser($userId, $userData));
        break;

    case $request[0] === 'users' && $method === 'DELETE' && count($request) === 2:
        $userId = $request[1];
        echo json_encode($userController->deleteUser($userId));
        break;

    case $request[0] === 'check-status' && $method === 'GET':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'User is not logged in.']);
            exit; // Stop further execution
        }
        $userId = $_SESSION['user_id'];
        echo json_encode($userController->checkUserStatus($userId));
        break;
    case $request[0] === 'users' && $request[1] === 'permissions' && $method === 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($userController->updatePermissions($data));
        break;
    case $request[0] === 'create-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $data['teacher_id'] = $userId;
        echo json_encode($examController->createExam($data));
        break;
    case $request[0] === 'toggle-exam-status' && isset($request[1]) && $method === 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($examController->toggleExamStatus($request[1], $data['status']));
        break;
    case $request[0] === 'delete-exam' && isset($request[1]) && $method === 'DELETE':
        echo json_encode($examController->deleteExam($request[1]));
        break;
    case $request[0] === 'teacher-exams' && isset($request[1]) && $method === 'GET':
        echo json_encode($examController->getExamsByTeacher($request[1]));
        break;
    case $request[0] === 'create-question' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($questionController->createQuestion($data));
        break;


    case $request[0] === 'exam' && isset($request[1]) && $method === 'GET':
        $teacher_id = $_GET['teacher_id'] ?? null;
        if (!$teacher_id) {
            echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
            break;
        }
        echo json_encode($examController->getExamById($request[1], $teacher_id));
        break;

    case $request[0] === 'update-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        // Debug: Log the request data
        error_log("Update Exam Request: " . json_encode($data));

        // Ensure we have all required data
        if (!isset($data['id']) || !isset($data['teacher_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: id or teacher_id'
            ]);
            break;
        }

        // Call the controller method
        $result = $examController->updateExam($data);

        // Debug: Log the response
        error_log("Update Exam Response: " . json_encode($result));

        echo json_encode($result);
        break;
    case $request[0] === 'start-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->startExam($data));
        break;


    default:
        echo json_encode(['message' => 'Route not found']);
}

// Add error handling
function handleError($errno, $errstr, $errfile, $errline)
{
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit();
}

set_error_handler('handleError');
