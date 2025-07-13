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
$user_name = $_SESSION['user_full_name'];

$success_message = '';
$error_message = '';

// Create connection
$conn = createConnection();

// Create favorites table if it doesn't exist
$create_favorites_table = "CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
)";
mysqli_query($conn, $create_favorites_table);

// Create cart table if it doesn't exist
$create_cart_table = "CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
)";
mysqli_query($conn, $create_cart_table);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'remove_favorite':
                $product_id = intval($_POST['product_id']);
                
                $stmt = mysqli_prepare($conn, "DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Product removed from favorites!";
                } else {
                    $error_message = "Error removing product from favorites.";
                }
                mysqli_stmt_close($stmt);
                break;
                
            case 'add_to_cart':
                $product_id = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                
                if ($product_id > 0 && $quantity > 0) {
                    // Check if product exists and has enough quantity
                    $check_stmt = mysqli_prepare($conn, "SELECT quantity, user_id FROM products WHERE id = ? AND status = 'active'");
                    mysqli_stmt_bind_param($check_stmt, "i", $product_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $product = mysqli_fetch_assoc($check_result);
                    mysqli_stmt_close($check_stmt);
                    
                    if ($product && $product['quantity'] >= $quantity && $product['user_id'] != $user_id) {
                        // Add to cart or update quantity
                        $cart_stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                        mysqli_stmt_bind_param($cart_stmt, "iiii", $user_id, $product_id, $quantity, $quantity);
                        
                        if (mysqli_stmt_execute($cart_stmt)) {
                            $success_message = "Product added to cart successfully!";
                        } else {
                            $error_message = "Error adding product to cart.";
                        }
                        mysqli_stmt_close($cart_stmt);
                    } else {
                        $error_message = "Product not available or insufficient quantity.";
                    }
                }
                break;
                
            case 'clear_all_favorites':
                $stmt = mysqli_prepare($conn, "DELETE FROM favorites WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "All favorites cleared successfully!";
                } else {
                    $error_message = "Error clearing favorites.";
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Handle search functionality
$search_query = '';
$favorites = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    
    // Search in favorites
    $search_sql = "SELECT f.*, p.*, u.first_name, u.last_name, 
                   COALESCE(u.location, 'Location not specified') as location,
                   f.added_at as favorited_at
                   FROM favorites f
                   JOIN products p ON f.product_id = p.id
                   JOIN users u ON p.user_id = u.id
                   WHERE f.user_id = ? AND p.status = 'active' 
                   AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)
                   ORDER BY f.added_at DESC";
    
    $search_term = "%$search_query%";
    $stmt = mysqli_prepare($conn, $search_sql);
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $search_term, $search_term, $search_term);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $favorites = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    // Get all user's favorite products
    $favorites_query = "SELECT f.*, p.*, u.first_name, u.last_name, 
                       COALESCE(u.location, 'Location not specified') as location,
                       f.added_at as favorited_at
                       FROM favorites f
                       JOIN products p ON f.product_id = p.id
                       JOIN users u ON p.user_id = u.id
                       WHERE f.user_id = ? AND p.status = 'active'
                       ORDER BY f.added_at DESC";
    
    $stmt = mysqli_prepare($conn, $favorites_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $favorites = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Get favorites statistics
$stats_query = "SELECT COUNT(*) as total_favorites,
                COUNT(CASE WHEN p.quantity > 0 THEN 1 END) as available_favorites
                FROM favorites f
                JOIN products p ON f.product_id = p.id
                WHERE f.user_id = ? AND p.status = 'active'";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stmt);

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Favorites - Farm2Door</title>
    <link rel="stylesheet" href="assets/fonts/inter.css">
    <link rel="stylesheet" href="styles/styles.css"/>
    <link rel="stylesheet" href="navbar.css"/>
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
      .favorites-stats {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin: 20px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
      }
      .stat-item {
        text-align: center;
      }
      .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #51cf66;
      }
      .stat-label {
        font-size: 14px;
        color: #666;
      }
      .favorite-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #ffc107;
        color: white;
        padding: 5px 8px;
        border-radius: 15px;
        font-size: 12px;
      }
      .product-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 10px;
        flex-wrap: wrap;
      }
      .btn-small {
        padding: 5px 10px;
        font-size: 12px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
      }
      .btn-primary { background: #51cf66; color: white; }
      .btn-danger { background: #dc3545; color: white; }
      .btn-secondary { background: #6c757d; color: white; }
      .qty-input {
        width: 60px;
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
      }
      .out-of-stock {
        opacity: 0.6;
        position: relative;
      }
      .out-of-stock::after {
        content: "Out of Stock";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(220, 53, 69, 0.9);
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        font-weight: bold;
      }
      .favorite-date {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
      }
      .clear-all-btn {
        background: #dc3545;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
      }
      .clear-all-btn:hover {
        background: #c82333;
      }
    </style>
  </head>

  <body>
    <div class="page-wrapper">
      <?php include 'navbar.php'; ?>

      <!-- Main Content -->
      <main class="main-section">

        <!-- Page Title -->
        <section class="section-title">
          <h1 class="title">Favorites & Saves</h1>
        </section>

        <div style="width: 100%; text-align: center;">
          <p style="text-align: center; color: #666; margin-top: -1rem 0 1rem 0; display: block; font-size: 16px;">Welcome back, <?php echo htmlspecialchars($user_name); ?>! Here are your saved products.</p>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($success_message)): ?>
          <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
          <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Favorites Statistics -->
        <div style="width: 100%; text-align: center;">
          <div class="favorites-stats" style="display: inline-flex; text-align: left;">
            <div class="stat-item">
              <div class="stat-number"><?php echo $stats['total_favorites']; ?></div>
              <div class="stat-label">Total Favorites</div>
            </div>
            <div class="stat-item">
              <div class="stat-number"><?php echo $stats['available_favorites']; ?></div>
              <div class="stat-label">Available Now</div>
            </div>
            <div class="stat-item">
              <div class="stat-number"><?php echo $stats['total_favorites'] - $stats['available_favorites']; ?></div>
              <div class="stat-label">Out of Stock</div>
            </div>
            <?php if ($stats['total_favorites'] > 0): ?>
              <div>
                <form method="POST" action="" style="display: inline;">
                  <input type="hidden" name="action" value="clear_all_favorites">
                  <button type="submit" class="clear-all-btn" onclick="return confirm('Are you sure you want to remove all favorites? This action cannot be undone.')">
                    Clear All Favorites
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Search Bar Section -->
        <section class="search-section">
          <div class="search-label">Search your favorites</div>
          <form method="GET" action="favorites.php" class="search-bar">
            <input type="search" name="search" class="search-input" placeholder="Search by product name, category..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="search-button">Search</button>
            <?php if (!empty($search_query)): ?>
              <a href="favorites.php" class="btn-secondary" style="margin-left: 10px; padding: 8px 15px; text-decoration: none;">Clear</a>
            <?php endif; ?>
          </form>
        </section>

        <!-- Favorite Products Listing Section -->
        <section class="produce-listing-section">
          <h2 class="produce-title">
                        <?php
            if (!empty($search_query)) {
                echo count($favorites) . " Products Found for \"" . htmlspecialchars($search_query) . "\"";
            } else {
                echo count($favorites) . " Products Saved";
            }
            ?>
          </h2>

          <!-- Favorite Produce Grid -->
          <div class="produce-grid">

            <?php if (empty($favorites)): ?>
              <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <?php if (!empty($search_query)): ?>
                  <h3>No favorites found matching "<?php echo htmlspecialchars($search_query); ?>"</h3>
                  <p>Try searching with different keywords or <a href="favorites.php">view all favorites</a></p>
                <?php else: ?>
                  <h3>No favorites yet</h3>
                  <div style="margin: 20px;">
                    <p>Start adding products to your favorites from the <a href="home.php">home page</a></p>
                  </div>
                  <a href="home.php">
                    <button class="btn-primary" style="padding: 10px 20px; margin-top: 15px;">Browse Products</button>
                  </a>
                <?php endif; ?>
              </div>
            <?php else: ?>

              <?php foreach ($favorites as $favorite): ?>
                <div class="produce-listing-card <?php echo ($favorite['quantity'] <= 0) ? 'out-of-stock' : ''; ?>" style="position: relative;">
                  
                  <!-- Favorite Badge -->
                  <div class="favorite-badge">♥ Favorite</div>
                  
                  <img src="<?php echo !empty($favorite['image']) ? htmlspecialchars($favorite['image']) : 'https://placehold.co/340x180'; ?>" alt="<?php echo htmlspecialchars($favorite['name']); ?>" class="produce-image">
                  
                  <div class="card-body">
                    <div class="card-info">
                      <div class="product-name"><?php echo htmlspecialchars($favorite['name']); ?></div>
                      <div class="product-price">$<?php echo number_format($favorite['price'], 2); ?></div>
                      <div class="product-location"><?php echo htmlspecialchars($favorite['location']); ?></div>
                      <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        Seller: <?php echo htmlspecialchars($favorite['first_name'] . ' ' . $favorite['last_name']); ?>
                      </div>
                      <div style="font-size: 12px; color: <?php echo ($favorite['quantity'] > 0) ? '#28a745' : '#dc3545'; ?>;">
                        <?php if ($favorite['quantity'] > 0): ?>
                          Available: <?php echo $favorite['quantity']; ?> units
                        <?php else: ?>
                          Out of Stock
                        <?php endif; ?>
                      </div>
                      <?php if (!empty($favorite['category'])): ?>
                        <div style="font-size: 11px; background: #e9ecef; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-top: 5px;">
                          <?php echo htmlspecialchars($favorite['category']); ?>
                        </div>
                      <?php endif; ?>
                      <div class="favorite-date">
                        Added to favorites: <?php echo date('M j, Y', strtotime($favorite['favorited_at'])); ?>
                      </div>
                    </div>

                    <!-- Product Actions -->
                    <div class="product-actions">
                      <?php if ($favorite['quantity'] > 0 && $favorite['user_id'] != $user_id): ?>
                        <!-- Add to Cart Form -->
                        <form method="POST" action="" style="display: flex; align-items: center; gap: 5px;">
                          <input type="hidden" name="action" value="add_to_cart">
                          <input type="hidden" name="product_id" value="<?php echo $favorite['id']; ?>">
                          <label class="qty-label" style="font-size: 12px;">Qty:</label>
                          <input type="number" name="quantity" class="qty-input" min="1" max="<?php echo $favorite['quantity']; ?>" value="1" required>
                          <button type="submit" class="btn-small btn-primary">Add to Cart</button>
                        </form>
                      <?php elseif ($favorite['user_id'] == $user_id): ?>
                        <div style="font-size: 12px; color: #666; padding: 5px;">
                          This is your product
                        </div>
                      <?php endif; ?>

                      <!-- Remove from Favorites -->
                      <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="remove_favorite">
                        <input type="hidden" name="product_id" value="<?php echo $favorite['id']; ?>">
                        <button type="submit" class="btn-small btn-danger" onclick="return confirm('Remove this product from favorites?')">
                          Remove ♥
                        </button>
                      </form>

                      <!-- View Seller -->
                      <a href="seller-detail.php?seller_id=<?php echo $favorite['user_id']; ?>" class="btn-small btn-secondary">
                        View Seller
                      </a>
                    </div>

                    <!-- Product Description Preview -->
                    <?php if (!empty($favorite['description'])): ?>
                      <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <div style="font-size: 12px; font-weight: bold; color: #666; margin-bottom: 5px;">Description:</div>
                        <div style="font-size: 12px; color: #666;">
                          <?php 
                          $description = htmlspecialchars($favorite['description']);
                          echo (strlen($description) > 100) ? substr($description, 0, 100) . '...' : $description;
                          ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>

            <?php endif; ?>

          </div>
        </section>

        <!-- Quick Actions Section -->
        <?php if (!empty($favorites)): ?>
          <section class="produce-listing-section">
            <h3>Quick Actions</h3>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; display: flex; gap: 15px; flex-wrap: wrap; justify-content: center;">
              
              <!-- Add All Available to Cart -->
              <form method="POST" action="user-checkout-cart.php" style="display: inline;">
                <input type="hidden" name="action" value="add_all_favorites">
                <button type="submit" class="btn-primary" style="padding: 10px 20px;" 
                        <?php echo ($stats['available_favorites'] == 0) ? 'disabled' : ''; ?>>
                  Add All Available to Cart (<?php echo $stats['available_favorites']; ?>)
                </button>
              </form>

              <!-- Continue Shopping -->
              <a href="home.php">
                <button class="btn-secondary" style="padding: 10px 20px;">Continue Shopping</button>
              </a>

              <!-- View Cart -->
              <a href="user-checkout-cart.php">
                <button class="btn-secondary" style="padding: 10px 20px;">View Cart</button>
              </a>

            </div>
          </section>
        <?php endif; ?>

        <!-- Recommendations Section -->
        <section class="produce-listing-section">
          <h3>You Might Also Like</h3>
          <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="margin: 20px;">
              <p>Discover more products similar to your favorites</p>
            </div>
            <a href="home.php?recommended=true">
              <button class="btn-primary" style="padding: 10px 20px;">Browse Recommendations</button>
            </a>
          </div>
        </section>

      </main>

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

      // Quantity input validation
      document.querySelectorAll('.qty-input').forEach(function(input) {
        input.addEventListener('change', function() {
          var max = parseInt(this.getAttribute('max'));
          var value = parseInt(this.value);
          
          if (value > max) {
            this.value = max;
            alert('Maximum available quantity is ' + max);
          }
          if (value < 1) {
            this.value = 1;
          }
        });
      });

      // Search form enhancement
      document.querySelector('.search-bar').addEventListener('submit', function(e) {
        var searchInput = document.querySelector('input[name="search"]');
        
        if (!searchInput.value.trim()) {
          e.preventDefault();
          alert('Please enter a search term');
          return false;
        }
      });

      // Confirm bulk actions
      document.querySelectorAll('form').forEach(function(form) {
        var action = form.querySelector('input[name="action"]');
        if (action && action.value === 'add_all_favorites') {
          form.addEventListener('submit', function(e) {
            if (!confirm('Add all available favorite products to your cart?')) {
              e.preventDefault();
            }
          });
        }
      });
    </script>
  </body>
</html>

