# Farm2Door - XAMPP PHP Web Application

## Build/Test Commands
- **Start server**: Run XAMPP and access via `http://localhost/farm/`
- **Database**: MySQL via phpMyAdmin at `http://localhost/phpmyadmin/`
- **No automated tests**: Manual testing required via browser
- **Syntax check**: `php -l filename.php` for individual files

## Architecture & Structure
- **Database**: MySQL (db: `farm`) with auto-created tables via SQL in PHP files
- **Authentication**: Session-based with `$_SESSION['user_id']` and redirect protection
- **Config**: Database connection in `config_files/config.php` using `createConnection()`
- **Tables**: users, products, cart, orders, order_items, notifications
- **User types**: buyer/seller with role-based functionality

## Code Style & Conventions
- **File structure**: Root-level PHP pages, config in `config_files/`, styles in `styles/`
- **Database**: MySQLi with prepared statements for security
- **Error handling**: Display errors enabled, `$success_message` and `$error_message` variables
- **Sessions**: Start with `session_start()` at top of protected pages
- **Redirects**: Use `header('Location: page.php'); exit();` pattern
- **SQL**: CREATE TABLE IF NOT EXISTS, foreign keys, proper constraints
- **HTML**: Semantic structure with CSS classes, form validation
- **JavaScript**: Inline and external scripts for dynamic functionality
- **Naming**: snake_case for variables/database, kebab-case for CSS classes
