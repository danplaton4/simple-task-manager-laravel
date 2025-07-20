import axios from 'axios';
import { Task, TaskFormData, TaskFilters, ApiResponse, PaginatedResponse } from '@/types';

class TaskService {
  private static readonly ENDPOINTS = {
    TASKS: '/api/tasks',
    TASK: (id: number) => `/api/tasks/${id}`,
    RESTORE: (id: number) => `/api/tasks/${id}/restore`
  };

  /**
   * Get all tasks with optional filtering and pagination
   */
  static async getTasks(filters?: TaskFilters, page = 1, perPage = 15): Promise<PaginatedResponse<Task>> {
    try {
      const params = new URLSearchParams();
      
      if (filters?.status) params.append('status', filters.status);
      if (filters?.priority) params.append('priority', filters.priority);
      if (filters?.search) params.append('search', filters.search);
      if (filters?.parent_id) params.append('parent_id', filters.parent_id.toString());
      
      params.append('page', page.toString());
      params.append('per_page', perPage.toString());

      const response = await axios.get<PaginatedResponse<Task>>(
        `${this.ENDPOINTS.TASKS}?${params.toString()}`
      );
      
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to fetch tasks.'
        );
      }
      throw new Error('An unexpected error occurred while fetching tasks.');
    }
  }

  /**
   * Get a specific task by ID
   */
  static async getTask(id: number): Promise<Task> {
    try {
      const response = await axios.get<ApiResponse<Task>>(
        this.ENDPOINTS.TASK(id)
      );
      
      return response.data.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to fetch task.'
        );
      }
      throw new Error('An unexpected error occurred while fetching the task.');
    }
  }

  /**
   * Create a new task
   */
  static async createTask(taskData: TaskFormData): Promise<Task> {
    try {
      const response = await axios.post<ApiResponse<Task>>(
        this.ENDPOINTS.TASKS,
        taskData
      );
      
      return response.data.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const errorMessage = error.response?.data?.message || 
          'Failed to create task. Please try again.';
        throw new Error(errorMessage);
      }
      throw new Error('An unexpected error occurred while creating the task.');
    }
  }

  /**
   * Update an existing task
   */
  static async updateTask(id: number, taskData: Partial<TaskFormData>): Promise<Task> {
    try {
      const response = await axios.put<ApiResponse<Task>>(
        this.ENDPOINTS.TASK(id),
        taskData
      );
      
      return response.data.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const errorMessage = error.response?.data?.message || 
          'Failed to update task. Please try again.';
        throw new Error(errorMessage);
      }
      throw new Error('An unexpected error occurred while updating the task.');
    }
  }

  /**
   * Delete a task (soft delete)
   */
  static async deleteTask(id: number): Promise<void> {
    try {
      await axios.delete(this.ENDPOINTS.TASK(id));
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to delete task.'
        );
      }
      throw new Error('An unexpected error occurred while deleting the task.');
    }
  }

  /**
   * Restore a soft-deleted task
   */
  static async restoreTask(id: number): Promise<Task> {
    try {
      const response = await axios.post<ApiResponse<Task>>(
        this.ENDPOINTS.RESTORE(id)
      );
      
      return response.data.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to restore task.'
        );
      }
      throw new Error('An unexpected error occurred while restoring the task.');
    }
  }

  /**
   * Toggle task status between pending/in_progress/completed
   */
  static async toggleTaskStatus(id: number, newStatus: Task['status']): Promise<Task> {
    try {
      const response = await axios.put<ApiResponse<Task>>(
        this.ENDPOINTS.TASK(id),
        { status: newStatus }
      );
      
      return response.data.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to update task status.'
        );
      }
      throw new Error('An unexpected error occurred while updating task status.');
    }
  }

  /**
   * Get subtasks for a specific parent task
   */
  static async getSubtasks(parentId: number): Promise<Task[]> {
    try {
      const response = await axios.get<PaginatedResponse<Task>>(
        `${this.ENDPOINTS.TASKS}?parent_id=${parentId}`
      );
      
      return response.data.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to fetch subtasks.'
        );
      }
      throw new Error('An unexpected error occurred while fetching subtasks.');
    }
  }

  /**
   * Create a subtask for a parent task
   */
  static async createSubtask(parentId: number, taskData: Omit<TaskFormData, 'parent_id'>): Promise<Task> {
    const subtaskData: TaskFormData = {
      ...taskData,
      parent_id: parentId
    };
    
    return this.createTask(subtaskData);
  }

  /**
   * Bulk update multiple tasks
   */
  static async bulkUpdateTasks(updates: Array<{ id: number; data: Partial<TaskFormData> }>): Promise<Task[]> {
    try {
      const response = await axios.patch<ApiResponse<Task[]>>(
        `${this.ENDPOINTS.TASKS}/bulk`,
        { updates }
      );
      
      return response.data.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to bulk update tasks.'
        );
      }
      throw new Error('An unexpected error occurred during bulk update.');
    }
  }
}

export default TaskService;