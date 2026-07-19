import CurriculumSectionList from '@/components/curriculum-section-list';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { CoursePlayerProps } from '@/types/page';
import { Link, router, usePage } from '@inertiajs/react';
import Lesson from './lesson';
import Quiz from './quiz';

interface ContentListProps {
   completedContents: CompletedContent[];
   courseCompletion: {
      percentage: string;
      totalContents: number;
      completedContents: number;
   };
}

const ContentList = ({ completedContents, courseCompletion }: ContentListProps) => {
   const { props } = usePage<CoursePlayerProps>();
   const { course, watchHistory, translate, subscriptionAccess } = props;
   const { common, button } = translate;
   const canFinishCourse = subscriptionAccess?.can_finish_course ?? true;

   const finishCourseHandler = () => {
      router.get(route('course.player.finish', { watch_history: watchHistory.id }));
   };

   return (
      <div className="ssu-curriculum-panel relative flex h-[calc(100vh-4rem)] flex-col">
         <div className="border-border shrink-0 border-b px-4">
            <div className="flex items-center gap-6">
               <span className="border-primary text-foreground border-b-2 py-3 text-sm font-semibold">{button.lessons}</span>
            </div>
         </div>

         <div className="border-border text-muted-foreground shrink-0 border-b px-4 py-3 text-xs">
            <span className="text-foreground font-medium">{courseCompletion.percentage}%</span>
            <span className="mx-1.5">·</span>
            <span>
               {common.completed} {courseCompletion.completedContents}/{courseCompletion.totalContents}
            </span>
         </div>

         <ScrollArea className="min-h-0 flex-1">
            <CurriculumSectionList
               sections={course.sections}
               renderLesson={(lesson, index) => (
                  <Lesson key={lesson.id} lesson={lesson} completed={completedContents} variant="simple" index={index} />
               )}
               renderQuiz={(quiz, index) => (
                  <Quiz key={quiz.id} quiz={quiz} completed={completedContents} variant="simple" index={index} />
               )}
            />

            {course.sections.length > 0 && (
               <div className="px-4 pt-4 pb-6">
                  {watchHistory.completion_date ? (
                     <Link
                        href={route('student.course.show', {
                           id: course.id,
                           tab: 'certificate',
                        })}
                     >
                        <Button className="w-full" variant="default" size="lg" disabled={courseCompletion.percentage !== '100.00'}>
                           Course Certificate
                        </Button>
                     </Link>
                  ) : !watchHistory.next_watching_id ? (
                     <Button
                        className="w-full"
                        variant="default"
                        size="lg"
                        onClick={finishCourseHandler}
                        disabled={!canFinishCourse}
                     >
                        Finish Course
                     </Button>
                  ) : (
                     <Button className="w-full" variant="default" size="lg" disabled>
                        Finish Course
                     </Button>
                  )}
               </div>
            )}
         </ScrollArea>
      </div>
   );
};

export default ContentList;
