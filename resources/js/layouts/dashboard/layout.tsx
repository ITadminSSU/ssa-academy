import { AppContent } from '@/layouts/dashboard/partials/app-content';
import { AppShell } from '@/layouts/dashboard/partials/app-shell';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import Main from '../main';
import DashboardHeader from './header';
import LearnerSidebar from './learner-sidebar';
import DashboardSidebar from './sidebar';

interface Props {
   headTitle?: string;
   breadcrumbs?: BreadcrumbItem[];
   variant?: 'admin' | 'learner';
   contentClassName?: string;
   lockViewport?: boolean;
}

const DashboardLayout = (props: PropsWithChildren<Props>) => {
   const { children, headTitle, breadcrumbs = [], variant = 'admin', contentClassName, lockViewport = false } = props;

   return (
      <Main>
         <AppShell variant="sidebar">
            {variant === 'learner' ? <LearnerSidebar /> : <DashboardSidebar />}

            <AppContent variant="sidebar" className={cn(lockViewport && 'min-h-0 overflow-hidden')}>
               {headTitle && <Head title={headTitle} />}

               <DashboardHeader breadcrumbs={breadcrumbs} />

               <div
                  className={cn(
                     'ssu-page-shell container py-6',
                     lockViewport && 'flex h-[calc(100dvh-4rem)] min-h-0 flex-col overflow-hidden py-3',
                     contentClassName,
                  )}
               >
                  {children}
               </div>
            </AppContent>
         </AppShell>
      </Main>
   );
};

export default DashboardLayout;
