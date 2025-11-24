<?php
require_once 'config.php';
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        $user_type = $_SESSION['user_type'];
        $user_id = $_SESSION['user_id'];
        
        // Get current password from user_login_info
        $stmt = $conn->prepare("SELECT password FROM user_login_info WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in user_login_info
            $stmt = $conn->prepare("UPDATE user_login_info SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Update must_change_password flag based on user type
            if ($user_type === 'student') {
                $stmt = $conn->prepare("UPDATE students SET must_change_password = FALSE WHERE id = ?");
                $stmt->execute([$user_id]);
            } elseif ($user_type === 'professor') {
                $stmt = $conn->prepare("UPDATE professors SET must_change_password = FALSE WHERE id = ?");
                $stmt->execute([$user_id]);
            }
            
            $_SESSION['must_change_password'] = false;
            $success = 'Password changed successfully!';
            
            // Determine redirect URL based on user type
            $redirect_url = 'login.php'; // default fallback
            if ($user_type === 'admin') {
                $redirect_url = 'admin_dashboard.php';
            } elseif ($user_type === 'professor') {
                $redirect_url = 'prof_dashboard.php';
            } elseif ($user_type === 'student') {
                $redirect_url = 'student_dashboard.php';
            }
            
            // Redirect after 2 seconds
            header("refresh:2;url=$redirect_url");
        } else {
            $error = 'Current password is incorrect';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Academic Advising System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .change-password-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 450px;
            max-width: 90%;
        }
        
        .header {
            background: #1e3c72;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #1e3c72;
        }
        
        .btn-change {
            width: 100%;
            padding: 12px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-change:hover {
            background: #2a5298;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .info {
            background: #e3f2fd;
            color: #1565c0;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <div class="header">
            <h1>Change Password</h1>
            <p>Please set a new secure password</p>
        </div>
        <div class="form-container">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?> Redirecting...</div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password']): ?>
                <div class="info">
                    <strong>First Time Login</strong><br>
                    For security reasons, you must change your initial password before accessing the system.
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password (minimum 8 characters)</label>
                    <input type="password" name="new_password" id="new_password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-change">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>