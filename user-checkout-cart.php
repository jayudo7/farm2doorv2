<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: sign-in.php');
  exit();
}

// Include your database connection
require_once 'config_files/config.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_full_name'];

$success_message = '';
$error_message = '';

// Create connection
$conn = createConnection();

// Create necessary tables if they don't exist
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

$create_orders_table = "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_cost DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    delivery_address TEXT,
    phone_number VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_orders_table);

$create_order_items_table = "CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_order_items_table);

$create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('order_received', 'order_confirmed', 'order_shipped', 'order_delivered', 'order_cancelled', 'general') DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    order_id INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_notifications_table);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  switch ($_POST['action']) {
    case 'update_quantity':
      $cart_id = intval($_POST['cart_id']);
      $quantity = intval($_POST['quantity']);

      if ($quantity > 0) {
        // Check product availability
        $check_stmt = mysqli_prepare($conn, "SELECT p.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
        mysqli_stmt_bind_param($check_stmt, "ii", $cart_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $product = mysqli_fetch_assoc($check_result);
        mysqli_stmt_close($check_stmt);

        if ($product && $product['quantity'] >= $quantity) {
          $update_stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
          mysqli_stmt_bind_param($update_stmt, "iii", $quantity, $cart_id, $user_id);

          if (mysqli_stmt_execute($update_stmt)) {
            $success_message = "Cart updated successfully!";
          } else {
            $error_message = "Error updating cart.";
          }
          mysqli_stmt_close($update_stmt);
        } else {
          $error_message = "Insufficient stock. Maximum available: " . ($product['quantity'] ?? 0);
        }
      }
      break;

    case 'remove_item':
      $cart_id = intval($_POST['cart_id']);

      $remove_stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE id = ? AND user_id = ?");
      mysqli_stmt_bind_param($remove_stmt, "ii", $cart_id, $user_id);

      if (mysqli_stmt_execute($remove_stmt)) {
        $success_message = "Item removed from cart!";
      } else {
        $error_message = "Error removing item.";
      }
      mysqli_stmt_close($remove_stmt);
      break;

    case 'remove_seller_products':
      $seller_id = intval($_POST['seller_id']);

      $remove_stmt = mysqli_prepare($conn, "DELETE c FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND p.user_id = ?");
      mysqli_stmt_bind_param($remove_stmt, "ii", $user_id, $seller_id);

      if (mysqli_stmt_execute($remove_stmt)) {
        $success_message = "All products from seller removed!";
      } else {
        $error_message = "Error removing products.";
      }
      mysqli_stmt_close($remove_stmt);
      break;

    case 'clear_cart':
      $clear_stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
      mysqli_stmt_bind_param($clear_stmt, "i", $user_id);

      if (mysqli_stmt_execute($clear_stmt)) {
        $success_message = "Cart cleared successfully!";
      } else {
        $error_message = "Error clearing cart.";
      }
      mysqli_stmt_close($clear_stmt);
      break;

    case 'checkout_seller':
      $seller_id = intval($_POST['seller_id']);
      $delivery_address = trim($_POST['delivery_address']);
      $phone_number = trim($_POST['phone_number']);
      $notes = trim($_POST['notes']);

      if (empty($delivery_address) || empty($phone_number)) {
        $error_message = "Delivery address and phone number are required.";
        break;
      }

      // Start transaction
      mysqli_begin_transaction($conn);

      try {
        // Get cart items for this seller
        $cart_query = "SELECT c.*, p.name, p.price, p.quantity as stock_quantity 
                              FROM cart c 
                              JOIN products p ON c.product_id = p.id 
                              WHERE c.user_id = ? AND p.user_id = ? AND p.status = 'active'";
        $cart_stmt = mysqli_prepare($conn, $cart_query);
        mysqli_stmt_bind_param($cart_stmt, "ii", $user_id, $seller_id);
        mysqli_stmt_execute($cart_stmt);
        $cart_result = mysqli_stmt_get_result($cart_stmt);
        $cart_items = mysqli_fetch_all($cart_result, MYSQLI_ASSOC);
        mysqli_stmt_close($cart_stmt);

        if (empty($cart_items)) {
          throw new Exception("No items found for this seller.");
        }

        // Check stock availability for all items
        foreach ($cart_items as $item) {
          if ($item['stock_quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock for " . $item['name'] . ". Available: " . $item['stock_quantity']);
          }
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($cart_items as $item) {
          $subtotal += $item['price'] * $item['quantity'];
        }

        $delivery_cost = calculateDeliveryFee($subtotal); // Custom function
        $total_amount = $subtotal + $delivery_cost;

      
        // Create order
        $order_stmt = mysqli_prepare($conn, "INSERT INTO orders (buyer_id, seller_id, total_price, delivery_cost, delivery_address, phone_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($order_stmt, "iiddsss", $user_id, $seller_id, $total_amount, $delivery_cost, $delivery_address, $phone_number, $notes);


        if (!mysqli_stmt_execute($order_stmt)) {
          throw new Exception("Error creating order.");
        }

        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($order_stmt);

        // Add order items and update product quantities
        foreach ($cart_items as $item) {
          // Add order item
          $item_subtotal = $item['price'] * $item['quantity'];
          $item_stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
          mysqli_stmt_bind_param($item_stmt, "iiidd", $order_id, $item['product_id'], $item['quantity'], $item['price'], $item_subtotal);

          if (!mysqli_stmt_execute($item_stmt)) {
            throw new Exception("Error adding order item.");
          }
          mysqli_stmt_close($item_stmt);

          // Update product quantity
          $update_stock_stmt = mysqli_prepare($conn, "UPDATE products SET quantity = quantity - ? WHERE id = ?");
          mysqli_stmt_bind_param($update_stock_stmt, "ii", $item['quantity'], $item['product_id']);

          if (!mysqli_stmt_execute($update_stock_stmt)) {
            throw new Exception("Error updating product stock.");
          }
          mysqli_stmt_close($update_stock_stmt);
        }

        // Remove items from cart
        $remove_cart_stmt = mysqli_prepare($conn, "DELETE c FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND p.user_id = ?");
        mysqli_stmt_bind_param($remove_cart_stmt, "ii", $user_id, $seller_id);
        mysqli_stmt_execute($remove_cart_stmt);
        mysqli_stmt_close($remove_cart_stmt);

        // Create notification for seller
        $seller_notification = "New order #$order_id received from $user_name. Total: $" . number_format($total_amount, 2);
        $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_received', 'New Order Received', ?, ?)");
        mysqli_stmt_bind_param($notif_stmt, "isi", $seller_id, $seller_notification, $order_id);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);

        // Create notification for buyer
        $buyer_notification = "Your order #$order_id has been placed successfully. Total: $" . number_format($total_amount, 2);
        $buyer_notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_confirmed', 'Order Placed', ?, ?)");
        mysqli_stmt_bind_param($buyer_notif_stmt, "isi", $user_id, $buyer_notification, $order_id);
        mysqli_stmt_execute($buyer_notif_stmt);
        mysqli_stmt_close($buyer_notif_stmt);

        // Commit transaction
        mysqli_commit($conn);

        $success_message = "Order placed successfully! Order ID: #$order_id";

        // Send email notifications (optional)
        sendOrderNotificationEmail($seller_id, $order_id, $conn);
      } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
      }
      break;
  }
}

// Get cart items grouped by seller
$cart_query = "SELECT c.*, p.name, p.price, p.image, p.quantity as stock_quantity, p.user_id as seller_id,
               u.first_name, u.last_name, u.location, u.email,
               (p.price * c.quantity) as item_total
               FROM cart c 
               JOIN products p ON c.product_id = p.id 
               JOIN users u ON p.user_id = u.id
               WHERE c.user_id = ? AND p.status = 'active'
               ORDER BY u.first_name, u.last_name, p.name";

$stmt = mysqli_prepare($conn, $cart_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Group items by seller
$sellers_cart = [];
foreach ($cart_items as $item) {
  $seller_key = $item['seller_id'];
  if (!isset($sellers_cart[$seller_key])) {
    $sellers_cart[$seller_key] = [
      'seller_info' => [
        'id' => $item['seller_id'],
        'name' => $item['first_name'] . ' ' . $item['last_name'],
        'location' => $item['location'],
        'email' => $item['email']
      ],
      'items' => [],
      'subtotal' => 0
    ];
  }

  $sellers_cart[$seller_key]['items'][] = $item;
  $sellers_cart[$seller_key]['subtotal'] += $item['item_total'];
}

// Calculate delivery fees for each seller
foreach ($sellers_cart as &$seller_cart) {
  $seller_cart['delivery_cost'] = calculateDeliveryFee($seller_cart['subtotal']);
  $seller_cart['total'] = $seller_cart['subtotal'] + $seller_cart['delivery_cost'];
}

// Helper function to calculate delivery fee
function calculateDeliveryFee($subtotal)
{
  if ($subtotal >= 100) return 0; // Free delivery for orders over $100
  if ($subtotal >= 50) return 5;  // $5 for orders $50-$99
  return 10; // $10 for orders under $50
}

// Helper function to send email notifications
function sendOrderNotificationEmail($seller_id, $order_id, $conn)
{
  // Get seller email
  $email_stmt = mysqli_prepare($conn, "SELECT email, first_name FROM users WHERE id = ?");
  mysqli_stmt_bind_param($email_stmt, "i", $seller_id);
  mysqli_stmt_execute($email_stmt);
  $email_result = mysqli_stmt_get_result($email_stmt);
  $seller = mysqli_fetch_assoc($email_result);
  mysqli_stmt_close($email_stmt);

  if ($seller) {
    // In a real application, you would use a proper email service like PHPMailer
    // For now, we'll just log the email notification
    error_log("Email notification sent to " . $seller['email'] . " for order #" . $order_id);

    // You can implement actual email sending here
    /*
        $to = $seller['email'];
        $subject = "New Order Received - Farm2Door";
        $message = "Hello " . $seller['first_name'] . ",\n\nYou have received a new order #" . $order_id . " on Farm2Door.\n\nPlease log in to your account to view the details.\n\nBest regards,\nFarm2Door Team";
        $headers = "From: noreply@farm2door.com";
        
        mail($to, $subject, $message, $headers);
        */
  }
}

// Store cart count in session for navbar
if (isset($_SESSION['user_id'])) {
  $_SESSION['cart_count'] = getCartCount($_SESSION['user_id'], $conn);
}

// Close the connection after all database operations are complete
closeConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Checkout Cart - Farm2Door</title>
  <link rel="stylesheet" href="assets/fonts/inter.css">
  <link rel="stylesheet" href="styles/styles.css" />
  <link rel="stylesheet" href="navbar.css" />
  <style>
    .message {
      padding: 15px;
      margin: 15px 0;
      border-radius: 5px;
      text-align: center;
      font-weight: bold;
    }

    .success {
      background: rgba(81, 207, 102, 0.1);
      color: #51cf66;
      border: 1px solid #51cf66;
    }

    .error {
      background: rgba(255, 107, 107, 0.1);
      color: #ff6b6b;
      border: 1px solid #ff6b6b;
    }

    .checkout-summary {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 10px;
      margin: 20px 0;
      border: 2px solid #e9ecef;
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      margin: 10px 0;
      padding: 5px 0;
    }

    .summary-row.total {
      border-top: 2px solid #51cf66;
      font-weight: bold;
      font-size: 18px;
      color: #51cf66;
      margin-top: 15px;
      padding-top: 15px;
    }

    .checkout-form {
      width: 100%;
      background: #FAFAFA;
      padding: 20px;
      border-radius: 10px;
      margin: 20px 0;
    }

    .form-group {
      margin: 15px 0;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #333;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 14px;
    }

    .form-group textarea {
      height: 80px;
      resize: vertical;
    }

    .quantity-controls {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .quantity-input {
      width: 80px;
      text-align: center;
    }

    .btn-quantity {
      background: #51cf66;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 3px;
      cursor: pointer;
      font-size: 12px;
    }

    .btn-quantity:hover {
      background: #40c057;
    }

    .btn-remove {
      background: #ff6b6b;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 3px;
      cursor: pointer;
      font-size: 12px;
    }

    .btn-remove:hover {
      background: #ff5252;
    }

    .empty-cart {
      text-align: center;
      padding: 60px 20px;
      background: #f8f9fa;
      border-radius: 10px;
      margin: 40px 0;
    }

    .empty-cart h3 {
      color: #666;
      margin-bottom: 15px;
    }

    .empty-cart p {
      color: #888;
      margin-bottom: 25px;
    }

    .delivery-info {
      background: #e8f5e8;
      padding: 15px;
      border-radius: 8px;
      margin: 15px 0;
      border-left: 4px solid #51cf66;
    }

    .delivery-info h4 {
      margin: 0 0 10px 0;
      color: #2d5a2d;
    }

    .delivery-info ul {
      margin: 0;
      padding-left: 20px;
      color: #2d5a2d;
    }

    .stock-warning {
      background: #fff3cd;
      color: #856404;
      padding: 10px;
      border-radius: 5px;
      margin: 10px 0;
      border: 1px solid #ffeaa7;
    }

    .out-of-stock {
      background: #f8d7da;
      color: #721c24;
      padding: 10px;
      border-radius: 5px;
      margin: 10px 0;
      border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
      .checkout-cart-details {
        flex-direction: column;
      }

      .checkout-cart-details-left,
      .checkout-cart-details-right {
        width: 100%;
      }

      .quantity-controls {
        flex-wrap: wrap;
      }
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
        <h1 class="title">Checkout Cart</h1>
      </section>

      <p style="text-align: center; color: #666; font-size: 16px; margin: -1rem 0 1rem 0; width: 100%; display: block;">
          <a href="home.php" style="color: #51cf66; text-decoration: none;">Home</a> >
          <span>Shopping Cart</span>
      </p>

      <!-- Display Messages -->
      <?php if (!empty($success_message)): ?>
        <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>

      <?php if (empty($sellers_cart)): ?>
        <!-- Empty Cart -->
        <div class="empty-cart">
          <h3>Your cart is empty</h3>
          <p>Looks like you haven't added any products to your cart yet.</p>
          <a href="home.php">
            <button class="checkout-cart-main-button" style="padding: 15px 30px;">Start Shopping</button>
          </a>
          <div style="margin-top: 20px;">
            <a href="favorites.php" style="color: #51cf66; text-decoration: none;">
              View your favorites →
            </a>
          </div>
        </div>
      <?php else: ?>

        <section class="section-title">
          <h2 class="produce-title">
            You have items from <?php echo count($sellers_cart); ?>
            seller<?php echo count($sellers_cart) > 1 ? 's' : ''; ?>
          </h2>
        </section>

        <!-- Centered Container for Delivery Info and Cart Actions -->
        <div style="display: flex; flex-direction: column; align-items: center; width: 100%; margin-top: 30px;">
          <!-- Delivery Information -->
          <div class="delivery-info" style="text-align: center;">
            <h4>🚚 Delivery Information</h4>
            <ul style="list-style: none; padding: 0;">
              <li>Free delivery on orders over $100</li>
              <li>$5 delivery fee for orders $50 - $99</li>
              <li>$10 delivery fee for orders under $50</li>
              <li>Estimated delivery: 2-5 business days</li>
            </ul>
          </div>

          <!-- Cart Actions -->
          <div style="display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap; justify-content: center;">
            <form method="POST" action="" style="display: inline;">
              <input type="hidden" name="action" value="clear_cart">
              <button type="submit" class="btn-remove" style="padding: 10px 20px;"
                onclick="return confirm('Are you sure you want to clear your entire cart?')">
                Clear Entire Cart
              </button>
            </form>
            <a href="home.php">
              <button class="btn-quantity" style="padding: 10px 20px;">Continue Shopping</button>
            </a>
          </div>
        </div>

        <!-- CHECKOUT CART CARD GRID -->
        <section class="checkout-cart-grid">

          <?php foreach ($sellers_cart as $seller_id => $seller_cart): ?>
            <!-- CHECKOUT CART CARD -->
            <div class="checkout-cart-card">
              <div class="checkout-cart-title">
                <h6>
                  <a href="seller-detail.php?seller_id=<?php echo $seller_id; ?>"
                    style="color: #51cf66; text-decoration: none;">
                    <?php echo htmlspecialchars($seller_cart['seller_info']['name']); ?>
                  </a>
                  <span style="font-size: 12px; color: #666; margin-left: 10px;">
                    📍 <?php echo htmlspecialchars($seller_cart['seller_info']['location']); ?>
                  </span>
                </h6>
              </div>

              <div class="checkout-cart-details">

                <div class="checkout-cart-details-left">
                  <div class="checkout-cart-user-image">
                    <img src="https://placehold.co/82x82" alt="Seller image">
                  </div>

                  <div class="checkout-cart-left-details-text-wrapper">
                    <div class="checkout-cart-left-details-text">
                      Subtotal: $<?php echo number_format($seller_cart['subtotal'], 2); ?>
                    </div>
                    <div class="checkout-cart-left-details-text">
                      Delivery Cost: $<?php echo number_format($seller_cart['delivery_cost'], 2); ?>
                      <?php if ($seller_cart['delivery_cost'] == 0): ?>
                        <span style="color: #51cf66; font-size: 12px;">🎉 FREE!</span>
                      <?php endif; ?>
                    </div>
                    <div class="checkout-cart-left-details-text" style="font-weight: bold; color: #51cf66;">
                      Total: $<?php echo number_format($seller_cart['total'], 2); ?>
                    </div>
                  </div>

                  <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="remove_seller_products">
                    <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                    <button type="submit" class="checkout-cart-left-details-button"
                      onclick="return confirm('Remove all products from this seller?')">
                      <small>Remove all Products</small>
                    </button>
                  </form>
                </div>

                <div class="checkout-cart-details-right">

                  <?php foreach ($seller_cart['items'] as $item): ?>
                    <div class="checkout-cart-item">
                      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <img src="<?php echo !empty($item['image']) ? htmlspecialchars($item['image']) : 'https://placehold.co/50x50'; ?>"
                          alt="<?php echo htmlspecialchars($item['name']); ?>"
                          style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                        <div style="flex: 1;">
                          <div class="checkout-cart-item-name">
                            <?php echo htmlspecialchars($item['name']); ?></div>
                          <div class="checkout-cart-item-price">
                            $<?php echo number_format($item['price'], 2); ?> each</div>
                          <div style="font-size: 12px; color: #666;">
                            Stock: <?php echo $item['stock_quantity']; ?> available
                          </div>
                        </div>
                      </div>

                      <?php if ($item['stock_quantity'] < $item['quantity']): ?>
                        <div class="out-of-stock">
                          ⚠️ Only <?php echo $item['stock_quantity']; ?> units available. Please update
                          quantity.
                        </div>
                      <?php elseif ($item['stock_quantity'] <= 5): ?>
                        <div class="stock-warning">
                          ⚠️ Low stock: Only <?php echo $item['stock_quantity']; ?> units left.
                        </div>

                      <?php endif; ?>

                      <div class="quantity-controls">
                        <form method="POST" action=""
                          style="display: flex; align-items: center; gap: 10px;">
                          <input type="hidden" name="action" value="update_quantity">
                          <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">

                          <label class="checkout-cart-item-quantity">Qty:</label>
                          <input type="number" name="quantity" class="quantity-input" min="1"
                            max="<?php echo $item['stock_quantity']; ?>"
                            value="<?php echo $item['quantity']; ?>" onchange="this.form.submit()">

                          <div style="font-size: 12px; color: #666;">
                            = $<?php echo number_format($item['item_total'], 2); ?>
                          </div>
                        </form>

                        <form method="POST" action="" style="display: inline;">
                          <input type="hidden" name="action" value="remove_item">
                          <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                          <button type="submit" class="btn-remove"
                            onclick="return confirm('Remove this item from cart?')">
                            Remove
                          </button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>

                </div>
              </div>

              <!-- Checkout Form for this Seller -->
              <div class="checkout-form">
                <h4 style="margin-top: 0; color: #333;">
                  🛒 Checkout from <?php echo htmlspecialchars($seller_cart['seller_info']['name']); ?>
                </h4>

                <form method="POST" action="" id="checkout-form-<?php echo $seller_id; ?>">
                  <input type="hidden" name="action" value="checkout_seller">
                  <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">

                  <div class="form-group">
                    <label for="delivery_address_<?php echo $seller_id; ?>">
                      📍 Delivery Address *
                    </label>
                    <textarea name="delivery_address" id="delivery_address_<?php echo $seller_id; ?>"
                      placeholder="Enter your complete delivery address..." required></textarea>
                  </div>

                  <div class="form-group">
                    <label for="phone_number_<?php echo $seller_id; ?>">
                      📱 Phone Number *
                    </label>
                    <input type="tel" name="phone_number" id="phone_number_<?php echo $seller_id; ?>"
                      placeholder="Your phone number for delivery contact" required>
                  </div>

                  <div class="form-group">
                    <label for="notes_<?php echo $seller_id; ?>">
                      📝 Special Instructions (Optional)
                    </label>
                    <textarea name="notes" id="notes_<?php echo $seller_id; ?>"
                      placeholder="Any special delivery instructions or notes for the seller..."></textarea>
                  </div>

                  <!-- Order Summary -->
                  <div class="checkout-summary">
                    <h4 style="margin-top: 0; color: #333;">Order Summary</h4>

                    <?php foreach ($seller_cart['items'] as $item): ?>
                      <div class="summary-row">
                        <span><?php echo htmlspecialchars($item['name']); ?>
                          (×<?php echo $item['quantity']; ?>)</span>
                        <span>$<?php echo number_format($item['item_total'], 2); ?></span>
                      </div>
                    <?php endforeach; ?>

                    <div class="summary-row">
                      <span>Subtotal</span>
                      <span>$<?php echo number_format($seller_cart['subtotal'], 2); ?></span>
                    </div>

                    <div class="summary-row">
                      <span>Delivery Fee</span>
                      <span>
                        $<?php echo number_format($seller_cart['delivery_cost'], 2); ?>
                        <?php if ($seller_cart['delivery_cost'] == 0): ?>
                          <span style="color: #51cf66; font-size: 12px;">(FREE!)</span>
                        <?php endif; ?>
                      </span>
                    </div>

                    <div class="summary-row total">
                      <span>Total Amount</span>
                      <span>$<?php echo number_format($seller_cart['total'], 2); ?></span>
                    </div>
                  </div>

                  <!-- Check if any items are out of stock -->
                  <?php
                  $has_stock_issues = false;
                  foreach ($seller_cart['items'] as $item) {
                    if ($item['stock_quantity'] < $item['quantity']) {
                      $has_stock_issues = true;
                      break;
                    }
                  }
                  ?>

                  <button type="submit" class="checkout-cart-main-button"
                    style="width: 100%; padding: 15px; font-size: 16px; <?php echo $has_stock_issues ? 'opacity: 0.5; cursor: not-allowed;' : ''; ?>"
                    <?php echo $has_stock_issues ? 'disabled' : ''; ?>
                    onclick="return <?php echo $has_stock_issues ? 'false' : 'confirmCheckout(' . $seller_id . ')'; ?>">
                    <?php if ($has_stock_issues): ?>
                      ⚠️ Fix Stock Issues to Continue
                    <?php else: ?>
                      🛒 Place Order - $<?php echo number_format($seller_cart['total'], 2); ?>
                    <?php endif; ?>
                  </button>

                  <?php if ($has_stock_issues): ?>
                    <div class="out-of-stock" style="margin-top: 10px; text-align: center;">
                      Please update quantities for out-of-stock items before placing your order.
                    </div>
                  <?php endif; ?>
                </form>
              </div>
            </div>
            <!-- END OF CHECKOUT CART CARD -->
          <?php endforeach; ?>

        </section>

        <!-- Overall Cart Summary -->
        <section class="produce-listing-section">
          <h3>🛍️ Complete Cart Summary</h3>
          <div class="checkout-summary" style="max-width: 500px; margin: 0 auto;">
            <?php
            $grand_subtotal = 0;
            $grand_delivery = 0;
            $grand_total = 0;

            foreach ($sellers_cart as $seller_cart) {
              $grand_subtotal += $seller_cart['subtotal'];
              $grand_delivery += $seller_cart['delivery_cost'];
              $grand_total += $seller_cart['total'];
            }
            ?>

            <div class="summary-row">
              <span>Total Items Subtotal</span>
              <span>$<?php echo number_format($grand_subtotal, 2); ?></span>
            </div>

            <div class="summary-row">
              <span>Total Delivery Fees</span>
              <span>$<?php echo number_format($grand_delivery, 2); ?></span>
            </div>

            <div class="summary-row total">
              <span>Grand Total</span>
              <span>$<?php echo number_format($grand_total, 2); ?></span>
            </div>

            <div style="text-align: center; margin-top: 20px; font-size: 14px; color: #666;">
              💡 <strong>Tip:</strong> You can checkout each seller separately or all at once!
            </div>
          </div>
        </section>

        <!-- Bulk Actions -->
        <section class="produce-listing-section">
          <h3>⚡ Quick Actions</h3>
          <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">

              <button onclick="checkoutAllSellers()" class="checkout-cart-main-button"
                style="padding: 12px 25px;">
                🛒 Checkout All Sellers
              </button>

              <a href="favorites.php">
                <button class="btn-quantity" style="padding: 12px 25px;">
                  ♡ View Favorites
                </button>
              </a>

              <a href="my-orders.php">
                <button class="btn-quantity" style="padding: 12px 25px;">
                  📦 My Orders
                </button>
              </a>

              <button onclick="saveCartForLater()" class="btn-quantity" style="padding: 12px 25px;">
                💾 Save for Later
              </button>
            </div>

            <div style="margin-top: 15px; font-size: 12px; color: #666;">
              Need help? <a href="mailto:support@farm2door.com" style="color: #51cf66;">Contact Support</a>
            </div>
          </div>
        </section>

      <?php endif; ?>

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
        message.style.opacity = '0';
        setTimeout(function() {
          message.style.display = 'none';
        }, 300);
      });
    }, 5000);

    // Confirm checkout function
    function confirmCheckout(sellerId) {
      var sellerName = document.querySelector('#checkout-form-' + sellerId + ' h4').textContent;
      var total = document.querySelector('#checkout-form-' + sellerId + ' .summary-row.total span:last-child')
        .textContent;

      return confirm('Confirm your order from ' + sellerName + '?\n\nTotal: ' + total +
        '\n\nThis will:\n• Place your order\n• Notify the seller\n• Remove items from your cart\n• Send you an order confirmation'
      );
    }

    // Checkout all sellers function
    function checkoutAllSellers() {
      if (confirm(
          'Checkout from all sellers at once?\n\nThis will create separate orders for each seller and require you to fill delivery details for each.'
        )) {
        // Get all checkout forms
        var forms = document.querySelectorAll('[id^="checkout-form-"]');
        var allValid = true;

        // Check if all forms have required fields filled
        forms.forEach(function(form) {
          var address = form.querySelector('textarea[name="delivery_address"]');
          var phone = form.querySelector('input[name="phone_number"]');

          if (!address.value.trim() || !phone.value.trim()) {
            allValid = false;
          }
        });

        if (!allValid) {
          alert('Please fill in delivery address and phone number for all sellers before checking out.');
          return;
        }

        // Submit all forms
        forms.forEach(function(form, index) {
          setTimeout(function() {
            form.submit();
          }, index * 1000); // Stagger submissions
        });
      }
    }

    // Save cart for later function
    function saveCartForLater() {
      // In a real application, you might save cart state to localStorage or database
      localStorage.setItem('farm2door_saved_cart', JSON.stringify({
        timestamp: new Date().toISOString(),
        url: window.location.href
      }));

      alert('Cart saved! You can return to complete your purchase later.');
    }

    // Load saved cart if exists
    window.addEventListener('load', function() {
      var savedCart = localStorage.getItem('farm2door_saved_cart');
      if (savedCart) {
        var cartData = JSON.parse(savedCart);
        var savedDate = new Date(cartData.timestamp);
        var daysSaved = Math.floor((new Date() - savedDate) / (1000 * 60 * 60 * 24));

        if (daysSaved <= 7) { // Show notification for carts saved within 7 days
          var notification = document.createElement('div');
          notification.className = 'message success';
          notification.innerHTML = '💾 You have a saved cart from ' + daysSaved +
            ' day(s) ago. Complete your purchase now!';
          notification.style.cursor = 'pointer';
          notification.onclick = function() {
            this.style.display = 'none';
            localStorage.removeItem('farm2door_saved_cart');
          };

          document.querySelector('.section-title').after(notification);
        }
      }
    });

    // Quantity input validation
    document.querySelectorAll('.quantity-input').forEach(function(input) {
      input.addEventListener('input', function() {
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

    // Form validation before submission
    document.querySelectorAll('form[id^="checkout-form-"]').forEach(function(form) {
      form.addEventListener('submit', function(e) {
        var address = this.querySelector('textarea[name="delivery_address"]').value.trim();
        var phone = this.querySelector('input[name="phone_number"]').value.trim();

        if (address.length < 10) {
          e.preventDefault();
          alert('Please provide a complete delivery address (at least 10 characters).');
          return false;
        }

        if (phone.length < 10) {
          e.preventDefault();
          alert('Please provide a valid phone number (at least 10 digits).');
          return false;
        }

        // Show loading state
        var submitBtn = this.querySelector('button[type="submit"]');
        var originalText = submitBtn.textContent;
        submitBtn.textContent = '⏳ Processing Order...';
        submitBtn.disabled = true;

        // Re-enable button after 10 seconds in case of issues
        setTimeout(function() {
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
        }, 10000);
      });
    });

    // Phone number formatting
    document.querySelectorAll('input[type="tel"]').forEach(function(input) {
      input.addEventListener('input', function() {
        // Remove non-numeric characters
        var value = this.value.replace(/\D/g, '');

        // Format as (XXX) XXX-XXXX for US numbers
        if (value.length >= 6) {
          value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
        } else if (value.length >= 3) {
          value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
        }

        this.value = value;
      });
    });

    // Auto-save form data to prevent loss
    document.querySelectorAll('textarea, input[type="tel"]').forEach(function(input) {
      input.addEventListener('input', function() {
        var formId = this.closest('form').id;
        var fieldName = this.name;
        var key = 'farm2door_' + formId + '_' + fieldName;

        localStorage.setItem(key, this.value);
      });

      // Restore saved data on page load
      var formId = input.closest('form').id;
      var fieldName = input.name;
      var key = 'farm2door_' + formId + '_' + fieldName;
      var savedValue = localStorage.getItem(key);

      if (savedValue && !input.value) {
        input.value = savedValue;
      }
    });

    // Clear saved form data after successful submission
    window.addEventListener('beforeunload', function() {
      // Only clear if we're navigating away due to successful form submission
      if (document.querySelector('.message.success')) {
        Object.keys(localStorage).forEach(function(key) {
          if (key.startsWith('farm2door_checkout-form-')) {
            localStorage.removeItem(key);
          }
        });
      }
    });

    // Smooth scroll to checkout form when quantity is updated
    document.querySelectorAll('form input[name="quantity"]').forEach(function(input) {
      input.addEventListener('change', function() {
        setTimeout(function() {
          var checkoutForm = input.closest('.checkout-cart-card').querySelector(
            '.checkout-form');
          checkoutForm.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
          });
        }, 500);
      });
    });

    // Add visual feedback for form interactions
    document.querySelectorAll('.checkout-form input, .checkout-form textarea').forEach(function(input) {
      input.addEventListener('focus', function() {
        this.style.borderColor = '#51cf66';
        this.style.boxShadow = '0 0 0 2px rgba(81, 207, 102, 0.2)';
      });

      input.addEventListener('blur', function() {
        this.style.borderColor = '#ddd';
        this.style.boxShadow = 'none';
      });
    });

    // Estimate delivery date
    function addDeliveryEstimate() {
      var today = new Date();
      var minDelivery = new Date(today.getTime() + (2 * 24 * 60 * 60 * 1000)); // 2 days
      var maxDelivery = new Date(today.getTime() + (5 * 24 * 60 * 60 * 1000)); // 5 days

      var options = {
        weekday: 'short',
        month: 'short',
        day: 'numeric'
      };
      var minDate = minDelivery.toLocaleDateString('en-US', options);
      var maxDate = maxDelivery.toLocaleDateString('en-US', options);

      document.querySelectorAll('.checkout-form h4').forEach(function(header) {
        if (!header.querySelector('.delivery-estimate')) {
          var estimate = document.createElement('div');
          estimate.className = 'delivery-estimate';
          estimate.style.fontSize = '12px';
          estimate.style.color = '#666';
          estimate.style.fontWeight = 'normal';
          estimate.style.marginTop = '5px';
          estimate.innerHTML = '📅 Estimated delivery: ' + minDate + ' - ' + maxDate;
          header.appendChild(estimate);
        }
      });
    }

    // Add delivery estimates on page load
    addDeliveryEstimate();

    // Track cart abandonment for analytics
    var cartValue = <?php echo json_encode($grand_total ?? 0); ?>;
    var itemCount =
      <?php echo json_encode(count($sellers_cart) > 0 ? array_sum(array_map(function ($cart) {
        return count($cart['items']);
      }, $sellers_cart)) : 0); ?>;


    if (cartValue > 0) {
      // Log cart view for analytics
      console.log('Cart viewed:', {
        value: cartValue,
        items: itemCount
      });

      // Track time spent on cart page
      var startTime = Date.now();
      window.addEventListener('beforeunload', function() {
        var timeSpent = Math.round((Date.now() - startTime) / 1000);
        console.log('Time spent on cart:', timeSpent + ' seconds');
      });
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + Enter to checkout first seller
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        var firstCheckoutBtn = document.querySelector('button[onclick*="confirmCheckout"]');
        if (firstCheckoutBtn && !firstCheckoutBtn.disabled) {
          firstCheckoutBtn.click();
        }
      }

      // Escape to clear cart (with confirmation)
      if (e.key === 'Escape' && e.shiftKey) {
        var clearBtn = document.querySelector('button[onclick*="clear_cart"]');
        if (clearBtn) {
          clearBtn.click();
        }
      }
    });

    // Show keyboard shortcuts help
    var helpText = document.createElement('div');
    helpText.style.position = 'fixed';
    helpText.style.bottom = '20px';
    helpText.style.right = '20px';
    helpText.style.background = 'rgba(0,0,0,0.8)';
    helpText.style.color = 'white';
    helpText.style.padding = '10px';
    helpText.style.borderRadius = '5px';
    helpText.style.fontSize = '12px';
    helpText.style.display = 'none';
    helpText.style.zIndex = '1000';
    helpText.innerHTML = '⌨️ Shortcuts:<br>Ctrl+Enter: Quick checkout<br>Shift+Esc: Clear cart<br>Click to hide';
    helpText.onclick = function() {
      this.style.display = 'none';
    };
    document.body.appendChild(helpText);

    // Show help on Alt key
    document.addEventListener('keydown', function(e) {
      if (e.altKey && e.key === 'h') {
        helpText.style.display = helpText.style.display === 'none' ? 'block' : 'none';
      }
    });
  </script>

  <script src="scripts/script.js"></script>
</body>

</html>

<?php
// Additional helper functions for cart management

// Function to add product to cart (can be called from other pages)
function addToCart($user_id, $product_id, $quantity, $conn)
{
  // Check if product exists and has sufficient stock
  $product_stmt = mysqli_prepare($conn, "SELECT quantity, status FROM products WHERE id = ?");
  mysqli_stmt_bind_param($product_stmt, "i", $product_id);
  mysqli_stmt_execute($product_stmt);
  $product_result = mysqli_stmt_get_result($product_stmt);
  $product = mysqli_fetch_assoc($product_result);
  mysqli_stmt_close($product_stmt);

  if (!$product || $product['status'] !== 'active') {
    return ['success' => false, 'message' => 'Product not available'];
  }

  if ($product['quantity'] < $quantity) {
    return ['success' => false, 'message' => 'Insufficient stock'];
  }

  // Check if item already in cart
  $check_stmt = mysqli_prepare($conn, "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
  mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $product_id);
  mysqli_stmt_execute($check_stmt);
  $check_result = mysqli_stmt_get_result($check_stmt);
  $existing = mysqli_fetch_assoc($check_result);
  mysqli_stmt_close($check_stmt);

  if ($existing) {
    // Update existing cart item
    $new_quantity = $existing['quantity'] + $quantity;
    if ($new_quantity > $product['quantity']) {
      return ['success' => false, 'message' => 'Total quantity exceeds available stock'];
    }

    $update_stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_stmt, "ii", $new_quantity, $existing['id']);
    $success = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    return ['success' => $success, 'message' => $success ? 'Cart updated' : 'Error updating cart'];
  } else {
    // Add new cart item
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($insert_stmt, "iii", $user_id, $product_id, $quantity);
    $success = mysqli_stmt_execute($insert_stmt);
    mysqli_stmt_close($insert_stmt);

    return ['success' => $success, 'message' => $success ? 'Added to cart' : 'Error adding to cart'];
  }
}

// Function to get cart count for navbar display
function getCartCount($user_id, $conn)
{
  $count_stmt = mysqli_prepare($conn, "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
  mysqli_stmt_bind_param($count_stmt, "i", $user_id);
  mysqli_stmt_execute($count_stmt);
  $count_result = mysqli_stmt_get_result($count_stmt);
  $count_data = mysqli_fetch_assoc($count_result);
  mysqli_stmt_close($count_stmt);

  return intval($count_data['total'] ?? 0);
}

// Function to clean up expired cart items (can be called via cron job)
function cleanupExpiredCartItems($conn, $days = 30)
{
  $cleanup_stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE added_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
  mysqli_stmt_bind_param($cleanup_stmt, "i", $days);
  $result = mysqli_stmt_execute($cleanup_stmt);
  $affected = mysqli_affected_rows($conn);
  mysqli_stmt_close($cleanup_stmt);

  return ['success' => $result, 'cleaned' => $affected];
}