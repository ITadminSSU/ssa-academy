import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { getCompletedContents } from '@/lib/utils';
import { StudentCourseProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import QuizStatus from '../partials/quiz-status';

const CourseQuizzes = () => {
   const { props } = usePage<StudentCourseProps>();
   const { quizzes, watchHistory, courseGates } = props;
   const completed = getCompletedContents(watchHistory);

   return (
      <>
         {courseGates?.has_assignments && !courseGates.quizzes_unlocked && (
            <Alert className="mb-4">
               <Lock className="h-4 w-4" />
               <AlertTitle>Quizzes locked</AlertTitle>
               <AlertDescription>
                  Complete assignments (download sample, submit, and receive trainer Passed/Approved status) before taking quizzes
                  {courseGates.pending_assignments_count > 0
                     ? ` (${courseGates.pending_assignments_count} remaining).`
                     : '.'}
               </AlertDescription>
            </Alert>
         )}

         {quizzes.length > 0 ? (
            <div className="ssu-curriculum-panel space-y-2">
               {quizzes.map((section, sectionIndex) => (
                  <div key={section.id}>
                     <p className="text-muted-foreground px-1 pt-2 pb-3 text-xs leading-snug">
                        Section {sectionIndex + 1} — {section.title}
                     </p>

                     {section.section_quizzes.length > 0 ? (
                        <div className="space-y-2">
                           {section.section_quizzes.map((quiz) => (
                              <QuizStatus key={quiz.id} quiz={quiz} completed={completed} />
                           ))}
                        </div>
                     ) : (
                        <p className="text-muted-foreground px-1 pb-3 text-sm">There is no quiz added</p>
                     )}
                  </div>
               ))}
            </div>
         ) : (
            <div className="text-muted-foreground p-6 text-center text-sm">There is no quiz added</div>
         )}
      </>
   );
};

export default CourseQuizzes;
