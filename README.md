# Simple Task Manager

A Laravel-based task management application with Docker setup.

## Quick Start

1. **Clone and start**
   ```bash
   git clone <repository-url>
   cd simple-task-manager-laravel
   cp .env.example .env
   docker compose up -d
   ```

2. **Setup database**
   ```bash
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate
   ```

3. **Access the application**
   - **App**: http://localhost
   - **API**: http://localhost/api/health
   - **Database**: http://localhost:8080 (PHPMyAdmin)
   - **Email**: http://localhost:8025 (MailHog)

## Custom Domains (Optional)

Add to your hosts file (`/etc/hosts` on Mac/Linux, `C:\Windows\System32\drivers\etc\hosts` on Windows):

```
127.0.0.1    app.taskapp.local
127.0.0.1    phpmyadmin.taskapp.local
127.0.0.1    mailhog.taskapp.local
```

Then access:
- **App**: http://app.taskapp.local
- **Database**: http://phpmyadmin.taskapp.local:8080
- **Email**: http://mailhog.taskapp.local:8025

## Development Commands

```bash
# Laravel commands
docker compose exec app php artisan migrate
docker compose exec app php artisan make:model Task
docker compose exec app php artisan cache:clear

# Composer
docker compose exec app composer install
docker compose exec app composer require package-name

# Container management
docker compose up -d        # Start
docker compose down         # Stop
docker compose logs app     # View logs
```

## Database Access

- **Host**: localhost:3307
- **Database**: task_management
- **Username**: taskapp
- **Password**: taskapp_password

## Troubleshooting

```bash
# Restart everything
docker compose down && docker compose up -d

# Fix permissions
docker compose exec app chmod -R 775 storage bootstrap/cache

# Clear all caches
docker compose exec app php artisan optimize:clear
```

---

**That's it!** The application should be running at http://localhost