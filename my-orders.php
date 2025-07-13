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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['order_id'])) {
        $order_id = intval($_POST['order_id']);
        
        // Verify the order belongs to this user
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM orders WHERE id = ? AND buyer_id = ?");
        mysqli_stmt_bind_param($check_stmt, "ii", $order_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        $order_exists = mysqli_stmt_num_rows($check_stmt) > 0;
        mysqli_stmt_close($check_stmt);
        
        if ($order_exists) {
            switch ($_POST['action']) {
                case 'cancel_order':
                    // Only allow cancellation if order is pending or confirmed
                    $status_stmt = mysqli_prepare($conn, "SELECT status FROM orders WHERE id = ?");
                    mysqli_stmt_bind_param($status_stmt, "i", $order_id);
                    mysqli_stmt_execute($status_stmt);
                    $status_result = mysqli_stmt_get_result($status_stmt);
                    $order_status = mysqli_fetch_assoc($status_result)['status'];
                    mysqli_stmt_close($status_stmt);
                    
                    if ($order_status == 'pending' || $order_status == 'confirmed') {
                        // Update order status to cancelled
                        $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'cancelled' WHERE id = ?");
                        mysqli_stmt_bind_param($update_stmt, "i", $order_id);
                        
                        if (mysqli_stmt_execute($update_stmt)) {
                            // Get seller ID
                            $seller_stmt = mysqli_prepare($conn, "SELECT seller_id FROM orders WHERE id = ?");
                            mysqli_stmt_bind_param($seller_stmt, "i", $order_id);
                            mysqli_stmt_execute($seller_stmt);
                            $seller_result = mysqli_stmt_get_result($seller_stmt);
                            $seller_id = mysqli_fetch_assoc($seller_result)['seller_id'];
                            mysqli_stmt_close($seller_stmt);
                            
                            // Create notification for seller
                            $notification = "Order #$order_id has been cancelled by the buyer.";
                            
                            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_cancelled', 'Order Cancelled', ?, ?)");
                            mysqli_stmt_bind_param($notif_stmt, "isi", $seller_id, $notification, $order_id);
                            mysqli_stmt_execute($notif_stmt);
                            mysqli_stmt_close($notif_stmt);
                            
                            // Return products to inventory
                            $restore_stmt = mysqli_prepare($conn, "UPDATE products p JOIN order_items oi ON p.id = oi.product_id SET p.quantity = p.quantity + oi.quantity WHERE oi.order_id = ?");
                            mysqli_stmt_bind_param($restore_stmt, "i", $order_id);
                            mysqli_stmt_execute($restore_stmt);
                            mysqli_stmt_close($restore_stmt);
                            
                            $success_message = "Order #$order_id has been cancelled successfully.";
                        } else {
                            $error_message = "Error cancelling order.";
                        }
                        mysqli_stmt_close($update_stmt);
                    } else {
                        $error_message = "This order cannot be cancelled because it has already been " . $order_status . ".";
                    }
                    break;
                    
                case 'confirm_delivery':
                    // Only allow confirmation if order is shipped
                    $status_stmt = mysqli_prepare($conn, "SELECT status FROM orders WHERE id = ?");
                    mysqli_stmt_bind_param($status_stmt, "i", $order_id);
                    mysqli_stmt_execute($status_stmt);
                    $status_result = mysqli_stmt_get_result($status_stmt);
                    $order_status = mysqli_fetch_assoc($status_result)['status'];
                    mysqli_stmt_close($status_stmt);
                    
                    if ($order_status == 'shipped') {
                        // Update order status to delivered
                        $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'delivered' WHERE id = ?");
                        mysqli_stmt_bind_param($update_stmt, "i", $order_id);
                        
                        if (mysqli_stmt_execute($update_stmt)) {
                            // Get seller ID
                            $seller_stmt = mysqli_prepare($conn, "SELECT seller_id FROM orders WHERE id = ?");
                            mysqli_stmt_bind_param($seller_stmt, "i", $order_id);
                            mysqli_stmt_execute($seller_stmt);
                            $seller_result = mysqli_stmt_get_result($seller_stmt);
                            $seller_id = mysqli_fetch_assoc($seller_result)['seller_id'];
                            mysqli_stmt_close($seller_stmt);
                            
                            // Create notification for seller
                            $notification = "Order #$order_id has been confirmed as delivered by the buyer.";
                            
                            $notif_stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, order_id) VALUES (?, 'order_delivered', 'Delivery Confirmed', ?, ?)");
                            mysqli_stmt_bind_param($notif_stmt, "isi", $seller_id, $notification, $order_id);
                            mysqli_stmt_execute($notif_stmt);
                            mysqli_stmt_close($notif_stmt);
                            
                            $success_message = "You have confirmed delivery of order #$order_id.";
                        } else {
                            $error_message = "Error confirming delivery.";
                        }
                        mysqli_stmt_close($update_stmt);
                    } else {
                        $error_message = "This order cannot be marked as delivered because it is currently " . $order_status . ".";
                    }
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

// Fetch user's orders
$orders_query = "
    SELECT o.id, o.seller_id, o.total_price, o.delivery_cost, o.status, 
           o.delivery_address, o.phone_number, o.notes, o.created_at,
           u.first_name as seller_first_name, u.last_name as seller_last_name, 
           u.email as seller_email, u.location as seller_location,
           GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names,
           GROUP_CONCAT(DISTINCT p.image SEPARATOR ', ') as product_images,
           SUM(oi.quantity) as total_quantity
    FROM orders o
    JOIN users u ON o.seller_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.buyer_id = ?";

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
$my_orders = mysqli_fetch_all($orders_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Count orders by status
$status_counts = [
    'pending' => 0,
    'confirmed' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0,
    'total' => count($my_orders)
];

foreach ($my_orders as $order) {
    if (isset($order['status']) && isset($status_counts[$order['status']])) {
        $status_counts[$order['status']]++;
    }
}

// Get detailed order items for each order
foreach ($my_orders as &$order) {
    $items_query = "
        SELECT oi.*, p.name, p.image, p.description
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id";
    
    $items_stmt = mysqli_prepare($conn, $items_query);
    mysqli_stmt_bind_param($items_stmt, "i", $order['id']);
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);
    $order['items'] = mysqli_fetch_all($items_result, MYSQLI_ASSOC);
    mysqli_stmt_close($items_stmt);
}

closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Orders - Farm2Door</title>
    <link rel="stylesheet" href="assets/fonts/inter.css">
    <link rel="stylesheet" href="styles/styles.css"/>
    <link rel="stylesheet" href="navbar.css"/>
    <script src="scripts/script.js" defer></script>
    <style>
      * {
        font-family: "Inter", sans-serif;
      }

      .message {
        padding: 10px;
        margin: 10px 0;
        border-radius: 5px;
        text-align: center;
      }
      .success { background: rgba(81, 207, 102, 0.1); color: #51cf66; }
      .error { background: rgba(255, 107, 107, 0.1); color: #ff6b6b; }
      
      .status-filter {
        display: flex;
        gap: 10px;
        margin: 20px 0;
        flex-wrap: wrap;
        justify-content: center;
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
      
      .status-pending { background: #ffe066; color: #664d03; }
      .status-confirmed { background: #74c0fc; color: #0c326f; }
      .status-shipped { background: #8ce99a; color: #0b4419; }
      .status-delivered { background: #51cf66; color: white; }
      .status-cancelled { background: #ff6b6b; color: white; }
      
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
      
      .order-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
        overflow: hidden;
      }
      
      .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
      }
      
      .order-id {
        font-weight: bold;
        font-size: 16px;
      }
      
      .order-date {
        color: #6c757d;
        font-size: 14px;
      }
      
      .order-content {
        padding: 20px;
      }
      
      .order-summary {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
      }
      
      .order-seller {
        flex: 1;
      }
      
      .order-seller-name {
        font-weight: bold;
        margin-bottom: 5px;
      }
      
      .order-seller-location {
        color: #6c757d;
        font-size: 14px;
      }
      
      .order-total {
        text-align: right;
      }
      
      .order-price {
        font-size: 18px;
        font-weight: bold;
        color: #51cf66;
      }
      
      .order-delivery {
        font-size: 14px;
        color: #6c757d;
      }
      
      .order-items {
        margin: 15px 0;
        border-top: 1px solid #e9ecef;
        padding-top: 15px;
      }
      
      .order-item {
        display: flex;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f3f5;
      }
      
      .order-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
      }
      
      .item-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 5px;
        margin-right: 15px;
      }
      
      .item-details {
        flex: 1;
      }
      
      .item-name {
        font-weight: bold;
        margin-bottom: 5px;
      }
      
      .item-price {
        color: #495057;
      }
      .item-quantity {
        color: #6c757d;
        font-size: 14px;
      }
      
      .item-subtotal {
        text-align: right;
        font-weight: bold;
      }
      
      .order-address {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin: 15px 0;
      }
      
      .address-title {
        font-weight: bold;
        margin-bottom: 5px;
      }
      
      .order-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e9ecef;
      }
      
      .btn {
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
      }
      
      .btn-primary {
        background: #51cf66;
        color: white;
      }
      
      .btn-primary:hover {
        background: #40c057;
      }
      
      .btn-secondary {
        background: #e9ecef;
        color: #495057;
      }
      
      .btn-secondary:hover {
        background: #dee2e6;
      }
      
      .btn-danger {
        background: #ff6b6b;
        color: white;
      }
      
      .btn-danger:hover {
        background: #fa5252;
      }
      
      .btn-disabled {
        background: #e9ecef;
        color: #adb5bd;
        cursor: not-allowed;
      }
      
      .order-progress {
        display: flex;
        justify-content: space-between;
        margin: 20px 0;
        position: relative;
      }
      
      .progress-step {
        flex: 1;
        text-align: center;
        position: relative;
      }
      
      .step-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 5px;
        position: relative;
        z-index: 2;
      }
      
      .step-active .step-icon {
        background: #51cf66;
        color: white;
      }
      
      .step-completed .step-icon {
        background: #51cf66;
        color: white;
      }
      
      .step-label {
        font-size: 12px;
        color: #6c757d;
      }
      
      .step-active .step-label {
        color: #51cf66;
        font-weight: bold;
      }
      
      .step-completed .step-label {
        color: #51cf66;
      }
      
      .progress-bar {
        position: absolute;
        top: 15px;
        left: 15%;
        right: 15%;
        height: 2px;
        background: #e9ecef;
        z-index: 1;
      }
      
      .progress-completed {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        background: #51cf66;
        transition: width 0.3s ease;
      }
      
      .order-notes {
        font-style: italic;
        color: #6c757d;
        margin-top: 10px;
      }
      
      .order-contact {
        margin-top: 15px;
        font-size: 14px;
      }
      
      .contact-seller {
        color: #51cf66;
        text-decoration: none;
        font-weight: bold;
      }
      
      .contact-seller:hover {
        text-decoration: underline;
      }
      
      @media (max-width: 768px) {
        .order-summary {
          flex-direction: column;
        }
        
        .order-total {
          text-align: left;
          margin-top: 10px;
        }
        
        .order-progress {
          overflow-x: auto;
          padding-bottom: 10px;
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
          <h1 class="title">My Orders</h1>
        </section>

        <p style="text-align: center; color: #666; font-size: 16px; margin: -1rem 0 1rem 0; width: 100%; display: block;">Track and manage your purchases</p>

        <!-- Display Messages -->
        <?php if (!empty($success_message)): ?>
          <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
          <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Search Bar Section -->
        <section class="search-section">
          <div class="search-label">Search for Order ID, Seller or Product</div>
          <form method="GET" action="" class="search-bar">
            <input type="search" name="search" class="search-input" value="<?php echo htmlspecialchars($search_query ?? ''); ?>" placeholder="Enter order ID, seller name or product...">
            <button type="submit" class="search-button">Search</button>
            <?php if (!empty($search_query)): ?>
              <a href="my-orders.php" style="margin-left: 10px; color: #666; text-decoration: none;">Clear</a>
            <?php endif; ?>
          </form>
        </section>

        <section class="section-title">
          <h2 class="produce-title">
            <?php if ($status_counts['total'] > 0): ?>
              You have ordered <?php echo $status_counts['total']; ?> item<?php echo $status_counts['total'] != 1 ? 's' : ''; ?>
            <?php else: ?>
              You haven't placed any orders yet
            <?php endif; ?>
          </h2>
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

        <!-- Order Card SECTION AND GRID -->
        <section class="produce-listing-section">
          <?php if (empty($my_orders)): ?>
            <div class="no-orders">
              <h3>No orders found</h3>
              <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
              <a href="home.php">
                <button class="btn btn-primary" style="margin-top: 15px;">Start Shopping</button>
              </a>
            </div>
          <?php else: ?>
            <?php foreach ($my_orders as $order): ?>
              <div class="order-card" data-status="<?php echo htmlspecialchars($order['status']); ?>">
                <div class="order-header">
                  <div class="order-id">Order #<?php echo $order['id']; ?></div>
                  <div class="order-date"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></div>
                </div>
                
                <div class="order-content">
                  <div class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                  </div>
                  
                  <div class="order-summary">
                    <div class="order-seller">
                      <div class="order-seller-name">
                        Seller: <?php echo htmlspecialchars($order['seller_first_name'] . ' ' . $order['seller_last_name']); ?>
                      </div>
                      <div class="order-seller-location">
                        Location: <?php echo htmlspecialchars($order['seller_location'] ?? ''); ?>
                      </div>
                    </div>
                    
                    <div class="order-total">
                      <div class="order-price">$<?php echo number_format($order['total_price'], 2); ?></div>
                      <div class="order-delivery">
                        <?php if ($order['delivery_cost'] > 0): ?>
                          Includes $<?php echo number_format($order['delivery_cost'], 2); ?> delivery
                        <?php else: ?>
                          Free delivery
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Order Progress Tracker -->
                  <div class="order-progress">
                    <div class="progress-bar">
                      <div class="progress-completed" style="width: 
                        <?php 
                          switch($order['status']) {
                            case 'pending': echo '0%'; break;
                            case 'confirmed': echo '33%'; break;
                            case 'shipped': echo '66%'; break;
                            case 'delivered': echo '100%'; break;
                            case 'cancelled': echo '0%'; break;
                            default: echo '0%';
                          }
                        ?>
                      "></div>
                    </div>
                    
                    <div class="progress-step <?php echo in_array($order['status'], ['pending', 'confirmed', 'shipped', 'delivered']) ? 'step-completed' : ''; ?> <?php echo $order['status'] == 'pending' ? 'step-active' : ''; ?>">
                      <div class="step-icon">1</div>
                      <div class="step-label">Ordered</div>
                    </div>
                    
                    <div class="progress-step <?php echo in_array($order['status'], ['confirmed', 'shipped', 'delivered']) ? 'step-completed' : ''; ?> <?php echo $order['status'] == 'confirmed' ? 'step-active' : ''; ?>">
                      <div class="step-icon">2</div>
                      <div class="step-label">Confirmed</div>
                    </div>
                    
                    <div class="progress-step <?php echo in_array($order['status'], ['shipped', 'delivered']) ? 'step-completed' : ''; ?> <?php echo $order['status'] == 'shipped' ? 'step-active' : ''; ?>">
                      <div class="step-icon">3</div>
                      <div class="step-label">Shipped</div>
                    </div>
                    
                    <div class="progress-step <?php echo $order['status'] == 'delivered' ? 'step-completed step-active' : ''; ?>">
                      <div class="step-icon">4</div>
                      <div class="step-label">Delivered</div>
                    </div>
                  </div>
                  
                  <!-- Order Items -->
                  <div class="order-items">
                    <h3>Order Items (<?php echo count($order['items']); ?>)</h3>
                    
                    <?php foreach ($order['items'] as $item): ?>
                      <div class="order-item">
                        <img src="<?php echo !empty($item['image']) ? htmlspecialchars($item['image']) : 'https://placehold.co/60x60'; ?>" alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" class="item-image">
                        
                        <div class="item-details">
                          <div class="item-name"><?php echo htmlspecialchars($item['name'] ?? ''); ?></div>
                          <div class="item-price">$<?php echo number_format($item['price'], 2); ?> each</div>
                          <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                        </div>
                        
                        <div class="item-subtotal">
                          $<?php echo number_format($item['subtotal'], 2); ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  
                  <!-- Delivery Address -->
                  <div class="order-address">
                    <div class="address-title">Delivery Address:</div>
                    <?php echo nl2br(htmlspecialchars($order['delivery_address'] ?? '')); ?>
                    
                    <div class="order-contact">
                      <strong>Contact:</strong> <?php echo htmlspecialchars($order['phone_number'] ?? ''); ?>
                    </div>
                    
                    <?php if (!empty($order['notes'])): ?>
                      <div class="order-notes">
                        <strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  
                  <!-- Order Actions -->
                  <div class="order-actions">
                    <a href="mailto:<?php echo htmlspecialchars($order['seller_email'] ?? ''); ?>" class="btn btn-secondary">
                      Contact Seller
                    </a>
                    
                    <?php if ($order['status'] == 'shipped'): ?>
                      <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="confirm_delivery">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Confirm that you have received this order?')">
                          Confirm Delivery
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] == 'pending' || $order['status'] == 'confirmed'): ?>
                      <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this order?')">
                          Cancel Order
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] == 'delivered'): ?>
                      <button class="btn btn-primary" onclick="window.location.href='home.php'">
                        Order Again
                      </button>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] == 'cancelled'): ?>
                      <button class="btn btn-disabled" disabled>
                        Order Cancelled
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
        
        <!-- Order Tips Section -->
        <section style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 30px; margin-bottom: 30px;">
          <h3 style="margin-top: 0;">Order Tips</h3>
          <ul style="padding-left: 20px; line-height: 1.6;">
            <li><strong>Pending:</strong> Your order has been placed and is awaiting confirmation from the seller.</li>
            <li><strong>Confirmed:</strong> The seller has confirmed your order and is preparing it for shipping.</li>
            <li><strong>Shipped:</strong> Your order is on the way! You'll be able to confirm delivery when it arrives.</li>
            <li><strong>Delivered:</strong> Your order has been delivered successfully.</li>
            <li><strong>Cancelled:</strong> The order has been cancelled.</li>
          </ul>
          <p style="margin-top: 15px;">
            Need help with your order? <a href="mailto:support@farm2door.com" style="color: #51cf66; text-decoration: none;">Contact our support team</a>.
          </p>
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
          document.querySelectorAll('.order-card').forEach(function(card) {
            if (status === 'all' || card.getAttribute('data-status') === status) {
              card.style.display = 'block';
            } else {
              card.style.display = 'none';
            }
          });
        });
      });
      
      // Expand/collapse order details
      document.addEventListener('DOMContentLoaded', function() {
        var orderHeaders = document.querySelectorAll('.order-header');
        
        orderHeaders.forEach(function(header) {
          header.addEventListener('click', function() {
            var content = this.nextElementSibling;
            
            if (content.style.maxHeight) {
              content.style.maxHeight = null;
            } else {
              content.style.maxHeight = content.scrollHeight + 'px';
            }
          });
        });
      });
    </script>
  </body>
</html>
