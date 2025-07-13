<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Product Detail</title>
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
          <h1 class="title">Product Details</h1>
        </section>

        <!-- Search Bar Section -->
        <section class="search-section">
          <div class="search-label">Search for something else?</div>
          <div class="search-bar">
            <input type="text" class="search-input">
            <button class="search-button">Search</button>
          </div>
        </section>

        <!-- PRODUCT DETAIL CONTAINER -->
        <section class="product-detail-container">

          <!-- PRODUCT DETAIL LEFT IMAGE CONTAINER -->

          <div class="product-detail-carousel">
            <div class="carousel-slide active">
              <img src="https://placehold.co/536x536?text=Image+1" alt="Product Detail Image 1">
            </div>
            <div class="carousel-slide">
              <img src="https://placehold.co/536x536?text=Image+2" alt="Product Detail Image 2">
            </div>
            <div class="carousel-slide">
              <img src="https://placehold.co/536x536?text=Image+3" alt="Product Detail Image 3">
            </div>
            
            <!-- Navigation arrows -->
            <button class="carousel-prev">←</button>
            <button class="carousel-next">→</button>
          </div>






          <!-- PRODUCT DETAIL RIGHT DETAILS CONTAINER -->
          <div class="product-detail-right-container">
            <div class="product-detail-info-wrapper">
              <div class="product-detail-name-pricing-wrapper">
                <div class="product-detail-name">Product Name</div>
                <div class="product-detail-price-quantity">Price/Qty</div>
              </div>
              <div class="product-detail-quantity">Available Qty: X </div>
              <div class="product-detail-quantity-select-bar">
                <body>Qty</body>
                <input type="number" class="product-detail-quantity-input">
                <button class="product-detail-quantity-button">Select</button>
              </div>
              <div class="product-detail-delivery-and-button">
                <div class="product-detail-delivery-time">Estimated Delivery Time: 3 days</div>
                <div class="product-detail-button-wrapper">
                  <button class="product-detail-buy-now-button">Buy Now</button>
                  <button class="product-detail-add-to-cart-button"><div class="text-white"> Add to Cart </div></button>
                </div>
              </div>
            </div>

            <!-- PRODUCT DETAIL ABOUT US CONTAINER -->
            <div class="product-detail-about-us-wrapper">
              <h6>About Seller</h6>
              <div class="product-detail-seller-details">
                <div class="product-detail-seller-image">
                  <img src="https://placehold.co/30x30" alt="Product Detail Image">
                </div>
                <div class="product-detail-seller-name-city-wrapper">
                  <div class="product-detail-seller-name">Lorem Ipsum</div>
                  <div class="product-detail-seller-city">City, Region</div>
                </div>
              </div>
              <div class="product-detail-seller-phone-number">Phone No: 029 238 2981</div>
              <button class="product-detail-seller-button">Shop</button>
            </div>
          </div>
        </section>



            <!-- PRODUCT DETAIL MORE ABOUT PRODUCT CONTAINER -->
            <section class="product-detail-more-about-product">
              <h3 style="text-align: center;">More about the product</h3>
              <div class="product-detail-more-about-product-text">In hac habitasse platea dictumst. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Nulla facilisi. Mauris sollicitudin fermentum libero. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. </div>
            </section>

            <!-- PRODUCT DETAIL LISTING SECTION -->
            <section class="produce-listing-section">
              <h2 class="produce-title">Similar Products</h2>

              <!-- Latest Produce Grid -->
              <div class="produce-grid">

                <div class="produce-listing-card">
                  <img src="https://placehold.co/340x180" alt="Product Image" class="produce-image">
                  <div class="card-body">
                    <div class="card-info">
                      <div class="product-name">Product Name</div>
                      <div class="product-price">Price/Qty</div>
                      <div class="product-location">City, Region</div>
                    </div>
                    <div class="quantity-selector-action">
                      <label class="qty-label">Qty:</label>
                      <input type="number" class="qty-input">
                      <button class="qty-select-button">Select</button>
                    </div>
                  </div>
                  <button class="add-cart-button">Add to Cart</button>
                </div>

                <div class="produce-listing-card">
                  <img src="https://placehold.co/340x180" alt="Product Image" class="produce-image">
                  <div class="card-body">
                    <div class="card-info">
                      <div class="product-name">Product Name</div>
                      <div class="product-price">Price/Qty</div>
                      <div class="product-location">City, Region</div>
                    </div>
                    <div class="quantity-selector-action">
                      <label class="qty-label">Qty:</label>
                      <input type="number" class="qty-input">
                      <button class="qty-select-button">Select</button>
                    </div>
                  </div>
                  <button class="add-cart-button">Add to Cart</button>
                </div>

              </div>
          </section>





          </div>
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
          <a href="sign-in.php" class="nav-item">Log Out</a>
        </div>
        <div class="footer-brand">
          <h2 class="brand-name">FARM2DOOR.COM</h2>
          <p class="brand-tagline">Leveraging innovative e-commerce technology to solve food problems</p>
        </div>
      </footer>
        
    
    </div>
    <script src="scripts/script.js"></script>
    <script src="scripts/image-carousel.js"></script>
  </body>
</html>