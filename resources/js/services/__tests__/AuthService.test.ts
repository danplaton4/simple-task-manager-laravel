import { describe, it, expect, beforeEach, vi } from 'vitest';
import axios from 'axios';
import AuthService from '../AuthService';
import { LoginCredentials, RegisterData } from '@/types';

// Mock axios
vi.mock('axios');
const mockedAxios = vi.mocked(axios);

// Mock localStorage
const localStorageMock = {
  getItem: vi.fn(),
  setItem: vi.fn(),
  removeItem: vi.fn(),
  clear: vi.fn(),
};
Object.defineProperty(window, 'localStorage', {
  value: localStorageMock
});

describe('AuthService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorageMock.getItem.mockClear();
    localStorageMock.setItem.mockClear();
    localStorageMock.removeItem.mockClear();
  });

  describe('login', () => {
    it('should login successfully and store token', async () => {
      const credentials: LoginCredentials = {
        email: 'test@example.com',
        password: 'password123'
      };

      const mockResponse = {
        data: {
          user: {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            preferred_language: 'en',
            timezone: 'UTC',
            created_at: '2023-01-01T00:00:00Z',
            updated_at: '2023-01-01T00:00:00Z'
          },
          token: 'mock-jwt-token'
        }
      };

      mockedAxios.post.mockResolvedValueOnce(mockResponse);

      const result = await AuthService.login(credentials);

      expect(mockedAxios.post).toHaveBeenCalledWith('/auth/login', credentials);
      expect(localStorageMock.setItem).toHaveBeenCalledWith('auth_token', 'mock-jwt-token');
      expect(result).toEqual(mockResponse.data);
    });

    it('should handle login errors', async () => {
      const credentials: LoginCredentials = {
        email: 'test@example.com',
        password: 'wrongpassword'
      };

      const mockError = {
        response: {
          data: {
            message: 'Invalid credentials'
          }
        }
      };

      mockedAxios.post.mockRejectedValueOnce(mockError);
      mockedAxios.isAxiosError.mockReturnValueOnce(true);

      await expect(AuthService.login(credentials)).rejects.toThrow('Invalid credentials');
    });
  });

  describe('register', () => {
    it('should register successfully and store token', async () => {
      const userData: RegisterData = {
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123',
        password_confirmation: 'password123',
        preferred_language: 'en',
        timezone: 'UTC'
      };

      const mockResponse = {
        data: {
          user: {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            preferred_language: 'en',
            timezone: 'UTC',
            created_at: '2023-01-01T00:00:00Z',
            updated_at: '2023-01-01T00:00:00Z'
          },
          token: 'mock-jwt-token'
        }
      };

      mockedAxios.post.mockResolvedValueOnce(mockResponse);

      const result = await AuthService.register(userData);

      expect(mockedAxios.post).toHaveBeenCalledWith('/auth/register', userData);
      expect(localStorageMock.setItem).toHaveBeenCalledWith('auth_token', 'mock-jwt-token');
      expect(result).toEqual(mockResponse.data);
    });
  });

  describe('logout', () => {
    it('should logout and clear token', async () => {
      mockedAxios.post.mockResolvedValueOnce({});

      await AuthService.logout();

      expect(mockedAxios.post).toHaveBeenCalledWith('/auth/logout');
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('auth_token');
    });

    it('should clear token even if API call fails', async () => {
      mockedAxios.post.mockRejectedValueOnce(new Error('Network error'));

      await AuthService.logout();

      expect(localStorageMock.removeItem).toHaveBeenCalledWith('auth_token');
    });
  });

  describe('getCurrentUser', () => {
    it('should fetch current user data', async () => {
      const mockUser = {
        id: 1,
        name: 'Test User',
        email: 'test@example.com',
        preferred_language: 'en',
        timezone: 'UTC',
        created_at: '2023-01-01T00:00:00Z',
        updated_at: '2023-01-01T00:00:00Z'
      };

      mockedAxios.get.mockResolvedValueOnce({ data: mockUser });

      const result = await AuthService.getCurrentUser();

      expect(mockedAxios.get).toHaveBeenCalledWith('/auth/me');
      expect(result).toEqual(mockUser);
    });
  });

  describe('isAuthenticated', () => {
    it('should return true when token exists', () => {
      localStorageMock.getItem.mockReturnValueOnce('mock-token');

      const result = AuthService.isAuthenticated();

      expect(result).toBe(true);
      expect(localStorageMock.getItem).toHaveBeenCalledWith('auth_token');
    });

    it('should return false when token does not exist', () => {
      localStorageMock.getItem.mockReturnValueOnce(null);

      const result = AuthService.isAuthenticated();

      expect(result).toBe(false);
    });
  });

  describe('getToken', () => {
    it('should return token from localStorage', () => {
      localStorageMock.getItem.mockReturnValueOnce('mock-token');

      const result = AuthService.getToken();

      expect(result).toBe('mock-token');
      expect(localStorageMock.getItem).toHaveBeenCalledWith('auth_token');
    });
  });

  describe('clearAuth', () => {
    it('should clear token from localStorage', () => {
      AuthService.clearAuth();

      expect(localStorageMock.removeItem).toHaveBeenCalledWith('auth_token');
    });
  });
});