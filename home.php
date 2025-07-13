<?php
session_start();

// Include your database connection
require_once 'config_files/config.php';

// Check if user is logged in (optional for home page, but useful for personalization)
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$user_name = $is_logged_in ? $_SESSION['user_full_name'] : null;

// Create connection
$conn = createConnection();

// Handle search functionality
$search_query = '';
$search_results = [];
$category_filter = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
    
    // Build search query
    $sql = "SELECT p.*, u.first_name, u.last_name, u.location 
            FROM products p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.status = 'active' AND p.quantity > 0";
    
    $params = [];
    $types = '';
    
    // Add search term condition
    if (!empty($search_query)) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
        $search_term = "%$search_query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'sss';
    }
    
    // Add category filter
    if (!empty($category_filter)) {
        $sql .= " AND p.category = ?";
        $params[] = $category_filter;
        $types .= 's';
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT 20";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $search_results = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Get latest products for homepage
$latest_products_query = "SELECT p.*, u.first_name, u.last_name, u.location 
                         FROM products p 
                         JOIN users u ON p.user_id = u.id 
                         WHERE p.status = 'active' AND p.quantity > 0 
                         ORDER BY p.created_at DESC 
                         LIMIT 12";
$stmt = mysqli_prepare($conn, $latest_products_query);
mysqli_stmt_execute($stmt);
$latest_result = mysqli_stmt_get_result($stmt);
$latest_products = mysqli_fetch_all($latest_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Get product categories with counts
$categories_query = "SELECT category, COUNT(*) as count 
                    FROM products 
                    WHERE status = 'active' AND quantity > 0 
                    GROUP BY category 
                    ORDER BY count DESC";
$stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_execute($stmt);
$categories_result = mysqli_stmt_get_result($stmt);
$categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Handle add to cart functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (!$is_logged_in) {
        header('Location: sign-in.php');
        exit();
    }
    
    switch ($_POST['action']) {
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
                    // Create cart table if it doesn't exist
                    $create_cart = "CREATE TABLE IF NOT EXISTS cart (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        product_id INT NOT NULL,
                        quantity INT NOT NULL,
                        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_user_product (user_id, product_id)
                    )";
                    mysqli_query($conn, $create_cart);
                    
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
            
        case 'add_to_favorites':
            $product_id = intval($_POST['product_id']);
            
            if ($product_id > 0) {
                // Create favorites table if it doesn't exist
                $create_favorites = "CREATE TABLE IF NOT EXISTS favorites (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    product_id INT NOT NULL,
                    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_user_product (user_id, product_id)
                )";
                mysqli_query($conn, $create_favorites);
                
                $fav_stmt = mysqli_prepare($conn, "INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($fav_stmt, "ii", $user_id, $product_id);
                
                if (mysqli_stmt_execute($fav_stmt)) {
                    $success_message = "Product added to favorites!";
                } else {
                    $error_message = "Error adding to favorites.";
                }
                mysqli_stmt_close($fav_stmt);
            }
            break;
    }
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Home - Farm2Door</title>
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
      .product-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 10px;
      }
      .qty-input {
        width: 60px;
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
      }
      .btn-small {
        padding: 5px 10px;
      }
    </style>
  </head>

  <body>
    <?php include 'navbar.php'; ?>
    <div class="page-wrapper">

      <!-- Search Section -->
      <section class="home-search-section">
        <section class="section-title">
          <h1 class="title-white">
            <?php if ($is_logged_in): ?>
              Welcome back, <?php echo htmlspecialchars($user_name); ?>! What are you looking for?
            <?php else: ?>
              What are you looking for?
            <?php endif; ?>
          </h1>
        </section>

        <!-- Search Bar -->
        <form method="GET" action="home.php" class="home-search-bar">
          <input type="search" name="search" class="search-input" placeholder="Search for products..." value="<?php echo htmlspecialchars($search_query); ?>">
          <select name="category" class="search-input" style="max-width: 150px;">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="search-button">Search</button>
        </form>
      </section>

      <!-- Main Content -->
      <main class="main-section">

        <!-- Display Messages -->
        <?php if (isset($success_message)): ?>
          <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
          <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Search Results Section -->
        <?php if (!empty($search_query)): ?>
          <div class="search-results">
            <h2>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
            <p style="margin: 20px 0;">Found <?php echo count($search_results); ?> products</p>
            
            <?php if (!empty($search_results)): ?>
              <div class="produce-grid">
                <?php foreach ($search_results as $product): ?>
                  <div class="produce-listing-card">
                    <img src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://placehold.co/340x180'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="produce-image">

                    <div class="card-body">
                      <div class="card-info">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                        <div class="product-location"><?php echo htmlspecialchars($product['location'] ?? 'Location not specified'); ?></div>
                        <div class="product-seller">Seller: <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></div>
                        <div class="product-stock">Available: <?php echo $product['quantity']; ?></div>
                      </div>

                      <?php if ($is_logged_in && $product['user_id'] != $user_id): ?>
                        <div class="quantity-selector-action">
                          <form method="POST" action="" class="product-actions" style="display: flex; gap: 10px; align-items: center; width: 100%;">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <label class="qty-label">Qty:</label>
                            <input type="number" name="quantity" class="qty-input" min="1" max="<?php echo $product['quantity']; ?>" value="1" required>
                            <button type="submit" class="qty-select-button">Select</button>
                          </form>
                        </div>
                        <button type="submit" class="add-cart-button">Add to Cart</button>
                        
                        <form method="POST" action="" style="margin-top: 10px;">
                          <input type="hidden" name="action" value="add_to_favorites">
                          <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                          <button type="submit" class="qty-select-button" style="width: 100%;">♥ Favorite</button>
                        </form>
                      <?php elseif (!$is_logged_in): ?>
                        <p><a href="sign-in.php">Sign in</a> to purchase this product</p>
                      <?php else: ?>
                        <p style="color: #666; font-style: italic;">This is your product</p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p>No products found matching your search criteria.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Shop By Category Section -->
        <section class="section-title">
          <h2 class="produce-title">Shop By Category</h2>
        </section>

        <!-- CATEGORY CARDS SECTION -->
        <section class="category-section">
          <!-- Shop By Category Grid -->
          <div class="category-grid">

            <!-- Dynamic Category Cards -->
            <?php
            $category_images = [
                'Vegetables' => 'assets/images/cat1.jpg',
                'Fruits' => 'assets/images/cat8.jpg',
                'Meat' => 'assets/images/cat4.jpg',
                'Dairy' => 'assets/images/cat5.jpg',
                'Grains' => 'assets/images/cat6.jpg',
                'Fish' => 'assets/images/cat3.jpg',
                'Other' => 'assets/images/cat2.jpg'
            ];

            $default_categories = ['Vegetables', 'Fruits', 'Meat', 'Dairy', 'Grains', 'Fish', 'Other'];
            
            foreach ($default_categories as $category):
                $count = 0;
                foreach ($categories as $cat) {
                    if ($cat['category'] == $category) {
                        $count = $cat['count'];
                        break;
                    }
                }
            ?>
              <a href="home.php?search=&category=<?php echo urlencode($category); ?>" class="category-card">
                <div>
                  <img src="<?php echo $category_images[$category] ?? 'https://placehold.co/200x150'; ?>" alt="<?php echo $category; ?>" class="category-image" />
                  <div class="category-name"><?php echo $category; ?></div>
                  <div class="category-count"><?php echo $count; ?> products</div>
                </div>
              </a>
            <?php endforeach; ?>

          </div>
        </section>

        <!-- Latest Product Section -->
        <section class="produce-listing-section">
          <h2 class="produce-title">Latest Produce (<?php echo count($latest_products); ?> items)</h2>

          <!-- Latest Produce Grid -->
          <div class="produce-grid">

            <?php if (empty($latest_products)): ?>
              <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <h3>No products available yet</h3>
                <p>Be the first to add products to Farm2Door!</p>
                <?php if ($is_logged_in): ?>
                  <a href="user-dashboard.php">
                    <button class="btn-primary" style="padding: 10px 20px; margin-top: 10px;">Add Your Products</button>
                  </a>
                <?php else: ?>
                  <a href="sign-in.php">
                    <button class="btn-primary" style="padding: 10px 20px; margin-top: 10px;">Sign In to Sell</button>
                  </a>
                <?php endif; ?>
              </div>
            <?php else: ?>

              <?php foreach ($latest_products as $product): ?>
                <div class="produce-listing-card">
                  <img src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://placehold.co/340x180'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="produce-image">

                  <div class="card-body">
                    <div class="card-info">
                      <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                      <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                      <div class="product-location"><?php echo htmlspecialchars($product['location'] ?? 'Location not specified'); ?></div>
                      <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        Seller: <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?>
                      </div>
                      <div style="font-size: 12px; color: #666;">
                        Available: <?php echo $product['quantity']; ?> units
                      </div>
                      <?php if (!empty($product['category'])): ?>
                        <div style="font-size: 11px; background: #e9ecef; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-top: 5px;">
                          <?php echo htmlspecialchars($product['category']); ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <?php if ($is_logged_in): ?>
                      <?php if ($product['user_id'] != $user_id): ?>
                        <div class="quantity-selector-action">
                          <form method="POST" action="" class="product-actions" style="display: flex; gap: 10px; align-items: center; width: 100%;">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <label class="qty-label">Qty:</label>
                            <input type="number" name="quantity" class="qty-input" min="1" max="<?php echo $product['quantity']; ?>" value="1" required>
                            <button type="submit" class="qty-select-button">Select</button>
                          </form>
                        </div>
                        <button type="submit" class="add-cart-button">Add to Cart</button>
                        
                        <form method="POST" action="" style="margin-top: 10px;">
                          <input type="hidden" name="action" value="add_to_favorites">
                          <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                          <button type="submit" class="qty-select-button" style="width: 100%;">♥ Favorite</button>
                        </form>
                      <?php else: ?>
                        <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">
                          <small style="color: #666;">This is your product</small><br>
                          <a href="user-dashboard.php" style="font-size: 12px; color: #51cf66;">Edit in Dashboard</a>
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">
                        <a href="sign-in.php" style="color: #51cf66; text-decoration: none;">Sign in to purchase</a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>

            <?php endif; ?>

          </div>
        </section>

        <!-- Featured Sellers Section -->
        <?php if ($is_logged_in): ?>
          <section class="produce-listing-section">
            <h2 class="produce-title">Featured Sellers</h2>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
              <p style="padding: 20px;">Discover top-rated sellers in your area</p>
              <a href="sellers.php">
                <button class="btn-primary" style="padding: 10px 20px;">Browse All Sellers</button>
              </a>
            </div>
          </section>
        <?php endif; ?>

      </main>

      <!-- Footer -->
      <footer class="footer">
        <div class="footer-links">
          <a href="home.php" class="nav-item">Home</a>
          <?php if ($is_logged_in): ?>
            <a href="user-dashboard.php" class="nav-item">My Dashboard</a>
            <a href="my-orders.php" class="nav-item">My Orders</a>
            <a href="seller-shop-orders.php" class="nav-item">Shop Account</a>
            <a href="favorites.php" class="nav-item">Favorites</a>
            <a href="user-checkout-cart.php" class="nav-item">Cart</a>
            <a href="settings.php" class="nav-item">Settings</a>
            <a href="config_files/logout.php" class="nav-item">Log Out</a>
          <?php else: ?>
            <a href="sign-in.php" class="nav-item">Sign In</a>
            <a href="index.php" class="nav-item">Sign Up</a>
          <?php endif; ?>
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
      document.querySelector('.home-search-bar').addEventListener('submit', function(e) {
        var searchInput = document.querySelector('input[name="search"]');
        var categorySelect = document.querySelector('select[name="category"]');
        
        if (!searchInput.value.trim() && !categorySelect.value) {
          e.preventDefault();
          alert('Please enter a search term or select a category');
          return false;
        }
      });
    </script>
  </body>
</html>

                        
