import { Accordion } from '@/components/ui/accordion';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuItem } from '@/components/ui/sidebar';
import { getRouteSegments } from '@/lib/route';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import NavMainItem from './nav-main-item';
import { getDashboardRoutes } from './routes';

export function NavMain() {
   const page = usePage<SharedData>();
   const { auth, system, features } = page.props;
   const routes = getDashboardRoutes(auth.dashboardUrl ?? route('dashboard'), features, Boolean(auth.canManagePlatformSettings));
   const [openAccordions, setOpenAccordions] = useState<string>('');

   const role = auth.user?.role || 'admin';
   const subType = system?.sub_type || 'collaborative';

   const visibleRoutes = useMemo(() => {
      return routes
         .map((group) => ({
            ...group,
            pages: group.pages.filter((pageRoute) => {
               const roleOk = pageRoute.access.includes(role);
               const subTypeOk = pageRoute.access.includes(subType);
               if (!roleOk || !subTypeOk) {
                  return false;
               }

               if (pageRoute.children?.length) {
                  return pageRoute.children.some((child) => child.access.includes(role));
               }

               return true;
            }),
         }))
         .filter((group) => group.pages.length > 0);
   }, [routes, role, subType]);

   // Set initial accordion state based on URL
   useEffect(() => {
      const slug = getRouteSegments(page.url);

      if (slug.length > 1) {
         setOpenAccordions(slug[1]);
      }
   }, [page.url]);

   return (
      <SidebarGroup className="px-2 py-0">
         <Accordion type="single" collapsible value={openAccordions} defaultValue={openAccordions} onValueChange={setOpenAccordions}>
            {visibleRoutes.map(({ title, pages }, key) => (
               <SidebarMenu key={key} className="space-y-1">
                  <SidebarGroupLabel className="text-sidebar-foreground/60 text-[11px] tracking-[0.14em] uppercase">{title}</SidebarGroupLabel>

                  {pages.map((pageRoute) => (
                     <SidebarMenuItem key={pageRoute.slug}>
                        <NavMainItem pageRoute={pageRoute} />
                     </SidebarMenuItem>
                  ))}
               </SidebarMenu>
            ))}
         </Accordion>
      </SidebarGroup>
   );
}
