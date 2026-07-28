import DashboardLogoLink from '@/components/dashboard-logo-link';
import { SidebarHeader, SidebarMenu, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

interface Props {
   compact?: boolean;
}

/** Shared navy sidebar logo — trainer, admin, and learner dashboards */
const DashboardSidebarBrand = ({ compact = false }: Props) => (
   <SidebarHeader className={cn('ssu-sidebar-brand', compact ? 'px-2 py-3' : 'px-3 py-5')}>
      <SidebarMenu>
         <SidebarMenuItem className={cn(compact && 'flex justify-center')}>
            <DashboardLogoLink
               theme="dark"
               variant={compact ? 'icon' : 'wordmark'}
               className={compact ? 'h-10 w-10' : 'dashboard-sidebar-logo w-full'}
            />
         </SidebarMenuItem>
      </SidebarMenu>
   </SidebarHeader>
);

export default DashboardSidebarBrand;
