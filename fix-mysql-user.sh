#!/bin/bash
# Script untuk fix MySQL user permissions
# Run ini kalau ada masalah connect ke database

echo "Fixing MySQL user permissions..."

docker-compose exec mysql mysql -u root -prootpassword <<EOF
-- Drop existing users if they exist (to recreate properly)
DROP USER IF EXISTS 'finiteapp_user'@'%';
DROP USER IF EXISTS 'finiteapp_user'@'localhost';

-- Create user with mysql_native_password (more compatible)
CREATE USER 'finiteapp_user'@'%' IDENTIFIED WITH mysql_native_password BY 'finiteapp_pass';
CREATE USER 'finiteapp_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'finiteapp_pass';

-- Grant privileges
GRANT ALL PRIVILEGES ON finiteapp.* TO 'finiteapp_user'@'%';
GRANT ALL PRIVILEGES ON finiteapp.* TO 'finiteapp_user'@'localhost';

FLUSH PRIVILEGES;

-- Show users and verify
SELECT User, Host, plugin FROM mysql.user WHERE User='finiteapp_user';
EOF

echo ""
echo "Done! Try connecting again."
