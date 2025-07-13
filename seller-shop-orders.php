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

$success_message = '';
$error_message = '';

// Create connection
$conn = createConnection();

// Handle form submissions for order actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['action']) && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);

    // Verify the order belongs to this seller
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM orders WHERE id = ? AND seller_id = ?");
    mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    $order_exists = mysqli_stmt_num_rows($check_stmt) > 0;
    mysqli_stmt_close($check_stmt);

    if ($order_exists) {
      switch ($_POST['action']) {
        case 'confirm_order':
          // Update order status to confirmed
          $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'confirmed' WHERE id = ?");
          mysqli_stmt_bind_param($update_stmt, "i", $order_id);

          if (mysqli_stmt_execute($update_stmt)) {
            // Create notification for buyer
            $buyer_id = intval($_POST['buyer_id']);
            $notification = "Your order #$order_id has been confirmed by the seller.";

            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_confirmed', 'Order Confirmed', ?, ?)");
            mysqli_stmt_bind_param($notif_stmt, "isi", $buyer_id, $notification, $order_id);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            $success_message = "Order #$order_id has been confirmed successfully!";
          } else {
            $error_message = "Error confirming order.";
          }
          mysqli_stmt_close($update_stmt);
          break;

        case 'reject_order':
          // Update order status to cancelled
          $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'cancelled' WHERE id = ?");
          mysqli_stmt_bind_param($update_stmt, "i", $order_id);

          if (mysqli_stmt_execute($update_stmt)) {
            // Create notification for buyer
            $buyer_id = intval($_POST['buyer_id']);
            $notification = "Your order #$order_id has been cancelled by the seller.";

            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_cancelled', 'Order Cancelled', ?, ?)");
            mysqli_stmt_bind_param($notif_stmt, "isi", $buyer_id, $notification, $order_id);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            // Return products to inventory
            $restore_stmt = mysqli_prepare($conn, "UPDATE products p JOIN order_items oi ON p.id = oi.product_id SET p.quantity = p.quantity + oi.quantity WHERE oi.order_id = ?");
            mysqli_stmt_bind_param($restore_stmt, "i", $order_id);
            mysqli_stmt_execute($restore_stmt);
            mysqli_stmt_close($restore_stmt);

            $success_message = "Order #$order_id has been cancelled and products returned to inventory.";
          } else {
            $error_message = "Error cancelling order.";
          }
          mysqli_stmt_close($update_stmt);
          break;

        case 'mark_shipped':
          // Update order status to shipped
          $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'shipped' WHERE id = ?");
          mysqli_stmt_bind_param($update_stmt, "i", $order_id);

          if (mysqli_stmt_execute($update_stmt)) {
            // Create notification for buyer
            $buyer_id = intval($_POST['buyer_id']);
            $notification = "Your order #$order_id has been shipped!";

            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_shipped', 'Order Shipped', ?, ?)");
            mysqli_stmt_bind_param($notif_stmt, "isi", $buyer_id, $notification, $order_id);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            $success_message = "Order #$order_id has been marked as shipped!";
          } else {
            $error_message = "Error updating order status.";
          }
          mysqli_stmt_close($update_stmt);
          break;

        case 'mark_delivered':
          // Update order status to delivered
          $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'delivered' WHERE id = ?");
          mysqli_stmt_bind_param($update_stmt, "i", $order_id);

          if (mysqli_stmt_execute($update_stmt)) {
            // Create notification for buyer
            $buyer_id = intval($_POST['buyer_id']);
            $notification = "Your order #$order_id has been marked as delivered!";

            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_delivered', 'Order Delivered', ?, ?)");
            mysqli_stmt_bind_param($notif_stmt, "isi", $buyer_id, $notification, $order_id);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);

            $success_message = "Order #$order_id has been marked as delivered!";
          } else {
            $error_message = "Error updating order status.";
          }
          mysqli_stmt_close($update_stmt);
          break;
      }
    } else {
      $error_message = "Invalid order or you don't have permission to manage this order.";
    }
  }
}

