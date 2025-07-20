import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { Task, TaskFormData, TaskFilters, PaginatedResponse } from '@/types';
import TaskService from '@/services/TaskService';
import { webSocketService, TaskUpdateEvent } from '@/services/WebSocketService';
import { useAuth } from '@/contexts/AuthContext';
import { useNotifications } from '@/components/ui/notification';

interface TaskState {
  tasks: Task[];
  currentTask: Task | null;
  isLoading: boolean;
  error: string | null;
  pagination: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
  };
  filters: TaskFilters;
}

interface TaskContextType extends TaskState {
  // Task operations
  fetchTasks: (page?: number, filters?: TaskFilters) => Promise<void>;
  fetchTask: (id: number) => Promise<void>;
  createTask: (taskData: TaskFormData) => Promise<Task>;
  updateTask: (id: number, taskData: Partial<TaskFormData>) => Promise<Task>;
  deleteTask: (id: number) => Promise<void>;
  restoreTask: (id: number) => Promise<Task>;
  toggleTaskStatus: (id: number, newStatus: Task['status']) => Promise<Task>;
  
  // Subtask operations
  fetchSubtasks: (parentId: number) => Promise<Task[]>;
  createSubtask: (parentId: number, taskData: Omit<TaskFormData, 'parent_id'>) => Promise<Task>;
  
  // Bulk operations
  bulkUpdateTasks: (updates: Array<{ id: number; data: Partial<TaskFormData> }>) => Promise<Task[]>;
  reorderTasks: (taskIds: number[]) => Promise<void>;
  
  // State management
  setFilters: (filters: TaskFilters) => void;
  clearError: () => void;
  clearCurrentTask: () => void;
}

const initialState: TaskState = {
  tasks: [],
  currentTask: null,
  isLoading: false,
  error: null,
  pagination: {
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0
  },
  filters: {}
};

const TaskContext = createContext<TaskContextType | undefined>(undefined);

export const useTask = () => {
  const context = useContext(TaskContext);
  if (context === undefined) {
    throw new Error('useTask must be used within a TaskProvider');
  }
  return context;
};

interface TaskProviderProps {
  children: React.ReactNode;
}

