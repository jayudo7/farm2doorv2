<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>User Checkout Payment</title>
    <link rel="stylesheet" href="styles/styles.css"/>
  </head>



  <body>
    <div class="page-wrapper">

      <!-- Navbar -->
      <nav class="navbar">
        <div class="logo">
          <div class="logo-text">Farm2Door</div>
        </div>
        <button class="hamburger-menu">&#9776;</button>

        <div class="nav-links">
          <a href="home.php" class="nav-item">Home</a>
          <a href="user-dashboard.php" class="nav-item">My Dashboard</a>
          <a href="my-orders.php" class="nav-item">My Orders</a>
          <a href="seller-shop-orders.php" class="nav-item">Shop Account</a>
          <a href="favorites.php" class="nav-item">Favorites</a>
          <a href="user-checkout-cart.php" class="nav-item">Cart</a>

          <div class="user-menu-container">
            <div class="user-icon">
              <img src="https://placehold.co/24x24" alt="User Icon">
            </div>
            <div class="user-menu">
              <a href="settings.php" class="nav-item">Settings</a>
              <a href="sign-in.php" class="nav-item">Log Out</a>
            </div>
          </div>
        </div>
      </nav>
      
      <!-- Main Content -->
      <main class="main-section">


        <!-- Page Title -->
        <section class="section-title">
          <h1 class="title">Payment Confirmation</h1>
        </section>

        <!-- CHECKOUT PAYMENT MAIN CONTAINER-->
        <section class="checkout-payment-main-container">

          <!-- CHECKOUT PAYMENT LEFT CONTAINER-->
          <div class="checkout-payment-left-container">
            <h3>Please read the instructions and follow them!</h3>
            <div class="checkout-payment-user-image">
              <img src="https://placehold.co/132x132" alt="Checkout payment page seller image">
            </div>
            <div class="checkout-payment-text-wrapper">
              <h4>Details</h4>
              <div class="checkout-payment-left-text"> Name: Kweku Armstrong</div>
              <div class="checkout-payment-left-text"> Amount to Pay: $12,902</div>
            </div>
            <div class="checkout-payment-text-wrapper">
              <h4>Pay Via</h4>
              <div class="checkout-payment-left-text"> Momo Number: 029 329 1246</div>
              <div class="checkout-payment-left-text"> Bank Account No: 12903 34980</div>
            </div>
          </div>

          <!-- CHECKOUT PAYMENT RIGHT CONTAINER-->
          <div class="checkout-payment-right-container">
            <div class="checkout-payment-right-text-container">
              <h3>Instructions on how to pay</h3>
              <div class="checkout-payment-right-text">1. Pick a payment method (momo or bank account).</div>
              <div class="checkout-payment-right-text">2. Make the payment. Save the receipt. </div>
              <div class="checkout-payment-right-text">3. Click the confirm payment button.</div>
              <div class="checkout-payment-right-text">4. Wait for 5 minutes.</div>
              <div class="checkout-payment-right-text">5. You will receive a confirmation email & a popup screen if the order was successful.</div>
            </div>

            <div class="checkout-payment-buttons-wrapper">
              <button class="checkout-payment-right-red-button">Report Fraudulent Activity</button>
              <button class="checkout-payment-right-green-button">Confirm Payment</button>
            </div>
          </div>

        </section>

      </main>

        <!-- Footer -->
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
          <a href="sign-in.php" class="nav-item">Log Out</a>
        </div>
        <div class="footer-brand">
          <h2 class="brand-name">FARM2DOOR.COM</h2>
          <p class="brand-tagline">Leveraging innovative e-commerce technology to solve food problems</p>
        </div>
      </footer>
    
    </div>
    <script src="scripts/script.js"></script>
  </body>
</html>