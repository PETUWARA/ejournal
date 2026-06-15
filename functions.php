<?php
session_start();

// Execute database query using PDO
function query($sql, $parameters=[]) {
    require 'config.php';
    $charset = 'utf8mb4';
    
    try {
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset={$charset}", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare($sql);

        foreach($parameters as $nam=>$val) {
            $stmt->bindValue(":{$nam}", $val, PDO::PARAM_STR);
        }

        $stmt->execute();
        
        if (strpos(strtoupper($sql), 'SELECT') === 0) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $result = true;
        }
        unset($pdo);
    }
    catch(PDOException $e) {
        $result = false;
    }
    return $result;
}

// Destroy session and redirect to login
function logout() {
    if(isset($_SESSION)) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    header('Location: login.php');
    exit;
}

// Authenticate user credentials
function login($user, $password) {
    $_SESSION = array();

    $sql = "SELECT * FROM users WHERE username = :user AND password = MD5(:password)";
    $parameters = [
        'user' => $user, 
        'password' => $password
    ];

    if($results = query($sql, $parameters)) {
        $_SESSION['user_id']  = $results[0]['id'];
        $_SESSION['username'] = $results[0]['username'];
        $_SESSION['role']     = $results[0]['role'];
        return true;
    } else {
        return false;
    }
}

// Check if user session exists
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Verify if logged-in user is a teacher
function isTeacher() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher');
}

// Sanitize user inputs
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Convert numeric grade to letter format
function getLetterGrade($grade) {
    if ($grade >= 90) return 'A';
    if ($grade >= 80) return 'B';
    if ($grade >= 70) return 'C';
    if ($grade >= 60) return 'D';
    return 'F';
}