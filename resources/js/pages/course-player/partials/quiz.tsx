import { cn } from '@/lib/utils';
import { CoursePlayerProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Circle, CircleCheck, CirclePause, CirclePlay, FileQuestion, Lock } from 'lucide-react';

interface Props {
   quiz: SectionQuiz;
   completed: { id: number | string; type: string }[];
   variant?: 'default' | 'simple';
   index?: number;
}

const QuizIcon = ({ quiz }: { quiz: SectionQuiz }) => {
   return (
      <div className="flex items-center gap-2">
         <div className="bg-secondary flex h-6 w-6 items-center justify-center rounded-full">
            <FileQuestion className="h-4 w-4" />
         </div>

         <p>{quiz.title}</p>
      </div>
   );
};

const Quiz = ({ quiz, completed, variant = 'default', index }: Props) => {
   const { props } = usePage<CoursePlayerProps>();
   const { watchHistory, courseGates, subscriptionAccess } = props;

   const dripContent = true;
   const subscriptionLocked = subscriptionAccess?.mode === 'completed_only';
   const quizzesUnlocked = courseGates?.quizzes_unlocked ?? true;
   const isCompleted = completed.some((item) => item.type === 'quiz' && item.id == quiz.id);
   const isCurrentLesson = watchHistory.current_watching_type === 'quiz' && watchHistory.current_watching_id == quiz.id;
   const isNext = watchHistory.next_watching_type === 'quiz' && quiz.id == watchHistory.next_watching_id;
   const canAccess = subscriptionLocked
      ? isCompleted
      : quizzesUnlocked && (isCompleted || isCurrentLesson || isNext);

   if (variant === 'simple') {
      const meta = quiz.duration ? `Quiz · ${quiz.duration}` : 'Quiz';

      const content = (
         <div className="flex min-w-0 flex-1 items-start gap-3">
            <span className="text-muted-foreground w-6 shrink-0 pt-0.5 text-sm tabular-nums">{index}</span>
            <div className="min-w-0 flex-1">
               <p className="text-sm leading-snug font-semibold">{quiz.title}</p>
               <p className="text-muted-foreground mt-0.5 text-xs">{meta}</p>
            </div>
            {isCompleted ? (
               <CheckCircle2 className="text-primary mt-0.5 h-4 w-4 shrink-0" />
            ) : !canAccess ? (
               <Lock className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
            ) : null}
         </div>
      );

      if (!canAccess) {
         return (
            <div className="text-muted-foreground px-4 py-3">
               {content}
               {!quizzesUnlocked && !isCompleted ? (
                  <p className="text-muted-foreground mt-1 pl-9 text-[11px]">Trainer approval required</p>
               ) : subscriptionLocked && !isCompleted ? (
                  <p className="text-muted-foreground mt-1 pl-9 text-[11px]">Resubscribe to unlock</p>
               ) : null}
            </div>
         );
      }

      return (
         <Link
            href={route('course.player', {
               type: 'quiz',
               watch_history: watchHistory.id,
               lesson_id: quiz.id,
            })}
            className={cn(
               'hover:bg-muted/70 ssu-curriculum-item block px-4 py-3 transition-colors',
               isCurrentLesson && 'ssu-curriculum-item--active',
               isCompleted && !isCurrentLesson && 'text-foreground',
            )}
         >
            {content}
         </Link>
      );
   }

   if (!quizzesUnlocked && !isCompleted) {
      return (
         <div className="flex items-center justify-between gap-3 rounded-sm border p-2 py-2 md:gap-3">
            <div className="flex items-center gap-3 py-1 text-muted-foreground">
               <Lock className="h-4 w-4" />
               <QuizIcon quiz={quiz} />
            </div>
            <span className="text-muted-foreground text-xs">Trainer approval required</span>
         </div>
      );
   }

   return !dripContent ? (
      <div className="flex items-center justify-between gap-3 rounded-sm border p-2 py-2 md:gap-3">
         {quizzesUnlocked ? (
            <Link
               className={cn(
                  'flex cursor-pointer items-center gap-3 py-1',
                  isCompleted ? 'text-blue-500' : isCurrentLesson ? 'text-green-500' : 'text-primary',
               )}
               href={route('course.player', {
                  type: 'quiz',
                  watch_history: watchHistory.id,
                  lesson_id: quiz.id,
               })}
            >
               {isCompleted ? <CircleCheck className="h-4 w-4" /> : <Circle className="h-4 w-4" />}

               <QuizIcon quiz={quiz} />
            </Link>
         ) : (
            <div className="flex items-center gap-3 py-1 text-muted-foreground">
               <Lock className="h-4 w-4" />
               <QuizIcon quiz={quiz} />
            </div>
         )}

         <span>{quiz.duration}</span>
      </div>
   ) : (
      <>
         {canAccess ? (
            <div className="flex items-center justify-between gap-3 rounded-sm border p-2 py-2 md:gap-3">
               <Link
                  className={cn(
                     'flex cursor-pointer items-center gap-3 py-1',
                     isCompleted ? 'text-blue-500' : isCurrentLesson ? 'text-green-500' : isNext ? 'text-primary' : 'text-muted-foreground',
                  )}
                  href={route('course.player', {
                     type: 'quiz',
                     watch_history: watchHistory.id,
                     lesson_id: quiz.id,
                  })}
               >
                  {isCompleted ? (
                     <CircleCheck className="h-4 w-4" />
                  ) : isCurrentLesson ? (
                     <CirclePlay className="h-4 w-4" />
                  ) : isNext ? (
                     <CirclePause className="h-4 w-4" />
                  ) : (
                     <Lock className="h-4 w-4" />
                  )}

                  <QuizIcon quiz={quiz} />
               </Link>

               <span>{quiz.duration}</span>
            </div>
         ) : (
            <div className="flex items-center justify-between gap-3 rounded-sm border p-2 py-2 md:gap-3">
               <div className="flex items-center gap-3 py-1 text-muted-foreground">
                  <Lock className="h-4 w-4" />

                  <QuizIcon quiz={quiz} />
               </div>

               <span>{quiz.duration}</span>
            </div>
         )}
      </>
   );
};

export default Quiz;
