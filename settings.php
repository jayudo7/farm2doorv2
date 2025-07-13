<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit();
}

// Include your database connection
require_once 'config_files/config.php';

// Get the logged-in user's ID and info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_full_name'] ?? '';
$user_first_name = $_SESSION['user_first_name'] ?? '';
$user_last_name = $_SESSION['user_last_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

$success_message = '';
$error_message = '';

// Create connection
$conn = createConnection();

// Fetch user details
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $location = trim($_POST['location']);
                $description = trim($_POST['description']);
                
                // Basic validation
                if (empty($first_name) || empty($last_name) || empty($email)) {
                    $error_message = "Name and email are required fields.";
                    break;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = "Please enter a valid email address.";
                    break;
                }
                
                // Check if email is already used by another user
                $email_check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
                mysqli_stmt_bind_param($email_check, "si", $email, $user_id);
                mysqli_stmt_execute($email_check);
                mysqli_stmt_store_result($email_check);
                
                if (mysqli_stmt_num_rows($email_check) > 0) {
                    $error_message = "This email is already in use by another account.";
                    mysqli_stmt_close($email_check);
                    break;
                }
                mysqli_stmt_close($email_check);
                
                // Update user profile
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, location = ?, description = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "ssssssi", $first_name, $last_name, $email, $phone, $location, $description, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // Update session variables
                    $_SESSION['user_first_name'] = $first_name;
                    $_SESSION['user_last_name'] = $last_name;
                    $_SESSION['user_full_name'] = $first_name . ' ' . $last_name;
                    $_SESSION['user_email'] = $email;
                    
                    $success_message = "Profile updated successfully!";
                    
                    // Refresh user data
                    $stmt = mysqli_prepare($conn, $user_query);
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Error updating profile: " . mysqli_error($conn);
                }
                mysqli_stmt_close($update_stmt);
                break;
                
            case 'update_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Validate passwords
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = "All password fields are required.";
                    break;
                }
                
                if ($new_password !== $confirm_password) {
                    $error_message = "New passwords do not match.";
                    break;
                }
                
                if (strlen($new_password) < 8) {
                    $error_message = "New password must be at least 8 characters long.";
                    break;
                }
                
                // Verify current password
                $password_check = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
                mysqli_stmt_bind_param($password_check, "i", $user_id);
                mysqli_stmt_execute($password_check);
                $password_result = mysqli_stmt_get_result($password_check);
                $stored_password = mysqli_fetch_assoc($password_result)['password'];
                mysqli_stmt_close($password_check);
                
                if (!password_verify($current_password, $stored_password)) {
                    $error_message = "Current password is incorrect.";
                    break;
                }
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success_message = "Password updated successfully!";
                } else {
                    $error_message = "Error updating password: " . mysqli_error($conn);
                }
                mysqli_stmt_close($update_stmt);
                break;
                
            case 'delete_account':
                $password = $_POST['confirm_delete_password'];
                
                // Verify password
                $password_check = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
                mysqli_stmt_bind_param($password_check, "i", $user_id);
                mysqli_stmt_execute($password_check);
                $password_result = mysqli_stmt_get_result($password_check);
                $stored_password = mysqli_fetch_assoc($password_result)['password'];
                mysqli_stmt_close($password_check);
                
                if (!password_verify($password, $stored_password)) {
                    $error_message = "Password is incorrect. Account not deleted.";
                    break;
                }
                
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Delete user's products
                    $delete_products = mysqli_prepare($conn, "DELETE FROM products WHERE user_id = ?");
                    mysqli_stmt_bind_param($delete_products, "i", $user_id);
                    mysqli_stmt_execute($delete_products);
                    mysqli_stmt_close($delete_products);
                    
                    // Delete user's cart items
                    $delete_cart = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
                    mysqli_stmt_bind_param($delete_cart, "i", $user_id);
                    mysqli_stmt_execute($delete_cart);
                    mysqli_stmt_close($delete_cart);
                    
                    // Delete user's favorites
                    $delete_favorites = mysqli_prepare($conn, "DELETE FROM favorites WHERE user_id = ?");
                    mysqli_stmt_bind_param($delete_favorites, "i", $user_id);
                    mysqli_stmt_execute($delete_favorites);
                    mysqli_stmt_close($delete_favorites);
                    
                    // Delete user's notifications
                    $delete_notifications = mysqli_prepare($conn, "DELETE FROM notifications WHERE user_id = ?");
                    mysqli_stmt_bind_param($delete_notifications, "i", $user_id);
                    mysqli_stmt_execute($delete_notifications);
                    mysqli_stmt_close($delete_notifications);
                    
                    // Delete user account
                    $delete_user = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($delete_user, "i", $user_id);
                    mysqli_stmt_execute($delete_user);
                    mysqli_stmt_close($delete_user);
                    
                    // Commit transaction
                    mysqli_commit($conn);
                    
                    // Destroy session and redirect to home
                    session_destroy();
                    header('Location: index.php?deleted=1');
                    exit();
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    mysqli_rollback($conn);
                    $error_message = "Error deleting account: " . $e->getMessage();
                }
                break;
                
            case 'delete_all_products':
                $password = $_POST['confirm_delete_products_password'];
                
                // Verify password
                $password_check = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
                mysqli_stmt_bind_param($password_check, "i", $user_id);
                mysqli_stmt_execute($password_check);
                $password_result = mysqli_stmt_get_result($password_check);
                $stored_password = mysqli_fetch_assoc($password_result)['password'];
                mysqli_stmt_close($password_check);
                
                if (!password_verify($password, $stored_password)) {
                    $error_message = "Password is incorrect. Products not deleted.";
                    break;
                }
                
                // Delete all products
                $delete_products = mysqli_prepare($conn, "DELETE FROM products WHERE user_id = ?");
                mysqli_stmt_bind_param($delete_products, "i", $user_id);
                
                if (mysqli_stmt_execute($delete_products)) {
                    $success_message = "All products deleted successfully!";
                } else {
                    $error_message = "Error deleting products: " . mysqli_error($conn);
                }
                mysqli_stmt_close($delete_products);
                break;
                
            case 'report_fraud':
                $report_details = trim($_POST['fraud_details']);
                
                if (empty($report_details)) {
                    $error_message = "Please provide details about the fraudulent activity.";
                    break;
                }
                
                // Create a report in the database (you would need to create this table)
                $report_stmt = mysqli_prepare($conn, "INSERT INTO fraud_reports (user_id, details, status, created_at) VALUES (?, ?, 'pending', NOW())");
                mysqli_stmt_bind_param($report_stmt, "is", $user_id, $report_details);
                
                if (mysqli_stmt_execute($report_stmt)) {
                    $success_message = "Fraud report submitted successfully. Our team will investigate and contact you.";
                } else {
                    $error_message = "Error submitting report: " . mysqli_error($conn);
                }
                mysqli_stmt_close($report_stmt);
                break;
        }
    }
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Farm2Door</title>
    <link rel="stylesheet" href="assets/fonts/inter.css">
    <link rel="stylesheet" href="styles/styles.css">
    <link rel="stylesheet" href="navbar.css">
    <script src="scripts/script.js" defer></script>
    <style>
      .message {
        padding: 10px;
        margin: 10px 0;
        border-radius: 5px;
        text-align: center;
      }
      .success { background: rgba(81, 207, 102, 0.1); color: #51cf66; }
      .error { background: rgba(255, 107, 107, 0.1); color: #ff6b6b; }
      
      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
      }
      
      .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 20px;
        border: none;
        border-radius: 10px;
        width: 80%;
        max-width: 500px;
      }
      
      .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
      }
      
      .close:hover { color: black; }
      
      .form-group {
        margin-bottom: 15px;
      }
      
      .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
      }
      
      .form-group input, 
      .form-group textarea, 
      .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
      }
      
      .form-group textarea {
        height: 100px;
        resize: vertical;
      }
      
      .btn-group {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
      }
      
      .btn {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
      }
      
      .btn-primary {
        background: #51cf66;
        color: white;
      }
      
      .btn-danger {
        background: #ff6b6b;
        color: white;
      }
      
      .btn-secondary {
        background: #e9ecef;
        color: #495057;
      }
      
      .profile-pic-container {
        position: relative;
        width: 132px;
        height: 132px;
        margin: 0 auto 20px;
        cursor: pointer;
      }
      
      .profile-pic-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;
        border-radius: 50%;
      }
      
      .profile-pic-container:hover .profile-pic-overlay {
        opacity: 1;
      }
      
      #profilePic {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
      }
    </style>
  </head>
  <body>
    <?php include 'navbar.php'; ?>
    <div class="page-wrapper">

      <!-- Main Content -->
      <main class="main-content">
        <div class="container">
          <h1 class="page-title">Account Settings</h1>
          
          <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
          <?php endif; ?>
          
          <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
          <?php endif; ?>
          
          <div class="settings-container">
            <div class="settings-sidebar">
              <ul class="settings-nav">
                <li><a href="#profile" class="active">Profile</a></li>
                <li><a href="#security">Security</a></li>
                <li><a href="#notifications">Notifications</a></li>
                <li><a href="#payment">Payment Methods</a></li>
                <li><a href="#address">Addresses</a></li>
                <li><a href="#danger">Danger Zone</a></li>
              </ul>
            </div>
            
            <div class="settings-content">
              <!-- Profile Section -->
              <section id="profile" class="settings-section active">
                <h2>Profile Information</h2>
                <p>Update your personal information and how others see you on the platform.</p>
                
                <form method="POST" action="" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="update_profile">
                  
                  <div class="profile-pic-container" onclick="document.getElementById('profilePicInput').click()">
                    <img id="profilePic" src="<?php echo !empty($user['profile_pic']) ? 'uploads/profile/' . $user['profile_pic'] : 'assets/default-profile.png'; ?>" alt="Profile Picture">
                    <div class="profile-pic-overlay">
                      <span>Change Photo</span>
                    </div>
                    <input type="file" id="profilePicInput" name="profile_pic" style="display: none;" accept="image/*">
                  </div>
                  
                  <div class="form-row">
                    <div class="form-group">
                      <label for="first_name">First Name</label>
                      <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                      <label for="last_name">Last Name</label>
                      <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                  </div>
                  
                  <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                  </div>
                  
                  <div class="form-group">
                    <label for="description">About Me</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
                  </div>
                  
                  <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                  </div>
                </form>
              </section>
              
              <!-- Security Section -->
              <section id="security" class="settings-section">
                <h2>Security Settings</h2>
                <p>Manage your password and account security settings.</p>
                
                <form method="POST" action="">
                  <input type="hidden" name="action" value="update_password">
                  
                  <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small>Password must be at least 8 characters long.</small>
                  </div>
                  
                  <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                  </div>
                  
                  <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                  </div>
                </form>
                
                <hr>
                
                <h3>Two-Factor Authentication</h3>
                <p>Add an extra layer of security to your account.</p>
                
                <div class="form-group">
                  <button id="enable2FA" class="btn btn-primary">Enable Two-Factor Authentication</button>
                </div>
                
                <hr>
                
                <h3>Login Sessions</h3>
                <p>Manage your active login sessions.</p>
                
                <div class="sessions-list">
                  <div class="session-item">
                    <div class="session-info">
                      <strong>Current Session</strong>
                      <div>Device: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></div>
                      <div>IP Address: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></div>
                    </div>
                    <button class="btn btn-secondary" disabled>Current</button>
                  </div>
                </div>
              </section>
              
              <!-- Notifications Section -->
              <section id="notifications" class="settings-section">
                <h2>Notification Preferences</h2>
                <p>Control which notifications you receive.</p>
                
                <form method="POST" action="">
                  <input type="hidden" name="action" value="update_notifications">
                  
                  <div class="form-group">
                    <label class="checkbox-label">
                      <input type="checkbox" name="order_updates" checked>
                      Order Updates
                    </label>
                    <small>Receive notifications about your order status changes.</small>
                  </div>
                  
                  <div class="form-group">
                    <label class="checkbox-label">
                      <input type="checkbox" name="product_updates" checked>
                      Product Updates
                    </label>
                    <small>Get notified when products you've purchased are updated.</small>
                  </div>
                  
                  <div class="form-group">
                    <label class="checkbox-label">
                      <input type="checkbox" name="promotions">
                      Promotions and Offers
                    </label>
                    <small>Receive special offers, discounts, and promotional content.</small>
                  </div>
                  
                  <div class="form-group">
                    <label class="checkbox-label">
                      <input type="checkbox" name="newsletter">
                      Newsletter
                    </label>
                    <small>Subscribe to our monthly newsletter with farming tips and updates.</small>
                  </div>
                  
                  <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                  </div>
                </form>
              </section>
              
              <!-- Payment Methods Section -->
              <section id="payment" class="settings-section">
                <h2>Payment Methods</h2>
                <p>Manage your payment methods for faster checkout.</p>
                
                <div class="payment-methods-list">
                  <p>No payment methods saved yet.</p>
                </div>
                
                <button id="addPaymentMethod" class="btn btn-primary">Add Payment Method</button>
              </section>
              
              <!-- Addresses Section -->
              <section id="address" class="settings-section">
                <h2>Delivery Addresses</h2>
                <p>Manage your delivery addresses for faster checkout.</p>
                
                <div class="addresses-list">
                  <p>No addresses saved yet.</p>
                </div>
                
                <button id="addAddress" class="btn btn-primary">Add New Address</button>
              </section>
              
              <!-- Danger Zone Section -->
              <section id="danger" class="settings-section">
                <h2>Danger Zone</h2>
                <p>These actions are irreversible. Please proceed with caution.</p>
                
                <div class="danger-card">
                  <div class="danger-info">
                    <h3>Delete All Products</h3>
                    <p>This will permanently delete all products you have listed.</p>
                  </div>
                  <button id="deleteProductsBtn" class="btn btn-danger">Delete All Products</button>
                </div>
                
                <div class="danger-card">
                  <div class="danger-info">
                    <h3>Report Fraudulent Activity</h3>
                    <p>Report any suspicious or fraudulent activity on the platform.</p>
                  </div>
                  <button id="reportFraudBtn" class="btn btn-danger">Report Fraud</button>
                </div>
                
                <div class="danger-card">
                  <div class="danger-info">
                    <h3>Delete Account</h3>
                    <p>This will permanently delete your account and all associated data.</p>
                  </div>
                  <button id="deleteAccountBtn" class="btn btn-danger">Delete Account</button>
                </div>
              </section>
            </div>
          </div>
        </div>
      </main>
      
      <!-- Delete Products Modal -->
      <div id="deleteProductsModal" class="modal">
        <div class="modal-content">
          <span class="close">&times;</span>
          <h2>Delete All Products</h2>
          <p>Are you sure you want to delete all your products? This action cannot be undone.</p>
          
          <form method="POST" action="">
            <input type="hidden" name="action" value="delete_all_products">
            
            <div class="form-group">
              <label for="confirm_delete_products_password">Enter your password to confirm:</label>
              <input type="password" id="confirm_delete_products_password" name="confirm_delete_products_password" required>
            </div>
            
            <div class="btn-group">
              <button type="button" class="btn btn-secondary close-modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Delete All Products</button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Report Fraud Modal -->
      <div id="reportFraudModal" class="modal">
        <div class="modal-content">
          <span class="close">&times;</span>
          <h2>Report Fraudulent Activity</h2>
          <p>Please provide details about the fraudulent activity you've encountered.</p>
          
          <form method="POST" action="">
            <input type="hidden" name="action" value="report_fraud">
            
            <div class="form-group">
              <label for="fraud_details">Details:</label>
              <textarea id="fraud_details" name="fraud_details" required></textarea>
            </div>
            
            <div class="btn-group">
              <button type="button" class="btn btn-secondary close-modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Submit Report</button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Delete Account Modal -->
      <div id="deleteAccountModal" class="modal">
        <div class="modal-content">
          <span class="close">&times;</span>
          <h2>Delete Your Account</h2>
          <p>Are you absolutely sure you want to delete your account? This will:</p>
          <ul>
            <li>Delete all your products</li>
            <li>Remove all your orders and purchase history</li>
            <li>Delete your profile and all personal information</li>
            <li>Cancel any active subscriptions</li>
          </ul>
          <p><strong>This action cannot be undone.</strong></p>
          
          <form method="POST" action="">
            <input type="hidden" name="action" value="delete_account">
            
            <div class="form-group">
              <label for="confirm_delete_password">Enter your password to confirm:</label>
              <input type="password" id="confirm_delete_password" name="confirm_delete_password" required>
            </div>
            
            <div class="btn-group">
              <button type="button" class="btn btn-secondary close-modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Delete My Account</button>
            </div>
          </form>
        </div>
      </div>
      <!-- Footer -->
      <footer class="footer">
        <div class="footer-links">
          <a href="home.php" class="nav-item">Home</a>
          <a href="user-dashboard.php" class="nav-item">My Dashboard</a>
          <a href="my-orders.php" class="nav-item">My Orders</a>
          <a href="seller-shop-orders.php" class="nav-item">Shop Account</a>
          <a href="favorites.php" class="nav-item">Favorites</a>
          <a href="user-checkout-cart.php" class="nav-item">Cart</a>
          <a href="settings.php" class="nav-item">Settings</a>
          <a href="logout.php" class="nav-item">Log Out</a>
        </div>
        <div class="footer-brand">
          <h2 class="brand-name">FARM2DOOR.COM</h2>
          <p class="brand-tagline">Leveraging innovative e-commerce technology to solve food problems</p>
        </div>
      </footer>
    </div>

    <script>
      // Auto-hide messages after 5 seconds
      setTimeout(function() {
        var messages = document.querySelectorAll('.message');
        messages.forEach(function(message) {
          message.style.display = 'none';
        });
      }, 5000);
      
      // Profile picture preview
      document.getElementById('profilePicInput').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
          var reader = new FileReader();
          
          reader.onload = function(e) {
            document.getElementById('profilePic').src = e.target.result;
          }
          
          reader.readAsDataURL(e.target.files[0]);
        }
      });
      
      // Settings navigation
      document.querySelectorAll('.settings-nav a').forEach(function(link) {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          
          // Remove active class from all links and sections
          document.querySelectorAll('.settings-nav a').forEach(function(l) {
            l.classList.remove('active');
          });
          
          document.querySelectorAll('.settings-section').forEach(function(section) {
            section.classList.remove('active');
          });
          
          // Add active class to clicked link
          this.classList.add('active');
          
          // Show corresponding section
          var targetId = this.getAttribute('href').substring(1);
          document.getElementById(targetId).classList.add('active');
        });
      });
      
      // Modal functionality
      function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
      }
      
      function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
      }
      
      // Delete Products Modal
      document.getElementById('deleteProductsBtn').addEventListener('click', function() {
        openModal('deleteProductsModal');
      });
      
      // Report Fraud Modal
      document.getElementById('reportFraudBtn').addEventListener('click', function() {
        openModal('reportFraudModal');
      });
      
      // Delete Account Modal
      document.getElementById('deleteAccountBtn').addEventListener('click', function() {
        openModal('deleteAccountModal');
      });
      
      // Close modals
      document.querySelectorAll('.close, .close-modal').forEach(function(element) {
        element.addEventListener('click', function() {
          document.querySelectorAll('.modal').forEach(function(modal) {
            modal.style.display = 'none';
          });
        });
      });
      
      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        document.querySelectorAll('.modal').forEach(function(modal) {
          if (event.target == modal) {
            modal.style.display = 'none';
          }
        });
      });
      
      // Password confirmation validation
      document.getElementById('confirm_password').addEventListener('input', function() {
        var newPassword = document.getElementById('new_password').value;
        var confirmPassword = this.value;
        
        if (newPassword !== confirmPassword) {
          this.setCustomValidity('Passwords do not match');
        } else {
          this.setCustomValidity('');
        }
      });
      
      // Profile dropdown functionality
      document.querySelector('.profile-dropdown-btn').addEventListener('click', function() {
        document.querySelector('.profile-dropdown-list').classList.toggle('active');
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', function(event) {
        var dropdown = document.querySelector('.profile-dropdown');
        var dropdownBtn = document.querySelector('.profile-dropdown-btn');
        
        if (!dropdown.contains(event.target) || event.target !== dropdownBtn) {
          document.querySelector('.profile-dropdown-list').classList.remove('active');
        }
      });
    </script>
  </body>
</html>
