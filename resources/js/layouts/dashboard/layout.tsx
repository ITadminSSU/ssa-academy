import { AppContent } from '@/layouts/dashboard/partials/app-content';
import { AppShell } from '@/layouts/dashboard/partials/app-shell';
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
}

const DashboardLayout = (props: PropsWithChildren<Props>) => {
   const { children, headTitle, breadcrumbs = [], variant = 'admin' } = props;

   return (
      <Main>
         <AppShell variant="sidebar">
            {variant === 'learner' ? <LearnerSidebar /> : <DashboardSidebar />}

            <AppContent variant="sidebar">
               {headTitle && <Head title={headTitle} />}

               <DashboardHeader breadcrumbs={breadcrumbs} />

               <div className="ssu-page-shell container py-6">{children}</div>
            </AppContent>
         </AppShell>
      </Main>
   );
};

export default DashboardLayout;
