import {
   SidebarGroup,
   SidebarGroupLabel,
   SidebarMenu,
   SidebarMenuButton,
   SidebarMenuItem,
} from '@/components/ui/sidebar';
import { getDashboardUrl, getStudentDashboardUrl } from '@/lib/dashboard';
import { routeLastSegment } from '@/lib/route';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { StudentDashboardProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import {
   Award,
   FileQuestion,
   FolderClosed,
   GraduationCap,
   Heart,
   HelpCircle,
   Home as HomeIcon,
   LayoutDashboard,
   Megaphone,
   MessagesSquare,
   CreditCard,
   Settings as SettingsIcon,
   UserCircle,
} from 'lucide-react';
export function LearnerNavMain() {
   const page = usePage<SharedData & StudentDashboardProps>();
   const { auth, translate, instructor, learnerNav } = page.props;
   const { button } = translate;
   const activeTab = routeLastSegment(page.url) || 'home';
   const categories = learnerNav?.categories ?? [];

   const tabItem = (slug: string, name: string, Icon: typeof HomeIcon) => (
      <SidebarMenuItem key={slug}>
         <SidebarMenuButton
            asChild
            isActive={activeTab === slug}
            className={cn('h-9 rounded-lg data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground')}
         >
            <Link href={getStudentDashboardUrl(auth.user!, slug)} prefetch>
               <Icon className="h-4 w-4" />
               <span className="text-sm">{name}</span>
            </Link>
         </SidebarMenuButton>
      </SidebarMenuItem>
   );

   return (
      <SidebarGroup className="px-2 py-0">
         <SidebarMenu className="space-y-1">
            <SidebarGroupLabel className="text-sidebar-foreground/60 text-[11px] tracking-[0.14em] uppercase">My Academy</SidebarGroupLabel>

            {/* 1. Home */}
            {tabItem('home', 'Home', HomeIcon)}

            {/* 2. Dynamic course-category links (open the filtered catalog page) */}
            {categories.map((category) => {
               const categoryUrl = route('student.category.courses', { category: category.slug });
               const isActive = page.url.split('?')[0].replace(/\/$/, '') === new URL(categoryUrl, window.location.origin).pathname.replace(/\/$/, '');

               return (
                  <SidebarMenuItem key={category.id}>
                     <SidebarMenuButton
                        asChild
                        isActive={isActive}
                        className={cn('h-9 rounded-lg data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground')}
                     >
                        <Link href={categoryUrl} prefetch>
                           <FolderClosed className="h-4 w-4" />
                           <span className="truncate text-sm">{category.title}</span>
                        </Link>
                     </SidebarMenuButton>
                  </SidebarMenuItem>
               );
            })}

            {/* 3. My Courses */}
            {tabItem('courses', button.courses, GraduationCap)}

            {/* 4. Certificates */}
            {tabItem('certificates', 'Certificates', Award)}

            {/* 5. Exams */}
            {tabItem('exams', 'Exams', FileQuestion)}

            {/* 6. Announcement */}
            {tabItem('announcements', 'Announcement', Megaphone)}

            {/* 9. Community Discussion */}
            {tabItem('community', 'Community Discussion', MessagesSquare)}

            {/* 10. Help Center */}
            {tabItem('help-center', 'Help Center', HelpCircle)}

            {/* Explore */}
            <SidebarGroupLabel className="text-sidebar-foreground/60 mt-4 text-[11px] tracking-[0.14em] uppercase">Explore</SidebarGroupLabel>

            <SidebarMenuItem>
               <SidebarMenuButton asChild className="h-9 rounded-lg">
                  <Link href={route('student.category.courses', { category: 'all' })} prefetch>
                     <span className="text-sm">Browse Courses</span>
                  </Link>
               </SidebarMenuButton>
            </SidebarMenuItem>

            {tabItem('wishlist', button.wishlist, Heart)}

            {/* Account: 11. Profile, 12. Settings */}
            <SidebarGroupLabel className="text-sidebar-foreground/60 mt-4 text-[11px] tracking-[0.14em] uppercase">Account</SidebarGroupLabel>

            {tabItem('profile', button.profile, UserCircle)}
            {tabItem('subscriptions', 'Subscriptions', CreditCard)}
            {tabItem('settings', button.settings, SettingsIcon)}

            {instructor?.status === 'approved' && (
               <SidebarMenuItem>
                  <SidebarMenuButton asChild className="h-9 rounded-lg">
                     <Link href={getDashboardUrl(auth)} prefetch>
                        <LayoutDashboard className="h-4 w-4" />
                        <span className="text-sm">{translate.common.dashboard}</span>
                     </Link>
                  </SidebarMenuButton>
               </SidebarMenuItem>
            )}
         </SidebarMenu>
      </SidebarGroup>
   );
}
