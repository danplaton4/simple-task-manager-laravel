import axios from 'axios';

/**
 * Locale Service for managing user language preferences and locale-related API calls
 * 
 * This service handles:
 * - Updating user locale preferences on the backend
 * - Retrieving current locale information
 * - Retry logic for failed preference updates
 * - Proper error handling for locale-related operations
 */

export interface LocaleInfo {
  locale: string;
  user_preference: string | null;
  available_locales: Record<string, string>;
}

export interface LocalePreferenceResponse {
  locale: string;
  message: string;
}

export type Language = 'en' | 'fr' | 'de';

class LocaleService {
  private static readonly ENDPOINTS = {
    UPDATE_PREFERENCE: '/locale/preference',
    GET_CURRENT: '/locale/current'
  } as const;

  private static readonly MAX_RETRIES = 3;
  private static readonly RETRY_DELAY = 1000; // 1 second

  /**
   * Update user's locale preference on the backend
   * Includes retry logic for failed requests
   */
  static async updatePreference(locale: Language): Promise<LocalePreferenceResponse> {
    let lastError: Error;
    
    for (let attempt = 1; attempt <= this.MAX_RETRIES; attempt++) {
      try {
        const response = await axios.post<LocalePreferenceResponse>(
          this.ENDPOINTS.UPDATE_PREFERENCE,
          { locale }
        );
        
        return response.data;
      } catch (error) {
        lastError = this.handleError(error, `Failed to update locale preference (attempt ${attempt}/${this.MAX_RETRIES})`);
        
        // If this is the last attempt, throw the error
        if (attempt === this.MAX_RETRIES) {
          throw lastError;
        }
        
        // Wait before retrying
        await this.delay(this.RETRY_DELAY * attempt);
      }
    }
    
    // This should never be reached, but TypeScript requires it
    throw lastError!;
  }

  /**
   * Get current locale information from the backend
   */
  static async getCurrentLocale(): Promise<LocaleInfo> {
    try {
      const response = await axios.get<LocaleInfo>(this.ENDPOINTS.GET_CURRENT);
      return response.data;
    } catch (error) {
      throw this.handleError(error, 'Failed to retrieve current locale information');
    }
  }

  /**
   * Update locale preference with optimistic updates
   * Returns the previous locale for rollback if needed
   */
  static async updatePreferenceOptimistic(
    locale: Language,
    onOptimisticUpdate?: (locale: Language) => void,
    onRollback?: (previousLocale: Language) => void
  ): Promise<LocalePreferenceResponse> {
    // Get current locale for potential rollback
    let previousLocale: Language = 'en';
    try {
      const currentInfo = await this.getCurrentLocale();
      previousLocale = (currentInfo.user_preference || currentInfo.locale) as Language;
    } catch (error) {
      console.warn('Could not retrieve current locale for rollback:', error);
    }

    // Apply optimistic update
    if (onOptimisticUpdate) {
      onOptimisticUpdate(locale);
    }

    try {
      return await this.updatePreference(locale);
    } catch (error) {
      // Rollback optimistic update on failure
      if (onRollback) {
        onRollback(previousLocale);
      }
      throw error;
    }
  }

  /**
   * Validate if a locale is supported
   */
  static isSupportedLocale(locale: string): locale is Language {
    return ['en', 'fr', 'de'].includes(locale);
  }

  /**
   * Get available locales with their display names
   */
  static getAvailableLocales(): Record<Language, string> {
    return {
      en: 'English',
      fr: 'Français',
      de: 'Deutsch'
    };
  }

  /**
   * Handle and format errors from API calls
   */
  private static handleError(error: unknown, defaultMessage: string): Error {
    if (axios.isAxiosError(error)) {
      const apiMessage = error.response?.data?.message;
      const statusCode = error.response?.status;
      
      // Handle specific HTTP status codes
      switch (statusCode) {
        case 422:
          return new Error(apiMessage || 'Invalid locale provided');
        case 401:
          return new Error('Authentication required to update locale preference');
        case 403:
          return new Error('Permission denied to update locale preference');
        case 429:
          return new Error('Too many requests. Please try again later');
        case 500:
          return new Error('Server error occurred while updating locale preference');
        default:
          return new Error(apiMessage || defaultMessage);
      }
    }
    
    if (error instanceof Error) {
      return error;
    }
    
    return new Error(defaultMessage);
  }

  /**
   * Utility method for adding delays between retry attempts
   */
  private static delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Check if the locale service is available (backend endpoints exist)
   */
  static async isAvailable(): Promise<boolean> {
    try {
      await this.getCurrentLocale();
      return true;
    } catch (error) {
      console.warn('Locale service not available:', error);
      return false;
    }
  }
}

export default LocaleService;