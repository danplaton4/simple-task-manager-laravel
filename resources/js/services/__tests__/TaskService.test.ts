import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import axios from 'axios';
import TaskService from '../TaskService';
import { TaskFormData, Task } from '@/types';

// Mock axios
vi.mock('axios');
const mockedAxios = vi.mocked(axios);

describe('TaskService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  describe('getTasks', () => {
    it('should fetch tasks with default parameters', async () => {
      const mockResponse = {
        data: {
          data: [
            { id: 1, name: 'Test Task', status: 'pending', priority: 'medium' }
          ],
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 1
        }
      };

      mockedAxios.get.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.getTasks();

      expect(mockedAxios.get).toHaveBeenCalledWith('/api/tasks?page=1&per_page=15');
      expect(result).toEqual(mockResponse.data);
    });

    it('should fetch tasks with filters', async () => {
      const mockResponse = {
        data: {
          data: [],
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 0
        }
      };

      mockedAxios.get.mockResolvedValueOnce(mockResponse);

      const filters = { status: 'completed' as const, priority: 'high' as const };
      await TaskService.getTasks(filters, 2, 10);

      expect(mockedAxios.get).toHaveBeenCalledWith(
        '/api/tasks?status=completed&priority=high&page=2&per_page=10'
      );
    });

    it('should handle API errors', async () => {
      const errorResponse = {
        response: {
          data: { message: 'Server error' }
        }
      };

      mockedAxios.get.mockRejectedValueOnce(errorResponse);

      await expect(TaskService.getTasks()).rejects.toThrow('Server error');
    });
  });

  describe('getTask', () => {
    it('should fetch a single task', async () => {
      const mockTask: Task = {
        id: 1,
        name: 'Test Task',
        status: 'pending',
        priority: 'medium',
        user_id: 1,
        subtasks: [],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z'
      };

      const mockResponse = {
        data: { data: mockTask }
      };

      mockedAxios.get.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.getTask(1);

      expect(mockedAxios.get).toHaveBeenCalledWith('/api/tasks/1');
      expect(result).toEqual(mockTask);
    });
  });

  describe('createTask', () => {
    it('should create a new task', async () => {
      const taskData: TaskFormData = {
        name: 'New Task',
        description: 'Task description',
        status: 'pending',
        priority: 'medium'
      };

      const mockTask: Task = {
        id: 1,
        ...taskData,
        user_id: 1,
        subtasks: [],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z'
      };

      const mockResponse = {
        data: { data: mockTask }
      };

      mockedAxios.post.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.createTask(taskData);

      expect(mockedAxios.post).toHaveBeenCalledWith('/api/tasks', taskData);
      expect(result).toEqual(mockTask);
    });
  });

  describe('updateTask', () => {
    it('should update an existing task', async () => {
      const updateData = { name: 'Updated Task' };
      const mockTask: Task = {
        id: 1,
        name: 'Updated Task',
        status: 'pending',
        priority: 'medium',
        user_id: 1,
        subtasks: [],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z'
      };

      const mockResponse = {
        data: { data: mockTask }
      };

      mockedAxios.put.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.updateTask(1, updateData);

      expect(mockedAxios.put).toHaveBeenCalledWith('/api/tasks/1', updateData);
      expect(result).toEqual(mockTask);
    });
  });

  describe('deleteTask', () => {
    it('should delete a task', async () => {
      mockedAxios.delete.mockResolvedValueOnce({});

      await TaskService.deleteTask(1);

      expect(mockedAxios.delete).toHaveBeenCalledWith('/api/tasks/1');
    });
  });

  describe('toggleTaskStatus', () => {
    it('should toggle task status', async () => {
      const mockTask: Task = {
        id: 1,
        name: 'Test Task',
        status: 'completed',
        priority: 'medium',
        user_id: 1,
        subtasks: [],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z'
      };

      const mockResponse = {
        data: { data: mockTask }
      };

      mockedAxios.put.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.toggleTaskStatus(1, 'completed');

      expect(mockedAxios.put).toHaveBeenCalledWith('/api/tasks/1', { status: 'completed' });
      expect(result).toEqual(mockTask);
    });
  });

  describe('getSubtasks', () => {
    it('should fetch subtasks for a parent task', async () => {
      const mockSubtasks: Task[] = [
        {
          id: 2,
          name: 'Subtask 1',
          status: 'pending',
          priority: 'low',
          parent_id: 1,
          user_id: 1,
          subtasks: [],
          created_at: '2024-01-01T00:00:00Z',
          updated_at: '2024-01-01T00:00:00Z'
        }
      ];

      const mockResponse = {
        data: { data: mockSubtasks }
      };

      mockedAxios.get.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.getSubtasks(1);

      expect(mockedAxios.get).toHaveBeenCalledWith('/api/tasks?parent_id=1');
      expect(result).toEqual(mockSubtasks);
    });
  });

  describe('createSubtask', () => {
    it('should create a subtask', async () => {
      const subtaskData = {
        name: 'Subtask',
        status: 'pending' as const,
        priority: 'low' as const
      };

      const mockSubtask: Task = {
        id: 2,
        ...subtaskData,
        parent_id: 1,
        user_id: 1,
        subtasks: [],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z'
      };

      const mockResponse = {
        data: { data: mockSubtask }
      };

      mockedAxios.post.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.createSubtask(1, subtaskData);

      expect(mockedAxios.post).toHaveBeenCalledWith('/api/tasks', {
        ...subtaskData,
        parent_id: 1
      });
      expect(result).toEqual(mockSubtask);
    });
  });

  describe('bulkUpdateTasks', () => {
    it('should bulk update multiple tasks', async () => {
      const updates = [
        { id: 1, data: { status: 'completed' as const } },
        { id: 2, data: { priority: 'high' as const } }
      ];

      const mockTasks: Task[] = [
        {
          id: 1,
          name: 'Task 1',
          status: 'completed',
          priority: 'medium',
          user_id: 1,
          subtasks: [],
          created_at: '2024-01-01T00:00:00Z',
          updated_at: '2024-01-01T00:00:00Z'
        },
        {
          id: 2,
          name: 'Task 2',
          status: 'pending',
          priority: 'high',
          user_id: 1,
          subtasks: [],
          created_at: '2024-01-01T00:00:00Z',
          updated_at: '2024-01-01T00:00:00Z'
        }
      ];

      const mockResponse = {
        data: { data: mockTasks }
      };

      mockedAxios.patch.mockResolvedValueOnce(mockResponse);

      const result = await TaskService.bulkUpdateTasks(updates);

      expect(mockedAxios.patch).toHaveBeenCalledWith('/api/tasks/bulk', { updates });
      expect(result).toEqual(mockTasks);
    });
  });
});