// Handle search functionality
$search_query = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
  $search_query = trim($_GET['search']);
}

// Fetch incoming orders
$orders_query = "
    SELECT o.id, o.buyer_id, o.total_price, o.delivery_cost, o.status, 
           o.delivery_address, o.phone_number, o.notes, o.created_at,
           u.first_name, u.last_name, u.email, u.location,
           GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names,
           GROUP_CONCAT(DISTINCT p.image SEPARATOR ', ') as product_images,
           SUM(oi.quantity) as total_quantity
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.seller_id = ?";

// Add search condition if search query is provided
if (!empty($search_query)) {
  $orders_query .= " AND (o.id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.name LIKE ?)";
}

$orders_query .= " GROUP BY o.id ORDER BY o.created_at DESC";

$stmt = mysqli_prepare($conn, $orders_query);

if (!empty($search_query)) {
  $search_param = "%$search_query%";
  mysqli_stmt_bind_param($stmt, "issss", $user_id, $search_param, $search_param, $search_param, $search_param);
} else {
  mysqli_stmt_bind_param($stmt, "i", $user_id);
}

mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);
$incoming_orders = mysqli_fetch_all($orders_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Count orders by status
$status_counts = [
  'pending' => 0,
  'confirmed' => 0,
  'shipped' => 0,
  'delivered' => 0,
  'cancelled' => 0,
  'total' => count($incoming_orders)
];

foreach ($incoming_orders as $order) {
  if (isset($order['status']) && isset($status_counts[$order['status']])) {
    $status_counts[$order['status']]++;
  }
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Incoming Orders</title>
  <link rel="stylesheet" href="assets/fonts/inter.css" />
  <link rel="stylesheet" href="styles/styles.css" />
  <link rel="stylesheet" href="navbar.css" />
  <script src="scripts/script.js" defer></script>
  <style>
    .message {
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      text-align: center;
    }

    .success {
      background: rgba(81, 207, 102, 0.1);
      color: #51cf66;
    }

    .error {
      background: rgba(255, 107, 107, 0.1);
      color: #ff6b6b;
    }

    .status-filter {
      display: flex;
      gap: 10px;
      margin: 20px 0;
      flex-wrap: wrap;
    }

    .status-filter button {
      padding: 8px 15px;
      border: none;
      border-radius: 20px;
      background: #f1f3f5;
      cursor: pointer;
      font-size: 14px;
    }

    .status-filter button.active {
      background: #51cf66;
      color: white;
    }

    .status-badge {
      display: inline-block;
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: bold;
      text-transform: uppercase;
    }

    .status-pending {
      background: #ffe066;
      color: #664d03;
    }

    .status-confirmed {
      background: #74c0fc;
      color: #0c326f;
    }

    .status-shipped {
      background: #8ce99a;
      color: #0b4419;
    }

    .status-delivered {
      background: #51cf66;
      color: white;
    }

    .status-cancelled {
      background: #ff6b6b;
      color: white;
    }

    .order-details {
      margin-top: 10px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 5px;
      font-size: 14px;
    }

    .no-orders {
      text-align: center;
      padding: 40px;
      background: #f8f9fa;
      border-radius: 10px;
      margin: 20px 0;
    }
  </style>
</head>

<body>
  <div class="page-wrapper">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <main class="main-section">

        <!-- Page Title -->
        <section class="section-title">
          <h1 class="title">My Shop & Incoming Orders</h1>
        </section>

        <p style="text-align: center; color: #666; font-size: 16px; margin: -1rem 0 1rem 0; width: 100%; display: block;">Manage your shop orders and track customer purchases</p>

      <!-- Display Messages -->
      <?php if (!empty($success_message)): ?>
        <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>

      <!-- Search Bar Section -->
      <section class="search-section">
        <div class="search-label">Search for Order ID, Customer or Product</div>
        <form method="GET" action="" class="search-bar">
          <input type="search" name="search" class="search-input" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Enter order ID, customer name or product...">
          <button type="submit" class="search-button">Search</button>
          <?php if (!empty($search_query)): ?>
            <a href="seller-shop-orders.php" style="margin-left: 10px; color: #666; text-decoration: none;">Clear</a>
          <?php endif; ?>
        </form>
      </section>

      <!-- Order Status Filter -->
      <div class="status-filter">
        <button class="filter-btn active" data-status="all">
          All Orders (<?php echo $status_counts['total']; ?>)
        </button>
        <button class="filter-btn" data-status="pending">
          Pending (<?php echo $status_counts['pending']; ?>)
        </button>
        <button class="filter-btn" data-status="confirmed">
          Confirmed (<?php echo $status_counts['confirmed']; ?>)
        </button>
        <button class="filter-btn" data-status="shipped">
          Shipped (<?php echo $status_counts['shipped']; ?>)
        </button>
        <button class="filter-btn" data-status="delivered">
          Delivered (<?php echo $status_counts['delivered']; ?>)
        </button>
        <button class="filter-btn" data-status="cancelled">
          Cancelled (<?php echo $status_counts['cancelled']; ?>)
        </button>
      </div>

      <!-- Seller shop orders Section -->
      <section class="sellers-shop-orders-dashboard">

        <!-- LINK TO SETTINGS SECTION-->
        <section class="link-to-settings">
          <h6>To edit your shop details, go to settings</h6>
          <a href="settings.php">
            <button class="to-settings-button">Go to Settings</button>
          </a>
        </section>

        <section class="incoming-orders-section">
          <h3>
            <?php if ($status_counts['pending'] > 0): ?>
              <?php echo $status_counts['pending']; ?> new incoming order<?php echo $status_counts['pending'] != 1 ? 's' : ''; ?>
            <?php else: ?>
              Manage Your Orders
            <?php endif; ?>
          </h3>

          <!-- Seller shop orders GRID -->
          <div class="incoming-order-grid">

            <?php if (empty($incoming_orders)): ?>
              <div class="no-orders">
                <h3>No orders found</h3>
                <p style="margin-top: 10px;">You don't have any orders yet. When customers place orders, they will appear here.</p>
                <a href="user-dashboard.php">
                  <button class="to-settings-button" style="margin-top: 15px;">Manage Your Products</button>
                </a>
              </div>
            <?php else: ?>
              <?php foreach ($incoming_orders as $order): ?>
                <div class="seller-order-card" data-status="<?php echo htmlspecialchars($order['status']); ?>">
                  <?php
                  // Get the first product image
                  $images = explode(', ', $order['product_images'] ?? '');
                  $first_image = !empty($images[0]) ? $images[0] : 'https://placehold.co/170x180';


                  ?>
                  <img src="<?php echo htmlspecialchars($first_image); ?>" alt="Image of ordered product" class="seller-order-card-image">

                  <div class="seller-order-card-body">
                    <div class="seller-order-card-text-wrapper">
                      <div class="incoming-order-name">
                        <?php echo htmlspecialchars($order['product_names']); ?>
                        <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                          <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                        </span>
                      </div>
                      <div class="incoming-buyer-name">
                        Buyer: <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                      </div>
                      <div class="incoming-order-total">
                        Order Total: $<?php echo number_format($order['total_price'], 2); ?>
                        <?php if ($order['delivery_cost'] > 0): ?>
                          (includes $<?php echo number_format($order['delivery_cost'], 2); ?> delivery)
                        <?php endif; ?>
                      </div>
                      <div class="incoming-order-id">
                        Order ID: #<?php echo $order['id']; ?> |
                        Placed: <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                      </div>
                      <div class="incoming-order-quantity">
                        Total Items: <?php echo $order['total_quantity']; ?>
                      </div>
                      <div class="incoming-order-location">
                        Delivery Address: <?php echo htmlspecialchars($order['delivery_address']); ?>
                      </div>

                      <div class="order-details">
                        <strong>Contact:</strong> <?php echo htmlspecialchars($order['phone_number']); ?><br>
                        <?php if (!empty($order['notes'])): ?>
                          <strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?><br>
                        <?php endif; ?>
                        <strong>Customer Email:</strong> <?php echo htmlspecialchars($order['email']); ?>
                      </div>
                    </div>

                    <div class="seller-order-button-wrapper">
                      <?php if ($order['status'] == 'pending'): ?>
                        <form method="POST" action="" style="display: inline;">
                          <input type="hidden" name="action" value="confirm_order">
                          <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                          <input type="hidden" name="buyer_id" value="<?php echo $order['buyer_id']; ?>">
                          <button type="submit" class="seller-order-confirm-button-green" onclick="return confirm('Confirm this order?')">
                            <small>Confirm Order</small>
                          </button>
                        </form>

                        <form method="POST" action="" style="display: inline;">
                          <input type="hidden" name="action" value="reject_order">
                          <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                          <input type="hidden" name="buyer_id" value="<?php echo $order['buyer_id']; ?>">
                          <button type="submit" class="seller-order-confirm-button-black" onclick="return confirm('Are you sure you want to reject this order? This will cancel the order and return products to inventory.')">
                            <small>Reject Order</small>
                          </button>
                        </form>
                      <?php elseif ($order['status'] == 'confirmed'): ?>
                        <form method="POST" action="" style="display: inline;">
                          <input type="hidden" name="action" value="mark_shipped">
                          <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                          <input type="hidden" name="buyer_id" value="<?php echo $order['buyer_id']; ?>">
                          <button type="submit" class="seller-order-confirm-button-green" onclick="return confirm('Mark this order as shipped?')">
                            <small>Mark as Shipped</small>
                          </button>
                        </form>

                        <form method="POST" action="" style="display: inline;">
                          <input type="hidden" name="action" value="reject_order">
                          <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                          <input type="hidden" name="buyer_id" value="<?php echo $order['buyer_id']; ?>">
                          <button type="submit" class="seller-order-confirm-button-black" onclick="return confirm('Are you sure you want to cancel this order? This will return products to inventory.')">
                            <small>Cancel Order</small>
                          </button>
                        </form>
                      <?php elseif ($order['status'] == 'shipped'): ?>
                        <form method="POST" action="" style="display: inline;">
                          <input type="hidden" name="action" value="mark_delivered">
                          <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                          <input type="hidden" name="buyer_id" value="<?php echo $order['buyer_id']; ?>">
                          <button type="submit" class="seller-order-confirm-button-green" onclick="return confirm('Mark this order as delivered?')">
                            <small>Mark as Delivered</small>
                          </button>
                        </form>
                      <?php elseif ($order['status'] == 'delivered'): ?>
                        <button class="seller-order-confirm-button-green" disabled>
                          <small>Order Completed</small>
                        </button>
                      <?php elseif ($order['status'] == 'cancelled'): ?>
                        <button class="seller-order-confirm-button-black" disabled>
                          <small>Order Cancelled</small>
                        </button>
                      <?php endif; ?>

                      <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>" class="seller-order-confirm-button-black" style="text-decoration: none; text-align: center;">
                        <small>Contact Buyer</small>
                      </a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

          </div>
        </section>

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

    // Status filter functionality
    document.querySelectorAll('.filter-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        // Remove active class from all buttons
        document.querySelectorAll('.filter-btn').forEach(function(b) {
          b.classList.remove('active');
        });

        // Add active class to clicked button
        this.classList.add('active');

        // Get selected status
        var status = this.getAttribute('data-status');

        // Filter order cards
        document.querySelectorAll('.seller-order-card').forEach(function(card) {
          if (status === 'all' || card.getAttribute('data-status') === status) {
            card.style.display = 'flex';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });
  </script>

  <script src="scripts/script.js"></script>
</body>

</html>