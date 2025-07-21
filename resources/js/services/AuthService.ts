import axios from 'axios';
import { LoginCredentials, RegisterData, AuthResponse, User } from '@/types';

/**
 * Authentication Service for Laravel Sanctum SPA Authentication
 * 
 * This service implements proper SPA authentication as per Laravel Sanctum documentation:
 * https://laravel.com/docs/12.x/sanctum#spa-authentication
 * 
 * For SPAs, Sanctum uses stateful authentication with CSRF protection:
 * - First request gets CSRF cookie from /sanctum/csrf-cookie
 * - Authentication uses session cookies (not Bearer tokens)
 * - CSRF tokens are automatically handled by axios interceptors
 */
class AuthService {
  private static readonly ENDPOINTS = {
    CSRF_COOKIE: '/sanctum/csrf-cookie',
    LOGIN: '/auth/login',
    REGISTER: '/auth/register',
    LOGOUT: '/auth/logout',
    LOGOUT_ALL: '/auth/logout-all',
    ME: '/auth/me'
  } as const;

  /**
   * Get CSRF cookie before making authenticated requests
   */
  private static async getCsrfCookie(): Promise<void> {
    try {
      await axios.get(this.ENDPOINTS.CSRF_COOKIE);
    } catch (error) {
      console.warn('Failed to get CSRF cookie:', error);
      throw error;
    }
  }

  /**
   * Authenticate user with email and password
   */
  static async login(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      // Get CSRF cookie first (required for SPA authentication)
      await this.getCsrfCookie();
      
      const response = await axios.post<AuthResponse>(
        this.ENDPOINTS.LOGIN,
        credentials
      );
      
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const errorMessage = error.response?.data?.message || 'Login failed. Please try again.';
        throw new Error(errorMessage);
      }
      throw new Error('An unexpected error occurred during login.');
    }
  }

  /**
   * Register a new user account
   */
  static async register(userData: RegisterData): Promise<AuthResponse> {
    try {
      // Get CSRF cookie first (required for SPA authentication)
      await this.getCsrfCookie();
      
      const response = await axios.post<AuthResponse>(
        this.ENDPOINTS.REGISTER,
        userData
      );
      
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const data = error.response?.data;
        
        // Handle validation errors
        if (data && data.errors) {
          throw { 
            message: data.message || 'Registration failed.', 
            errors: data.errors 
          };
        }
        
        // Handle other API errors
        throw new Error(data?.message || 'Registration failed. Please try again.');
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
      console.warn('Logout API call failed:', error);
    }
  }

  /**
   * Logout from all devices
   */
  static async logoutAll(): Promise<void> {
    try {
      await axios.post(this.ENDPOINTS.LOGOUT_ALL);
    } catch (error) {
      console.warn('Logout all API call failed:', error);
    }
  }

  /**
   * Get current authenticated user data
   */
  static async getCurrentUser(): Promise<User> {
    try {
      const response = await axios.get<{ user: User }>(this.ENDPOINTS.ME);
      return response.data.user;
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
   * Check if user is currently authenticated by making a request to /me
   * For SPA authentication, we rely on session cookies, not stored tokens
   */
  static async isAuthenticated(): Promise<boolean> {
    try {
      await this.getCurrentUser();
      return true;
    } catch (error) {
      return false;
    }
  }

  /**
   * Validate authentication by making a request to the /me endpoint
   */
  static async validateAuth(): Promise<boolean> {
    try {
      await this.getCurrentUser();
      return true;
    } catch (error) {
      return false;
    }
  }
}

export default AuthService;