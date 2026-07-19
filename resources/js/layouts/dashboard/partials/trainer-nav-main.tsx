import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { Fragment } from 'react';
import { getDashboardRoutes } from './routes';

function normalizePath(url: string): string {
   const path = url.startsWith('http') ? new URL(url).pathname : url.split('?')[0];

   return path.replace(/\/$/, '') || '/';
}

function isNavPathMatch(currentUrl: string, targetPath: string): boolean {
   const current = normalizePath(currentUrl);
   const target = normalizePath(targetPath);

   if (current === target) {
      return true;
   }

   return current.startsWith(`${target}/`);
}

// Returns the single most specific (longest) matching path so that nested
// sibling routes (e.g. `/courses` vs `/courses/categories`) don't both light up.
function findActiveNavPath(currentUrl: string, candidatePaths: string[]): string | null {
   let activePath: string | null = null;
   let activeLength = -1;

   for (const candidate of candidatePaths) {
      if (!candidate || !isNavPathMatch(currentUrl, candidate)) {
         continue;
      }

      const length = normalizePath(candidate).length;

      if (length > activeLength) {
         activePath = candidate;
         activeLength = length;
      }
   }

   return activePath;
}

export function TrainerNavMain() {
   const page = usePage<SharedData>();
   const { auth, system, features } = page.props;
   const routes = getDashboardRoutes(auth.dashboardUrl ?? route('dashboard'), features);
   const pages = routes[0]?.pages ?? [];

   const canAccess = (access: string[]) =>
      access.includes(auth.user?.role || '') && access.includes(system?.sub_type || 'collaborative');

   const accessiblePages = pages.filter((entry) => canAccess(entry.access));

   const candidatePaths = accessiblePages.flatMap((section) =>
      section.children.length > 0
         ? section.children.filter((child) => canAccess(child.access)).map((child) => child.path)
         : [section.path],
   );
   const activePath = findActiveNavPath(page.url, candidatePaths);

   return (
      <SidebarGroup className="px-2 py-0">
         <SidebarMenu className="space-y-1">
            {accessiblePages.map((section) => {
               if (section.children.length === 0) {
                  return (
                     <SidebarMenuItem key={section.slug}>
                        <SidebarMenuButton
                           asChild
                           isActive={section.path === activePath}
                           className={cn(
                              'h-9 rounded-lg data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground',
                           )}
                        >
                           <Link href={section.path} prefetch>
                              <section.Icon className="h-4 w-4" />
                              <span className="text-sm">{section.name}</span>
                           </Link>
                        </SidebarMenuButton>
                     </SidebarMenuItem>
                  );
               }

               const items = section.children.filter((child) => canAccess(child.access));

               if (items.length === 0) {
                  return null;
               }

               return (
                  <Fragment key={section.slug}>
                     <SidebarGroupLabel className="text-sidebar-foreground/60 mt-4 text-[11px] tracking-[0.14em] uppercase">
                        {section.name}
                     </SidebarGroupLabel>

                     {items.map((item) => (
                        <SidebarMenuItem key={item.path}>
                           <SidebarMenuButton
                              asChild
                              isActive={item.path === activePath}
                              className={cn(
                                 'h-9 rounded-lg data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground',
                              )}
                           >
                              <Link href={item.path} prefetch>
                                 <span className="text-sm">{item.name}</span>
                              </Link>
                           </SidebarMenuButton>
                        </SidebarMenuItem>
                     ))}
                  </Fragment>
               );
            })}
         </SidebarMenu>
      </SidebarGroup>
   );
}
