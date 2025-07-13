<?php
session_start();

// Include your database connection
require_once 'config_files/config.php';

// Check if seller_id is provided
if (!isset($_GET['seller_id']) || empty($_GET['seller_id'])) {
    header('Location: home.php');
    exit();
}

$seller_id = intval($_GET['seller_id']);
$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;

$success_message = '';
$error_message = '';

// Create connection
$conn = createConnection();

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

// Get seller information
$seller_query = "SELECT id, first_name, last_name, email, phone, 
                 COALESCE(location, 'Location not specified') as location,
                 COALESCE(profile_image, 'https://placehold.co/132x132') as profile_image,
                 created_at
                 FROM users WHERE id = ?";

$stmt = mysqli_prepare($conn, $seller_query);
mysqli_stmt_bind_param($stmt, "i", $seller_id);
mysqli_stmt_execute($stmt);
$seller_result = mysqli_stmt_get_result($stmt);
$seller = mysqli_fetch_assoc($seller_result);
mysqli_stmt_close($stmt);

// If seller doesn't exist, redirect
if (!$seller) {
    header('Location: home.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_logged_in) {
    switch ($_POST['action']) {
        case 'add_to_cart':
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($product_id > 0 && $quantity > 0 && $current_user_id != $seller_id) {
                // Check if product exists and has enough quantity
                $check_stmt = mysqli_prepare($conn, "SELECT quantity FROM products WHERE id = ? AND user_id = ? AND status = 'active'");
                mysqli_stmt_bind_param($check_stmt, "ii", $product_id, $seller_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $product = mysqli_fetch_assoc($check_result);
                mysqli_stmt_close($check_stmt);
                
                if ($product && $product['quantity'] >= $quantity) {
                    // Add to cart or update quantity
                    $cart_stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                    mysqli_stmt_bind_param($cart_stmt, "iiii", $current_user_id, $product_id, $quantity, $quantity);
                    
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
            
            if ($product_id > 0 && $current_user_id != $seller_id) {
                $fav_stmt = mysqli_prepare($conn, "INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($fav_stmt, "ii", $current_user_id, $product_id);
                
                if (mysqli_stmt_execute($fav_stmt)) {
                    if (mysqli_affected_rows($conn) > 0) {
                        $success_message = "Product added to favorites!";
                    } else {
                        $error_message = "Product is already in your favorites.";
                    }
                } else {
                    $error_message = "Error adding to favorites.";
                }
                mysqli_stmt_close($fav_stmt);
            }
            break;
    }
}

// Handle search functionality for seller's products
$search_query = '';
$category_filter = '';
$products = [];

if (isset($_GET['search']) || isset($_GET['category'])) {
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
    
    $sql = "SELECT * FROM products WHERE user_id = ? AND status = 'active'";
    $params = [$seller_id];
    $types = 'i';
    
    if (!empty($search_query)) {
        $sql .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $search_term = "%$search_query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'sss';
    }
    
    if (!empty($category_filter)) {
        $sql .= " AND category = ?";
        $params[] = $category_filter;
        $types .= 's';
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    // Get all seller's products
    $products_query = "SELECT * FROM products WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $products_query);
    mysqli_stmt_bind_param($stmt, "i", $seller_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Get seller's product categories
$categories_query = "SELECT category, COUNT(*) as count FROM products WHERE user_id = ? AND status = 'active' GROUP BY category ORDER BY count DESC";
$stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($stmt, "i", $seller_id);
mysqli_stmt_execute($stmt);
$categories_result = mysqli_stmt_get_result($stmt);
$categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Get seller statistics
$stats_query = "SELECT 
                COUNT(*) as total_products,
                COUNT(CASE WHEN quantity > 0 THEN 1 END) as available_products,
                AVG(price) as avg_price,
                SUM(quantity) as total_stock
                FROM products 
                WHERE user_id = ? AND status = 'active'";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $seller_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stmt);

// Check if current user has favorited any products from this seller
$user_favorites = [];
if ($is_logged_in) {
    $fav_query = "SELECT product_id FROM favorites WHERE user_id = ? AND product_id IN (SELECT id FROM products WHERE user_id = ?)";
    $stmt = mysqli_prepare($conn, $fav_query);
    mysqli_stmt_bind_param($stmt, "ii", $current_user_id, $seller_id);
    mysqli_stmt_execute($stmt);
    $fav_result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($fav_result)) {
        $user_favorites[] = $row['product_id'];
    }
    mysqli_stmt_close($stmt);
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?> - Seller Details</title>
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
      .seller-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin: 20px 0;
      }
      .stat-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
      }
      .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #51cf66;
      }
      .stat-label {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
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
      }
      .btn-primary { background: #51cf66; color: white; }
      .btn-favorite { background: #ffc107; color: white; }
      .btn-favorited { background: #28a745; color: white; }
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
      .seller-contact {
        background: #e8f5e8;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
      }
      .contact-item {
        margin: 8px 0;
        font-size: 14px;
      }
      .contact-label {
        font-weight: bold;
        color: #333;
      }
      .search-filters {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 10px;
      }
      .filter-select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-size: 14px;
      }
    </style>
  </head>

  <body>
    <?php include 'navbar.php'; ?>

    <div class="page-wrapper">


      <!-- Main Content -->
      <main class="main-section">

        <section class="section-title">
          <h1 class="title">Seller Details</h1>
        </section>

        <p style="text-align: center; color: #666; font-size: 16px; margin: 20px 0px; width: 100%; display: block;"><a href="home.php" style="color: #51cf66; text-decoration: none;">Home</a> > 
          <span>Seller Profile</span></p>

        <!-- Display Messages -->
        <?php if (!empty($success_message)): ?>
          <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
               <?php if (!empty($error_message)): ?>
          <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Search Bar Section -->
        <section class="search-section">
          <div class="search-label">Search <?php echo htmlspecialchars($seller['first_name']); ?>'s products</div>
          <form method="GET" action="" class="search-bar">
            <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
            <div class="search-filters">
              <input type="search" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
              
              <select name="category" class="filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                          <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              
              <button type="submit" class="search-button">Search</button>
              
              <?php if (!empty($search_query) || !empty($category_filter)): ?>
                <a href="seller-detail.php?seller_id=<?php echo $seller_id; ?>" class="btn-secondary" style="padding: 8px 15px; text-decoration: none;">Clear</a>
              <?php endif; ?>
            </div>
          </form>
        </section>

        <!-- Seller Details Section -->
        <section class="seller-details-section">

        <!-- Seller Details Left Card -->
        <div class="seller-details-left-card">
          <div class="seller-info-wrapper">

            <div class="seller-details-user-image">
              <img src="<?php echo htmlspecialchars($seller['profile_image']); ?>" alt="<?php echo htmlspecialchars($seller['first_name']); ?>" style="border-radius: 50%; object-fit: cover;">
            </div>

            <div class="seller-info-content-wrapper">
              <div class="seller-name">Name</div>
              <div class="seller-name-info"><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></div>
            </div>

            <div class="seller-info-content-wrapper">
              <div class="seller-location">Location</div>
              <div class="seller-location-info"><?php echo htmlspecialchars($seller['location']); ?></div>
            </div>

            <div class="seller-info-content-wrapper">
              <div class="seller-email">Email</div>
              <div class="seller-email-info"><?php echo htmlspecialchars($seller['email']); ?></div>
            </div>

            <?php if (!empty($seller['phone'])): ?>
            <div class="seller-info-content-wrapper">
              <div class="seller-phone-number">Phone No</div>
              <div class="seller-phone-number-info"><?php echo htmlspecialchars($seller['phone']); ?></div>
            </div>
            <?php endif; ?>

            <div class="seller-info-content-wrapper">
              <div class="seller-phone-number">Member Since</div>
              <div class="seller-phone-number-info"><?php echo date('M Y', strtotime($seller['created_at'])); ?></div>
            </div>
          </div>

          <!-- Seller Statistics -->
          <div class="seller-stats">
            <div class="stat-card">
              <div class="stat-number"><?php echo $stats['total_products']; ?></div>
              <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $stats['available_products']; ?></div>
              <div class="stat-label">Available Now</div>
            </div>
            <div class="stat-card">
              <div class="stat-number">$<?php echo number_format($stats['avg_price'] ?? 0, 2); ?></div>
              <div class="stat-label">Avg. Price</div>
            </div>
            <div class="stat-card">
              <div class="stat-number"><?php echo $stats['total_stock']; ?></div>
              <div class="stat-label">Total Stock</div>
            </div>
          </div>

          <!-- Contact Information -->
          <?php if ($is_logged_in && $current_user_id != $seller_id): ?>
          <div class="seller-contact">
            <h4 style="margin-top: 0; color: #333;">Contact Seller</h4>
            <div class="contact-item">
              <span class="contact-label">Email:</span> 
              <a href="mailto:<?php echo htmlspecialchars($seller['email']); ?>" style="color: #51cf66;">
                <?php echo htmlspecialchars($seller['email']); ?>
              </a>
            </div>
            <?php if (!empty($seller['phone'])): ?>
            <div class="contact-item">
              <span class="contact-label">Phone:</span> 
              <a href="tel:<?php echo htmlspecialchars($seller['phone']); ?>" style="color: #51cf66;">
                <?php echo htmlspecialchars($seller['phone']); ?>
              </a>
            </div>
            <?php endif; ?>
            <div style="margin-top: 10px; font-size: 12px; color: #666;">
              Contact the seller directly for bulk orders or special requests.
            </div>
          </div>
          <?php endif; ?>

          <div class="seller-about-us-wrapper">
            <h3>About Seller</h3>
            <div class="seller-about-us">
              <?php echo htmlspecialchars($seller['first_name']); ?> is a trusted seller on Farm2Door with 
              <?php echo $stats['total_products']; ?> products available. 
              <?php if ($stats['available_products'] > 0): ?>
                Currently has <?php echo $stats['available_products']; ?> products in stock.
              <?php endif ?>
              Member since <?php echo date('F Y', strtotime($seller['created_at'])); ?>.
              
              <?php if (count($categories) > 0): ?>
                <br><br><strong>Specializes in:</strong> 
                <?php 
                $category_names = array_column($categories, 'category');
                echo htmlspecialchars(implode(', ', array_slice($category_names, 0, 3)));
                if (count($category_names) > 3) echo ' and more';
                ?>
              <?php endif; ?>
            </div>
          </div> 
        </div>

        <!-- Seller Details Right Card -->
        <div class="seller-details-right-card">
          <h3>
            <?php 
            if (!empty($search_query) || !empty($category_filter)) {
                echo count($products) . " Products Found";
                if (!empty($search_query)) echo " for \"" . htmlspecialchars($search_query) . "\"";
                if (!empty($category_filter)) echo " in " . htmlspecialchars($category_filter);
            } else {
                echo "All Products (" . count($products) . ")";
            }
            ?>
          </h3>

          <div class="about-seller-grid">
            
            <?php if (empty($products)): ?>
              <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <?php if (!empty($search_query) || !empty($category_filter)): ?>
                  <h4>No products found</h4>
                  <p>Try adjusting your search criteria</p>
                  <a href="seller-detail.php?seller_id=<?php echo $seller_id; ?>">
                    <button class="btn-primary" style="padding: 10px 20px;">View All Products</button>
                  </a>
                <?php else: ?>
                  <h4>No products available</h4>
                  <p>This seller hasn't added any products yet.</p>
                  <a href="home.php">
                    <button class="btn-primary" style="padding: 10px 20px;">Browse Other Sellers</button>
                  </a>
                <?php endif; ?>
              </div>
            <?php else: ?>

              <?php foreach ($products as $product): ?>
                <div class="produce-listing-card <?php echo ($product['quantity'] <= 0) ? 'out-of-stock' : ''; ?>">
                  <img src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://placehold.co/340x180'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="produce-image">
                  
                  <div class="card-body">
                    <div class="card-info">
                      <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                      <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                      <div class="product-location"><?php echo htmlspecialchars($seller['location']); ?></div>
                      <div style="font-size: 12px; color: <?php echo ($product['quantity'] > 0) ? '#28a745' : '#dc3545'; ?>; margin-top: 5px;">
                        <?php if ($product['quantity'] > 0): ?>
                          Available: <?php echo $product['quantity']; ?> units
                        <?php else: ?>
                          Out of Stock
                        <?php endif; ?>
                      </div>
                      <?php if (!empty($product['category'])): ?>
                        <div style="font-size: 11px; background: #e9ecef; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-top: 5px;">
                          <?php echo htmlspecialchars($product['category']); ?>
                        </div>
                      <?php endif; ?>
                      <div style="font-size: 11px; color: #666; margin-top: 5px;">
                        Added: <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                      </div>
                    </div>

                    <?php if ($is_logged_in): ?>
                      <?php if ($current_user_id != $seller_id): ?>
                        <div class="product-actions">
                          <?php if ($product['quantity'] > 0): ?>
                            <!-- Add to Cart Form -->
                            <form method="POST" action="" style="display: flex; align-items: center; gap: 5px;">
                              <input type="hidden" name="action" value="add_to_cart">
                              <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                              <label class="qty-label" style="font-size: 12px;">Qty:</label>
                              <input type="number" name="quantity" class="qty-input" min="1" max="<?php echo $product['quantity']; ?>" value="1" required>
                              <button type="submit" class="btn-small btn-primary">Add to Cart</button>
                            </form>
                          <?php endif; ?>

                          <!-- Add to Favorites -->
                          <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="action" value="add_to_favorites">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn-small <?php echo in_array($product['id'], $user_favorites) ? 'btn-favorited' : 'btn-favorite'; ?>">
                              <?php echo in_array($product['id'], $user_favorites) ? '♥ Favorited' : '♡ Favorite'; ?>
                            </button>
                          </form>
                        </div>
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

                    <!-- Product Description -->
                    <?php if (!empty($product['description'])): ?>
                      <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <div style="font-size: 12px; font-weight: bold; color: #666; margin-bottom: 5px;">Description:</div>
                        <div style="font-size: 12px; color: #666;">
                          <?php 
                          $description = htmlspecialchars($product['description']);
                          echo (strlen($description) > 150) ? substr($description, 0, 150) . '...' : $description;
                          ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>

            <?php endif; ?>

          </div>

          <!-- Pagination or Load More (if needed) -->
          <?php if (count($products) >= 20): ?>
            <div style="text-align: center; margin-top: 30px;">
              <p style="color: #666;">Showing first 20 products. Use search to find specific items.</p>
            </div>
          <?php endif; ?>

        </div>
      </section>

      <!-- Quick Actions Section -->
      <?php if ($is_logged_in && $current_user_id != $seller_id && !empty($products)): ?>
        <section class="produce-listing-section">
          <h3>Quick Actions</h3>
          <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; display: flex; gap: 15px; flex-wrap: wrap; justify-content: center;">
            
            <!-- Add All Available to Cart -->
            <form method="POST" action="user-checkout-cart.php" style="display: inline;">
              <input type="hidden" name="action" value="add_seller_products">
              <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
              <button type="submit" class="btn-primary" style="padding: 10px 20px;" 
                      <?php echo ($stats['available_products'] == 0) ? 'disabled' : ''; ?>
                      onclick="return confirm('Add all available products from this seller to your cart?')">
                Add All Available to Cart (<?php echo $stats['available_products']; ?>)
              </button>
            </form>

            <!-- Favorite All Products -->
            <form method="POST" action="" style="display: inline;">
              <input type="hidden" name="action" value="favorite_all_products">
              <button type="submit" class="btn-favorite" style="padding: 10px 20px;"
                      onclick="return confirm('Add all products from this seller to your favorites?')">
                ♡ Favorite All Products
              </button>
            </form>

            <!-- View Cart -->
            <a href="user-checkout-cart.php">
              <button class="btn-secondary" style="padding: 10px 20px;">View My Cart</button>
            </a>

            <!-- Continue Shopping -->
            <a href="home.php">
              <button class="btn-secondary" style="padding: 10px 20px;">Continue Shopping</button>
            </a>

          </div>
        </section>
      <?php endif; ?>

      <!-- Related Sellers Section -->
      <section class="produce-listing-section">
        <h3>Other Sellers You Might Like</h3>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
          <p>Discover more sellers with similar products</p>
          <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 15px;">
            <?php if (!empty($categories)): ?>
              <?php foreach (array_slice($categories, 0, 3) as $category): ?>
                <a href="home.php?category=<?php echo urlencode($category['category']); ?>" style="text-decoration: none;">
                  <button class="btn-secondary" style="padding: 8px 15px;">
                    Browse <?php echo htmlspecialchars($category['category']); ?>
                  </button>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
            <a href="home.php">
              <button class="btn-primary" style="padding: 8px 15px;">All Sellers</button>
            </a>
          </div>
        </div>
      </section>

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
            <a href="logout.php" class="nav-item">Log Out</a>
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
      document.querySelector('.search-bar').addEventListener('submit', function(e) {
        var searchInput = document.querySelector('input[name="search"]');
        var categorySelect = document.querySelector('select[name="category"]');
        
        if (!searchInput.value.trim() && !categorySelect.value) {
          e.preventDefault();
          alert('Please enter a search term or select a category');
          return false;
        }
      });

      // Favorite button animation
      document.querySelectorAll('button[type="submit"]').forEach(function(button) {
        if (button.textContent.includes('Favorite')) {
          button.addEventListener('click', function() {
            if (this.textContent.includes('♡')) {
              this.style.transform = 'scale(0.95)';
              setTimeout(() => {
                this.style.transform = 'scale(1)';
              }, 150);
            }
          });
        }
      });

      // Contact seller tracking
      document.querySelectorAll('a[href^="mailto:"], a[href^="tel:"]').forEach(function(link) {
        link.addEventListener('click', function() {
          console.log('Contact initiated with seller: <?php echo $seller_id; ?>');
        });
      });

      // Product image error handling
      document.querySelectorAll('.produce-image').forEach(function(img) {
        img.addEventListener('error', function() {
          this.src = 'https://placehold.co/340x180/cccccc/666666?text=No+Image';
        });
      });

      // Smooth scroll to products section when coming from search
      if (window.location.search.includes('search=') || window.location.search.includes('category=')) {
        setTimeout(function() {
          document.querySelector('.seller-details-right-card').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }, 500);
      }
    </script>

    <script src="scripts/script.js"></script>
  </body>
</html>

<?php
// Handle additional actions that weren't in the main POST handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_logged_in && isset($_POST['action'])) {
    if ($_POST['action'] == 'favorite_all_products') {
        $conn = createConnection();
        
        // Get all active products from this seller
        $products_query = "SELECT id FROM products WHERE user_id = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $products_query);
        mysqli_stmt_bind_param($stmt, "i", $seller_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $added_count = 0;
        while ($product = mysqli_fetch_assoc($result)) {
            $fav_stmt = mysqli_prepare($conn, "INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($fav_stmt, "ii", $current_user_id, $product['id']);
            
            if (mysqli_stmt_execute($fav_stmt)) {
                if (mysqli_affected_rows($conn) > 0) {
                    $added_count++;
                }
            }
            mysqli_stmt_close($fav_stmt);
        }
        
        mysqli_stmt_close($stmt);
        closeConnection($conn);
        
        if ($added_count > 0) {
            $_SESSION['success_message'] = "$added_count products added to favorites!";
        } else {
            $_SESSION['error_message'] = "All products are already in your favorites.";
        }
        
        header("Location: seller-detail.php?seller_id=$seller_id");
        exit();
    }
}

// Display session messages
if (isset($_SESSION['success_message'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var message = document.createElement('div');
            message.className = 'message success';
            message.textContent = '" . addslashes($_SESSION['success_message']) . "';
            document.querySelector('.section-title').after(message);
            setTimeout(function() { message.style.display = 'none'; }, 5000);
        });
    </script>";
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var message = document.createElement('div');
            message.className = 'message error';
            message.textContent = '" . addslashes($_SESSION['error_message']) . "';
            document.querySelector('.section-title').after(message);
            setTimeout(function() { message.style.display = 'none'; }, 5000);
        });
    </script>";
    unset($_SESSION['error_message']);
}
?>


