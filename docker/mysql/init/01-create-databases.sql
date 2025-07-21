-- Create main application database
CREATE DATABASE IF NOT EXISTS `task_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create test database
CREATE DATABASE IF NOT EXISTS `task_management_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create application user
CREATE USER IF NOT EXISTS 'taskapp'@'%' IDENTIFIED BY 'taskapp_password';

-- Grant privileges to application user
GRANT ALL PRIVILEGES ON `task_management`.* TO 'taskapp'@'%';
GRANT ALL PRIVILEGES ON `task_management_test`.* TO 'taskapp'@'%';

-- Flush privileges
FLUSH PRIVILEGES;