import DashboardSidebarBrand from '@/components/dashboard-sidebar-brand';
import { Sidebar, SidebarContent, SidebarFooter } from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar';
import { NavMain } from '@/layouts/dashboard/partials/nav-main';
import { NavUser } from '@/layouts/dashboard/partials/nav-user';
import { TrainerNavMain } from '@/layouts/dashboard/partials/trainer-nav-main';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const DashboardSidebar = () => {
   const { state } = useSidebar();
   const compact = state === 'collapsed';
   const { auth } = usePage<SharedData>().props;
   const isTrainer = auth.user?.role === 'instructor';

   return (
      <Sidebar
         collapsible="icon"
         variant="sidebar"
         side="left"
         className="border-sidebar-border [&_[data-sidebar=sidebar]]:bg-sidebar [&_[data-sidebar=sidebar]]:text-sidebar-foreground shadow-lg"
      >
         <DashboardSidebarBrand compact={compact} />
         <SidebarContent>{isTrainer ? <TrainerNavMain /> : <NavMain />}</SidebarContent>
         <SidebarFooter>
            <NavUser />
         </SidebarFooter>
      </Sidebar>
   );
};

export default DashboardSidebar;
