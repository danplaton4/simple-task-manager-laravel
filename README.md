# Simple Task Management Application

A Laravel-based task management application built as a coding interview solution. This application allows users to create, edit, delete, and manage tasks with hierarchical subtask support, featuring multilingual capabilities and soft delete functionality.

## � Interveiew Task Requirements

This application was built to fulfill the following coding task requirements:

### Core Requirements ✅
- **Web Form**: Laravel form for adding new tasks with name, description, and status fields
- **Subtask Support**: Each task can have one or more subtasks with hierarchical organization
- **Task Display**: Separate page listing all existing tasks from the database
- **Edit & Delete**: Full CRUD functionality for individual tasks
- **Soft Deletes**: Implemented soft delete functionality instead of permanent deletion
- **MySQL Database**: Uses MySQL as the primary database

### Bonus Features ✅
- **Multilingual Support**: Name and description fields are translatable in German (DE), French (FR), and English (EN)
- **Modern UI**: React TypeScript frontend with responsive design
- **API Architecture**: RESTful API with comprehensive endpoints
- **Docker Setup**: Fully containerized for easy deployment and testing

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose
- Git

### 1. Clone and Setup
```bash
git clone <repository-url>
cd task-management-app
cp .env.example .env
```

### 2. Quick Start (Automated)
```bash
# Run the setup script for automatic installation
./scripts/setup.sh
```

### 3. Manual Setup (Alternative)
```bash
# Start Docker containers
docker compose up -d

# Generate application key
docker compose exec app php artisan key:generate

# Run database migrations
docker compose exec app php artisan migrate

# Seed with sample data (optional)
docker compose exec app php artisan db:seed

# Build frontend assets
docker compose exec app npm install && npm run build
```

### 4. Access the Application
- **Main Application**: http://localhost
- **Database Admin**: http://localhost:8080 (PHPMyAdmin)
- **API Health**: http://localhost/api/health

## 🎯 Application Features

### Task Management
- ✅ **Create Tasks**: Add new tasks with name, description, and status
- ✅ **Subtask Support**: Create hierarchical subtasks under parent tasks
- ✅ **Edit Tasks**: Modify existing tasks and their properties
- ✅ **Delete Tasks**: Soft delete functionality (tasks can be restored)
- ✅ **List Tasks**: View all tasks in an organized interface
- ✅ **Task Status**: Track task progress (Pending, In Progress, Completed, Cancelled)

### Multilingual Support (Bonus)
- ✅ **English (EN)**: Default language
- ✅ **German (DE)**: Full translation support
- ✅ **French (FR)**: Full translation support
- ✅ **Dynamic Language Switching**: Change language on the fly

### Technical Features
- ✅ **MySQL Database**: Robust data storage with proper relationships
- ✅ **Soft Deletes**: Tasks are marked as deleted, not permanently removed
- ✅ **RESTful API**: Clean API architecture for frontend-backend communication
- ✅ **Modern Frontend**: React TypeScript with responsive design
- ✅ **Docker Environment**: Consistent development and deployment environment

## 🐳 Docker Architecture

The application uses Docker for consistent development and deployment:

| Service | Port | Description |
|---------|------|-------------|
| **Laravel App** | - | PHP 8.2 application with Laravel framework |
| **Nginx** | 80 | Web server serving the application |
| **MySQL** | 3307 | Database server for task storage |
| **Redis** | 6380 | Caching and session management |
| **PHPMyAdmin** | 8080 | Database administration interface |

### Database Configuration
- **Database**: `task_management`
- **Username**: `taskapp`
- **Password**: `taskapp_password`
- **Host**: `localhost:3307` (external access)

## 📚 API Overview

The application provides a RESTful API for task management:

### Core Task Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tasks` | List all tasks (with filtering) |
| POST | `/api/tasks` | Create new task |
| GET | `/api/tasks/{id}` | Get specific task |
| PUT | `/api/tasks/{id}` | Update task |
| DELETE | `/api/tasks/{id}` | Soft delete task |
| POST | `/api/tasks/{id}/restore` | Restore deleted task |

### Subtask Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tasks/{id}/subtasks` | Get task subtasks |
| POST | `/api/tasks/{parentId}/subtasks` | Create subtask |

### Example Task Creation (Multilingual)
```json
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
  "parent_id": null
}
```

## �️ Dateabase Structure

### Tasks Table
The main tasks table includes:
- `id` - Primary key
- `name` - JSON field for multilingual names (en, de, fr)
- `description` - JSON field for multilingual descriptions
- `status` - Task status (pending, in_progress, completed, cancelled)
- `priority` - Task priority (low, medium, high, urgent)
- `parent_id` - Foreign key for subtask relationships
- `user_id` - Task owner
- `due_date` - Optional due date
- `deleted_at` - Soft delete timestamp
- `created_at` / `updated_at` - Timestamps

### Key Features
- **Soft Deletes**: Tasks are never permanently deleted
- **Hierarchical Structure**: Parent-child relationships for subtasks
- **Multilingual Support**: JSON fields store translations
- **User Ownership**: Each task belongs to a user

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