# Task Management App

A Laravel + React task management application with multilingual support (EN/DE/FR), hierarchical tasks, and Docker setup.

## What it does
- Create, edit, delete tasks with subtasks
- Multilingual interface (English, German, French)
- Soft delete (tasks can be restored)
- Real-time updates
- User authentication

## Quick Setup

### Prerequisites
- Docker Desktop
- Git

### 1. Clone and Setup
```bash
git clone <repository-url>
cd task-management-app
cp .env.example .env
```

### 2. Run Setup Script
```bash
./docker/scripts/setup.sh setup
```

### 3. Access Application
- **App**: http://localhost
- **Database Admin**: http://localhost:8080
- **Login**: `admin@example.com` / `password`

### Manual Setup (if script fails)
```bash
docker compose up -d
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm run build
```

## Development Commands
```bash
# Start/stop services
docker compose up -d
docker compose down

# Run tests
docker compose exec app php artisan test

# Reset database
docker compose exec app php artisan migrate:fresh --seed
```

## Troubleshooting
```bash
# Check container status
docker compose ps

# View logs
docker compose logs app

# Fix permissions
docker compose exec app chmod -R 775 storage bootstrap/cache
```