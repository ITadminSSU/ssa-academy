import DashboardSidebarBrand from '@/components/dashboard-sidebar-brand';
import { Sidebar, SidebarContent, SidebarFooter } from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar';
import { LearnerNavMain } from '@/layouts/dashboard/partials/learner-nav-main';
import { NavUser } from '@/layouts/dashboard/partials/nav-user';

const LearnerSidebar = () => {
   const { state } = useSidebar();
   const compact = state === 'collapsed';

   return (
      <Sidebar
         collapsible="icon"
         variant="sidebar"
         side="left"
         className="border-sidebar-border [&_[data-sidebar=sidebar]]:bg-sidebar [&_[data-sidebar=sidebar]]:text-sidebar-foreground shadow-lg"
      >
         <DashboardSidebarBrand compact={compact} />
         <SidebarContent>
            <LearnerNavMain />
         </SidebarContent>
         <SidebarFooter>
            <NavUser />
         </SidebarFooter>
      </Sidebar>
   );
};

export default LearnerSidebar;
