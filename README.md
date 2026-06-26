# E-Commerce Platform (Vue + Laravel)

This project is a full-stack e-commerce platform built with **Laravel**, **Vue.js**, and **Inertia.js**. It features a public storefront for customers to browse products, manage their carts, and place orders, alongside a secure, role-based Content Management System (CMS) for administrators to manage users, products, and orders.

## 🚀 Features Implemented

### 1. Authentication & Authorization
* **Secure Login & Registration**: Standard authentication using Laravel Sanctum.
* **Role-Based Access Control (RBAC)**: Custom middleware to enforce role-based access (`admin` vs `guest`).
* **Account Lockout**: Security measure that locks out users after 5 failed login attempts for 5 minutes.
* **Account Deactivation**: Admins can toggle guest accounts between active and deactivated states.

### 2. Storefront (Customer Facing)
* **Product Catalog**: Publicly accessible product listing with pagination.
* **Shopping Cart System**: Authenticated users can add products to their cart, adjust quantities, and remove items. The cart accurately tracks stock availability.
* **Checkout Flow**: Seamless conversion of cart items into orders using transactional database operations to prevent overselling.
* **Order History**: Customers can view their past orders and order statuses.

### 3. CMS / Admin Dashboard (Admin Facing)
* **User Management**: Admins can view, search, create, update, and deactivate users.
* **Product Management**: Full CRUD operations for the product catalog, including uploading local images or linking to external image URLs, price management, and stock tracking.
* **Order Management**: Admins can view all customer orders, inspect line items, and update order statuses (Pending, For Delivery, Delivered, Canceled).

### 4. Code Quality & CI/CD
* **Static Analysis**: The project is strictly typed and conforms to **PHPStan Level 7**. Type coverage includes all Eloquent Models, Controllers, Requests, and generic annotations for Eloquent Relationships.
* **Linting & Formatting**: Configured with ESLint and Prettier for consistent frontend code quality. Unused variables and imports have been eliminated.
* **Dependency Management**: Platform-locked composer dependencies (`config.platform.php: 8.3.31`) to ensure parity between local development and CI/CD pipelines, explicitly locking Symfony dependencies to `v7.4.x`.

## 🛠️ Tech Stack

* **Backend**: Laravel 11, PHP 8.3, SQLite
* **Frontend**: Vue 3 (Composition API), Inertia.js, TailwindCSS, shadcn-vue components
* **Quality Assurance**: PHPStan (Larastan), Pest PHP (Testing), ESLint, GitHub Actions

## 📦 Local Development Setup

1. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database Setup & Seeding**
   ```bash
   php artisan migrate --seed
   ```
   *The seeder will create a default admin user and several mock products to get started.*

4. **Run Development Servers**
   In one terminal:
   ```bash
   php artisan serve
   ```
   In a second terminal:
   ```bash
   npm run dev
   ```

## 🧪 Testing and Static Analysis

* **Run Pest Feature Tests:**
  ```bash
  php artisan test
  ```
* **Run PHPStan Static Analysis:**
  ```bash
  composer types:check
  ```
* **Run ESLint Frontend Linter:**
  ```bash
  npm run lint
  ```
  
* **Admin Account:**
    Username: admin@purplebug.com
    Password: Admin@12345
