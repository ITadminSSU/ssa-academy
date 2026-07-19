import { AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { SidebarMenuButton, useSidebar } from '@/components/ui/sidebar';
import { routeLastSegment, routeSecondSegment } from '@/lib/route';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

interface NavMainItemProps {
   pageRoute: RoutePage;
}

const NavMainItem = (props: NavMainItemProps) => {
   const page = usePage<SharedData>();
   const { auth } = page.props;
   const { state, toggleSidebar } = useSidebar();

   const { pageRoute } = props;
   const { Icon, name, path, children, slug } = pageRoute;
   const compact = state === 'collapsed' ? true : false;

   const activeAccordion = (slug: string) => {
      return routeSecondSegment(page.url) === slug;
   };

   const activeRoute = (slug: string) => {
      return routeLastSegment(page.url) === slug;
   };

   // Child routes can share the same last segment (e.g., "create").
   // To avoid multiple items being marked active, we also require the parent
   // accordion slug (second segment) to match.
   const activeChildRoute = (parentSlug: string, childSlug: string) => {
      return activeAccordion(parentSlug) && activeRoute(childSlug);
   };

   return children.length > 0 ? (
      <AccordionItem value={slug} className="border-0">
         <div
            onClick={() => compact && toggleSidebar()}
            className={cn(
               'hover:bg-sidebar-accent h-9 overflow-hidden rounded-lg',
               activeAccordion(slug) && 'bg-sidebar-accent text-sidebar-accent-foreground',
            )}
         >
            <AccordionTrigger
               className={cn('h-9 cursor-pointer py-0 pr-2 font-normal hover:no-underline', compact && '[&>svg]:hidden')}
            >
               <SidebarMenuButton
                  className={cn(
                     'cursor-pointer hover:bg-transparent active:bg-transparent',
                     activeAccordion(slug) && 'text-sidebar-accent-foreground',
                  )}
               >
                  <Icon className="h-4 w-4" />
                  <span className="text-sm">{name}</span>
               </SidebarMenuButton>
            </AccordionTrigger>
         </div>

         <AccordionContent className={cn('space-y-1 p-0 py-2', compact ? 'hidden' : '')}>
            {children.map(({ path, name, slug, access }, index: number) => {
               if (access.includes(auth.user?.role ?? '')) {
                  return (
                     <SidebarMenuButton
                        asChild
                        key={index}
                        isActive={activeChildRoute(pageRoute.slug, slug)}
                        className="h-8 rounded-lg px-3 data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground"
                     >
                        <Link href={path} prefetch>
                           <ChevronRight className="text-sidebar-foreground/50 h-3.5 w-3.5" />
                           <span className="text-sm font-normal capitalize">{name}</span>
                        </Link>
                     </SidebarMenuButton>
                  );
               }
            })}
         </AccordionContent>
      </AccordionItem>
   ) : (
      <SidebarMenuButton
         asChild
         isActive={activeRoute(slug)}
         className={cn(
            'h-9 rounded-lg data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground',
         )}
      >
         <Link href={path} prefetch>
            <Icon className="h-4 w-4" />
            <span className="text-sm">{name}</span>
         </Link>
      </SidebarMenuButton>
   );
};

export default NavMainItem;
