<?php
// Get user information for the navbar
$user_name = $_SESSION['user_full_name'] ?? 'User';
$user_profile_pic = $_SESSION['user_profile_pic'] ?? 'assets/default-profile.png';
?>

<nav class="navbar">
  <div class="logo">
    <div class="logo-text">Farm2Door</div>
  </div>

  <div class="nav-links">
    <a href="home.php" class="nav-item">Home</a>
    <a href="user-dashboard.php" class="nav-item">My Dashboard</a>
    <a href="my-orders.php" class="nav-item">My Orders</a>
    <a href="seller-shop-orders.php" class="nav-item">Shop Account</a>
    <a href="favorites.php" class="nav-item">Favorites</a>
    <a href="user-checkout-cart.php" class="nav-item">Cart</a>
  </div>

  <div class="user-menu-container">
    <div class="user-icon" id="userMenuToggle">
      <img src="<?php echo htmlspecialchars($user_profile_pic); ?>" alt="User Icon">
      <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
    </div>
    <div class="user-menu" id="userMenu">
      <div class="mobile-nav-items">
        <a href="home.php" class="nav-item">Home</a>
        <a href="user-dashboard.php" class="nav-item">My Dashboard</a>
        <a href="my-orders.php" class="nav-item">My Orders</a>
        <a href="seller-shop-orders.php" class="nav-item">Shop Account</a>
        <a href="favorites.php" class="nav-item">Favorites</a>
        <a href="user-checkout-cart.php" class="nav-item">Cart</a>
        <div class="mobile-nav-divider"></div>
      </div>
      <a href="settings.php" class="nav-item">Settings</a>
      <a href="config_files/logout.php" class="nav-item">Log Out</a>
    </div>
  </div>
</nav>