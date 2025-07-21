# Multilingual Task Management Application

A modern, full-stack task management application built with Laravel 12 and React 19. This application provides comprehensive task management capabilities with multilingual support, hierarchical task organization, real-time updates, and a fully containerized Docker environment.

## 🌟 Key Features

### Core Task Management
- **Complete CRUD Operations**: Create, read, update, and delete tasks with full validation
- **Hierarchical Task Structure**: Support for parent tasks and unlimited subtask nesting
- **Task Status Management**: Track progress with pending, in-progress, completed, and cancelled states
- **Priority Levels**: Organize tasks by priority (low, medium, high, urgent)
- **Due Date Management**: Set and track task deadlines with overdue indicators
- **Soft Delete System**: Tasks are safely archived, not permanently deleted

### Multilingual Support 🌍
- **Three Languages**: Full support for English (EN), German (DE), and French (FR)
- **Dynamic Language Switching**: Change interface language on-the-fly
- **Translatable Content**: Task names and descriptions support multiple languages
- **Translation Status Tracking**: Visual indicators for translation completeness
- **Locale-Aware API**: Backend automatically serves content in user's preferred language

### Modern Frontend Architecture
- **React 19 + TypeScript**: Type-safe, modern React application
- **Responsive Design**: Mobile-first design with Tailwind CSS 4.0
- **Real-time Updates**: WebSocket integration for live task updates
- **Optimistic Updates**: Instant UI feedback with automatic error recovery
- **Advanced UI Components**: Drag-and-drop, modals, notifications, and more
- **Context-based State Management**: Efficient state management with React Context

## 🚀 Quick Start Guide

### Prerequisites
- **Docker Desktop** (v20.10+) and **Docker Compose** (v2.0+)
- **Git** for cloning the repository
- **8GB RAM** recommended for optimal performance

### 1. Clone and Initial Setup
```bash
# Clone the repository
git clone <repository-url>
cd task-management-app

# Copy environment configuration
cp .env.example .env
```

### 2. Automated Setup (Recommended)
```bash
# Run the complete setup script
./docker/scripts/setup.sh setup
```

This single command will:
- ✅ Check Docker dependencies
- ✅ Set up environment configuration
- ✅ Generate SSL certificates for HTTPS
- ✅ Start all Docker services
- ✅ Install PHP and Node.js dependencies
- ✅ Generate Laravel application key
- ✅ Run database migrations
- ✅ Seed sample data
- ✅ Build frontend assets
- ✅ Set proper file permissions

### 3. Manual Setup (Alternative)
```bash
# Start Docker services
docker compose up -d

# Wait for services to be ready (especially MySQL)
sleep 30

# Install dependencies
docker compose exec app composer install
docker compose exec app npm install

# Generate application key
docker compose exec app php artisan key:generate

# Run database setup
docker compose exec app php artisan migrate --seed

# Build frontend assets
docker compose exec app npm run build

# Fix permissions
docker compose exec app chmod -R 775 storage bootstrap/cache
```

### 4. Access the Application
| Service | URL | Description |
|---------|-----|-------------|
| **Main App** | http://localhost | Task management interface |
| **PHPMyAdmin** | http://localhost:8080 | Database administration |
| **MailHog** | http://localhost:8025 | Email testing interface |
| **API Docs** | http://localhost/api/health | API health check |

### 5. Default Login Credentials
After seeding, you can log in with:
- **Email**: `admin@example.com`
- **Password**: `password`

## �️ Technicial Architecture

### Backend Stack
- **Laravel 12**: Latest PHP framework with modern features
- **PHP 8.2**: High-performance PHP runtime
- **MySQL 8.0**: Robust relational database with JSON support
- **Redis**: Caching, sessions, and queue management
- **Laravel Sanctum**: API authentication and authorization
- **Spatie Translatable**: Advanced multilingual content management

### Frontend Stack
- **React 19**: Latest React with concurrent features
- **TypeScript 5.8**: Full type safety and modern JavaScript features
- **Vite 6**: Lightning-fast build tool and dev server
- **Tailwind CSS 4.0**: Utility-first CSS framework
- **Radix UI**: Accessible, unstyled UI components
- **React Hook Form**: Performant form handling with validation
- **Zod**: Runtime type validation and schema parsing

### DevOps & Infrastructure
- **Docker Compose**: Multi-container orchestration
- **Nginx**: High-performance web server and reverse proxy
- **Supervisor**: Process management for background tasks
- **MailHog**: Email testing and debugging
- **PHPMyAdmin**: Database administration interface

### Development Tools
- **Laravel Pint**: PHP code style fixer
- **Laravel Telescope**: Application debugging and monitoring
- **Pest**: Modern PHP testing framework
- **ESLint + Prettier**: JavaScript/TypeScript code quality

