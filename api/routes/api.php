<?php
header("Access-Control-Allow-Origin: https://examlyfront.vercel.app");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/JWTMiddleware.php';
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);




include_once __DIR__ . '/../controllers/UserController.php';
include_once __DIR__ . '/../controllers/ExamController.php';
include_once __DIR__ . '/../controllers/QuestionController.php';
include_once __DIR__ . '/../controllers/GroupController.php';
include_once __DIR__ . '/../controllers/ModuleController.php';
include_once __DIR__ . '/../controllers/ProfileController.php';
include_once __DIR__ . '/../controllers/QuizController.php';
include_once __DIR__ . '/../controllers/StudentAnswerController.php';
include_once __DIR__ . '/../controllers/GradeController.php';
include_once __DIR__ . '/../controllers/NotificationController.php';

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

// ✅ FIXED ROUTING LOGIC - More reliable URL parsing
$request_uri = $_SERVER['REQUEST_URI'];
$request_uri = strtok($request_uri, '?'); // Remove query string
$request_uri = trim($request_uri, '/');
$request_path = explode('/', $request_uri);

// Get first segment (the route)
$route = $request_path[0] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Debug - Remove after testing
error_log("Route: $route | Method: $method | Full path: " . json_encode($request_path));

// ✅ Define public routes (NO JWT required)
$publicRoutes = [
    'login' => ['POST'],
    'refresh-token' => ['POST'],
    'add-user' => ['POST'],
    'test' => ['GET'],
];

// ✅ Check if the current route requires authentication
$requiresAuth = true;

if (isset($publicRoutes[$route]) && in_array($method, $publicRoutes[$route])) {
    $requiresAuth = false;
}

// ✅ Only verify JWT for protected routes
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

