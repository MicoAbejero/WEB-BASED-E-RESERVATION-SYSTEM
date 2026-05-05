## E-Reserve for Crochet Flowers - System Definition & Features

### System Definition

**E-Reserve for Crochet Flowers** is a web-based reservation and order management system designed for a crochet flower shop.  
It allows customers to browse products, add items to cart, reserve orders with pickup dates, and track reservation status, while admins manage products, customers, reservations, and pickup schedules.

---

### Core Features

#### 1. User Management

- Customer registration and login
- Admin login and dashboard access
- Profile management for both customer and admin

#### 2. Product Management

- Admin can add, edit, and delete products
- Product details include name, category, description, price, stock, and image
- Product availability and stock tracking
- Product variations support (e.g., size/quantity options)

#### 3. Cart and Reservation Workflow

- Customers add products to cart
- Customers select pickup date and submit reservation
- Reservation statuses: `pending`, `confirmed`, `completed`, `cancelled`
- Automatic stock deduction on reservation and stock restoration on cancellation

#### 4. Pickup Date & Calendar Management

- Customer pickup calendar to view reservation pickup schedules
- Admin pickup calendar to monitor daily capacity and reservations
- Date-based reservation limits (custom max reservations per day)

#### 5. Reservation Tracking

- Customers can view and track their reservation history
- Admin can review all reservations and update status
- Reservation update tracking includes updater role and timestamp

#### 6. Live Notifications / Data Updates

- Dashboard and reservation data can be fetched dynamically via API endpoints
- Recent activity and status updates visible in admin and customer panels

#### 7. Data Integrity Improvements

- Reservation history is preserved even if a product is deleted
- Snapshot fields store product name/image at reservation time
- Reservation pages use fallback logic so old records remain visible

#### 8. Role-based Access Control (RBAC)

- Enforces access by user role (`admin` and `customer`)
- Protects admin-only modules (product/customer/reservation management)
- Prevents customers from accessing administrative pages and actions
- Supports secure session-based authorization checks on protected routes
- Improves overall system security and permission control

---

### Main System Roles

#### Customer

- Browse products
- Add to cart
- Reserve orders
- Select pickup date
- Track reservation status

#### Admin

- Manage customers
- Manage products and variations
- Manage reservation statuses
- Control pickup-date limits and monitor schedule

---

### Technology Stack (Current Project)

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Environment:** XAMPP / Apache + MySQL
