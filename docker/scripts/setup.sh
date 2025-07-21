#!/bin/bash

# Task Management App Docker Setup Script

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker and Docker Compose are installed
check_dependencies() {
    print_status "Checking dependencies..."
    
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! command -v docker compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    print_status "Dependencies check passed."
}

# Setup environment file
setup_env() {
    print_status "Setting up environment file..."
    
    if [ ! -f .env ]; then
        if [ -f .env.docker ]; then
            cp .env.docker .env
            print_status "Copied .env.docker to .env"
        elif [ -f .env.example ]; then
            cp .env.example .env
            print_status "Copied .env.example to .env"
        else
            print_error "No environment template found. Please create .env file manually."
            exit 1
        fi
    else
        print_warning ".env file already exists. Skipping..."
    fi
}

# Generate application key
generate_app_key() {
    print_status "Generating application key..."
    docker compose exec app php artisan key:generate --ansi
}

# Run database migrations
run_migrations() {
    print_status "Running database migrations..."
    docker compose exec app php artisan migrate --force
}

# Seed database
seed_database() {
    print_status "Seeding database..."
    docker compose exec app php artisan db:seed --force
}

# Install dependencies
install_dependencies() {
    print_status "Installing PHP dependencies..."
    docker compose exec app composer install --optimize-autoloader
    
    print_status "Installing Node.js dependencies..."
    docker compose exec app npm install
    
    print_status "Building frontend assets..."
    docker compose exec app npm run build
}

# Set permissions
set_permissions() {
    print_status "Setting proper permissions..."
    docker compose exec app chown -R www-data:www-data /var/www/html/storage
    docker compose exec app chown -R www-data:www-data /var/www/html/bootstrap/cache
    docker compose exec app chmod -R 775 /var/www/html/storage
    docker compose exec app chmod -R 775 /var/www/html/bootstrap/cache
}

# Generate SSL certificates for development
generate_ssl_certs() {
    print_status "Generating self-signed SSL certificates for development..."
    
    if [ ! -f docker/nginx/ssl/cert.pem ]; then
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout docker/nginx/ssl/key.pem \
            -out docker/nginx/ssl/cert.pem \
            -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"
        print_status "SSL certificates generated."
    else
        print_warning "SSL certificates already exist. Skipping..."
    fi
}

# Start services
start_services() {
    print_status "Starting Docker services..."
    docker compose up -d
    
    print_status "Waiting for services to be ready..."
    sleep 10
    
    # Wait for MySQL to be ready
    print_status "Waiting for MySQL to be ready..."
    until docker compose exec mysql mysqladmin ping -h"localhost" --silent; do
        sleep 2
    done
    
    # Wait for Redis to be ready
    print_status "Waiting for Redis to be ready..."
    until docker compose exec redis redis-cli ping; do
        sleep 2
    done
    
    print_status "All services are ready!"
}

# Stop services
stop_services() {
    print_status "Stopping Docker services..."
    docker compose down
}

# Clean up (remove containers, volumes, images)
cleanup() {
    print_warning "This will remove all containers, volumes, and images. Are you sure? (y/N)"
    read -r response
    if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
        print_status "Cleaning up Docker resources..."
        docker compose down -v --rmi all --remove-orphans
        print_status "Cleanup completed."
    else
        print_status "Cleanup cancelled."
    fi
}

# Show logs
show_logs() {
    if [ -n "$1" ]; then
        docker compose logs -f "$1"
    else
        docker compose logs -f
    fi
}

# Show status
show_status() {
    print_status "Docker services status:"
    docker compose ps
    
    print_status "\nApplication URLs:"
    echo "  - Application: http://localhost"
    echo "  - PHPMyAdmin: http://localhost:8080"
    echo "  - MailHog: http://localhost:8025"
    echo "  - Redis Commander: http://localhost:8081"
}

# Main script logic
case "$1" in
    "setup")
        check_dependencies
        setup_env
        generate_ssl_certs
        start_services
        install_dependencies
        generate_app_key
        run_migrations
        seed_database
        set_permissions
        show_status
        print_status "Setup completed successfully!"
        ;;
    "start")
        start_services
        show_status
        ;;
    "stop")
        stop_services
        ;;
    "restart")
        stop_services
        start_services
        show_status
        ;;
    "logs")
        show_logs "$2"
        ;;
    "status")
        show_status
        ;;
    "migrate")
        run_migrations
        ;;
    "seed")
        seed_database
        ;;
    "key")
        generate_app_key
        ;;
    "install")
        install_dependencies
        ;;
    "permissions")
        set_permissions
        ;;
    "ssl")
        generate_ssl_certs
        ;;
    "cleanup")
        cleanup
        ;;
    *)
        echo "Task Management App Docker Helper"
        echo ""
        echo "Usage: $0 {setup|start|stop|restart|logs|status|migrate|seed|key|install|permissions|ssl|cleanup}"
        echo ""
        echo "Commands:"
        echo "  setup       - Complete setup (first time)"
        echo "  start       - Start all services"
        echo "  stop        - Stop all services"
        echo "  restart     - Restart all services"
        echo "  logs        - Show logs (optionally specify service)"
        echo "  status      - Show services status and URLs"
        echo "  migrate     - Run database migrations"
        echo "  seed        - Seed database with sample data"
        echo "  key         - Generate application key"
        echo "  install     - Install dependencies"
        echo "  permissions - Fix file permissions"
        echo "  ssl         - Generate SSL certificates"
        echo "  cleanup     - Remove all containers, volumes, and images"
        echo ""
        exit 1
        ;;
esac