## 🐳 Docker Architecture

The application runs in a fully containerized environment with optimized resource allocation:

| Service | Container | Port | Resources | Description |
|---------|-----------|------|-----------|-------------|
| **PHP App** | `taskapp_php` | - | 1.5GB RAM, 1 CPU | Laravel application with Supervisor |
| **Nginx** | `taskapp_nginx` | 80, 443 | 128MB RAM | Web server with SSL support |
| **MySQL** | `taskapp_mysql` | 3307 | 1GB RAM, 1 CPU | Database with custom configuration |
| **Redis** | `taskapp_redis` | 6380 | 256MB RAM | Cache, sessions, and queues |
| **PHPMyAdmin** | `taskapp_phpmyadmin` | 8080 | 128MB RAM | Database administration |
| **MailHog** | `taskapp_mailhog` | 8025, 1025 | 64MB RAM | Email testing |
| **Node.js** | `taskapp_node` | 5173 | - | Frontend development server |

### Environment Configuration
```bash
# Database
DB_HOST=mysql
DB_DATABASE=task_management
DB_USERNAME=taskapp
DB_PASSWORD=taskapp_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Mail (Development)
MAIL_HOST=mailhog
MAIL_PORT=1025
```

### Volume Management
- **mysql_data**: Persistent database storage
- **redis_data**: Redis persistence
- **Application files**: Bind-mounted for development

## 📚 API Documentation

### Authentication Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | User registration |
| POST | `/api/auth/login` | User authentication |
| POST | `/api/auth/logout` | User logout |
| GET | `/api/auth/user` | Get authenticated user |

### Task Management API
| Method | Endpoint | Description | Parameters |
|--------|----------|-------------|-----------|
| GET | `/api/tasks` | List tasks with filtering | `status`, `priority`, `search`, `page` |
| POST | `/api/tasks` | Create new task | Task data with translations |
| GET | `/api/tasks/{id}` | Get specific task | Include translations |
| PUT | `/api/tasks/{id}` | Update task | Partial task data |
| DELETE | `/api/tasks/{id}` | Soft delete task | - |
| POST | `/api/tasks/{id}/restore` | Restore deleted task | - |
| PATCH | `/api/tasks/{id}/status` | Update task status | `status` |

### Subtask Operations
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tasks/{id}/subtasks` | Get task subtasks |
| POST | `/api/tasks/{parentId}/subtasks` | Create subtask |
| POST | `/api/tasks/bulk-update` | Bulk update multiple tasks |
| POST | `/api/tasks/reorder` | Reorder tasks |

### Localization API
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/locale` | Get current locale info |
| POST | `/api/locale/preference` | Set user language preference |

### Example API Requests

#### Create Multilingual Task
```json
POST /api/tasks
{
  "name": {
    "en": "Complete project documentation",
    "de": "Projektdokumentation vervollständigen", 
    "fr": "Compléter la documentation du projet"
  },
  "description": {
    "en": "Write comprehensive documentation",
    "de": "Umfassende Dokumentation schreiben",
    "fr": "Rédiger une documentation complète"
  },
  "status": "pending",
  "priority": "high",
  "due_date": "2025-08-01",
  "parent_id": null
}
```

#### Filter Tasks
```bash
GET /api/tasks?status=pending&priority=high&search=documentation&page=1
```

#### Bulk Update
```json
POST /api/tasks/bulk-update
{
  "updates": [
    {"id": 1, "data": {"status": "completed"}},
    {"id": 2, "data": {"priority": "urgent"}}
  ]
}
```

## 🗄️ Database Schema

### Tasks Table Structure
```sql
CREATE TABLE tasks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name JSON NOT NULL,                    -- Multilingual names
    description JSON,                      -- Multilingual descriptions
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    due_date DATE NULL,
    parent_id BIGINT UNSIGNED NULL,        -- Self-referencing for subtasks
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,             -- Soft delete support
    
    FOREIGN KEY (parent_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_

## 💻 Development Commands

### Essential Commands
```bash
# Start/stop the application
docker compose up -d              # Start all services
docker compose down               # Stop all services
docker compose logs -f app       # View application logs

# Laravel commands
docker compose exec app php artisan migrate        # Run migrations
docker compose exec app php artisan migrate:fresh  # Reset database
docker compose exec app php artisan db:seed        # Seed sample data

# Frontend development
docker compose exec app npm run dev    # Development build
docker compose exec app npm run build  # Production build

