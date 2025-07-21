# Docker Configuration for Task Management App

This directory contains all Docker-related configuration files for the Task Management Application.

## Directory Structure

```
docker/
├── mysql/
│   ├── init/
│   │   └── 01-create-databases.sql    # Database initialization
│   └── my.cnf                         # MySQL configuration
├── nginx/
│   ├── ssl/
│   │   └── .gitkeep                   # SSL certificates directory
│   ├── default.conf                   # Nginx server configuration
│   └── nginx.conf                     # Main Nginx configuration
├── php/
│   ├── Dockerfile                     # Custom PHP image
│   ├── php-fpm.conf                   # PHP-FPM configuration
│   ├── php.ini                        # PHP configuration
│   └── supervisord.conf               # Supervisor configuration
└── scripts/
    └── setup.sh                       # Docker helper script
```

## Services

### Application Stack

- **app**: Custom PHP 8.2-FPM container with Laravel application
- **nginx**: Nginx web server as reverse proxy
- **mysql**: MySQL 8.0 database server
- **redis**: Redis server for caching, sessions, and queues

### Development Tools

- **phpmyadmin**: Web-based MySQL administration
- **mailhog**: Email testing tool
- **redis-commander**: Redis GUI management tool

## Quick Start

1. **Initial Setup** (first time only):
   ```bash
   ./docker/scripts/setup.sh setup
   ```

2. **Start Services**:
   ```bash
   docker-compose up -d
   # or
   ./docker/scripts/setup.sh start
   ```

3. **Stop Services**:
   ```bash
   docker-compose down
   # or
   ./docker/scripts/setup.sh stop
   ```

## Environment Configuration

### Development (.env.docker)
- Database: MySQL with development credentials
- Cache/Sessions: Redis
- Mail: MailHog for testing
- Debug: Enabled

### Production (docker-compose.prod.yml)
- Optimized resource limits
- Security hardening
- Automated backups
- SSL/TLS configuration

## Service URLs

When running locally:

- **Application**: http://localhost
- **Application (HTTPS)**: https://localhost
- **PHPMyAdmin**: http://localhost:8080
- **MailHog Web UI**: http://localhost:8025
- **Redis Commander**: http://localhost:8081

## Database Configuration

### Default Databases
- `task_management`: Main application database
- `task_management_test`: Testing database

### Default Credentials
- **Root Password**: `root_password`
- **App User**: `taskapp`
- **App Password**: `taskapp_password`

## Redis Configuration

Multiple Redis databases for different purposes:
- **DB 0**: Default/Cache
- **DB 1**: Application cache
- **DB 2**: Sessions
- **DB 3**: Queues

## SSL/TLS Configuration

For development, self-signed certificates are generated automatically.

For production:
1. Place your SSL certificates in `docker/nginx/ssl/`
2. Update the certificate paths in `docker/nginx/default.conf`

## Performance Tuning

### PHP-FPM
- Process manager: Dynamic
- Max children: 50
- Start servers: 5
- Min/Max spare servers: 5/35

### MySQL
- InnoDB buffer pool: 512M
- Max connections: 200
- Query cache: 16M

### Nginx
- Worker processes: Auto
- Gzip compression: Enabled
- Static file caching: 1 year

## Security Features

### Nginx Security Headers
- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Strict-Transport-Security (HTTPS)
- Content-Security-Policy

### Rate Limiting
- API endpoints: 10 requests/second
- Authentication: 5 requests/second
- Connection limit: 10 per IP

### File Access Protection
- Hidden files (.*) blocked
- Sensitive files (.env, composer.json, etc.) blocked
- PHP execution limited to application files

## Monitoring and Logging

### Health Checks
- **Application**: `/health`
- **PHP-FPM**: `/ping` and `/status`

### Log Files
- **Nginx**: Access and error logs
- **PHP**: Application and error logs
- **MySQL**: Error and slow query logs

## Backup Strategy (Production)

Automated MySQL backups:
- Daily backups at midnight
- 7-day retention policy
- Stored in persistent volume

## Troubleshooting

### Common Issues

1. **Permission Errors**:
   ```bash
   ./docker/scripts/setup.sh permissions
   ```

2. **Database Connection Issues**:
   ```bash
   docker-compose logs mysql
   ```

3. **Application Key Missing**:
   ```bash
   ./docker/scripts/setup.sh key
   ```

4. **Frontend Assets Not Building**:
   ```bash
   docker-compose exec app npm run build
   ```

### Useful Commands

```bash
# View logs for specific service
docker-compose logs -f app

# Execute commands in container
docker-compose exec app php artisan migrate

# Access MySQL CLI
docker-compose exec mysql mysql -u taskapp -p task_management

# Access Redis CLI
docker-compose exec redis redis-cli

# Rebuild containers
docker-compose build --no-cache

# Clean up everything
./docker/scripts/setup.sh cleanup
```

## Development Workflow

1. **Code Changes**: Edit files locally (volume mounted)
2. **Database Changes**: Run migrations in container
3. **Frontend Changes**: Build assets in container
4. **Testing**: Run tests in container environment

## Production Deployment

1. Use `docker-compose.prod.yml`
2. Set production environment variables
3. Configure real SSL certificates
4. Set up external monitoring
5. Configure automated backups

```bash
# Production deployment
docker-compose -f docker-compose.prod.yml up -d
```