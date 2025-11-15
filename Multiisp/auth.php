<?php
session_start(); // Start the session

// Database connection
$host = '127.0.0.1'; // Database host
$dbname = 'isp_portal'; // Database name
$username = 'root'; // Database username
$password = ''; // Database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Check if the action is logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Destroy the session
    session_unset();
    session_destroy();

    // Redirect to the home page or login page
    header('Location: /');
    exit;
}

// Check if the action is login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Prepare and execute the query to check credentials
    $stmt = $pdo->prepare("SELECT role FROM users WHERE username = :username AND password = :password");
    $stmt->execute(['username' => $user, 'password' => $pass]);
    $userRole = $stmt->fetchColumn();

    var_dump($userRole); // This will output the role fetched from the database
    exit; // Stop execution to see the output

    if ($userRole) {
        $_SESSION['username'] = $user; // Store username in session
        // Ensure admin redirects to the correct dashboard
        if ($userRole === 'admin') {
            header('Location: /super/dashboard_admin.php'); // Redirect to admin dashboard
        } elseif ($userRole === 'isp') {
            header('Location: /isp/dashboard.php'); // Redirect to ISP dashboard
        } elseif ($userRole === 'user') {
            header('Location: /user/home.php'); // Redirect to user home page
        } else {
            echo "Role not recognized"; // Handle unrecognized roles
        }
        exit;
    } else {
        echo "Invalid credentials"; // Handle invalid login
    }
}

// ...existing code...
?>