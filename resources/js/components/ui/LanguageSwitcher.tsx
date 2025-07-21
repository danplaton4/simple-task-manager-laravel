import React, { useState, useEffect } from 'react';
import { useLanguage } from '@/contexts/LanguageContext';
import { Language } from '@/types';
import { Button } from './button';
import { Check, AlertTriangle, RotateCcw } from 'lucide-react';

const LANGS: { code: Language; label: string; name: string }[] = [
  { code: 'en', label: 'EN', name: 'English' },
  { code: 'fr', label: 'FR', name: 'Français' },
  { code: 'de', label: 'DE', name: 'Deutsch' },
];

const LanguageSwitcher: React.FC = () => {
  const { language, setLanguage, isUpdating, userPreference, error, clearError } = useLanguage();
  const [lastSuccessfulChange, setLastSuccessfulChange] = useState<Language | null>(null);
  const [showSuccessIndicator, setShowSuccessIndicator] = useState(false);
  const [retryAttempts, setRetryAttempts] = useState(0);

  // Show success indicator when language changes successfully
  useEffect(() => {
    if (!isUpdating && !error && lastSuccessfulChange !== language) {
      setLastSuccessfulChange(language);
      setShowSuccessIndicator(true);
      setRetryAttempts(0);
      
      // Hide success indicator after 2 seconds
      const timer = setTimeout(() => {
        setShowSuccessIndicator(false);
      }, 2000);
      
      return () => clearTimeout(timer);
    }
  }, [language, isUpdating, error, lastSuccessfulChange]);

  const handleLanguageChange = async (code: Language) => {
    if (code === language && !error) return;
    
    clearError();
    
    try {
      await setLanguage(code);
    } catch (error) {
      console.error('Failed to change language:', error);
      setRetryAttempts(prev => prev + 1);
    }
  };

  const handleRetry = async () => {
    if (error && lastSuccessfulChange) {
      await handleLanguageChange(lastSuccessfulChange);
    }
  };

  const getLanguageName = (code: Language) => {
    return LANGS.find(lang => lang.code === code)?.name || code.toUpperCase();
  };

  const isUserPreference = (code: Language) => {
    return userPreference === code;
  };

  const isSessionLanguage = (code: Language) => {
    return language === code && userPreference !== code;
  };

  return (
    <div className="flex flex-col gap-2">
      {/* Language Buttons */}
      <div className="flex gap-1">
        {LANGS.map(({ code, label }) => (
          <div key={code} className="relative">
            <Button
              variant={language === code ? 'default' : 'ghost'}
              size="sm"
              onClick={() => handleLanguageChange(code)}
              disabled={isUpdating}
              className={`
                ${language === code ? '' : 'text-muted-foreground'}
                ${isUserPreference(code) ? 'ring-2 ring-blue-500 ring-offset-1' : ''}
                transition-all duration-200
              `}
              title={
                isUserPreference(code) 
                  ? `${getLanguageName(code)} (Your preference)`
                  : isSessionLanguage(code)
                  ? `${getLanguageName(code)} (Session only)`
                  : getLanguageName(code)
              }
            >
              {isUpdating && language === code ? (
                <RotateCcw className="h-3 w-3 animate-spin" />
              ) : (
                label
              )}
            </Button>
            
            {/* User preference indicator */}
            {isUserPreference(code) && (
              <div className="absolute -top-1 -right-1 w-2 h-2 bg-blue-500 rounded-full" 
                   title="Your saved preference" />
            )}
          </div>
        ))}
      </div>

      {/* Status Indicators */}
      <div className="flex flex-col gap-1">
        {/* Success Indicator */}
        {showSuccessIndicator && !error && (
          <div className="flex items-center gap-1 text-xs text-green-600 animate-fade-in">
            <Check className="h-3 w-3" />
            <span>Language changed to {getLanguageName(language)}</span>
          </div>
        )}

        {/* Error Indicator with Retry */}
        {error && (
          <div className="flex flex-col gap-1">
            <div className="flex items-center gap-1 text-xs text-red-500">
              <AlertTriangle className="h-3 w-3" />
              <span>{error}</span>
            </div>
            {retryAttempts > 0 && (
              <Button
                variant="ghost"
                size="sm"
                onClick={handleRetry}
                className="text-xs h-6 px-2 text-blue-600 hover:text-blue-700"
              >
                <RotateCcw className="h-3 w-3 mr-1" />
                Retry ({retryAttempts} failed)
              </Button>
            )}
          </div>
        )}

        {/* Current Status */}
        {!error && !showSuccessIndicator && (
          <div className="text-xs text-muted-foreground">
            {userPreference && userPreference === language ? (
              <span>Using your preferred language: {getLanguageName(language)}</span>
            ) : userPreference && userPreference !== language ? (
              <span>
                Session: {getLanguageName(language)} • 
                Preference: {getLanguageName(userPreference)}
              </span>
            ) : (
              <span>Current: {getLanguageName(language)}</span>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default LanguageSwitcher; 