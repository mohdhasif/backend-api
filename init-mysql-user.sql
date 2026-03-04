-- Ensure finiteapp_user exists and can connect from any host
-- Use mysql_native_password for better compatibility
CREATE USER IF NOT EXISTS 'finiteapp_user'@'%' IDENTIFIED WITH mysql_native_password BY 'finiteapp_pass';
GRANT ALL PRIVILEGES ON finiteapp.* TO 'finiteapp_user'@'%';

-- Also allow localhost connections
CREATE USER IF NOT EXISTS 'finiteapp_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'finiteapp_pass';
GRANT ALL PRIVILEGES ON finiteapp.* TO 'finiteapp_user'@'localhost';

FLUSH PRIVILEGES;