// ✅ ROUTES - Using $route instead of $request[0]
switch (true) {
    // ========================================
    // PUBLIC ROUTES (No authentication needed)
    // ========================================
    case $route === 'test' && $method === 'GET':
        echo json_encode([
            'success' => true,
            'message' => 'API is working!',
            'route' => $route,
            'method' => $method
        ]);
        break;
    case $route === 'add-user' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($userController->addUsers($data));
        break;

    case $route === 'login' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($userController->login($data));
        break;

    case $route === 'refresh-token' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($userController->refreshToken($data));
        break;

    // ========================================
    // PROTECTED ROUTES (Authentication required)
    // ========================================
    
    case $route === 'attempt-details2' && $method === 'GET' && isset($request_path[1]):
        echo json_encode($studentAnswerController->getAttemptDetails($request_path[1]));
        break;
        
    case $route === 'update-attempt' && $method === 'GET' && isset($request_path[1]):
        echo json_encode($studentAnswerController->updateAttemptScore($request_path[1]));
        break;
        
    case $route === 'attempt-details3' && $method === 'GET':
        echo json_encode($gradeController->getAttemptDetails());
        break;

    case $route === 'update-score' && $method === 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($gradeController->updateScore($data['attemptId'], $data['score']));
        break;
        
    case $route === 'create-quiz' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($quizController->createQuiz($data));
        break;

    case $route === 'create-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->createExam($data));
        break;

    case $route === 'profile' && $method === 'GET' && isset($request_path[1]):
        echo json_encode($profileController->getProfile($request_path[1]));
        break;

    case $route === 'profile' && $method === 'PUT' && isset($request_path[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($profileController->updateProfile($request_path[1], $data));
        break;

    case $route === 'create-notification' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($notificationController->createNotification($data, $userId, $userRole));
        break;

    case $route === 'notifications' && ($request_path[2] ?? '') === 'publish' && $method === 'PUT':
        $notificationId = $request_path[1];
        echo json_encode($notificationController->publishNotification($notificationId, $userId));
        break;
        
    case $route === 'notifications' && ($request_path[2] ?? '') === 'unpublish' && $method === 'PUT':
        $notificationId = $request_path[1];
        echo json_encode($notificationController->unpublishNotification($notificationId, $userId));
        break;

    case strpos($route, 'update-notification') === 0 && $method === 'PUT':
        $notificationId = end($request_path);
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($notificationController->updateNotification($notificationId, $data, $userId, $userRole));
        break;

    case strpos($route, 'delete-notification') === 0 && $method === 'DELETE':
        $notificationId = end($request_path);
        echo json_encode($notificationController->deleteNotification($notificationId, $userId, $userRole));
        break;

    case $route === 'user-notifications' && $method === 'GET':
        $type = $request_path[1] ?? 'received';
        echo json_encode($notificationController->getNotifications($userId, $userRole, $type));
        break;
        
    case $route === 'mark-notification-read' && $method === 'POST':
        $notificationId = $request_path[1];
        echo json_encode($notificationController->markAsRead($notificationId, $userId));
        break;

    case $route === 'question-options' && $method === 'POST' && isset($request_path[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($questionController->getQuestionOptions($request_path[1], $data['optionIds']));
        break;
        
    case $route === 'exam-questions' && $method === 'GET' && isset($request_path[1]):
        echo json_encode($questionController->getQuestionsByExam($request_path[1]));
        break;

    case $route === 'exam-attempts' && $method === 'GET' && isset($request_path[1]):
        echo json_encode($examController->getExamAttempts($request_path[1]));
        break;
        
    case $route === 'submit-correction' && $method === 'POST' && isset($request_path[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($examController->submitCorrection($request_path[1], $data['answers']));
        break;

    case $route === 'attempt-details' && $method === 'GET' && isset($request_path[1]):
        echo json_encode($examController->getAttemptDetails($request_path[1]));
        break;

    case $route === 'update-score' && $method === 'PUT' && isset($request_path[1]):
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['score'])) {
            echo json_encode($examController->updateScore($request_path[1], floatval($data['score'])));
        } else {
            echo json_encode(['success' => false, 'message' => 'Score value is missing']);
        }
        break;

    case preg_match('/^student-exams\/(\d+)$/', $request_uri, $matches) && $method === 'GET':
        $studentId = $matches[1];
        echo json_encode($examController->getStudentExams($studentId));
        break;
        
    case preg_match('/^student-exams-grade\/(\d+)$/', $request_uri, $matches) && $method === 'GET':
        $studentId = $matches[1];
        echo json_encode($gradeController->getStudentExamsGrade($studentId));
        break;

    case $route === 'validate-exam-access' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->validateExamAccess($data['exam_id'], $data['student_id']));
        break;
        
    case $route === 'get-modules' && $method === 'GET':
        echo json_encode($moduleController->getAllModules());
        break;

    case $route === 'create-module' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($moduleController->createModule($data));
        break;
        
    case $route === 'v-attempt' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->verifyExamAttempt($data['attemptId']));
        break;

    case $route === 'create-attempt' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->createAttempt($data));
        break;

    case $route === 'submit-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->submitExam($data));
        break;

    case $route === 'groups' && $method === 'GET':
        echo json_encode($groupController->getAllGroups());
        break;

    case $route === 'groups' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($groupController->createGroup($data));
        break;

    case $route === 'users' && $method === 'GET':
        echo json_encode($userController->getUsers());
        break;

    case $route === 'users2' && $method === 'GET' && count($request_path) === 2:
        $userId = $request_path[1];
        echo json_encode($userController->getUserById($userId));
        break;

    case $route === 'users3' && $method === 'PUT' && count($request_path) === 2:
        $userId = $request_path[1];
        $userData = json_decode(file_get_contents('php://input'), true);
        echo json_encode($userController->updateUser($userId, $userData));
        break;

    case $route === 'users' && $method === 'DELETE' && count($request_path) === 2:
        $userId = $request_path[1];
        echo json_encode($userController->deleteUser($userId));
        break;

    case $route === 'check-status' && $method === 'GET':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'User is not logged in.']);
            exit;
        }
        $userId = $_SESSION['user_id'];
        echo json_encode($userController->checkUserStatus($userId));
        break;
        
    case $route === 'users' && ($request_path[1] ?? '') === 'permissions' && $method === 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($userController->updatePermissions($data));
        break;

    case $route === 'toggle-exam-status' && isset($request_path[1]) && $method === 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($examController->toggleExamStatus($request_path[1], $data['status']));
        break;
        
    case $route === 'delete-exam' && isset($request_path[1]) && $method === 'DELETE':
        echo json_encode($examController->deleteExam($request_path[1]));
        break;
        
    case $route === 'teacher-exams' && isset($request_path[1]) && $method === 'GET':
        echo json_encode($examController->getExamsByTeacher($request_path[1]));
        break;
        
    case $route === 'create-question' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($questionController->createQuestion($data));
        break;

    case $route === 'exam' && isset($request_path[1]) && $method === 'GET':
        $teacher_id = $_GET['teacher_id'] ?? null;
        if (!$teacher_id) {
            echo json_encode(['success' => false, 'message' => 'Teacher ID required']);
            break;
        }
        echo json_encode($examController->getExamById($request_path[1], $teacher_id));
        break;

    case $route === 'update-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        error_log("Update Exam Request: " . json_encode($data));

        if (!isset($data['id']) || !isset($data['teacher_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: id or teacher_id'
            ]);
            break;
        }

        $result = $examController->updateExam($data);
        error_log("Update Exam Response: " . json_encode($result));
        echo json_encode($result);
        break;
        
    case $route === 'start-exam' && $method === 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        echo json_encode($examController->startExam($data));
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Route not found', 'route' => $route, 'method' => $method]);
}

// Error handling
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