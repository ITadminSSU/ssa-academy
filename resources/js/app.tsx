import '../css/app.css';

import AppRealtimeShell from '@/components/app-realtime-shell';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME;

createInertiaApp({
   title: (title) => `${title}`,
   resolve: async (name) => {
      const page = await resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx'));
      const PageComponent = page.default;

      return (props: Record<string, unknown>) => (
         <AppRealtimeShell>
            <PageComponent {...props} />
         </AppRealtimeShell>
      );
   },
   setup({ el, App, props }) {
      const root = createRoot(el);

      root.render(<App {...props} />);
   },
   progress: {
      color: '#4B5563',
   },
});

// This will set light / dark mode on load...
initializeTheme();
