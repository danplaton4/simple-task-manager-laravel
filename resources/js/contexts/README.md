# Task Context and State Management

## Overview

The Task Context provides centralized state management for task operations with optimistic updates, error handling, and loading states. It's built on top of the TaskService and provides a React-friendly interface for managing tasks throughout the application.

## Features

- **Optimistic Updates**: Immediate UI updates with automatic rollback on errors
- **Error Handling**: Comprehensive error management with user-friendly messages
- **Loading States**: Fine-grained loading indicators for different operations
- **Pagination**: Built-in pagination support with navigation helpers
- **Filtering**: Advanced filtering capabilities with state management
- **Bulk Operations**: Support for bulk task updates
- **Subtask Management**: Hierarchical task support

## Usage

### Basic Setup

```tsx
import { TaskProvider } from '@/contexts/TaskContext';

function App() {
  return (
    <TaskProvider>
      <YourTaskComponents />
    </TaskProvider>
  );
}
```

### Using the Task Context

```tsx
import { useTask } from '@/contexts/TaskContext';

function TaskList() {
  const {
    tasks,
    isLoading,
    error,
    fetchTasks,
    createTask,
    updateTask,
    deleteTask
  } = useTask();

  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  const handleCreateTask = async () => {
    try {
      await createTask({
        name: 'New Task',
        status: 'pending',
        priority: 'medium'
      });
    } catch (error) {
      console.error('Failed to create task:', error);
    }
  };

  if (isLoading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      {tasks.map(task => (
        <TaskItem key={task.id} task={task} />
      ))}
    </div>
  );
}
```

### Using Task Operations Hook

```tsx
import { useTaskOperations } from '@/hooks/useTaskOperations';

function TaskItem({ task }) {
  const {
    isUpdating,
    isDeleting,
    updateTaskWithLoading,
    deleteTaskWithLoading,
    toggleTaskStatusWithLoading
  } = useTaskOperations();

  const handleToggleStatus = async () => {
    const newStatus = task.status === 'pending' ? 'completed' : 'pending';
    await toggleTaskStatusWithLoading(task.id, newStatus);
  };

  return (
    <div>
      <h3>{task.name}</h3>
      <button 
        onClick={handleToggleStatus}
        disabled={isUpdating}
      >
        {isUpdating ? 'Updating...' : 'Toggle Status'}
      </button>
      <button 
        onClick={() => deleteTaskWithLoading(task.id)}
        disabled={isDeleting}
      >
        {isDeleting ? 'Deleting...' : 'Delete'}
      </button>
    </div>
  );
}
```

### Using Task Filters Hook

```tsx
import { useTaskFilters } from '@/hooks/useTaskFilters';

function TaskFilters() {
  const {
    activeFilters,
    setStatusFilter,
    setPriorityFilter,
    setSearchFilter,
    applyFilters,
    clearAllFilters,
    hasActiveFilters
  } = useTaskFilters();

  return (
    <div>
      <select onChange={(e) => setStatusFilter(e.target.value || undefined)}>
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
      </select>

      <select onChange={(e) => setPriorityFilter(e.target.value || undefined)}>
        <option value="">All Priorities</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
        <option value="urgent">Urgent</option>
      </select>

      <input
        type="text"
        placeholder="Search tasks..."
        onChange={(e) => setSearchFilter(e.target.value || undefined)}
      />

      <button onClick={applyFilters}>Apply Filters</button>
      
      {hasActiveFilters && (
        <button onClick={clearAllFilters}>Clear All</button>
      )}
    </div>
  );
}
```

## API Reference

### TaskContext

#### State Properties

- `tasks: Task[]` - Array of current tasks
- `currentTask: Task | null` - Currently selected task
- `isLoading: boolean` - Global loading state
- `error: string | null` - Current error message
- `pagination` - Pagination information
- `filters: TaskFilters` - Current filter settings

#### Methods

- `fetchTasks(page?, filters?)` - Fetch tasks with pagination/filtering
- `fetchTask(id)` - Fetch a single task
- `createTask(taskData)` - Create a new task with optimistic update
- `updateTask(id, taskData)` - Update task with optimistic update
- `deleteTask(id)` - Delete task with optimistic update
- `toggleTaskStatus(id, newStatus)` - Toggle task status
- `fetchSubtasks(parentId)` - Get subtasks for a parent
- `createSubtask(parentId, taskData)` - Create a subtask
- `bulkUpdateTasks(updates)` - Bulk update multiple tasks

### useTaskOperations Hook

Provides loading states for individual operations:

- `isCreating: boolean` - Creating task loading state
- `isUpdating: boolean` - Updating task loading state
- `isDeleting: boolean` - Deleting task loading state
- `isToggling: boolean` - Toggling status loading state

### useTaskFilters Hook

Provides filtering and pagination utilities:

- `activeFilters: TaskFilters` - Current filter state
- `setStatusFilter(status)` - Set status filter
- `setPriorityFilter(priority)` - Set priority filter
- `setSearchFilter(search)` - Set search filter
- `applyFilters()` - Apply current filters
- `clearAllFilters()` - Clear all filters
- Pagination methods: `goToPage()`, `nextPage()`, `previousPage()`

## Optimistic Updates

The context automatically handles optimistic updates for better UX:

1. **Create**: Task appears immediately, replaced with server response
2. **Update**: Changes apply immediately, reverted on error
3. **Delete**: Task disappears immediately, restored on error
4. **Status Toggle**: Status changes immediately, reverted on error

## Error Handling

Errors are automatically caught and exposed through the context:

```tsx
const { error, clearError } = useTask();

if (error) {
  return (
    <div className="error">
      {error}
      <button onClick={clearError}>Dismiss</button>
    </div>
  );
}
```

## Best Practices

1. **Use Loading States**: Always show loading indicators for better UX
2. **Handle Errors**: Display error messages and provide retry options
3. **Optimistic Updates**: Let the context handle optimistic updates automatically
4. **Pagination**: Use the built-in pagination helpers
5. **Filtering**: Apply filters through the context for consistent state
6. **Cleanup**: Clear errors and reset state when appropriate

## Integration with Components

The task context integrates seamlessly with form components, lists, and detail views. See `TaskServiceExample.tsx` for a complete implementation example.