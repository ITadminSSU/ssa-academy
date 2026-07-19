import Appearance from '@/components/appearance';
import { Breadcrumbs } from '@/components/breadcrumbs';
import Language from '@/components/language';
import Notification from '@/components/notification';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { isEmployeeLearner } from '@/lib/dashboard';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';

const DashboardHeader = ({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) => {
   const { props } = usePage<SharedData>();
   const { system, auth } = props;

   const coursesHref = (() => {
      const user = auth?.user;
      if (user && !isEmployeeLearner(user) && (user.role === 'admin' || user.role === 'instructor' || user.role === 'collaborative' || user.role === 'administrative')) {
         return route('courses.index');
      }
      return route('category.courses', { category: 'all' });
   })();

   return (
      <header className="ssu-dashboard-header relative flex h-16 shrink-0 items-center gap-2 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
         <div className="flex flex-1 items-center gap-2 md:gap-4">
            <SidebarTrigger className="-ml-1" />
            <nav className="text-muted-foreground hidden items-center gap-4 text-sm md:flex">
               <Link href={coursesHref} className="hover:text-foreground transition-colors">
                  Courses
               </Link>
            </nav>
            <Breadcrumbs breadcrumbs={breadcrumbs} />
         </div>

         <div className="flex flex-1 items-center justify-end gap-2">
            <Appearance />
            <Notification />
            {system?.fields?.language_selector && <Language />}
         </div>
      </header>
   );
};

export default DashboardHeader;
