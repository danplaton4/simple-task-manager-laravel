import { useState, useCallback } from 'react';
import { useTask } from '@/contexts/TaskContext';
import { Task, TaskFormData } from '@/types';

interface UseTaskOperationsReturn {
  // Loading states for individual operations
  isCreating: boolean;
  isUpdating: boolean;
  isDeleting: boolean;
  isToggling: boolean;
  
  // Operation functions with loading states
  createTaskWithLoading: (taskData: TaskFormData) => Promise<Task | null>;
  updateTaskWithLoading: (id: number, taskData: Partial<TaskFormData>) => Promise<Task | null>;
  deleteTaskWithLoading: (id: number) => Promise<boolean>;
  toggleTaskStatusWithLoading: (id: number, newStatus: Task['status']) => Promise<Task | null>;
  
  // Batch operations
  bulkUpdateWithLoading: (updates: Array<{ id: number; data: Partial<TaskFormData> }>) => Promise<Task[] | null>;
}

export const useTaskOperations = (): UseTaskOperationsReturn => {
  const {
    createTask,
    updateTask,
    deleteTask,
    toggleTaskStatus,
    bulkUpdateTasks
  } = useTask();

  const [isCreating, setIsCreating] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isToggling, setIsToggling] = useState(false);

  const createTaskWithLoading = useCallback(async (taskData: TaskFormData): Promise<Task | null> => {
    try {
      setIsCreating(true);
      const task = await createTask(taskData);
      return task;
    } catch (error) {
      console.error('Failed to create task:', error);
      return null;
    } finally {
      setIsCreating(false);
    }
  }, [createTask]);

  const updateTaskWithLoading = useCallback(async (id: number, taskData: Partial<TaskFormData>): Promise<Task | null> => {
    try {
      setIsUpdating(true);
      const task = await updateTask(id, taskData);
      return task;
    } catch (error) {
      console.error('Failed to update task:', error);
      return null;
    } finally {
      setIsUpdating(false);
    }
  }, [updateTask]);

  const deleteTaskWithLoading = useCallback(async (id: number): Promise<boolean> => {
    try {
      setIsDeleting(true);
      await deleteTask(id);
      return true;
    } catch (error) {
      console.error('Failed to delete task:', error);
      return false;
    } finally {
      setIsDeleting(false);
    }
  }, [deleteTask]);

  const toggleTaskStatusWithLoading = useCallback(async (id: number, newStatus: Task['status']): Promise<Task | null> => {
    try {
      setIsToggling(true);
      const task = await toggleTaskStatus(id, newStatus);
      return task;
    } catch (error) {
      console.error('Failed to toggle task status:', error);
      return null;
    } finally {
      setIsToggling(false);
    }
  }, [toggleTaskStatus]);

  const bulkUpdateWithLoading = useCallback(async (updates: Array<{ id: number; data: Partial<TaskFormData> }>): Promise<Task[] | null> => {
    try {
      setIsUpdating(true);
      const tasks = await bulkUpdateTasks(updates);
      return tasks;
    } catch (error) {
      console.error('Failed to bulk update tasks:', error);
      return null;
    } finally {
      setIsUpdating(false);
    }
  }, [bulkUpdateTasks]);

  return {
    isCreating,
    isUpdating,
    isDeleting,
    isToggling,
    createTaskWithLoading,
    updateTaskWithLoading,
    deleteTaskWithLoading,
    toggleTaskStatusWithLoading,
    bulkUpdateWithLoading
  };
};