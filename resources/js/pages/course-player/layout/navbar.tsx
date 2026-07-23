import AppLogo from '@/components/app-logo';
import Appearance from '@/components/appearance';
import Notification from '@/components/notification';
import ProfileToggle from '@/components/profile-toggle';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { useSidebar } from '@/components/ui/sidebar';
import useScreen from '@/hooks/use-screen';
import { getCompletedContents, getCourseCompletion } from '@/lib/utils';
import { CoursePlayerProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ListTree, PanelRightClose, PanelRightOpen } from 'lucide-react';

const Navbar = () => {
   const { screen } = useScreen();
   const { open, toggleSidebar } = useSidebar();
   const { props } = usePage<CoursePlayerProps>();
   const { course, watchHistory } = props;

   const completed = getCompletedContents(watchHistory);
   const completion = getCourseCompletion(course, completed);

   return (
      <header className="ssu-player-header sticky top-0 z-50 overflow-hidden">
         <div className="flex h-16 items-center gap-3 px-4 md:gap-4 md:px-6">
            <div className="flex min-w-0 items-center gap-3 md:gap-4">
               <Link href={route('home')} className="ssu-logo-frame ssu-logo-frame--nav shrink-0">
                  <AppLogo theme="dark" className="ssu-nav-logo" />
               </Link>

               <div className="hidden h-8 w-px bg-card/20 sm:block" />

               <Link
                  href={route('course.details', { slug: course.slug, id: course.id })}
                  className="hidden items-center gap-1.5 text-sm font-medium text-white/85 transition-colors hover:text-white sm:inline-flex"
               >
                  <ArrowLeft className="h-4 w-4" />
                  Course details
               </Link>
            </div>

            <div className="min-w-0 flex-1 px-1 md:px-4">
               <p className="mb-0.5 hidden text-[10px] font-semibold tracking-[0.14em] text-white/70 uppercase md:block">Now learning</p>
               <p className="font-display truncate text-sm font-semibold text-white md:text-base">{course.title}</p>
               <div className="mt-1.5 hidden max-w-md items-center gap-2 md:flex">
                  <Progress value={Number(completion.percentage)} className="ssu-player-progress h-1.5 flex-1" />
                  <span className="shrink-0 text-xs tabular-nums text-white/90">{completion.percentage}%</span>
               </div>
            </div>

            <div className="flex shrink-0 items-center gap-2">
               <Appearance />

               <Notification />

               {screen > 768 && (
                  <Button
                     size="default"
                     variant="secondary"
                     onClick={() => toggleSidebar()}
                     className="hidden rounded-full border-white/30 bg-card/15 text-white hover:bg-card/25 hover:text-white sm:inline-flex"
                  >
                     {open ? <PanelRightClose className="mr-1.5 h-4 w-4" /> : <PanelRightOpen className="mr-1.5 h-4 w-4" />}
                     Curriculum
                  </Button>
               )}

               <ProfileToggle />

               {screen < 768 && (
                  <Button
                     size="icon"
                     variant="secondary"
                     onClick={() => toggleSidebar()}
                     className="rounded-full border-white/30 bg-card/15 text-white hover:bg-card/25 hover:text-white"
                  >
                     <ListTree className="h-4 w-4" />
                  </Button>
               )}
            </div>
         </div>
      </header>
   );
};

export default Navbar;
