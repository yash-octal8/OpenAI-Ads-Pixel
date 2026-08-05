import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';

// Import all language files statically so Vite bundles them
import en from './lang/en.json';
import es from './lang/es.json';
import fr from './lang/fr.json';
import de from './lang/de.json';
import hi from './lang/hi.json';
import ar from './lang/ar.json';
import it from './lang/it.json';
import ja from './lang/ja.json';
import pt from './lang/pt.json';
import zhCn from './lang/zh-cn.json';
import ca from './lang/ca.json';

const allTranslations = { en, es, fr, de, hi, ar, it, ja, pt, 'zh-cn': zhCn, ca };

const I18nContext = createContext({
  locale: 'en',
  setLanguage: () => {},
  t: (key, defaultStr) => defaultStr || key,
});

export const I18nProvider = ({ children }) => {
  const [locale, setLocale] = useState(() => {
    return localStorage.getItem('app_language') || 'en';
  });

  const setLanguage = useCallback((newLang) => {
    if (newLang) {
      setLocale(newLang);
      localStorage.setItem('app_language', newLang);
    }
  }, []);

  const t = useCallback((key, defaultStr = '', vars = {}) => {
    const langDict = allTranslations[locale] || allTranslations[locale.toLowerCase()] || allTranslations['en'];
    let str = (langDict && langDict[key]) || (allTranslations.en && allTranslations.en[key]) || defaultStr || key;
    if (vars && typeof vars === 'object') {
      for (const [k, v] of Object.entries(vars)) {
        str = str.replace(new RegExp(`{{${k}}}`, 'g'), v);
      }
    }
    return str;
  }, [locale]);

  return (
    <I18nContext.Provider value={{ locale, setLanguage, t }}>
      {children}
    </I18nContext.Provider>
  );
};

export const useI18n = () => {
  return useContext(I18nContext);
};