import '../css/app.css';

import AppRealtimeShell from '@/components/app-realtime-shell';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, Root } from 'react-dom/client';
import { ComponentType, ReactNode } from 'react';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME;

type PageModule = {
   default: ComponentType<unknown> & {
      layout?: ((page: ReactNode) => ReactNode) | ((page: ReactNode) => ReactNode)[];
   };
};

createInertiaApp({
   title: (title) => `${title}`,
   resolve: async (name) => {
      const page = (await resolvePageComponent(
         `./pages/${name}.tsx`,
         import.meta.glob('./pages/**/*.tsx'),
      )) as PageModule;

      const Page = page.default;
      const previousLayout = Page.layout;

      Page.layout = (pageNode: ReactNode) => {
         let content: ReactNode = pageNode;

         if (Array.isArray(previousLayout)) {
            content = previousLayout.reduceRight(
               (children, layout) => layout(children),
               pageNode,
            );
         } else if (typeof previousLayout === 'function') {
            content = previousLayout(pageNode);
         }

         return <AppRealtimeShell>{content}</AppRealtimeShell>;
      };

      return page;
   },
   setup({ el, App, props }) {
      const root: Root = createRoot(el);
      root.render(<App {...props} />);
   },
   progress: {
      color: '#4B5563',
   },
});

initializeTheme();
