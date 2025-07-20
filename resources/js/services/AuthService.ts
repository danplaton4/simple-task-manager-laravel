import axios from 'axios';
import { LoginCredentials, RegisterData, AuthResponse, User } from '@/types';

class AuthService {
  private static readonly ENDPOINTS = {
    LOGIN: '/auth/login',
    REGISTER: '/auth/register',
    LOGOUT: '/auth/logout',
    REFRESH: '/auth/refresh',
    ME: '/auth/me'
  };

  /**
   * Authenticate user with email and password
   */
  static async login(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const response = await axios.post<AuthResponse>(
        this.ENDPOINTS.LOGIN,
        credentials
      );
      
      // Store the token in localStorage
      if (response.data.token) {
        localStorage.setItem('auth_token', response.data.token);
      }
      
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Login failed. Please try again.'
        );
      }
      throw new Error('An unexpected error occurred during login.');
    }
  }

  /**
   * Register a new user account
   */
  static async register(userData: RegisterData): Promise<AuthResponse> {
    try {
      const response = await axios.post<AuthResponse>(
        this.ENDPOINTS.REGISTER,
        userData
      );
      
      // Store the token in localStorage
      if (response.data.token) {
        localStorage.setItem('auth_token', response.data.token);
      }
      
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const errorMessage = error.response?.data?.message || 
          'Registration failed. Please try again.';
        throw new Error(errorMessage);
      }
      throw new Error('An unexpected error occurred during registration.');
    }
  }

  /**
   * Logout the current user
   */
  static async logout(): Promise<void> {
    try {
      await axios.post(this.ENDPOINTS.LOGOUT);
    } catch (error) {
      // Even if the API call fails, we should still clear local storage
      console.warn('Logout API call failed:', error);
    } finally {
      // Always clear the token from localStorage
      localStorage.removeItem('auth_token');
    }
  }

  /**
   * Refresh the authentication token
   */
  static async refreshToken(): Promise<string> {
    try {
      const response = await axios.post<{ token: string }>(
        this.ENDPOINTS.REFRESH
      );
      
      const newToken = response.data.token;
      localStorage.setItem('auth_token', newToken);
      
      return newToken;
    } catch (error) {
      // If refresh fails, clear the token and throw error
      localStorage.removeItem('auth_token');
      
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Token refresh failed.'
        );
      }
      throw new Error('An unexpected error occurred during token refresh.');
    }
  }

  /**
   * Get current authenticated user data
   */
  static async getCurrentUser(): Promise<User> {
    try {
      const response = await axios.get<User>(this.ENDPOINTS.ME);
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(
          error.response?.data?.message || 'Failed to fetch user data.'
        );
      }
      throw new Error('An unexpected error occurred while fetching user data.');
    }
  }

  /**
   * Check if user is currently authenticated
   */
  static isAuthenticated(): boolean {
    const token = localStorage.getItem('auth_token');
    return !!token;
  }

  /**
   * Get the current authentication token
   */
  static getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  /**
   * Clear authentication data
   */
  static clearAuth(): void {
    localStorage.removeItem('auth_token');
  }
}

export default AuthService;