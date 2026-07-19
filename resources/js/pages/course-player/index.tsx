import { Sidebar, SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { useContentProtection } from '@/hooks/use-content-protection';
import Main from '@/layouts/main';
import { getCompletedContents, getCourseCompletion } from '@/lib/utils';
import Footer from '@/pages/course-player/layout/footer';
import { CoursePlayerProps } from '@/types/page';
import { useEffect, useState } from 'react';
import '../../../css/content-protection.css';
import Navbar from './layout/navbar';
import ContentList from './partials/content-list';
import ContentSummery from './partials/content-summery';
import LessonViewer from './partials/lesson-viewer';
import QuizViewer from './partials/quiz-viewer';
import SubscriptionAccessBanner from './partials/subscription-access-banner';

const Index = (props: CoursePlayerProps) => {
   const { type, watching, watchHistory } = props;
   const [sidebarWidth, setSidebarWidth] = useState('calc(var(--spacing) * 100)');

   useContentProtection(true);

   const completed = getCompletedContents(watchHistory);
   const completion = getCourseCompletion(props.course, completed);

   useEffect(() => {
      const handleResize = () => {
         if (window.innerWidth < 880) {
            setSidebarWidth('calc(var(--spacing) * 70)');
         } else if (window.innerWidth < 1024) {
            setSidebarWidth('calc(var(--spacing) * 80)');
         } else {
            setSidebarWidth('calc(var(--spacing) * 100)');
         }
      };

      handleResize();
      window.addEventListener('resize', handleResize);

      return () => window.removeEventListener('resize', handleResize);
   }, []);

   return (
      <div className="course-player-protected ssu-player-shell min-h-screen">
         <SidebarProvider
            className="flex-col"
            style={
               {
                  '--sidebar-width': sidebarWidth,
               } as React.CSSProperties
            }
         >
            <Navbar />

            <SubscriptionAccessBanner />

            <div className="flex w-full flex-row-reverse">
               <Sidebar side="right" className="ssu-player-sidebar top-16 shadow-lg">
                  <ContentList completedContents={completed} courseCompletion={completion} />
               </Sidebar>

               <SidebarInset>
                  <Main>
                     {type === 'lesson' ? (
                        <LessonViewer lesson={(watching as SectionLesson) ?? null} />
                     ) : (
                        <QuizViewer quiz={watching as SectionQuiz} />
                     )}

                     <ContentSummery />
                     <Footer />
                  </Main>
               </SidebarInset>
            </div>
         </SidebarProvider>
      </div>
   );
};

export default Index;
