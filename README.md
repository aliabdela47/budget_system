# Budget System

![Budget System Banner](https://via.placeholder.com/1280x400?text=Budget+System+-%20Efficient+Financial+Tracking) <!-- Replace with actual banner image -->

[![GitHub License](https://img.shields.io/github/license/aliabdela47/budget-system?color=blue)](https://github.com/aliabdela47/budget-system/blob/main/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/aliabdela47/budget-system?style=social)](https://github.com/aliabdela47/budget-system/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/aliabdela47/budget-system?style=social)](https://github.com/aliabdela47/budget-system/network)
[![PHP Version](https://img.shields.io/badge/php-%3E=7.4-blueviolet)](https://php.net)
[![Database](https://img.shields.io/badge/database-MySQL-orange)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/bootstrap-5.3-purple)](https://getbootstrap.com)

A comprehensive, secure, and modern web-based budget management application tailored for organizations like the Afar Regional Health Bureau. Built with PHP and MySQL, it integrates Ethiopian calendar support, transaction tracking, fuel management for vehicles, and role-based access control. Designed for seamless offline use with localized resources, it ensures efficient financial oversight and vehicle fuel allocation.

## Table of Contents
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Usage](#usage)
- [Screenshots](#screenshots)
- [Contributing](#contributing)
- [License](#license)
- [Contact](#contact)

## Features
- **Dashboard & Sidebar Navigation**: Intuitive sidebar for quick access to modules like Dashboard, Budget Adding, Settings (Owners/Codes), Transactions, Fuel Management, and Users Management. Responsive toggle for mobile views.
- **Budget Management**: Admin-only module to add, edit, or delete yearly/monthly budgets with Ethiopian months/quarters (e.g., ሐምሌ, ነሐሴ). Automatic validation and remaining balance calculations (monthly, quarterly, yearly).
- **Transactions**: Record and manage transactions with budget deduction validation. Supports zero-amount entries, role-based access, and dynamic remaining balance display.
- **Fuel Management**: Dedicated vehicle fuel tracking with searchable plate number dropdown (from vehicles database), auto-population of last gauge, journey calculations, and automatic deduction from specific fuel budget (code 6217 - Sansii kee Sukutih).
- **Vehicle Information**: Integrated database for vehicle details (model, plate no., chassis no.), enabling efficient fuel transaction history and gauge tracking.
- **Role-Based Access**: Admins manage budgets, settings, and users; officers have read-only or limited access in key modules.
- **Ethiopian Calendar Integration**: Custom functions for Ethiopian months and quarters, with auto-mapping from Gregorian dates.
- **Offline Compatibility**: Localized CSS/JS (Bootstrap, Font Awesome, Roboto fonts) for full functionality without internet. Works seamlessly in localhost environments.
- **Security & Validation**: CSRF protection, session management, duplicate prevention, and error handling for reliable operations.
- **Responsive Design**: Modern, stylish UI with Bootstrap, animations, and mobile optimization.

## Tech Stack
- **Backend**: PHP 7.4+ with PDO for secure database interactions, MySQL for data storage.
- **Frontend**: Bootstrap 5.3 for responsive UI, Font Awesome 6.4 for icons, Select2 for searchable dropdowns, Flatpickr for date pickers (localized).
- **Other**: Ethiopian calendar logic, vehicle and budget tables, offline resource localization.

## Installation
1. **Clone the Repository**:
   ```
   git clone https://github.com/aliabdela47/budget-system.git
   cd budget-system
   ```

2. **Set Up Database**:
   - Create a MySQL database (e.g., `budget_db`).
   - Import the schema from `database.sql` (if available) or run the provided CREATE TABLE queries for budgets, transactions, fuel_transactions, vehicles, budget_owners, and budget_codes.

3. **Configure Database Connection**:
   - Edit `includes/db.php` with your credentials:
     ```php
     $host = '127.0.0.1';
     $dbname = 'budget_db';
     $user = 'root';
     $pass = '';
     ```

4. **Localize Resources** (for Offline Use):
   - Download and place Bootstrap, Font Awesome, and Roboto fonts in `css/`, `js/`, and `fonts/` folders as described in the documentation.

5. **Run Locally**:
   - Start a local server (e.g., XAMPP, WAMP) with Apache and MySQL.
   - Access via `http://localhost/budget-system/index.php`.

## Database Setup
Run these SQL queries to create the tables:

- **budgets**:
  ```sql
  CREATE TABLE budgets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      owner_id INT,
      code_id INT,
      adding_date DATETIME DEFAULT CURRENT_TIMESTAMP,
      year INT,
      yearly_amount DECIMAL(15,2) DEFAULT 0.00,
      month VARCHAR(20),
      monthly_amount DECIMAL(15,2) DEFAULT 0.00,
      quarter INT,
      remaining_yearly DECIMAL(15,2) DEFAULT 0.00,
      remaining_monthly DECIMAL(15,2) DEFAULT 0.00,
      remaining_quarterly DECIMAL(15,2) DEFAULT 0.00
  );
  ```

- **transactions**:
  ```sql
  CREATE TABLE transactions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      owner_id INT,
      code_id INT,
      employee_name VARCHAR(100),
      ordered_by VARCHAR(100),
      reason MEDIUMTEXT,
      created_by VARCHAR(100),
      amount DECIMAL(15,2),
      date DATETIME DEFAULT CURRENT_TIMESTAMP,
      et_month VARCHAR(20),
      quarter INT,
      remaining_month DECIMAL(15,2),
      remaining_quarter DECIMAL(15,2),
      remaining_year DECIMAL(15,2)
  );
  ```

- **fuel_transactions**:
  ```sql
  CREATE TABLE fuel_transactions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      owner_id INT,
      driver_name VARCHAR(100),
      plate_number VARCHAR(50),
      previous_gauge DECIMAL(15,2),
      current_gauge DECIMAL(15,2),
      journey_distance DECIMAL(15,2),
      fuel_price DECIMAL(15,2),
      refuelable_amount DECIMAL(15,2),
      total_amount DECIMAL(15,2),
      new_gauge DECIMAL(15,2),
      gauge_gap DECIMAL(15,2),
      date DATETIME DEFAULT CURRENT_TIMESTAMP
  );
  ```

- **vehicles**:
  ```sql
  CREATE TABLE vehicles (
      id INT AUTO_INCREMENT PRIMARY KEY,
      model VARCHAR(50),
      plate_no VARCHAR(50) NOT NULL UNIQUE,
      chassis_no VARCHAR(50)
  );

  -- Insert vehicle data (from your list)
  INSERT INTO vehicles (model, plate_no, chassis_no) VALUES
  ('ሀይሎክስ', '4-01265', 'AHTKB8CD60298739'),
  ('ሀይሎክስ', '4-01264', 'AHTKB8CD302987832'),
  ('ሀይሎ ክች', '4-01263 AF', 'AHTKB8CD702987834'),
  ('ሀይሎክስ', '4-01274 AF', NULL),
  ('ሀይሎክስ', '4-01275 AF', NULL),
  ('ሀይሎክስ', '4-01203', NULL),
  ('ሀይሎክስ', '4-01283 AF', 'JTEEB71J30F029582'),
  ('ሀይሎክስ', '4-01198 AF', 'JTEEB71J6014901'),
  ('ሀይሎክስ', '4-00471 AF', NULL),
  ('ሀይሎክስ', '4-1237 AF', 'JTEEB71J30F026486'),
  ('ላንድክሩዘር', '4-1238 AF', 'JTEEB71J0F026485'),
  ('ሃይሎክች', '4-01066 AF', 'JTRB71j00F006200'),
  ('ላንድክሩዘር', '4-01299 AF', 'AHTFR22G006106874'),
  ('', '4-01302 AF', 'JTEBH9FJ80K198638'),
  ('ላንድክሩዘር', '4-01301 AF', 'JTEEB71J9041570'),
  ('', '4-01184 AF', 'AHTKB8CD702984139'),
  ('ላንድክሩዘር', '4-01250 AF', 'JTEEB71J30F024351'),
  ('', '4-01248 AF', 'JTEEB71J00F024291'),
  ('ላንድክሩዘር', '4-01249 AF', 'JTEEB71J70F024322'),
  ('አምቡላንስ', '4-01068 AF', 'JTERB71J70F004847'),
  ('አምቡላንስ', '4-01065 AF', 'JTERB71J20F005145'),
  ('', '4-01303', NULL),
  ('ሃርድቶፕ', '4-01247 AF', 'JTEEB71J60F024294'),
  ('ሃርድቶፕ', '4-01246 AF', 'JTEEB71J70F024319'),
  ('ሃርድቶፕ', '4-01245 AF', 'JTEEB71J30F024317'),
  ('ሃርድቶፕ', '4-01244 AF', 'JTEEB71J30F024298'),
  ('ሃርድቶፕ', '4-01243 AF', 'JTEEB71J00F024324'),
  ('ሃርድቶፕ', '4-01241 AF', 'JTEEB7160F024313'),
  ('ሃርድቶፕ', '4-01242 AF', 'JTEEB71J10F024350'),
  ('ሃርድቶፕ', '4-01203 AF', 'JTEEB71J00F018018524'),
  ('ሃርድቶፕ', '4-00910 AF', 'JTEEB71J607040487'),
  ('ሃርድቶፕ', '4-00912 AF', 'JTEEB71J507040562'),
  ('ላንድክሩዘር', '4-05621', 'JTEEB71J907031119'),
  ('ቶያታ ሃርድቶፕ', '4-32792', 'JTEEB71J0014550'),
  ('ቶያታ ሃርድቶፕ', '4-01259 AF', 'JTERB71X0F024221'),
  ('ሃርድቶፕ', '4-00903 AF', 'JTEEB71J307043198'),
  ('ሃርድቶፕ', '4-00909 AF', 'JTEEB71J507040559'),
  ('ሃርድቶፕ', '4-00906 AF', 'JTEEB71307043220'),
  ('ሃርድቶፕ', '4-00908 AF', 'JTEEB71J507040643'),
  ('ሃርድቶፕ', '4-00911 AF', 'JTEEB71J607040540'),
  ('ሃርድቶፕ', '4-00904 AF', 'JTEEB7J307043248'),
  ('ሃርድቶፕ', '4-00907 AF', 'JTEEB71J507043199'),
  ('ሃርድቶፕ', '4-00905 AF', 'JTEEB71507043168'),
  ('ሃርድቶፕ', '4-00902 AF', 'JTEEB71J407043226');
  ```

## Usage
- Run the SQL above to set up the database.
- Log in as admin to add budgets.
- Use the Transaction module to record expenditures.
- Manage fuel for vehicles in Fuel Management, with auto-deduction from the fuel budget.

## Screenshots
![Sidebar Navigation](https://via.placeholder.com/800x400?text=Sidebar+Navigation+Screenshot) <!-- Replace with actual screenshot -->
![Budget Adding](https://via.placeholder.com/800x400?text=Budget+Adding+Screenshot)
![Fuel Management](https://via.placeholder.com/800x400?text=Fuel+Management+Screenshot)

## Contributing
Fork the repo, make your changes, and submit a pull request. For bugs or suggestions, open an issue.

## License
MIT License - see [LICENSE](LICENSE) for details.

## Contact
- GitHub: [aliabdela47](https://github.com/aliabdela47)
- LinkedIn: [aliabdela](https://linkedin.com/in/aliabdela)
- Website: [ali.et](https://ali.et)
