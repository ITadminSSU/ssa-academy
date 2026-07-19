import LessonIcons from '@/components/lesson-icons';
import { cn } from '@/lib/utils';
import { CoursePlayerProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Lock } from 'lucide-react';
import { ReactNode } from 'react';

interface Props {
   lesson: SectionLesson;
   completed: { id: number | string; type: string }[];
   variant?: 'default' | 'simple';
   index?: number;
}

const getLessonMeta = (lesson: SectionLesson): string => {
   const labels: Record<string, string> = {
      video: 'Video',
      video_url: 'Video',
      document: 'Document',
      text: 'Reading',
      embed: 'Lesson',
      image: 'Image',
   };

   const label = labels[lesson.lesson_type] ?? 'Lesson';

   if (lesson.duration && ['video', 'video_url'].includes(lesson.lesson_type)) {
      return `${label} · ${lesson.duration}`;
   }

   return label;
};

const LessonWrapper = ({ lesson, children }: { lesson: SectionLesson; children: ReactNode }) => (
   <div className="relative flex items-center justify-between rounded-sm border p-2 md:gap-3">
      {children}

      {['video', 'video_url'].includes(lesson.lesson_type) && (
         <span className="absolute top-0.5 right-1 text-xs md:relative md:text-sm">{lesson.duration}</span>
      )}
   </div>
);

const Lesson = ({ lesson, completed, variant = 'default', index }: Props) => {
   const { props } = usePage<CoursePlayerProps>();
   const { watchHistory, subscriptionAccess } = props;

   const dripContent = true;
   const subscriptionLocked = subscriptionAccess?.mode === 'completed_only';
   const isNext = lesson.id == watchHistory.next_watching_id;
   const isCompleted = completed.some((item) => item.type === 'lesson' && item.id == lesson.id);
   const isCurrentLesson = watchHistory.current_watching_id == lesson.id && watchHistory.current_watching_type === 'lesson';
   const canAccess = subscriptionLocked ? isCompleted : isCompleted || isCurrentLesson || isNext;

   if (variant === 'simple') {
      const content = (
         <div className="flex min-w-0 flex-1 items-start gap-3">
            <span className="text-muted-foreground w-6 shrink-0 pt-0.5 text-sm tabular-nums">{index}</span>
            <div className="min-w-0 flex-1">
               <p className="text-sm leading-snug font-semibold">{lesson.title}</p>
               <p className="text-muted-foreground mt-0.5 text-xs">{getLessonMeta(lesson)}</p>
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
               {subscriptionLocked && !isCompleted ? (
                  <p className="text-muted-foreground mt-1 pl-9 text-[11px]">Resubscribe to unlock</p>
               ) : null}
            </div>
         );
      }

      return (
         <Link
            href={route('course.player', {
               type: 'lesson',
               watch_history: watchHistory.id,
               lesson_id: lesson.id,
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

   return !dripContent ? (
      <LessonWrapper lesson={lesson}>
         <Link
            className={cn(
               'flex cursor-pointer items-center gap-3 py-1',
               isCompleted ? 'text-blue-500' : isCurrentLesson ? 'text-green-500' : 'text-primary',
            )}
            href={route('course.player', {
               type: 'lesson',
               watch_history: watchHistory.id,
               lesson_id: lesson.id,
            })}
         >
            <LessonIcons type="active" lesson={lesson} dripContent={true} isCompleted={isCompleted} />

            <p>{lesson.title}</p>
         </Link>
      </LessonWrapper>
   ) : (
      <>
         {canAccess ? (
            <LessonWrapper lesson={lesson}>
               <Link
                  className={cn(
                     'flex cursor-pointer items-center gap-3 py-1',
                     isCompleted ? 'text-blue-500' : isCurrentLesson ? 'text-green-500' : isNext ? 'text-primary' : 'text-muted-foreground',
                  )}
                  href={route('course.player', {
                     type: 'lesson',
                     watch_history: watchHistory.id,
                     lesson_id: lesson.id,
                  })}
               >
                  <LessonIcons
                     type="active"
                     lesson={lesson}
                     dripContent={false}
                     isCompleted={isCompleted}
                     isCurrentLesson={isCurrentLesson}
                     isNext={isNext}
                  />

                  <p>{lesson.title}</p>
               </Link>
            </LessonWrapper>
         ) : (
            <LessonWrapper lesson={lesson}>
               <div className="flex items-center gap-3 py-1 text-muted-foreground">
                  <LessonIcons type="inactive" lesson={lesson} dripContent={true} isCompleted={isCompleted} />

                  <p>{lesson.title}</p>
               </div>
            </LessonWrapper>
         )}
      </>
   );
};

export default Lesson;
