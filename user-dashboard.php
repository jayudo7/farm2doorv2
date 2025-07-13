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
$user_email = $_SESSION['user_email'];
$user_first_name = $_SESSION['user_first_name'];
$user_last_name = $_SESSION['user_last_name'];

$success_message = '';
$error_message = '';

// Create connection
$conn = createConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                $name = trim($_POST['product_name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $quantity = intval($_POST['quantity']);
                $category = trim($_POST['category']);
                
                if (!empty($name) && $price > 0 && $quantity >= 0) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO products (user_id, name, description, price, quantity, category) VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "issdis", $user_id, $name, $description, $price, $quantity, $category);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Product added successfully!";
                    } else {
                        $error_message = "Error adding product: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Please fill all required fields correctly.";
                }
                break;
                
            case 'update_product':
                $product_id = intval($_POST['product_id']);
                $name = trim($_POST['product_name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $quantity = intval($_POST['quantity']);
                
                if (!empty($name) && $price > 0 && $quantity >= 0) {
                    $stmt = mysqli_prepare($conn, "UPDATE products SET name = ?, description = ?, price = ?, quantity = ? WHERE id = ? AND user_id = ?");
                    mysqli_stmt_bind_param($stmt, "ssdiii", $name, $description, $price, $quantity, $product_id, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Product updated successfully!";
                    } else {
                        $error_message = "Error updating product: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Please fill all required fields correctly.";
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ? AND user_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $product_id, $user_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Product deleted successfully!";
                } else {
                    $error_message = "Error deleting product: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
                break;
        }
    }
}

// Fetch user's products
$products_query = "SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $products_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$products_result = mysqli_stmt_get_result($stmt);
$user_products = mysqli_fetch_all($products_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Get user statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM products WHERE user_id = ?) as total_products,
    (SELECT COUNT(*) FROM orders WHERE seller_id = ?) as total_sales,
    (SELECT COUNT(*) FROM orders WHERE buyer_id = ?) as total_purchases,
    (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE seller_id = ? AND status != 'cancelled') as total_earnings";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "iiii", $user_id, $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$user_stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stmt);

// Fetch recent orders (as seller)
$orders_query = "SELECT o.*, oi.quantity, oi.price, p.name as product_name, u.first_name, u.last_name 
                FROM orders o 
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id 
                JOIN users u ON o.buyer_id = u.id 
                WHERE o.seller_id = ? 
                ORDER BY o.created_at DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $orders_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);
$recent_orders = mysqli_fetch_all($orders_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);


closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>User Dashboard - Farm2Door</title>
    <link rel="stylesheet" href="assets/fonts/inter.css">
    <link rel="stylesheet" href="styles/styles.css"/>
    <link rel="stylesheet" href="navbar.css"/>
    <script src="scripts/script.js" defer></script>
    <style>
      .message {
        padding: 10px;
        margin: 10px 0;
        border-radius: 5px;
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
        margin: 5% auto;
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
    </style>
  </head>

  <body>
    <div class="page-wrapper">
      <?php include 'navbar.php'; ?>

      <!-- Main Content -->
      <main class="main-section">

        <!-- Page Title -->
        <section class="section-title">
          <h1 class="title">User Dashboard</h1>
        </section>

        <p style="text-align: center; color: #666; font-size: 16px; margin: 10px; width: 100%; display: block;"> Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>

        <!-- Display Messages -->
        <?php if (!empty($success_message)): ?>
          <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
          <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- USER DASHBOARD MAIN CONTAINER-->
        <section class="user-dashboard-main-container">

          <!-- USER DASHBOARD LEFT CONTAINER-->
          <div class="user-dashboard-left-container">

            <!-- USER DASHBOARD SETTINGS CONTAINER-->
            <div class="user-dashboard-settings-wrapper">
              <h3>Personal Settings</h3>
              <a href="settings.php">
                <button class="user-dashboard-button-black">Edit Account Details</button>
              </a>
              <!-- <button class="user-dashboard-button-red">Report Fraudulent Activity</button> -->
            </div>

            <!-- USER DASHBOARD SETTINGS CONTAINER-->
            <div class="user-dashboard-settings-wrapper">
              <h3>Product Settings</h3>
              <button class="user-dashboard-button-black" style="width: 200px; align-self: center;" onclick="openAddProductModal()">Add Products</button>
              <!-- <button class="user-dashboard-button-black">Modify Products</button>
              <button class="user-dashboard-button-black">Delete Products</button> -->
            </div>

            <!-- USER DASHBOARD SETTINGS CONTAINER-->
            <!-- <div class="user-dashboard-settings-wrapper">
              <h3>Order Settings</h3>
              <a href="my-orders.php">
                <button class="user-dashboard-button-black">My Orders</button>
              </a>
              <a href="seller-shop-orders.php">
                <button class="user-dashboard-button-black">Incoming Shop Orders</button>
              </a>
            </div> -->

            <!-- USER STATS CONTAINER-->
            <div class="user-dashboard-settings-wrapper">
              <h3>Quick Stats</h3>
              <p>Products Listed: <strong><?php echo $user_stats['total_products']; ?></strong></p>
              <p>Total Sales: <strong><?php echo $user_stats['total_sales']; ?></strong></p>
              <p>Total Purchases: <strong><?php echo $user_stats['total_purchases']; ?></strong></p>
              <p>Total Earnings: <strong>$<?php echo number_format($user_stats['total_earnings'], 2); ?></strong></p>
            </div>

          </div>

          <!-- USER DASHBOARD RIGHT CONTAINER-->
          <div class="user-dashboard-right-container">
            <h3>My Products (<?php echo count($user_products); ?>)</h3>

            <!-- USER DASHBOARD GRID-->
            <div class="user-dashboard-grid">

              <?php if (empty($user_products)): ?>
                <div style="text-align: center; padding: 40px;">
                  <p style="margin-bottom: 20px;">You haven't added any products yet.</p>
                  <button class="user-dashboard-button-black" onclick="openAddProductModal()">Add Your First Product</button>
                </div>
              <?php else: ?>
                <?php foreach ($user_products as $product): ?>
                  <div class="user-dashboard-card">
                    <form method="POST" action="">
                      <input type="hidden" name="action" value="update_product">
                      <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                      <div class="user-dashboard-card-image-wrapper">
                        <img src="<?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : 'https://placehold.co/350x150'; ?>" alt="Product Image" class="user-dashboard-card-image">
                      </div>

                      <div class="user-dashboard-card-body">
                        <div class="user-dashboard-card-wrapper">
                          <label class="user-dashboard-card-name">Product Name</label>
                          <input type="text" name="product_name" class="user-dashboard-name-input" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                          
                          <label class="user-dashboard-card-price">Price ($):</label>
                          <input type="number" name="price" class="user-dashboard-price-input" value="<?php echo $product['price']; ?>" step="0.01" min="0" required>
                          
                          <label class="user-dashboard-card-quantity">Quantity Available:</label>
                         <input type="number" name="quantity" class="user-dashboard-quantity-input" value="<?php echo $product['quantity']; ?>" min="0" required>
                                                    <input type="number" name="quantity" class="user-dashboard-quantity-input" value="<?php echo $product['quantity']; ?>" min="0" required>
                        </div>
                        
                        <div class="user-dashboard-card-wrapper">
                          <div class="user-dashboard-card-more">More about product</div>
                          <textarea name="description" class="user-dashboard-card-textarea"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="user-dashboard-card-wrapper">
                          <button type="submit" class="user-dashboard-card-button-green">Save Changes</button>
                        </div>
                      </div>
                    </form>
                    
                    <!-- Delete form (separate form to avoid conflicts) -->
                    <form method="POST" action="" style="display: inline;">
                      <input type="hidden" name="action" value="delete_product">
                      <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                      <button type="submit" class="user-dashboard-card-button-black" onclick="return confirm('Are you sure you want to delete this product?')">Delete Product</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

            </div>

            <!-- Recent Orders Section -->
            <h3 style="margin-top: 40px;">Recent Orders (<?php echo count($recent_orders); ?>)</h3>
            <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; margin-top: 20px;">
              <?php if (empty($recent_orders)): ?>
                <p>No recent orders found.</p>
              <?php else: ?>
                <?php foreach ($recent_orders as $order): ?>
                  <div style="background: white; padding: 15px; margin-bottom: 10px; border-radius: 5px; border-left: 4px solid #51cf66;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                      <div>
                        <strong><?php echo htmlspecialchars($order['product_name']); ?></strong><br>
                        <small>Buyer: <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></small><br>
                        <small>Quantity: <?php echo $order['quantity']; ?> | Total: $<?php echo number_format($order['total_price'], 2); ?></small>
                      </div>
                      <div style="text-align: right;">
                        <span class="status-<?php echo $order['status']; ?>" style="padding: 5px 10px; border-radius: 15px; font-size: 12px; background: #e9ecef;">
                          <?php echo ucfirst($order['status']); ?>
                        </span><br>
                        <small><?php echo date('M j, Y', strtotime($order['order_date'])); ?></small>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

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

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
      <div class="modal-content">
        <span class="close" onclick="closeAddProductModal()">&times;</span>
        <h2>Add New Product</h2>
        <form method="POST" action="">
          <input type="hidden" name="action" value="add_product">
          
          <div style="margin-bottom: 15px;">
            <label>Product Name *</label>
            <input type="text" name="product_name" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 5px;" required>
          </div>
          
          <div style="margin-bottom: 15px;">
            <label>Category</label>
            <select name="category" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 5px;">
              <option value="Vegetables">Vegetables</option>
              <option value="Fruits">Fruits</option>
              <option value="Grains">Grains</option>
              <option value="Dairy">Dairy</option>
              <option value="Meat">Meat</option>
              <option value="Fish">Fish</option>
              <option value="Other">Other</option>
            </select>
          </div>
          
          <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1;">
              <label>Price ($) *</label>
              <input type="number" name="price" step="0.01" min="0" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 5px;" required>
            </div>
            <div style="flex: 1;">
              <label>Quantity *</label>
              <input type="number" name="quantity" min="0" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 5px;" required>
            </div>
          </div>
          
          <div style="margin-bottom: 15px;">
            <label>Description</label>
            <textarea name="description" rows="4" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;"></textarea>
          </div>
          
          <div style="text-align: right;">
            <button type="button" onclick="closeAddProductModal()" style="padding: 10px 20px; margin-right: 10px; background: #ccc; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
            <button type="submit" style="padding: 10px 20px; background: #51cf66; color: white; border: none; border-radius: 5px; cursor: pointer;">Add Product</button>
          </div>
        </form>
      </div>
    </div>

    <script>
      function openAddProductModal() {
        document.getElementById('addProductModal').style.display = 'block';
      }

      function closeAddProductModal() {
        document.getElementById('addProductModal').style.display = 'none';
      }

      // Close modal when clicking outside of it
      window.onclick = function(event) {
        var modal = document.getElementById('addProductModal');
        if (event.target == modal) {
          modal.style.display = 'none';
        }
      }

      // Auto-hide messages after 5 seconds
      setTimeout(function() {
        var messages = document.querySelectorAll('.message');
        messages.forEach(function(message) {
          message.style.display = 'none';
        });
      }, 5000);
    </script>
  </body>
</html>
