import { Task, TaskFormData } from '@/types';

/**
 * Creates an optimistic task for immediate UI updates
 */
export const createOptimisticTask = (taskData: TaskFormData, userId: number): Task => {
  return {
    id: Date.now(), // Temporary ID
    name: taskData.name,
    description: taskData.description,
    status: taskData.status,
    priority: taskData.priority,
    due_date: taskData.due_date,
    parent_id: taskData.parent_id,
    user_id: userId,
    subtasks: [],
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  };
};

/**
 * Applies optimistic updates to a task
 */
export const applyOptimisticUpdate = (task: Task, updates: Partial<Task>): Task => {
  return {
    ...task,
    ...updates,
    updated_at: new Date().toISOString()
  };
};

/**
 * Checks if a task is optimistic (has a temporary ID)
 */
export const isOptimisticTask = (task: Task): boolean => {
  // Optimistic tasks have IDs that are timestamps (very large numbers)
  return task.id > 1000000000000; // Timestamp-based IDs are much larger
};

/**
 * Replaces an optimistic task with the real task from the server
 */
export const replaceOptimisticTask = (tasks: Task[], optimisticId: number, realTask: Task): Task[] => {
  return tasks.map(task => 
    task.id === optimisticId ? realTask : task
  );
};

/**
 * Removes an optimistic task (used when operation fails)
 */
export const removeOptimisticTask = (tasks: Task[], optimisticId: number): Task[] => {
  return tasks.filter(task => task.id !== optimisticId);
};

/**
 * Handles error recovery by reverting optimistic updates
 */
export const revertOptimisticUpdate = (
  tasks: Task[], 
  taskId: number, 
  originalTask: Task
): Task[] => {
  return tasks.map(task => 
    task.id === taskId ? originalTask : task
  );
};

/**
 * Debounces multiple optimistic updates to prevent excessive API calls
 */
export const debounceOptimisticUpdate = (() => {
  const timeouts = new Map<string, NodeJS.Timeout>();
  
  return (key: string, callback: () => void, delay = 500) => {
    // Clear existing timeout for this key
    const existingTimeout = timeouts.get(key);
    if (existingTimeout) {
      clearTimeout(existingTimeout);
    }
    
    // Set new timeout
    const newTimeout = setTimeout(() => {
      callback();
      timeouts.delete(key);
    }, delay);
    
    timeouts.set(key, newTimeout);
  };
})();

/**
 * Validates task data before optimistic update
 */
export const validateTaskData = (taskData: Partial<TaskFormData>): boolean => {
  // Basic validation rules
  if (taskData.name !== undefined && taskData.name.trim().length === 0) {
    return false;
  }
  
  if (taskData.status && !['pending', 'in_progress', 'completed', 'cancelled'].includes(taskData.status)) {
    return false;
  }
  
  if (taskData.priority && !['low', 'medium', 'high', 'urgent'].includes(taskData.priority)) {
    return false;
  }
  
  return true;
};

/**
 * Generates a conflict resolution strategy for optimistic updates
 */
export const resolveOptimisticConflict = (
  localTask: Task, 
  serverTask: Task, 
  strategy: 'server-wins' | 'local-wins' | 'merge' = 'server-wins'
): Task => {
  switch (strategy) {
    case 'local-wins':
      return localTask;
    
    case 'merge':
      // Merge strategy: keep local changes for user-editable fields,
      // but use server data for system fields
      return {
        ...serverTask,
        name: localTask.name,
        description: localTask.description,
        status: localTask.status,
        priority: localTask.priority,
        due_date: localTask.due_date
      };
    
    case 'server-wins':
    default:
      return serverTask;
  }
};