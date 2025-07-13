<?php
session_start();

// Include your database connection file
require_once 'config_files/config.php';

$error_message = '';
$success_message = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Create connection using your existing function
        $conn = createConnection();
        
        // Prepare SQL statement to prevent SQL injection
        $stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, email, password_hash FROM users WHERE email = ?");
        
        if ($stmt) {
            // Bind parameters
            mysqli_stmt_bind_param($stmt, "s", $email);
            
            // Execute the statement
            mysqli_stmt_execute($stmt);
            
            // Get the result
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_first_name'] = $user['first_name'];
                $_SESSION['user_last_name'] = $user['last_name'];
                $_SESSION['user_full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Close statement and connection
                mysqli_stmt_close($stmt);
                closeConnection($conn);
                
                // Redirect to home page
                header('Location: home.php');
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
            
            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            $error_message = 'Database error. Please try again later.';
            // Log the actual error for debugging
            error_log("Login prepare error: " . mysqli_error($conn));
        }
        
        // Close connection
        closeConnection($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sign in</title>
    <link rel="stylesheet" href="styles/styles.css"/>
  </head>

  <body>
    <div class="page-wrapper">

      <!-- LOGIN MAIN SECTION -->
      <section class="login-main-container">

        <!-- LOGIN LEFT WRAPPER SECTION -->
        <div class="login-left-container">
          <div class="logo">
            <div class="logo-text">Farm2Door</div>
          </div>

          <!-- LOGIN FORM WRAPPER -->
          <div class="login-form-wrapper">

            <div class="login-header-text-wrapper">
              <h2 class="text-white">Sign into your account</h2>
              <body class="login-text-green">To access the Farm2Door platform</body>
            </div>

            <!-- Display error or success messages -->
            <?php if (!empty($error_message)): ?>
              <div class="error-message" style="color: #ff6b6b; background: rgba(255, 107, 107, 0.1); padding: 10px; border-radius: 5px; margin: 10px 0;">
                <?php echo htmlspecialchars($error_message); ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
              <div class="success-message" style="color: #51cf66; background: rgba(81, 207, 102, 0.1); padding: 10px; border-radius: 5px; margin: 10px 0;">
                <?php echo htmlspecialchars($success_message); ?>
              </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
              <div class="login-fields-wrapper">
                <div class="login-input-wrapper">
                  <label class="text-white">Email</label>
                  <input 
                    type="email" 
                    name="email" 
                    class="login-email-input" 
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required
                  >
                </div>
                <div class="login-input-wrapper">
                  <label class="text-white">Password</label>
                  <input 
                    type="password" 
                    name="password" 
                    class="login-password-input"
                    required
                  >
                </div>
                <button type="submit" class="login-button">Sign in</button>
              </div>
            </form>

            <!-- Additional links -->
            <div class="login-links" style="margin-top: 20px; text-align: center;">
              <p class="text-white">
                Don't have an account? 
                <a href="index.php" style="color: #51cf66; text-decoration: none;">Sign up here</a>
              </p>
              <p>
                <a href="forgot-password.php" style="color: #51cf66; text-decoration: none; font-size: 14px;">Forgot your password?</a>
              </p>
            </div>

          </div>

          <div class="copyright-wrapper">
            <div class="very-small">© 2025. All rights reserved.</div>
            <div class="very-small">Farm2Door.</div>
          </div>
        </div>

        <!-- LOGIN RIGHT WRAPPER SECTION -->
        <img class="login-image" src="assets\images\farm.jpg" />
      </section>

    </div>
  </body>
</html>
