============================================================

  TASTY BITES - RESTAURANT ORDERING SYSTEM (XAMPP EDITION)

  Plain PHP + HTML + MySQL. Nothing to install except XAMPP.

============================================================



HOW TO INSTALL (2 minutes)

--------------------------

1. Copy this "restaurant" folder into:  C:\xampp\htdocs\

   (so the app lives at C:\xampp\htdocs\restaurant)



2. Double-click:  setup.bat

   - it finds your XAMPP automatically

   - starts MySQL if it is not running

   - creates the 3 databases (accounts / menu / orders) + demo data

   - checks that everything is OK



3. Start Apache in the XAMPP Control Panel

   (or double-click start-restaurant.bat)



4. Open:  http://localhost/restaurant



LOGIN ACCOUNTS (already in the database)

----------------------------------------

  Admin:    admin@restaurant.com    password: admin123

  Customer: customer@restaurant.com password: customer123



  !! Change the admin password after first login !!

  (Users tab -> Reset pw, or use manage-users.bat)



WHAT YOU GET

------------

- Customers register, browse the menu, add to cart

- At checkout they choose their own PICKUP DATE (7 days)

  and PICKUP TIME with ANY MINUTE they want (11:00-22:30, e.g. 13:47)

- Orders cannot be placed in the past (15-min lead time,

  quick-time buttons; past minutes are blocked + re-checked on server)

- Admin panel: Orders / Menu / Users

    * Orders: change status (pending, confirmed, preparing,

      ready, completed, cancelled), search, today's stats

    * Menu: add, edit, hide, delete dishes

    * Users: add, promote to admin, disable, reset password,

      delete (you cannot delete/disable yourself)

- Manage users offline too:  double-click manage-users.bat



THE 3 BAT FILES

---------------

  setup.bat            one-time database import (run first!)

  start-restaurant.bat starts MySQL + Apache, opens the site

  manage-users.bat     console tool to fully manage users:

                       stats / list / search / add / promote /

                       demote / enable / disable / reset pw /

                       edit / delete



IF SOMETHING GOES WRONG

-----------------------

* "Database connection failed"

  -> MySQL is not running. Open XAMPP Control Panel, press

     Start next to MySQL. Then run setup.bat once.



* Page shows "Access denied for user 'root'@'localhost'"

  -> You set a MySQL root password. Open config.php and put

     your password in DB_PASS.



* App moved to a different folder name?

  -> Just open  http://localhost/<folder-name>

     Everything uses relative links, no config change needed.



* Want to start over?

  -> run setup.bat again (it rebuilds the database fresh)

     WARNING: this erases all existing orders/users.



PORTS / PATHS USED

------------------

  Apache:  http://localhost (port 80)

  MySQL:   127.0.0.1 port 3306
  Databases: restaurant_accounts / restaurant_menu /
             restaurant_orders (you see all 3 in phpMyAdmin)

  Sessions + logs: inside XAMPP's own tmp folder

