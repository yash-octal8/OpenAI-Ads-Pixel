import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AppProvider } from '@shopify/polaris';
import enTranslations from '@shopify/polaris/locales/en.json';
import { I18nProvider } from './i18n';
import '@shopify/polaris/build/esm/styles.css';
import AppLayout from './layouts/AppLayout';

// App Pages
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import Plan from './pages/Plan';
import Billing from './pages/Billing';

const App = () => {
  return (
    <I18nProvider>
      <AppProvider i18n={enTranslations}>
        <BrowserRouter>
          <Routes>
            <Route element={<AppLayout />}>
              <Route path="/" element={<Dashboard />} />
              <Route path="/settings" element={<Settings />} />
              <Route path="/plan" element={<Plan />} />
              <Route path="/billing/:plan?" element={<Billing />} />
            </Route>
          </Routes>
        </BrowserRouter>
      </AppProvider>
    </I18nProvider>
  );
};

const rootElement = document.getElementById('app');
if (rootElement) {
  const root = createRoot(rootElement);
  root.render(<App />);
}