export const TaskProvider: React.FC<TaskProviderProps> = ({ children }) => {
  const [state, setState] = useState<TaskState>(initialState);
  const { user } = useAuth();
  const { addNotification } = useNotifications();

  // Helper function to update state
  const updateState = useCallback((updates: Partial<TaskState>) => {
    setState(prev => ({ ...prev, ...updates }));
  }, []);

  // Helper function for optimistic updates
  const optimisticUpdate = useCallback((taskId: number, updates: Partial<Task>) => {
    setState(prev => ({
      ...prev,
      tasks: prev.tasks.map(task => 
        task.id === taskId ? { ...task, ...updates } : task
      ),
      currentTask: prev.currentTask?.id === taskId 
        ? { ...prev.currentTask, ...updates }
        : prev.currentTask
    }));
  }, []);

  // Helper function to add task optimistically
  const addTaskOptimistically = useCallback((task: Task) => {
    setState(prev => ({
      ...prev,
      tasks: [task, ...prev.tasks],
      pagination: {
        ...prev.pagination,
        total: prev.pagination.total + 1
      }
    }));
  }, []);

  // Helper function to remove task optimistically
  const removeTaskOptimistically = useCallback((taskId: number) => {
    setState(prev => ({
      ...prev,
      tasks: prev.tasks.filter(task => task.id !== taskId),
      currentTask: prev.currentTask?.id === taskId ? null : prev.currentTask,
      pagination: {
        ...prev.pagination,
        total: Math.max(0, prev.pagination.total - 1)
      }
    }));
  }, []);

  // Fetch tasks with pagination and filtering
  const fetchTasks = useCallback(async (page = 1, filters?: TaskFilters) => {
    try {
      updateState({ isLoading: true, error: null });
      
      const filtersToUse = filters || state.filters;
      const response = await TaskService.getTasks(filtersToUse, page, state.pagination.perPage);
      
      updateState({
        tasks: response.data,
        pagination: {
          currentPage: response.current_page,
          lastPage: response.last_page,
          perPage: response.per_page,
          total: response.total
        },
        filters: filtersToUse,
        isLoading: false
      });
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to fetch tasks';
      updateState({ error: errorMessage, isLoading: false });
    }
  }, [state.filters, state.pagination.perPage, updateState]);

  // Fetch single task
  const fetchTask = useCallback(async (id: number) => {
    try {
      updateState({ isLoading: true, error: null });
      
      const task = await TaskService.getTask(id);
      updateState({ currentTask: task, isLoading: false });
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to fetch task';
      updateState({ error: errorMessage, isLoading: false });
    }
  }, [updateState]);

  // Create task with optimistic update
  const createTask = useCallback(async (taskData: TaskFormData): Promise<Task> => {
    try {
      updateState({ error: null });
      
      // Create optimistic task
      const optimisticTask: Task = {
        id: Date.now(), // Temporary ID
        name: taskData.name,
        description: taskData.description,
        status: taskData.status,
        priority: taskData.priority,
        due_date: taskData.due_date,
        parent_id: taskData.parent_id,
        user_id: 0, // Will be set by server
        subtasks: [],
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };
      
      // Add optimistically
      addTaskOptimistically(optimisticTask);
      
      // Make API call
      const createdTask = await TaskService.createTask(taskData);
      
      // Replace optimistic task with real task
      setState(prev => ({
        ...prev,
        tasks: prev.tasks.map(task => 
          task.id === optimisticTask.id ? createdTask : task
        )
      }));
      
      return createdTask;
    } catch (error) {
      // Remove optimistic task on error
      removeTaskOptimistically(Date.now());
      
      const errorMessage = error instanceof Error ? error.message : 'Failed to create task';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState, addTaskOptimistically, removeTaskOptimistically]);

  // Update task with optimistic update
  const updateTask = useCallback(async (id: number, taskData: Partial<TaskFormData>): Promise<Task> => {
    try {
      updateState({ error: null });
      
      // Apply optimistic update
      optimisticUpdate(id, taskData);
      
      // Make API call
      const updatedTask = await TaskService.updateTask(id, taskData);
      
      // Update with real data
      optimisticUpdate(id, updatedTask);
      
      return updatedTask;
    } catch (error) {
      // Revert optimistic update by refetching
      await fetchTasks(state.pagination.currentPage, state.filters);
      
      const errorMessage = error instanceof Error ? error.message : 'Failed to update task';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState, optimisticUpdate, fetchTasks, state.pagination.currentPage, state.filters]);

  // Delete task with optimistic update
  const deleteTask = useCallback(async (id: number): Promise<void> => {
    try {
      updateState({ error: null });
      
      // Remove optimistically
      removeTaskOptimistically(id);
      
      // Make API call
      await TaskService.deleteTask(id);
    } catch (error) {
      // Revert by refetching
      await fetchTasks(state.pagination.currentPage, state.filters);
      
      const errorMessage = error instanceof Error ? error.message : 'Failed to delete task';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState, removeTaskOptimistically, fetchTasks, state.pagination.currentPage, state.filters]);

  // Restore task
  const restoreTask = useCallback(async (id: number): Promise<Task> => {
    try {
      updateState({ error: null });
      
      const restoredTask = await TaskService.restoreTask(id);
      
      // Add restored task to the list
      addTaskOptimistically(restoredTask);
      
      return restoredTask;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to restore task';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState, addTaskOptimistically]);

  // Toggle task status with optimistic update
  const toggleTaskStatus = useCallback(async (id: number, newStatus: Task['status']): Promise<Task> => {
    try {
      updateState({ error: null });
      
      // Apply optimistic update
      optimisticUpdate(id, { status: newStatus });
      
      // Make API call
      const updatedTask = await TaskService.toggleTaskStatus(id, newStatus);
      
      // Update with real data
      optimisticUpdate(id, updatedTask);
      
      return updatedTask;
    } catch (error) {
      // Revert optimistic update
      await fetchTasks(state.pagination.currentPage, state.filters);
      
      const errorMessage = error instanceof Error ? error.message : 'Failed to update task status';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState, optimisticUpdate, fetchTasks, state.pagination.currentPage, state.filters]);

  // Fetch subtasks
  const fetchSubtasks = useCallback(async (parentId: number): Promise<Task[]> => {
    try {
      updateState({ error: null });
      
      const subtasks = await TaskService.getSubtasks(parentId);
      
      // Update parent task with subtasks
      setState(prev => ({
        ...prev,
        tasks: prev.tasks.map(task => 
          task.id === parentId ? { ...task, subtasks } : task
        ),
        currentTask: prev.currentTask?.id === parentId 
          ? { ...prev.currentTask, subtasks }
          : prev.currentTask
      }));
      
      return subtasks;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to fetch subtasks';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState]);

  // Create subtask
  const createSubtask = useCallback(async (parentId: number, taskData: Omit<TaskFormData, 'parent_id'>): Promise<Task> => {
    try {
      updateState({ error: null });
      
      const subtask = await TaskService.createSubtask(parentId, taskData);
      
      // Update parent task's subtasks
      setState(prev => ({
        ...prev,
        tasks: prev.tasks.map(task => 
          task.id === parentId 
            ? { ...task, subtasks: [...(task.subtasks || []), subtask] }
            : task
        ),
        currentTask: prev.currentTask?.id === parentId 
          ? { ...prev.currentTask, subtasks: [...(prev.currentTask.subtasks || []), subtask] }
          : prev.currentTask
      }));
      
      return subtask;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to create subtask';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState]);

  // Bulk update tasks
  const bulkUpdateTasks = useCallback(async (updates: Array<{ id: number; data: Partial<TaskFormData> }>): Promise<Task[]> => {
    try {
      updateState({ error: null });
      
      // Apply optimistic updates
      updates.forEach(({ id, data }) => {
        optimisticUpdate(id, data);
      });
      
      // Make API call
      const updatedTasks = await TaskService.bulkUpdateTasks(updates);
      
      // Update with real data
      setState(prev => ({
        ...prev,
        tasks: prev.tasks.map(task => {
          const updatedTask = updatedTasks.find(ut => ut.id === task.id);
          return updatedTask || task;
        })
      }));
      
      return updatedTasks;
    } catch (error) {
      // Revert by refetching
      await fetchTasks(state.pagination.currentPage, state.filters);
      
      const errorMessage = error instanceof Error ? error.message : 'Failed to bulk update tasks';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState, optimisticUpdate, fetchTasks, state.pagination.currentPage, state.filters]);

  // Reorder tasks
  const reorderTasks = useCallback(async (taskIds: number[]): Promise<void> => {
    try {
      updateState({ error: null });
      
      // Apply optimistic reordering
      const reorderedTasks = taskIds.map(id => 
        state.tasks.find(task => task.id === id)
      ).filter(Boolean) as Task[];
      
      // Add any tasks not in the reorder list
      const remainingTasks = state.tasks.filter(task => !taskIds.includes(task.id));
      
      setState(prev => ({
        ...prev,
        tasks: [...reorderedTasks, ...remainingTasks]
      }));
      
      // Make API call (if you have a reorder endpoint)
      // await TaskService.reorderTasks(taskIds);
      
      // For now, we'll just send WebSocket updates
      webSocketService.sendTaskUpdate(0, 'updated');
      
    } catch (error) {
      // Revert by refetching
      await fetchTasks(state.pagination.currentPage, state.filters);
      
      const errorMessage = error instanceof Error ? error.message : 'Failed to reorder tasks';
      updateState({ error: errorMessage });
      throw error;
    }
  }, [updateState, state.tasks, state.pagination.currentPage, state.filters, fetchTasks]);

  // Set filters
  const setFilters = useCallback((filters: TaskFilters) => {
    updateState({ filters });
  }, [updateState]);

  // Clear error
  const clearError = useCallback(() => {
    updateState({ error: null });
  }, [updateState]);

  // Clear current task
  const clearCurrentTask = useCallback(() => {
    updateState({ currentTask: null });
  }, [updateState]);

  // Handle real-time task updates
  const handleTaskUpdate = useCallback((event: TaskUpdateEvent) => {
    const taskName = event.task_data?.name || `Task #${event.task_id}`;
    
    switch (event.action) {
      case 'created':
        if (event.task_data) {
          addTaskOptimistically(event.task_data);
          addNotification({
            type: 'success',
            title: 'Task Created',
            message: `"${taskName}" has been created`,
            duration: 3000
          });
        }
        break;
      case 'updated':
        if (event.task_data) {
          optimisticUpdate(event.task_id, event.task_data);
          addNotification({
            type: 'info',
            title: 'Task Updated',
            message: `"${taskName}" has been updated`,
            duration: 3000
          });
        }
        break;
      case 'deleted':
        removeTaskOptimistically(event.task_id);
        addNotification({
          type: 'warning',
          title: 'Task Deleted',
          message: `"${taskName}" has been deleted`,
          duration: 3000
        });
        break;
      case 'restored':
        if (event.task_data) {
          addTaskOptimistically(event.task_data);
          addNotification({
            type: 'success',
            title: 'Task Restored',
            message: `"${taskName}" has been restored`,
            duration: 3000
          });
        }
        break;
    }
  }, [addTaskOptimistically, optimisticUpdate, removeTaskOptimistically, addNotification]);

  // Set up WebSocket connection
  useEffect(() => {
    if (user?.id) {
      webSocketService.connect(user.id);
      const unsubscribe = webSocketService.subscribe(handleTaskUpdate);
      
      return () => {
        unsubscribe();
        webSocketService.disconnect();
      };
    }
  }, [user?.id, handleTaskUpdate]);

  // Load initial tasks on mount
  useEffect(() => {
    fetchTasks();
  }, []);

  const value: TaskContextType = {
    ...state,
    fetchTasks,
    fetchTask,
    createTask,
    updateTask,
    deleteTask,
    restoreTask,
    toggleTaskStatus,
    fetchSubtasks,
    createSubtask,
    bulkUpdateTasks,
    reorderTasks,
    setFilters,
    clearError,
    clearCurrentTask
  };

  return (
    <TaskContext.Provider value={value}>
      {children}
    </TaskContext.Provider>
  );
};