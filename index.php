<?php
// Get errors and form data from session
$errors = $_SESSION['errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];

// Clear session data
// unset($_SESSION['errors']);
// unset($_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sign Up - Farm2Door</title>
    <link rel="stylesheet" href="./assets/fonts/inter.css"/>
    <link rel="stylesheet" href="./styles/styles.css"/>
    <link rel="stylesheet" href="./styles/stylenew.css">
    <style>
      * {
        font-family: 'Inter', sans-serif;
      }
    </style>
  </head>

  <body>
    <div class="page-wrapper">
      <!-- Navbar -->
      <nav class="navbar">
        <div class="logo">
          <div class="logo-text">Farm2Door</div>
        </div>
        <div style="gap: 32px; display: flex;">
          <a href="home.php" class="nav-item">Home</a>
          <a href="sign-in.php" class="nav-item">Sign In</a>
        </div>
      </nav>

      <!-- Main Content -->
      <main class="main-section">
        <div class="auth-container">
          <div class="auth-card">
            <div class="auth-header">
              <h1 class="auth-title">Join Farm2Door</h1>
              <p class="auth-subtitle">Create your account to start buying or selling fresh produce</p>
            </div>

            <form class="auth-form" method="POST" action="config_files/process_signup.php" >
              <!-- User Type Toggle -->
              <div class="user-type-toggle">
                <div class="toggle-label">I want to:</div>
                <div class="toggle-switch">
                  <input type="radio" id="buyer" name="userType" value="buyer" checked>
                  <input type="radio" id="seller" name="userType" value="seller">
                  <div class="toggle-slider">
                    <label for="buyer" class="toggle-option">
                      <span class="toggle-icon">🛒</span>
                      <span class="toggle-text">Buy</span>
                    </label>
                    <label for="seller" class="toggle-option">
                      <span class="toggle-icon">🌾</span>
                      <span class="toggle-text">Sell</span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Form Fields -->
              <div class="form-row">
                <div class="form-group">
                  <label for="firstName" class="form-label">First Name</label>
                  <input type="text" id="firstName" name="firstName" class="form-input" required>
                </div>
                <div class="form-group">
                  <label for="lastName" class="form-label">Last Name</label>
                  <input type="text" id="lastName" name="lastName" class="form-input" required>
                </div>
              </div>

              <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" required>
              </div>

              <div class="form-group">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" id="phone" name="phone" class="form-input" required>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="city" class="form-label">City</label>
                  <input type="text" id="city" name="city" class="form-input" required>
                </div>
                <div class="form-group">
                  <label for="region" class="form-label">Region</label>
                  <input type="text" id="region" name="region" class="form-input" required>
                </div>
              </div>

              <div class="form-group">
                <label for="address" class="form-label">Full Address</label>
                <textarea id="address" name="address" class="form-textarea" rows="3" required></textarea>
              </div>

              <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required>
              </div>

              <div class="form-group">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" required>
              </div>

              <!-- Seller-specific fields (hidden by default) -->
              <div class="seller-fields" style="display: none;">
                <div class="form-group">
                  <label for="farmName" class="form-label">Farm/Business Name</label>
                  <input type="text" id="farmName" name="farmName" class="form-input">
                </div>
                
                <div class="form-group">
                  <label for="businessType" class="form-label">Business Type</label>
                  <select id="businessType" name="businessType" class="form-select">
                    <option value="">Select business type</option>
                    <option value="individual">Individual Farmer</option>
                    <option value="cooperative">Cooperative</option>
                    <option value="company">Company</option>
                  </select>
                </div>

                <div class="form-group">
                  <label for="description" class="form-label">Business Description</label>
                  <textarea id="description" name="description" class="form-textarea" rows="3" placeholder="Tell us about your farm and what you grow..."></textarea>
                </div>
              </div>

              <div class="form-group checkbox-group">
                <label class="checkbox-label">
                  <input type="checkbox" name="terms" required>
                  <span class="checkmark"></span>
                  I agree to the <a href="#" class="link">Terms of Service</a> and <a href="#" class="link">Privacy Policy</a>
                </label>
              </div>

              <button type="submit" class="auth-button">Create Account</button>
            </form>

            <div class="auth-footer">
              <p class="auth-link-text">
                Already have an account? 
                <a href="sign-in.php" class="auth-link">Sign in here</a>
              </p>
            </div>
          </div>
        </div>
      </main>

      <!-- Footer -->
      <footer class="footer">
        <div class="footer-links">
          <a href="home.php" class="nav-item">Home</a>
          <a href="sign-in.php" class="nav-item">Sign In</a>
        </div>
        <div class="footer-brand">
          <h2 class="brand-name">FARM2DOOR.COM</h2>
          <p class="brand-tagline">Leveraging innovative e-commerce technology to solve food problems</p>
        </div>
      </footer>
    </div>

    <script>
      // Toggle between buyer and seller forms
      const buyerRadio = document.getElementById('buyer');
      const sellerRadio = document.getElementById('seller');
      const sellerFields = document.querySelector('.seller-fields');
      const toggleSlider = document.querySelector('.toggle-slider');

      function updateToggle() {
        if (sellerRadio.checked) {
          sellerFields.style.display = 'block';
          toggleSlider.classList.add('seller-active');
        } else {
          sellerFields.style.display = 'none';
          toggleSlider.classList.remove('seller-active');
        }
      }

      buyerRadio.addEventListener('change', updateToggle);
      sellerRadio.addEventListener('change', updateToggle);

      // Initialize
      updateToggle();
    </script>
  </body>
</html>
