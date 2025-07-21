/**
 * Authentication Utilities
 * 
 * Utility functions for authentication-related operations
 */

/**
 * Check if the current environment supports localStorage
 */
export const isLocalStorageAvailable = (): boolean => {
  try {
    const test = '__localStorage_test__';
    localStorage.setItem(test, test);
    localStorage.removeItem(test);
    return true;
  } catch (error) {
    return false;
  }
};

/**
 * Safely get item from localStorage
 */
export const getStorageItem = (key: string): string | null => {
  if (!isLocalStorageAvailable()) {
    return null;
  }
  
  try {
    return localStorage.getItem(key);
  } catch (error) {
    console.error('Failed to get item from localStorage:', error);
    return null;
  }
};

/**
 * Safely set item in localStorage
 */
export const setStorageItem = (key: string, value: string): boolean => {
  if (!isLocalStorageAvailable()) {
    return false;
  }
  
  try {
    localStorage.setItem(key, value);
    return true;
  } catch (error) {
    console.error('Failed to set item in localStorage:', error);
    return false;
  }
};

/**
 * Safely remove item from localStorage
 */
export const removeStorageItem = (key: string): boolean => {
  if (!isLocalStorageAvailable()) {
    return false;
  }
  
  try {
    localStorage.removeItem(key);
    return true;
  } catch (error) {
    console.error('Failed to remove item from localStorage:', error);
    return false;
  }
};

/**
 * Parse JWT token payload (for informational purposes only)
 * Note: This should not be used for security decisions
 */
export const parseJWTPayload = (token: string): any | null => {
  try {
    const parts = token.split('.');
    if (parts.length !== 3) {
      return null;
    }
    
    const payload = parts[1];
    const decoded = atob(payload);
    return JSON.parse(decoded);
  } catch (error) {
    console.error('Failed to parse JWT payload:', error);
    return null;
  }
};

/**
 * Check if JWT token is expired (client-side check only)
 * Note: This should not be relied upon for security
 */
export const isJWTExpired = (token: string): boolean => {
  const payload = parseJWTPayload(token);
  if (!payload || !payload.exp) {
    return true;
  }
  
  const currentTime = Math.floor(Date.now() / 1000);
  return payload.exp < currentTime;
};

/**
 * Format authentication error messages
 */
export const formatAuthError = (error: any): string => {
  if (typeof error === 'string') {
    return error;
  }
  
  if (error?.message) {
    return error.message;
  }
  
  if (error?.response?.data?.message) {
    return error.response.data.message;
  }
  
  return 'An unexpected error occurred';
};

/**
 * Validate email format
 */
export const isValidEmail = (email: string): boolean => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

/**
 * Validate password strength
 */
export const validatePassword = (password: string): {
  isValid: boolean;
  errors: string[];
} => {
  const errors: string[] = [];
  
  if (password.length < 8) {
    errors.push('Password must be at least 8 characters long');
  }
  
  if (!/[A-Z]/.test(password)) {
    errors.push('Password must contain at least one uppercase letter');
  }
  
  if (!/[a-z]/.test(password)) {
    errors.push('Password must contain at least one lowercase letter');
  }
  
  if (!/\d/.test(password)) {
    errors.push('Password must contain at least one number');
  }
  
  return {
    isValid: errors.length === 0,
    errors
  };
};

/**
 * Generate a secure random string for CSRF-like purposes
 */
export const generateSecureToken = (length: number = 32): string => {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let result = '';
  
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  
  return result;
};

/**
 * Debounce function for API calls
 */
export const debounce = <T extends (...args: any[]) => any>(
  func: T,
  wait: number
): ((...args: Parameters<T>) => void) => {
  let timeout: NodeJS.Timeout;
  
  return (...args: Parameters<T>) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
};