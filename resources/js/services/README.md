# Services

## AuthService

The `AuthService` class provides a centralized API for handling authentication operations:

### Methods

- `login(credentials)` - Authenticate user with email/password
- `register(userData)` - Register new user account  
- `logout()` - Log out current user
- `refreshToken()` - Refresh authentication token
- `getCurrentUser()` - Get current authenticated user data
- `isAuthenticated()` - Check if user is currently authenticated
- `getToken()` - Get current authentication token
- `clearAuth()` - Clear authentication data

### Features

- Automatic token storage in localStorage
- Proper error handling with meaningful messages
- Axios integration with request/response interceptors
- Token refresh capability
- Graceful logout handling

### Usage

```typescript
import AuthService from '@/services/AuthService';

// Login
try {
  const response = await AuthService.login({ email, password });
  console.log('User:', response.user);
} catch (error) {
  console.error('Login failed:', error.message);
}

// Check authentication
if (AuthService.isAuthenticated()) {
  const user = await AuthService.getCurrentUser();
}
```

## Integration

The AuthService is integrated with:
- AuthContext for global state management
- Axios interceptors for automatic token handling
- React components for seamless authentication flows
## TaskS
ervice

The `TaskService` class provides a centralized API for handling task operations:

### Methods

- `getTasks(filters?, page?, perPage?)` - Get paginated list of tasks with optional filtering
- `getTask(id)` - Get a specific task by ID
- `createTask(taskData)` - Create a new task
- `updateTask(id, taskData)` - Update an existing task
- `deleteTask(id)` - Soft delete a task
- `restoreTask(id)` - Restore a soft-deleted task
- `toggleTaskStatus(id, newStatus)` - Toggle task status
- `getSubtasks(parentId)` - Get subtasks for a parent task
- `createSubtask(parentId, taskData)` - Create a subtask
- `bulkUpdateTasks(updates)` - Bulk update multiple tasks

### Features

- Comprehensive error handling with meaningful messages
- Support for pagination and filtering
- Hierarchical task management (subtasks)
- Bulk operations for efficiency
- Consistent API response handling
- Automatic token handling via Axios interceptors

### Usage

```typescript
import TaskService from '@/services/TaskService';

// Get tasks with filtering
const tasks = await TaskService.getTasks(
  { status: 'pending', priority: 'high' },
  1, // page
  20  // per page
);

// Create a new task
const newTask = await TaskService.createTask({
  name: 'Complete project',
  description: 'Finish the task management app',
  status: 'pending',
  priority: 'high',
  due_date: '2024-12-31'
});

// Update task status
const updatedTask = await TaskService.toggleTaskStatus(1, 'completed');
```

### Integration

The TaskService is integrated with:
- TaskContext for global state management
- Optimistic updates for better UX
- Error handling and loading states
- React hooks for easy component integration