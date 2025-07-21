import React from 'react';
import { Language } from '@/types';
import { Check, AlertTriangle, Globe, Info } from 'lucide-react';
import { useLanguage } from '@/contexts/LanguageContext';

interface TranslationStatusProps {
  translationStatus?: {
    current_locale: string;
    has_translation: boolean;
    fallback_used: boolean;
  };
  translationCompleteness?: Record<string, {
    name: boolean;
    description: boolean;
    complete: boolean;
    percentage: number;
  }>;
  availableLocales?: string[];
  showTooltip?: boolean;
  variant?: 'minimal' | 'detailed' | 'badge';
  className?: string;
}

const LANGUAGE_NAMES: Record<Language, string> = {
  en: 'English',
  fr: 'Français',
  de: 'Deutsch'
};

const TranslationStatus: React.FC<TranslationStatusProps> = ({
  translationStatus,
  translationCompleteness,
  availableLocales = ['en', 'fr', 'de'],
  showTooltip = true,
  variant = 'minimal',
  className = ''
}) => {
  const { language } = useLanguage();

  // Calculate overall translation status
  const getOverallStatus = () => {
    if (translationCompleteness) {
      const locales = Object.keys(translationCompleteness);
      const completeCount = locales.filter(locale => 
        translationCompleteness[locale]?.complete
      ).length;
      const totalCount = locales.length;
      
      return {
        complete: completeCount,
        total: totalCount,
        percentage: Math.round((completeCount / totalCount) * 100),
        hasCurrentLocale: translationCompleteness[language]?.complete || false,
        currentLocaleFallback: !translationCompleteness[language]?.complete
      };
    }

    if (translationStatus) {
      return {
        complete: translationStatus.has_translation ? 1 : 0,
        total: 1,
        percentage: translationStatus.has_translation ? 100 : 0,
        hasCurrentLocale: translationStatus.has_translation,
        currentLocaleFallback: translationStatus.fallback_used
      };
    }

    return {
      complete: 0,
      total: availableLocales.length,
      percentage: 0,
      hasCurrentLocale: false,
      currentLocaleFallback: true
    };
  };

  const status = getOverallStatus();

  const getStatusIcon = () => {
    if (status.percentage === 100) {
      return <Check className="h-3 w-3 text-green-600" />;
    } else if (status.hasCurrentLocale) {
      return <Globe className="h-3 w-3 text-blue-600" />;
    } else {
      return <AlertTriangle className="h-3 w-3 text-amber-600" />;
    }
  };

  const getStatusColor = () => {
    if (status.percentage === 100) return 'text-green-600';
    if (status.hasCurrentLocale) return 'text-blue-600';
    return 'text-amber-600';
  };

  const getTooltipContent = () => {
    if (!showTooltip) return '';

    const lines = [];
    
    if (translationCompleteness) {
      lines.push(`Translation Status:`);
      Object.entries(translationCompleteness).forEach(([locale, info]) => {
        const langName = LANGUAGE_NAMES[locale as Language] || locale.toUpperCase();
        const status = info.complete ? '✓' : '✗';
        lines.push(`${status} ${langName} (${info.percentage}%)`);
      });
    } else if (translationStatus) {
      const langName = LANGUAGE_NAMES[language] || language.toUpperCase();
      if (translationStatus.has_translation) {
        lines.push(`✓ Available in ${langName}`);
      } else {
        lines.push(`✗ Not available in ${langName}`);
        if (translationStatus.fallback_used) {
          lines.push(`Using fallback language`);
        }
      }
    }

    if (status.currentLocaleFallback) {
      lines.push(`⚠ Using fallback for current language`);
    }

    return lines.join('\n');
  };

  if (variant === 'minimal') {
    return (
      <div 
        className={`inline-flex items-center ${className}`}
        title={getTooltipContent()}
      >
        {getStatusIcon()}
      </div>
    );
  }

  if (variant === 'badge') {
    return (
      <div 
        className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${className}`}
        style={{
          backgroundColor: status.percentage === 100 ? '#dcfce7' : 
                          status.hasCurrentLocale ? '#dbeafe' : '#fef3c7',
          color: status.percentage === 100 ? '#166534' : 
                 status.hasCurrentLocale ? '#1e40af' : '#92400e'
        }}
        title={getTooltipContent()}
      >
        {getStatusIcon()}
        <span>{status.percentage}%</span>
      </div>
    );
  }

  // Detailed variant
  return (
    <div className={`flex flex-col gap-1 ${className}`}>
      <div className="flex items-center gap-2">
        {getStatusIcon()}
        <span className={`text-sm font-medium ${getStatusColor()}`}>
          {status.complete}/{status.total} languages ({status.percentage}%)
        </span>
      </div>
      
      {status.currentLocaleFallback && (
        <div className="flex items-center gap-1 text-xs text-amber-600">
          <Info className="h-3 w-3" />
          <span>Using fallback for {LANGUAGE_NAMES[language]}</span>
        </div>
      )}

      {translationCompleteness && (
        <div className="grid grid-cols-3 gap-1 mt-1">
          {Object.entries(translationCompleteness).map(([locale, info]) => {
            const langName = LANGUAGE_NAMES[locale as Language] || locale.toUpperCase();
            return (
              <div 
                key={locale}
                className={`text-xs px-2 py-1 rounded ${
                  info.complete 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-gray-100 text-gray-600'
                }`}
                title={`${langName}: ${info.percentage}% complete`}
              >
                {info.complete ? '✓' : '✗'} {langName.slice(0, 2).toUpperCase()}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default TranslationStatus;