#!/bin/bash

# Variables
GIT_REPO="https://github.com/fabed221/myapp1.git"
WEB_DIR="/var/www/html"
NGINX_CONF="/etc/nginx/sites-available/myapp.conf"
DB_NAME="myapp"
DB_USER="myuser"
DB_PASS="mypassword"

echo "🛠 Updating system..."
sudo apt update && sudo apt upgrade -y

echo "🛠 Installing required packages..."
sudo apt install -y nginx mysql-server php8.3 php8.3-fpm php8.3-mysql git unzip

echo "🛠 Enabling and starting services..."
sudo systemctl enable nginx mysql php8.3-fpm
sudo systemctl start nginx mysql php8.3-fpm

echo "📥 Cloning the repository..."
sudo rm -rf $WEB_DIR/*
sudo git clone $GIT_REPO $WEB_DIR

# Ensure PHP files are in place
echo "📂 Ensuring PHP files are in place..."
if [ -f "$WEB_DIR/index.php" ]; then
    echo "✅ index.php exists."
else
    echo "⚠️ index.php not found! Please check your GitHub repository."
fi

if [ -f "$WEB_DIR/register.php" ]; then
    echo "✅ register.php exists."
else
    echo "⚠️ register.php not found! Please check your GitHub repository."
fi

# Set correct permissions
echo "🔑 Setting permissions..."
sudo chown -R www-data:www-data $WEB_DIR
sudo chmod -R 755 $WEB_DIR

# Folder permission 
echo "📁 Creating uploads directory..."
sudo mkdir -p $WEB_DIR/uploads
sudo chmod -R 775 $WEB_DIR/uploads
sudo chown -R www-data:www-data $WEB_DIR/uploads

# Configure MySQL
echo "🛢 Setting up MySQL database..."
sudo mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import database
if [ -f "$WEB_DIR/myapp.sql" ]; then
    echo "📂 Importing database..."
    sudo mysql -u root $DB_NAME < $WEB_DIR/myapp.sql
else
    echo "⚠️ myapp.sql not found! Skipping database import."
fi

# Set up Nginx
echo "🌐 Configuring Nginx..."
sudo rm -f /etc/nginx/sites-enabled/default
sudo mv $WEB_DIR/myapp.conf $NGINX_CONF
sudo ln -s $NGINX_CONF /etc/nginx/sites-enabled/

# Restart services
echo "🔄 Restarting Nginx and PHP..."
sudo systemctl restart nginx php8.3-fpm

echo "✅ Setup complete! Your website should be live."
