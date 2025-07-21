import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { Language } from '@/types';
import LocaleService from '@/services/LocaleService';
import AuthService from '@/services/AuthService';

interface LanguageContextType {
  language: Language;
  setLanguage: (lang: Language) => Promise<void>;
  isUpdating: boolean;
  userPreference: Language | null;
  error: string | null;
  clearError: () => void;
}

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

export const LanguageProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [language, setLanguageState] = useState<Language>('en');
  const [isUpdating, setIsUpdating] = useState(false);
  const [userPreference, setUserPreference] = useState<Language | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isInitialized, setIsInitialized] = useState(false);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  /**
   * Initialize language from user preference or localStorage
   */
  const initializeLanguage = useCallback(async () => {
    try {
      // Check if user is authenticated
      const isAuthenticated = await AuthService.isAuthenticated();
      
      if (isAuthenticated) {
        try {
          // Get user's preferred language from backend
          const localeInfo = await LocaleService.getCurrentLocale();
          const preferredLang = localeInfo.user_preference as Language;
          
          if (preferredLang && LocaleService.isSupportedLocale(preferredLang)) {
            setUserPreference(preferredLang);
            setLanguageState(preferredLang);
            localStorage.setItem('language', preferredLang);
          } else {
            // Use current locale from backend
            const currentLang = localeInfo.locale as Language;
            if (LocaleService.isSupportedLocale(currentLang)) {
              setLanguageState(currentLang);
              localStorage.setItem('language', currentLang);
            }
          }
        } catch (error) {
          console.warn('Failed to get user locale preference, falling back to localStorage:', error);
          // Fall back to localStorage
          const storedLang = localStorage.getItem('language') as Language;
          if (storedLang && LocaleService.isSupportedLocale(storedLang)) {
            setLanguageState(storedLang);
          }
        }
      } else {
        // User not authenticated, use localStorage
        const storedLang = localStorage.getItem('language') as Language;
        if (storedLang && LocaleService.isSupportedLocale(storedLang)) {
          setLanguageState(storedLang);
        }
      }
    } catch (error) {
      console.error('Error initializing language:', error);
      // Fall back to default
      setLanguageState('en');
      localStorage.setItem('language', 'en');
    } finally {
      setIsInitialized(true);
    }
  }, []);

  /**
   * Set language with backend synchronization
   */
  const setLanguage = useCallback(async (lang: Language) => {
    if (!LocaleService.isSupportedLocale(lang)) {
      setError(`Unsupported language: ${lang}`);
      return;
    }

    setIsUpdating(true);
    clearError();

    try {
      // Check if user is authenticated
      const isAuthenticated = await AuthService.isAuthenticated();
      
      if (isAuthenticated) {
        // Update backend preference with optimistic updates
        await LocaleService.updatePreferenceOptimistic(
          lang,
          // Optimistic update
          (newLang) => {
            setLanguageState(newLang);
            localStorage.setItem('language', newLang);
          },
          // Rollback on failure
          (previousLang) => {
            setLanguageState(previousLang);
            localStorage.setItem('language', previousLang);
          }
        );
        
        // Update user preference state
        setUserPreference(lang);
      } else {
        // User not authenticated, just update local state
        setLanguageState(lang);
        localStorage.setItem('language', lang);
      }

      // Update axios headers for future requests
      if (window.updateAxiosLocaleHeaders) {
        window.updateAxiosLocaleHeaders(lang);
      }

      // Trigger a custom event for other components to react to language changes
      window.dispatchEvent(new CustomEvent('languageChanged', { 
        detail: { language: lang } 
      }));

    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Failed to update language preference';
      setError(errorMessage);
      console.error('Failed to update language preference:', error);
    } finally {
      setIsUpdating(false);
    }
  }, [clearError]);

  /**
   * Handle authentication state changes
   */
  useEffect(() => {
    const handleAuthChange = () => {
      // Re-initialize language when auth state changes
      initializeLanguage();
    };

    // Listen for auth state changes
    window.addEventListener('authStateChanged', handleAuthChange);
    
    return () => {
      window.removeEventListener('authStateChanged', handleAuthChange);
    };
  }, [initializeLanguage]);

  /**
   * Initialize language on mount
   */
  useEffect(() => {
    if (!isInitialized) {
      initializeLanguage();
    }
  }, [initializeLanguage, isInitialized]);

  const value: LanguageContextType = {
    language,
    setLanguage,
    isUpdating,
    userPreference,
    error,
    clearError
  };

  return (
    <LanguageContext.Provider value={value}>
      {children}
    </LanguageContext.Provider>
  );
};

export const useLanguage = () => {
  const ctx = useContext(LanguageContext);
  if (!ctx) throw new Error('useLanguage must be used within a LanguageProvider');
  return ctx;
}; 