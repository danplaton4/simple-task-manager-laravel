import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import axios from 'axios';
import LocaleService, { LocaleInfo, LocalePreferenceResponse } from '../LocaleService';

// Mock axios
vi.mock('axios');
const mockedAxios = vi.mocked(axios);

describe('LocaleService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('updatePreference', () => {
    it('should successfully update locale preference', async () => {
      const mockResponse: LocalePreferenceResponse = {
        locale: 'fr',
        message: 'Language preference updated successfully'
      };

      mockedAxios.post.mockResolvedValueOnce({ data: mockResponse });

      const result = await LocaleService.updatePreference('fr');

      expect(mockedAxios.post).toHaveBeenCalledWith('/locale/preference', { locale: 'fr' });
      expect(result).toEqual(mockResponse);
    });

    it('should retry on failure and eventually succeed', async () => {
      const mockResponse: LocalePreferenceResponse = {
        locale: 'de',
        message: 'Language preference updated successfully'
      };

      // First two calls fail, third succeeds
      mockedAxios.post
        .mockRejectedValueOnce(new Error('Network error'))
        .mockRejectedValueOnce(new Error('Server error'))
        .mockResolvedValueOnce({ data: mockResponse });

      const result = await LocaleService.updatePreference('de');

      expect(mockedAxios.post).toHaveBeenCalledTimes(3);
      expect(result).toEqual(mockResponse);
    });

    it('should throw error after max retries', async () => {
      const error = new Error('Persistent error');
      mockedAxios.post.mockRejectedValue(error);

      await expect(LocaleService.updatePreference('fr')).rejects.toThrow('Persistent error');
      expect(mockedAxios.post).toHaveBeenCalledTimes(3); // MAX_RETRIES
    });

    it('should handle axios errors with proper error messages', async () => {
      const axiosError = {
        isAxiosError: true,
        response: {
          status: 422,
          data: { message: 'Invalid locale provided' }
        }
      };

      mockedAxios.post.mockRejectedValue(axiosError);
      mockedAxios.isAxiosError.mockReturnValue(true);

      await expect(LocaleService.updatePreference('invalid' as any)).rejects.toThrow('Invalid locale provided');
    });
  });

  describe('getCurrentLocale', () => {
    it('should successfully get current locale info', async () => {
      const mockLocaleInfo: LocaleInfo = {
        locale: 'en',
        user_preference: 'fr',
        available_locales: {
          en: 'English',
          fr: 'Français',
          de: 'Deutsch'
        }
      };

      mockedAxios.get.mockResolvedValueOnce({ data: mockLocaleInfo });

      const result = await LocaleService.getCurrentLocale();

      expect(mockedAxios.get).toHaveBeenCalledWith('/locale/current');
      expect(result).toEqual(mockLocaleInfo);
    });

    it('should handle errors when getting current locale', async () => {
      const error = new Error('Failed to get locale');
      mockedAxios.get.mockRejectedValue(error);

      await expect(LocaleService.getCurrentLocale()).rejects.toThrow('Failed to retrieve current locale information');
    });
  });

  describe('isSupportedLocale', () => {
    it('should return true for supported locales', () => {
      expect(LocaleService.isSupportedLocale('en')).toBe(true);
      expect(LocaleService.isSupportedLocale('fr')).toBe(true);
      expect(LocaleService.isSupportedLocale('de')).toBe(true);
    });

    it('should return false for unsupported locales', () => {
      expect(LocaleService.isSupportedLocale('es')).toBe(false);
      expect(LocaleService.isSupportedLocale('invalid')).toBe(false);
      expect(LocaleService.isSupportedLocale('')).toBe(false);
    });
  });

  describe('getAvailableLocales', () => {
    it('should return available locales with display names', () => {
      const expected = {
        en: 'English',
        fr: 'Français',
        de: 'Deutsch'
      };

      expect(LocaleService.getAvailableLocales()).toEqual(expected);
    });
  });

  describe('updatePreferenceOptimistic', () => {
    it('should call optimistic update callback and succeed', async () => {
      const mockResponse: LocalePreferenceResponse = {
        locale: 'fr',
        message: 'Success'
      };

      const mockCurrentLocale: LocaleInfo = {
        locale: 'en',
        user_preference: 'en',
        available_locales: {}
      };

      mockedAxios.get.mockResolvedValueOnce({ data: mockCurrentLocale });
      mockedAxios.post.mockResolvedValueOnce({ data: mockResponse });

      const onOptimisticUpdate = vi.fn();
      const onRollback = vi.fn();

      const result = await LocaleService.updatePreferenceOptimistic('fr', onOptimisticUpdate, onRollback);

      expect(onOptimisticUpdate).toHaveBeenCalledWith('fr');
      expect(onRollback).not.toHaveBeenCalled();
      expect(result).toEqual(mockResponse);
    });

    it('should rollback on failure', async () => {
      const mockCurrentLocale: LocaleInfo = {
        locale: 'en',
        user_preference: 'en',
        available_locales: {}
      };

      mockedAxios.get.mockResolvedValueOnce({ data: mockCurrentLocale });
      mockedAxios.post.mockRejectedValue(new Error('Update failed'));

      const onOptimisticUpdate = vi.fn();
      const onRollback = vi.fn();

      await expect(
        LocaleService.updatePreferenceOptimistic('fr', onOptimisticUpdate, onRollback)
      ).rejects.toThrow('Update failed');

      expect(onOptimisticUpdate).toHaveBeenCalledWith('fr');
      expect(onRollback).toHaveBeenCalledWith('en');
    });
  });
});