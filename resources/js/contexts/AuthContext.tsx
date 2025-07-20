import React, { createContext, useContext, useState, useEffect } from 'react';
import { User, AuthResponse, LoginCredentials, RegisterData } from '@/types';

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: LoginCredentials) => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  logout: () => void;
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
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check for existing authentication on app load
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const token = localStorage.getItem('auth_token');
      if (token) {
        // TODO: Validate token with API and get user data
        // For now, just set loading to false
        setIsLoading(false);
      } else {
        setIsLoading(false);
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      setIsLoading(false);
    }
  };

  const login = async (credentials: LoginCredentials) => {
    try {
      // TODO: Replace with actual API call
      console.log('Login attempt:', credentials);
      
      // Simulate API response
      const mockResponse: AuthResponse = {
        user: {
          id: 1,
          name: 'Test User',
          email: credentials.email,
          preferred_language: 'en',
          timezone: 'UTC',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        token: 'mock-jwt-token'
      };

      // Store token and user data
      localStorage.setItem('auth_token', mockResponse.token);
      setUser(mockResponse.user);
    } catch (error) {
      console.error('Login failed:', error);
      throw error;
    }
  };

  const register = async (data: RegisterData) => {
    try {
      // TODO: Replace with actual API call
      console.log('Registration attempt:', data);
      
      // Simulate API response
      const mockResponse: AuthResponse = {
        user: {
          id: 1,
          name: data.name,
          email: data.email,
          preferred_language: data.preferred_language || 'en',
          timezone: data.timezone || 'UTC',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        },
        token: 'mock-jwt-token'
      };

      // Store token and user data
      localStorage.setItem('auth_token', mockResponse.token);
      setUser(mockResponse.user);
    } catch (error) {
      console.error('Registration failed:', error);
      throw error;
    }
  };

  const logout = () => {
    localStorage.removeItem('auth_token');
    setUser(null);
  };

  const value: AuthContextType = {
    user,
    isAuthenticated: !!user,
    isLoading,
    login,
    register,
    logout
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};