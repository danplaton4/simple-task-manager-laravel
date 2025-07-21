import { useEffect, useCallback } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import AuthService from '@/services/AuthService';

/**
 * Hook for handling authentication persistence and automatic token refresh
 */
export const useAuthPersistence = () => {
  const { user, isAuthenticated, validateAuth } = useAuth();

  /**
   * Set up automatic auth validation for session-based authentication
   */
  const setupAuthValidation = useCallback(() => {
    if (!isAuthenticated) return;

    // Set up periodic auth validation (every 5 minutes)
    const interval = setInterval(async () => {
      try {
        const isValid = await validateAuth();
        if (!isValid) {
          console.log('Auth validation failed, user will be logged out');
        }
      } catch (error) {
        console.error('Auth validation error:', error);
      }
    }, 5 * 60 * 1000); // 5 minutes

    return () => clearInterval(interval);
  }, [isAuthenticated, validateAuth]);

  /**
   * Handle page visibility changes to refresh auth when page becomes visible
   */
  const handleVisibilityChange = useCallback(async () => {
    if (document.visibilityState === 'visible' && isAuthenticated) {
      try {
        await validateAuth();
      } catch (error) {
        console.error('Auth validation on visibility change failed:', error);
      }
    }
  }, [isAuthenticated, validateAuth]);

  /**
   * Handle storage events (for multi-tab logout)
   * Note: For SPA authentication, we rely on session cookies, not localStorage
   */
  const handleStorageChange = useCallback((_event: StorageEvent) => {
    // For session-based auth, we don't need to handle storage events
    // Sessions are automatically synchronized across tabs
  }, []);

  useEffect(() => {
    // Set up auth validation interval
    const cleanup = setupAuthValidation();

    // Listen for page visibility changes
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Listen for storage changes (multi-tab support)
    window.addEventListener('storage', handleStorageChange);

    return () => {
      cleanup?.();
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('storage', handleStorageChange);
    };
  }, [setupAuthValidation, handleVisibilityChange, handleStorageChange]);

  return {
    user,
    isAuthenticated
  };
};