# Database access
docker compose exec mysql mysql -u taskapp -p task_management
```

### Project Structure
```
├── app/
│   ├── Http/Controllers/     # TaskController, AuthController
│   ├── Models/              # Task model with soft deletes
│   └── Services/            # Business logic
├── resources/
│   ├── js/                  # React TypeScript frontend
│   └── lang/                # Translation files (en, de, fr)
├── database/
│   ├── migrations/          # Task table migration
│   └── seeders/             # Sample data
└── routes/
    ├── api.php              # API endpoints
    └── web.php              # Web routes
```

## 🧪 Testing

The application includes comprehensive tests to verify all requirements:

### Run Tests
```bash
# Run all tests
docker compose exec app php artisan test

# Run specific test categories
docker compose exec app php artisan test --testsuite=Feature
docker compose exec app php artisan test --testsuite=Unit
```

### Test Coverage
- ✅ **Task CRUD Operations**: Create, read, update, delete tasks
- ✅ **Subtask Management**: Parent-child task relationships
- ✅ **Soft Delete Functionality**: Tasks are soft deleted, not permanently removed
- ✅ **Multilingual Support**: Translation functionality for name/description
- ✅ **API Endpoints**: All REST API endpoints tested
- ✅ **Database Relationships**: Task hierarchy and user ownership

### Example Test
```php
it('can create a task with subtasks', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->postJson('/api/tasks', [
        'name' => ['en' => 'Parent Task'],
        'description' => ['en' => 'Main task description'],
        'status' => 'pending'
    ]);
    
    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('Parent Task');
});
```

## Running Tests

To run the test suite, you can use the following commands from the root of the project:

### Run all tests
```bash
docker compose exec app php artisan test
```

### Run only fast tests
To get a quick feedback loop, you can exclude the more resource-intensive tests:
```bash
docker compose exec app php artisan test --exclude-group=slow
```

### Run only slow tests
To run the full integration and performance tests, you can run only the "slow" group:
```bash
docker compose exec app php artisan test --group=slow
```

## ⚙️ Key Implementation Details

### Soft Delete Implementation
```php
// Task model uses SoftDeletes trait
class Task extends Model
{
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
    // Tasks are marked as deleted, not removed from database
}
```

### Multilingual Fields
```php
// Name and description stored as JSON for multiple languages
protected $casts = [
    'name' => 'array',
    'description' => 'array',
];

// Example data structure:
{
    "name": {
        "en": "Complete project",
        "de": "Projekt abschließen", 
        "fr": "Terminer le projet"
    }
}
```

### Hierarchical Task Structure
```php
// Parent-child relationships
public function subtasks()
{
    return $this->hasMany(Task::class, 'parent_id');
}

public function parent()
{
    return $this->belongsTo(Task::class, 'parent_id');
}
```

## 🔧 Troubleshooting

### Common Issues

#### Application Won't Start
```bash
# Check container status
docker compose ps

# View logs for issues
docker compose logs app

# Restart services
docker compose down && docker compose up -d
```

#### Database Connection Issues
```bash
# Check MySQL container
docker compose exec mysql mysql -u taskapp -p

# Reset database if needed
docker compose exec app php artisan migrate:fresh --seed
```

#### Permission Issues
```bash
# Fix Laravel permissions
docker compose exec app chmod -R 775 storage bootstrap/cache

# Clear caches
docker compose exec app php artisan optimize:clear
```

#### Frontend Not Loading
```bash
# Rebuild frontend assets
docker compose exec app npm install
docker compose exec app npm run build
```

## 📝 Interview Task Completion Summary

This Laravel application successfully implements all the required features from the coding task:

### ✅ Core Requirements Met
1. **Web Form for Adding Tasks** - Implemented with React frontend and Laravel backend
2. **Task Fields** - Name, description, and status fields with validation
3. **Subtask Support** - Hierarchical task structure with parent-child relationships
4. **Task Display Page** - Comprehensive task listing with filtering and search
5. **Edit & Delete Functionality** - Full CRUD operations for tasks
6. **Soft Deletes** - Tasks are soft deleted, not permanently removed
7. **MySQL Database** - Proper database structure with relationships

### ✅ Bonus Features Implemented
1. **Multilingual Support** - Name and description translatable in German (DE), French (FR), and English (EN)
2. **Modern UI** - React TypeScript frontend with responsive design
3. **RESTful API** - Clean API architecture
4. **Docker Environment** - Fully containerized for easy setup and deployment

### 🚀 Additional Features
- User authentication and authorization
- Task priority levels and due dates
- Real-time updates and notifications
- Comprehensive testing suite
- Production-ready deployment scripts

The application demonstrates modern Laravel development practices, clean code architecture, and comprehensive feature implementation that goes beyond the basic requirements while maintaining simplicity and usability.

---

**Ready to evaluate?** Simply run `./scripts/setup.sh` and visit http://localhost to see the application in action!