import { useState, useEffect, useCallback } from 'react';
import { User } from '@/types';
import AuthService from '@/services/AuthService';

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

interface UseAuthStateReturn extends AuthState {
  setUser: (user: User | null) => void;
  setError: (error: string | null) => void;
  setLoading: (loading: boolean) => void;
  clearError: () => void;
  validateAndRefreshAuth: () => Promise<boolean>;
}

/**
 * Custom hook for managing authentication state
 * Provides centralized state management for authentication
 */
export const useAuthState = (): UseAuthStateReturn => {
  const [state, setState] = useState<AuthState>({
    user: null,
    isAuthenticated: false,
    isLoading: true,
    error: null
  });

  const setUser = useCallback((user: User | null) => {
    setState(prev => ({
      ...prev,
      user,
      isAuthenticated: !!user
    }));
  }, []);

  const setError = useCallback((error: string | null) => {
    setState(prev => ({ ...prev, error }));
  }, []);

  const setLoading = useCallback((isLoading: boolean) => {
    setState(prev => ({ ...prev, isLoading }));
  }, []);

  const clearError = useCallback(() => {
    setState(prev => ({ ...prev, error: null }));
  }, []);

  /**
   * Validate current authentication
   */
  const validateAndRefreshAuth = useCallback(async (): Promise<boolean> => {
    try {
      // For SPA authentication, check if user is authenticated via session
      const isValid = await AuthService.validateAuth();
      if (!isValid) {
        setUser(null);
        return false;
      }

      // Get fresh user data
      const userData = await AuthService.getCurrentUser();
      setUser(userData);
      return true;
    } catch (error) {
      console.error('Auth validation failed:', error);
      setUser(null);
      return false;
    }
  }, [setUser]);

  // Initialize authentication state on mount
  useEffect(() => {
    const initializeAuth = async () => {
      setLoading(true);
      try {
        await validateAndRefreshAuth();
      } catch (error) {
        console.error('Failed to initialize auth:', error);
      } finally {
        setLoading(false);
      }
    };

    initializeAuth();
  }, [validateAndRefreshAuth, setLoading]);

  return {
    ...state,
    setUser,
    setError,
    setLoading,
    clearError,
    validateAndRefreshAuth
  };
};