import MessagesUnreadBadge from '@/components/messages-unread-badge';
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
   GraduationCap,
   Heart,
   HelpCircle,
   Home as HomeIcon,
   LayoutDashboard,
   Megaphone,
   MessageCircle,
   MessagesSquare,
   CreditCard,
   Search,
   Settings as SettingsIcon,
   UserCircle,
} from 'lucide-react';
export function LearnerNavMain() {
   const page = usePage<SharedData & StudentDashboardProps>();
   const { auth, translate, instructor } = page.props;
   const { button } = translate;
   const activeTab = routeLastSegment(page.url) || 'home';

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

            {tabItem('home', 'Home', HomeIcon)}
            {tabItem('courses', button.my_courses || 'My Courses', GraduationCap)}

            {/* 4. Certificates */}
            {tabItem('certificates', 'Certificates', Award)}

            {/* 5. Exams */}
            {tabItem('exams', 'Exams', FileQuestion)}

            {/* 6. Announcement */}
            {tabItem('announcements', 'Announcement', Megaphone)}

            {/* 9. Community Discussion */}
            {tabItem('community', 'Community Discussion', MessagesSquare)}

            <SidebarMenuItem>
               <SidebarMenuButton asChild className="h-9 rounded-lg">
                  <Link href={route('messages.index')} prefetch>
                     <MessageCircle className="h-4 w-4" />
                     <span className="text-sm">Messages</span>
                     <MessagesUnreadBadge className="bg-accent ml-auto" />
                  </Link>
               </SidebarMenuButton>
            </SidebarMenuItem>

            {/* 10. Help Center */}
            {tabItem('help-center', 'Help Center', HelpCircle)}

            <SidebarMenuItem>
               <SidebarMenuButton asChild className="h-9 rounded-lg">
                  <Link href={route('fraud-training-tipline')} prefetch>
                     <Search className="h-4 w-4" />
                     <span className="text-sm">Fraud Training Tipline</span>
                  </Link>
               </SidebarMenuButton>
            </SidebarMenuItem>

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
