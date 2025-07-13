<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>User Search</title>
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

        <!-- Search Section: This section is outside the main tag because of the background image CSS style. The Main tag has a max width applied to it so it can't be inside it. -->
        <section class="user-search-section">
          <h1 class="title-white">Your search results...</h1>

          <!-- Search Bar -->
          <div class="home-search-bar">
            <input type="search" class="search-input">
            <button class="search-button">Search</button>
          </div>
        </section>


        <!-- USER SEARCH MAIN CONTAINER -->
        <main class="user-search-main-section">

          <!-- USER SEARCH FILTER SECTION -->
          <section class="user-search-filters-wrapper">
            <h5>Filters</h5>

            <!-- USER SEARCH FILTERS WRAPPER -->
            <div class = "filter-large-wrapper">
              <div class="filter-group">
                <label>Price Range</label>
                <div class="price-slider-container">
                    <input type="range" id="min-price-slider" min="0" max="1000" step="10" value="100">
                    <input type="range" id="max-price-slider" min="0" max="1000" step="10" value="900">
                </div>
                <div class="price-values">
                    <input type="number" id="min-price" min="0" max="1000" value="100">
                    <input type="number" id="max-price" min="0" max="1000" value="900">
                </div>
              </div>

              <div class="filter-group">
                  <label for="quantity">Quantity Needed</label>
                  <input type="number" id="quantity" min="1" max="100">
              </div>
              <div class="filter-group">
                  <label for="location">Location</label>
                  <select id="location">
                    <option value="Location">Location</option>
                    <option value="Greater Accra">Greater Accra</option>
                    <option value="Ashanti">Ashanti</option>
                    <option value="Western">Western</option>
                    <option value="Central">Central</option>
                    <option value="Eastern">Eastern</option>
                    <option value="Volta">Volta</option>
                    <option value="Northern">Northern</option>
                    <option value="Savannah">Savannah</option>
                    <option value="Upper East">Upper East</option>
                    <option value="Upper West">Upper West</option>
                  </select>
              </div>

              <div class="filter-group">
                  <label for="category">Category</label>
                  <select id="category">
                      <option value="">Select Category</option>
                      <option value="fruits">Fruits</option>
                      <option value="vegetables">Meat & Eggs</option>
                      <option value="meat">Dairy Products</option>
                      <option value="meat">Cereals</option>
                      <option value="meat">Oils</option>
                      <option value="meat">Fish</option>
                      <option value="meat">Miscellaneous</option>
                  </select>
              </div>

              <div class="filter-group">
                  <label for="availability">Availability</label>
                  <select id="availability">
                      <option value="">Select Availability</option>
                      <option value="in-stock">In Stock</option>
                      <option value="out-of-stock">Out of Stock</option>
                  </select>
              </div>
            </div>

            <button class="user-search-seller-button">Apply Filters</button>
          </section>






          <!-- SEARCH RESULTS FOR PRODUCE SECTION -->
          <section class="produce-listing-section">
            <h3 class="user-search-results-text">500+ products found</h3>

            <!-- USER SEARCH RESULTS GRID -->
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
