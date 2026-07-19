import { Icon } from '@/components/icon';
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { BRAND_NAME } from '@/lib/branding';
import { BookOpen, LifeBuoy } from 'lucide-react';
import { type ComponentPropsWithoutRef } from 'react';

const footerNavItems = [
   {
      title: 'Help Center',
      href: '/contact-us',
      icon: LifeBuoy,
   },
   {
      title: 'About SSU Academy',
      href: '/about-us',
      icon: BookOpen,
   },
];

export function NavFooter({ className, ...props }: ComponentPropsWithoutRef<typeof SidebarGroup>) {
   return (
      <SidebarGroup {...props} className={`group-data-[collapsible=icon]:p-0 ${className || ''}`}>
         <SidebarGroupContent>
            <p className="text-muted-foreground px-3 pb-2 text-xs group-data-[collapsible=icon]:hidden">{BRAND_NAME}</p>
            <SidebarMenu>
               {footerNavItems.map((item) => (
                  <SidebarMenuItem key={item.title}>
                     <SidebarMenuButton
                        asChild
                        className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                     >
                        <a href={item.href}>
                           {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                           <span>{item.title}</span>
                        </a>
                     </SidebarMenuButton>
                  </SidebarMenuItem>
               ))}
            </SidebarMenu>
         </SidebarGroupContent>
      </SidebarGroup>
   );
}
