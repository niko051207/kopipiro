<?php
/**
 * Authentication API.
 *
 *   GET  api/auth.php?action=me        current user (or {role:"guest"})
 *   POST api/auth.php?action=login     {username, password}
 *   POST api/auth.php?action=logout    end the session
 *   POST api/auth.php?action=register  {name, username, password} -> customer account
 */

require_once __DIR__ . '/../helpers.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'csrf':
        require_method(['GET']);
        out(['csrf_token' => csrf_token()]);

    case 'me':
        out(me() ?? ['role' => 'guest']);

    case 'login':
        require_method(['POST']);
        require_api_csrf();
        $input = body();
        $username = trim($input['username'] ?? '');
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || strlen($username) > 50 || $password === '' || strlen($password) > 72) {
            out(['message' => 'Username and password are required'], 400);
        }
        $statement = $conn->prepare('SELECT id, username, name, password, role, is_active FROM users WHERE username = ?');
        $statement->bind_param('s', $username);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        if (!$user || !password_verify($password, $user['password'])) {
            out(['message' => 'Wrong username or password'], 401);
        }
        if (!(int) $user['is_active']) {
            out(['message' => 'This account is disabled'], 403);
        }
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        out([
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'role' => $user['role'],
        ]);

    case 'register':
        require_method(['POST']);
        require_api_csrf();
        $input = body();
        $name = trim($input['name'] ?? '');
        $username = trim($input['username'] ?? '');
        $password = (string) ($input['password'] ?? '');
        if ($name === '' || strlen($name) > 100 || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || strlen($password) < 8 || strlen($password) > 72) {
            out(['message' => 'Provide a name (max 100), valid username (3-50), and password (8-72 characters)'], 400);
        }
        try {
            $statement = $conn->prepare('INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, "customer")');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $statement->bind_param('sss', $username, $name, $hash);
            $statement->execute();
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1062 || strpos($exception->getMessage(), 'Duplicate entry') !== false) {
                out(['message' => 'Username already registered'], 409);
            }
            out(['message' => 'Could not register this account'], 500);
        }
        // Deliberately does not start a session: the module registers and then logs in.
        out(['message' => 'Registered. You can log in now.'], 201);

    case 'logout':
        require_method(['POST']);
        require_api_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        out(['message' => 'Logged out']);

    default:
        out(['message' => 'Unknown action'], 404);
}
