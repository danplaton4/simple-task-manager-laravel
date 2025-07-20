# Task Management Components

This directory contains the core React components for the task management system, implementing hierarchical task display, comprehensive form handling, and advanced UI features.

## Components Overview

### TaskCard
**File:** `TaskCard.tsx`

A sophisticated card component for displaying individual tasks with hierarchical support.

**Features:**
- Hierarchical display with expandable/collapsible subtasks
- Visual status and priority indicators with color coding
- Due date formatting with overdue warnings
- Depth-based indentation for nested tasks
- Interactive buttons for edit, delete, and status toggle
- Responsive design with hover effects

**Props:**
- `task: Task` - The task object to display
- `onEdit?: (task: Task) => void` - Callback for edit action
- `onDelete?: (taskId: number) => void` - Callback for delete action
- `onToggleStatus?: (taskId: number) => void` - Callback for status toggle
- `onViewSubtasks?: (task: Task) => void` - Callback for subtask management
- `depth?: number` - Nesting depth for hierarchical display
- `showSubtasks?: boolean` - Whether to show subtasks inline

**Usage:**
```tsx
<TaskCard
  task={task}
  onEdit={handleEdit}
  onDelete={handleDelete}
  onToggleStatus={handleToggleStatus}
  onViewSubtasks={handleViewSubtasks}
  showSubtasks={true}
/>
```

### TaskList
**File:** `TaskList.tsx`

A comprehensive list component with advanced filtering, sorting, and view options.

**Features:**
- Built-in search functionality
- Advanced filtering by status, priority, and completion state
- Multiple sorting options (name, date, priority, status)
- List and grid view modes
- Hierarchical task display
- Loading states and empty states
- Task count display
- Responsive design

**Props:**
- `tasks: Task[]` - Array of tasks to display
- `onEdit?: (task: Task) => void` - Callback for edit action
- `onDelete?: (taskId: number) => void` - Callback for delete action
- `onToggleStatus?: (taskId: number) => void` - Callback for status toggle
- `onViewSubtasks?: (task: Task) => void` - Callback for subtask management
- `loading?: boolean` - Loading state
- `showFilters?: boolean` - Whether to show filter controls
- `showSearch?: boolean` - Whether to show search input
- `viewMode?: 'list' | 'grid'` - Default view mode

**Usage:**
```tsx
<TaskList
  tasks={tasks}
  onEdit={handleEdit}
  onDelete={handleDelete}
  onToggleStatus={handleToggleStatus}
  onViewSubtasks={handleViewSubtasks}
  showFilters={true}
  showSearch={true}
/>
```

### TaskForm
**File:** `TaskForm.tsx`

An enhanced form component for creating and editing tasks with comprehensive validation.

**Features:**
- Comprehensive form validation with real-time feedback
- Rich UI with icons and descriptions
- Due date validation (prevents past dates)
- Parent task selection for creating subtasks
- Character count limits and validation
- Loading states and error handling
- Responsive design with proper accessibility
- Optional card wrapper

**Props:**
- `task?: Task` - Task to edit (optional for create mode)
- `onSubmit: (taskData: TaskFormData) => void` - Form submission callback
- `onCancel: () => void` - Cancel callback
- `availableParents?: Task[]` - Available parent tasks for subtask creation
- `loading?: boolean` - Loading state
- `showCard?: boolean` - Whether to wrap form in a card

**Usage:**
```tsx
<TaskForm
  task={editingTask}
  onSubmit={handleSubmit}
  onCancel={handleCancel}
  availableParents={parentTasks}
  loading={loading}
  showCard={true}
/>
```

### SubtaskManager
**File:** `SubtaskManager.tsx`

A specialized component for managing subtasks with progress tracking and multiple view modes.

**Features:**
- Progress overview with completion percentage
- Visual progress bar and statistics
- Add/edit/delete subtask functionality
- List and card view modes
- Inline editing capabilities
- Bulk operations support
- Parent task context display
- Real-time progress updates

**Props:**
- `parentTask: Task` - The parent task
- `subtasks: Task[]` - Array of subtasks
- `onAddSubtask: (subtaskData: TaskFormData) => void` - Add subtask callback
- `onUpdateSubtask: (subtaskId: number, data: TaskFormData) => void` - Update callback
- `onDeleteSubtask: (subtaskId: number) => void` - Delete callback
- `onToggleSubtaskStatus?: (subtaskId: number) => void` - Status toggle callback
- `loading?: boolean` - Loading state
- `allowNesting?: boolean` - Whether to allow nested subtasks

**Usage:**
```tsx
<SubtaskManager
  parentTask={parentTask}
  subtasks={subtasks}
  onAddSubtask={handleAddSubtask}
  onUpdateSubtask={handleUpdateSubtask}
  onDeleteSubtask={handleDeleteSubtask}
  onToggleSubtaskStatus={handleToggleSubtaskStatus}
  allowNesting={false}
/>
```

## Key Features Implemented

### 1. Hierarchical Task Display
- Tasks can have unlimited levels of subtasks
- Visual indentation and connection lines
- Expandable/collapsible subtask sections
- Parent-child relationship preservation

### 2. Advanced Filtering and Sorting
- Real-time search across task names and descriptions
- Filter by status, priority, and completion state
- Multiple sorting options with ascending/descending order
- Persistent filter state

### 3. Enhanced User Experience
- Responsive design for all screen sizes
- Loading states and skeleton screens
- Empty states with helpful messaging
- Hover effects and smooth transitions
- Accessibility compliance (ARIA labels, keyboard navigation)

### 4. Comprehensive Form Validation
- Real-time validation with immediate feedback
- Character limits and format validation
- Due date validation (no past dates)
- Required field validation
- Error summary display

### 5. Progress Tracking
- Visual progress indicators for parent tasks
- Completion percentage calculations
- Status-based color coding
- Progress statistics display

## Design Patterns Used

### 1. Compound Components
Components are designed to work together seamlessly while maintaining independence.

### 2. Render Props Pattern
Flexible callback system for handling user interactions.

### 3. Controlled Components
All form inputs are controlled with proper state management.

### 4. Responsive Design
Mobile-first approach with progressive enhancement.

### 5. Accessibility First
WCAG 2.1 compliance with proper ARIA attributes and keyboard navigation.

## Integration with Backend

These components are designed to work with the Laravel backend API:

- Task CRUD operations through API endpoints
- Real-time updates via WebSocket connections
- Optimistic updates for better UX
- Error handling and retry mechanisms

## Testing Considerations

Components include:
- Comprehensive prop validation
- Error boundary integration
- Loading state management
- Edge case handling (empty states, network errors)

## Future Enhancements

Planned improvements:
- Drag-and-drop task reordering
- Bulk operations (multi-select)
- Advanced filtering (date ranges, custom fields)
- Task templates and duplication
- Collaborative features (comments, assignments)
- Offline support with sync capabilities

## Dependencies

- React 19+
- React Hook Form for form management
- Lucide React for icons
- Tailwind CSS for styling
- shadcn/ui components for consistent UI
- TypeScript for type safety