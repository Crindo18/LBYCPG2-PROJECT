<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_input = trim($_POST['id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($id_input) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Fetch login info using either username (admin) OR id_number (students/profs)
        $stmt = $conn->prepare("SELECT * FROM user_login_info WHERE id_number = ? OR username = ?");
        $stmt->bind_param("ss", $id_input, $id_input);
        $stmt->execute();
        $result = $stmt->get_result();
        $login = $result->fetch_assoc();

        if ($login && password_verify($password, $login['password'])) {
            
            // Store universal session info
            $_SESSION['user_id'] = $login['id'];
            $_SESSION['id_number'] = $login['id_number'];
            $_SESSION['username'] = $login['username'];
            $_SESSION['user_type'] = $login['user_type'];
            $_SESSION['logged_in'] = true;

            // Redirect based on user type
            if ($login['user_type'] === 'admin') {
                header("Location: admin_dashboard.php");
                exit();

            } elseif ($login['user_type'] === 'student') {
                // Load student profile (Removed must_change_password check)
                $s = $conn->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
                $s->bind_param("i", $login['id']);
                $s->execute();
                $student_result = $s->get_result();
                $student = $student_result->fetch_assoc();

                $_SESSION['name'] = $student['first_name'] . ' ' . $student['last_name'];
                
                header("Location: student_dashboard.php");
                exit();

            } elseif ($login['user_type'] === 'professor') {
                // Load professor profile (Removed must_change_password check)
                $p = $conn->prepare("SELECT first_name, last_name FROM professors WHERE id = ?");
                $p->bind_param("i", $login['id']);
                $p->execute();
                $prof_result = $p->get_result();
                $prof = $prof_result->fetch_assoc();

                $_SESSION['name'] = $prof['first_name'] . ' ' . $prof['last_name'];

                header("Location: prof_dashboard.php");
                exit();
            }
        }

        // If login failed
        $error = 'Invalid ID Number / Username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Academic Advising System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .login-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); width: 100%; max-width: 450px; }
        .logo-section { text-align: center; margin-bottom: 30px; }
        .logo-section h1 { color: #1e3c72; font-size: 28px; margin-bottom: 10px; }
        .logo-section p { color: #666; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; transition: all 0.3s; }
        .form-group input:focus { outline: none; border-color: #1e3c72; box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1); }
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .btn-login { width: 100%; padding: 14px; background: #1e3c72; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-login:hover { background: #152a52; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3); }
        .btn-login:active { transform: translateY(0); }
        .footer-text { text-align: center; margin-top: 20px; color: #666; font-size: 13px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <h1>Academic Advising System</h1>
            <p>De La Salle University</p>
        </div>

        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">            
                <div class="form-group">
                    <label for="id">ID Number / Username</label>
                    <input type="text" name="id" id="id" placeholder="Enter your ID number" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="footer-text">
                <p style="margin-top: 15px; font-size: 12px;">
                    Default password is your ID number. Contact your department for assistance.
                </p>
            </div>
        </div>
    </div>
</body>
</html>