-- schema.sql - MySQL schema for admin panel

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    theme ENUM('light', 'dark') DEFAULT 'light',
    twofa_enabled TINYINT(1) DEFAULT 0,
    twofa_secret VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255),
    smtp_port VARCHAR(10),
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(255) NOT NULL DEFAULT '',
    smtp_user VARCHAR(100),
    smtp_pass VARCHAR(100),
    smtp_secure VARCHAR(10)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) UNIQUE NOT NULL,
  role_desc VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(100) UNIQUE NOT NULL,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
  UNIQUE KEY unique_role_perm (role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100)        NOT NULL,     -- e.g. "Users"
  description VARCHAR(255)   NOT NULL,     -- e.g. "Manage Users"
  icon_class VARCHAR(100)    NOT NULL,     -- e.g. "fas fa-users"
  box_color VARCHAR(50)      NOT NULL,     -- e.g. "bg-info" or "bg-warning"
  link VARCHAR(255)          NOT NULL,     -- e.g. "users.php"
  permission_key VARCHAR(100) NOT NULL,    -- e.g. "user.manage"
  sort_order INT             NOT NULL DEFAULT 0,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active BOOLEAN          NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  dob DATE NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  notes TEXT,
  email VARCHAR(150) NOT NULL,
  address VARCHAR(255),
  city VARCHAR(100),
  gender ENUM('Male','Female','Other') NOT NULL,
  comments TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_client_person (first_name, last_name, dob, mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vat_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  vat_percent DECIMAL(5,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_vat_percent (vat_percent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- 1) Database schema for product_category table

CREATE TABLE IF NOT EXISTS product_category (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) Database schema for products table

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  purchase_price DECIMAL(10,2) NOT NULL,
  purchase_vat_type_id INT NOT NULL,
  sell_price DECIMAL(10,2) NOT NULL,
  sell_vat_type_id INT NOT NULL,
  quantity_stock INT NOT NULL DEFAULT 0,
  quantity_in_box INT NOT NULL DEFAULT 1,
  comment TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES product_category(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (purchase_vat_type_id) REFERENCES vat_types(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (sell_vat_type_id) REFERENCES vat_types(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) Database schema for service_categories table

CREATE TABLE IF NOT EXISTS service_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  color CHAR(7) NOT NULL,           -- store hex code, e.g. "#ff0000"
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_service_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) Database schema for services table

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category_id INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  vat_type_id INT NOT NULL,
  duration INT NOT NULL DEFAULT 0,
  waiting INT NOT NULL DEFAULT 0,
  gender ENUM('Male','Female','Both') NOT NULL DEFAULT 'Both',
  comment TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_service_name_category_gender (name, category_id, gender),
  FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (vat_type_id) REFERENCES vat_types(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricelist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pricelist_category_name (category_id, name),
  FOREIGN KEY (category_id) REFERENCES service_categories(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS therapists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  mobile VARCHAR(20),
  dob DATE,
  level ENUM('Therapist','Reception') NOT NULL DEFAULT 'Therapist',
  color CHAR(7) NOT NULL DEFAULT '#000000',         -- store as “#RRGGBB”
  show_in_calendar TINYINT(1) NOT NULL DEFAULT 1,   -- 1 = yes, 0 = no
  position INT NOT NULL DEFAULT 0,                   -- order/position in calendar
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  -- Date of the appointment
  appointment_date DATE NOT NULL,
  -- Start and end times (time-of-day)
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  -- Which therapist (staff) is assigned
  staff_id INT NOT NULL,
  FOREIGN KEY (staff_id) REFERENCES therapists(id) ON DELETE CASCADE,
  -- Either link to an existing client…
  client_id INT DEFAULT NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  -- …or store a new‐client name/phone if client_id is NULL
  client_name  VARCHAR(200) DEFAULT NULL,
  client_phone VARCHAR(20)  DEFAULT NULL,
  -- Which service was booked
  service_id INT NOT NULL,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  notes    TEXT,
  send_sms TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);