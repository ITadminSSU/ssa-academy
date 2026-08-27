import '../css/app.css';

import AppRealtimeShell from '@/components/app-realtime-shell';
import ErrorBoundary from '@/components/error-boundary';
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

const wrappedLayouts = new WeakMap<object, (pageNode: ReactNode) => ReactNode>();

createInertiaApp({
   title: (title) => `${title}`,
   resolve: async (name) => {
      const page = (await resolvePageComponent(
         `./pages/${name}.tsx`,
         import.meta.glob('./pages/**/*.tsx'),
      )) as PageModule;

      const Page = page.default;
      const cachedLayout = wrappedLayouts.get(Page);

      if (cachedLayout) {
         Page.layout = cachedLayout;
         return page;
      }

      const previousLayout = Page.layout;
      const layout = (pageNode: ReactNode) => {
         let content: ReactNode = pageNode;

         if (Array.isArray(previousLayout)) {
            content = previousLayout.reduceRight(
               (children, pageLayout) => pageLayout(children),
               pageNode,
            );
         } else if (typeof previousLayout === 'function') {
            content = previousLayout(pageNode);
         }

         return <AppRealtimeShell>{content}</AppRealtimeShell>;
      };

      wrappedLayouts.set(Page, layout);
      Page.layout = layout;

      return page;
   },
   setup({ el, App, props }) {
      const root: Root = createRoot(el);
      root.render(
         <ErrorBoundary
            title="This page failed to load"
            description="A display error stopped this screen. Reloading usually fixes it."
            actionLabel="Reload page"
            onReset={() => window.location.reload()}
         >
            <App {...props} />
         </ErrorBoundary>,
      );
   },
   progress: {
      color: '#4B5563',
   },
});

initializeTheme();
