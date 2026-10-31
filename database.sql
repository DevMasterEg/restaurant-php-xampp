-- ============================================================
--  Tasty Bites - MySQL Databases for XAMPP
--  Import this file via phpMyAdmin  (or just run setup.bat)
--
--  ORGANIZED INTO 3 SEPARATE DATABASES:
--    1. restaurant_accounts  -> user accounts (admin + customers)
--    2. restaurant_menu      -> menu items (the dishes)
--    3. restaurant_orders    -> orders + order items
--
--  After importing you will see the 3 databases in the left
--  sidebar of phpMyAdmin. Each one has its own tables.
--
--  NOTE: if you still have the old single database
--  "restaurant_db" from a previous version, you can safely
--  delete it in phpMyAdmin after this import succeeds.
-- ============================================================

-- ############################################################
-- 1) ACCOUNTS DATABASE  (users / logins / roles)
-- ############################################################
CREATE DATABASE IF NOT EXISTS restaurant_accounts
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_accounts;

DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(190)  NOT NULL UNIQUE,
  phone         VARCHAR(30)   DEFAULT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('ADMIN','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
  active        TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, phone, password_hash, role, active) VALUES
('Administrator', 'admin@restaurant.com',    '0100000000',  '$2y$10$J.LgsmHeBIi6Y1b987R73uAdoJ0FDZSFuB/lJr.tsOvM6W35whjS6', 'ADMIN',    1),
('Demo Customer', 'customer@restaurant.com', '0111111111',  '$2y$10$n/fVssBUpVDqfVNVLp/tEevuE2ZRC3Ao/RIi5ZXkOZlMU6DV9vZ8W', 'CUSTOMER', 1);

-- ############################################################
-- 2) MENU DATABASE  (the dishes)
-- ############################################################
CREATE DATABASE IF NOT EXISTS restaurant_menu
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_menu;

DROP TABLE IF EXISTS menu_items;

CREATE TABLE menu_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150)  NOT NULL,
  description VARCHAR(500)  NOT NULL DEFAULT '',
  price       DECIMAL(10,2) NOT NULL,
  category    VARCHAR(60)   NOT NULL DEFAULT 'Mains',
  emoji       VARCHAR(16)   NOT NULL DEFAULT '🍽️',
  available   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO menu_items (name, description, price, category, emoji, available) VALUES
('Classic Burger',      'Juicy beef patty, cheddar, lettuce, tomato and our secret sauce.', 8.50,  'Mains',      '🍔', 1),
('Crispy Chicken',      'Golden fried chicken fillet with pickles and mayo on a brioche.',  9.00,  'Mains',      '🍗', 1),
('Margherita Pizza',    'Stone-baked pizza with mozzarella, tomato sauce and fresh basil.', 10.00, 'Mains',      '🍕', 1),
('Pepperoni Pizza',     'Loaded with pepperoni slices and double mozzarella cheese.',       11.50, 'Mains',      '🍕', 1),
('Grilled Salmon',      'Fresh salmon fillet with lemon butter sauce and steamed veggies.', 14.00, 'Mains',      '🐟', 1),
('Beef Shawarma',       'Slow-roasted beef strips, garlic sauce and pickles in warm bread.',7.50,  'Mains',      '🌯', 1),
('French Fries',        'Crispy golden fries sprinkled with sea salt.',                     3.00,  'Sides',      '🍟', 1),
('Onion Rings',         'Crunchy battered onion rings served with ranch dip.',              3.50,  'Sides',      '🧅', 1),
('Garden Salad',        'Fresh greens, cherry tomatoes, cucumber and olive oil dressing.',  4.50,  'Sides',      '🥗', 1),
('Coca-Cola',           'Chilled classic cola 330 ml.',                                     1.50,  'Drinks',     '🥤', 1),
('Orange Juice',        'Freshly squeezed orange juice 300 ml.',                            2.50,  'Drinks',     '🧃', 1),
('Mineral Water',       'Still mineral water 500 ml.',                                      1.00,  'Drinks',     '💧', 1),
('Chocolate Brownie',   'Warm fudgy brownie with vanilla ice cream on top.',                5.00,  'Desserts',   '🍰', 1);

-- ############################################################
-- 3) ORDERS DATABASE  (orders + their items)
--     pickup_time accepts ANY MINUTE (e.g. 13:47:00),
--     the PHP code validates it against kitchen hours.
-- ############################################################
CREATE DATABASE IF NOT EXISTS restaurant_orders
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_orders;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;

CREATE TABLE orders (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT           NOT NULL,   -- -> restaurant_accounts.users.id
  status      ENUM('PENDING','CONFIRMED','PREPARING','READY','COMPLETED','CANCELLED')
              NOT NULL DEFAULT 'PENDING',
  pickup_date DATE          NOT NULL,
  pickup_time TIME          NOT NULL,
  notes       TEXT          DEFAULT NULL,
  total       DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_pickup (pickup_date, pickup_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  order_id     INT           NOT NULL,   -- -> orders.id (same database)
  menu_item_id INT           DEFAULT NULL, -- -> restaurant_menu.menu_items.id
  name         VARCHAR(150)  NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  quantity     INT           NOT NULL DEFAULT 1,
  CONSTRAINT fk_oitems_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_oitems_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
