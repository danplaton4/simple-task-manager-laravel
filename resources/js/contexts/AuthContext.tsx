import React, { createContext, useContext, useCallback } from 'react';
import { User, LoginCredentials, RegisterData } from '@/types';
import { useAuthState } from '@/hooks/useAuthState';
import AuthService from '@/services/AuthService';

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
  fieldErrors?: Record<string, string[]> | null;
  login: (credentials: LoginCredentials) => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  logout: () => Promise<void>;
  logoutAll: () => Promise<void>;
  clearError: () => void;
  validateAuth: () => Promise<boolean>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: React.ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const {
    user,
    isAuthenticated,
    isLoading,
    error,
    setUser,
    setError,
    setLoading,
    clearError: clearStateError,
    validateAndRefreshAuth
  } = useAuthState();

  const [fieldErrors, setFieldErrors] = React.useState<Record<string, string[]> | null>(null);

  const login = useCallback(async (credentials: LoginCredentials) => {
    try {
      clearStateError();
      setFieldErrors(null);
      setLoading(true);
      
      const response = await AuthService.login(credentials);
      setUser(response.user);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Login failed';
      setError(errorMessage);
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setUser, setError, setLoading, clearStateError]);

  const register = useCallback(async (data: RegisterData) => {
    try {
      clearStateError();
      setFieldErrors(null);
      setLoading(true);
      
      const response = await AuthService.register(data);
      setUser(response.user);
    } catch (error: any) {
      if (error && error.errors) {
        setFieldErrors(error.errors);
        setError(error.message || 'Registration failed');
      } else {
        setError(error instanceof Error ? error.message : 'Registration failed');
        setFieldErrors(null);
      }
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setUser, setError, setLoading, clearStateError]);

  const logout = useCallback(async () => {
    try {
      setLoading(true);
      await AuthService.logout();
    } catch (error) {
      console.error('Logout failed:', error);
    } finally {
      setUser(null);
      clearStateError();
      setFieldErrors(null);
      setLoading(false);
    }
  }, [setUser, setLoading, clearStateError]);

  const logoutAll = useCallback(async () => {
    try {
      setLoading(true);
      await AuthService.logoutAll();
    } catch (error) {
      console.error('Logout all failed:', error);
    } finally {
      setUser(null);
      clearStateError();
      setFieldErrors(null);
      setLoading(false);
    }
  }, [setUser, setLoading, clearStateError]);

  const clearError = useCallback(() => {
    clearStateError();
    setFieldErrors(null);
  }, [clearStateError]);

  const validateAuth = useCallback(async (): Promise<boolean> => {
    return await validateAndRefreshAuth();
  }, [validateAndRefreshAuth]);

  const value: AuthContextType = {
    user,
    isAuthenticated,
    isLoading,
    error,
    fieldErrors,
    login,
    register,
    logout,
    logoutAll,
    clearError,
    validateAuth
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};