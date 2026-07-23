import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import VideoPlayer from '@/components/video-player';
import { isExternalVideoLesson, isVideoLesson } from '@/lib/lesson';
import { cn, getCompletedContents } from '@/lib/utils';
import { CoursePlayerProps } from '@/types/page';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Renderer } from 'richtor';
import 'richtor/styles';
import DocumentViewer from './document-viewer';
import EmbedViewer from './embed-viewer';
import LessonControl from './lesson-control';
import PracticalActivityPanel from './practical-activity-panel';

interface LessonViewerProps {
   lesson: SectionLesson | null;
}

const LessonViewer = ({ lesson }: LessonViewerProps) => {
   const { props } = usePage<CoursePlayerProps>();
   const { translate, watchHistory, lessonWatchProgress, subscriptionAccess } = props;
   const { frontend } = translate;
   const canMarkProgress = subscriptionAccess?.can_mark_progress ?? true;
   const progressReported = useRef(false);
   const [hasVideoEnded, setHasVideoEnded] = useState(false);
   const [livePercent, setLivePercent] = useState(0);

   const completed = getCompletedContents(watchHistory);
   const isLessonComplete = lesson
      ? completed.some((item) => item.type === 'lesson' && String(item.id) === String(lesson.id))
      : false;

   useEffect(() => {
      setHasVideoEnded(false);
      setLivePercent(0);
      progressReported.current = false;
   }, [lesson?.id]);

   const markLessonComplete = useCallback(() => {
      if (!lesson || !canMarkProgress) {
         return;
      }

      router.post(
         route('course.player.complete', { watch_history: watchHistory.id }),
         {
            item_id: lesson.id,
            item_type: 'lesson',
         },
         {
            preserveScroll: true,
            preserveState: true,
            only: ['watchHistory', 'courseGates', 'lessonWatchProgress'],
            onError: () => setHasVideoEnded(true),
         },
      );
   }, [lesson, watchHistory.id, canMarkProgress]);

   const reportWatchProgress = useCallback(
      (currentTime: number, duration: number) => {
         if (!lesson || progressReported.current || !canMarkProgress) {
            return;
         }

         const percent = duration > 0 ? (currentTime / duration) * 100 : 0;
         setLivePercent((prev) => Math.max(prev, percent));

         if (percent >= 95) {
            progressReported.current = true;
         }

         // The watch-progress endpoint returns plain JSON (not an Inertia
         // response), so report it with axios instead of the Inertia router.
         axios
            .post(route('course.player.watch-progress', { watch_history: watchHistory.id }), {
               lesson_id: lesson.id,
               current_time: currentTime,
               duration,
            })
            .catch(() => {
               // Ignore transient progress-tracking errors; completion is still
               // handled on video end / via the manual button.
            });
      },
      [lesson, watchHistory.id, canMarkProgress],
   );

   const handleVideoEnded = useCallback(() => {
      setHasVideoEnded(true);
      markLessonComplete();
   }, [markLessonComplete]);

   if (!lesson) {
      return (
         <Card className="min-h-[60vh] w-full overflow-hidden rounded-lg">
            <div className="flex h-full items-center justify-center">
               <p>{frontend.no_lesson_found}</p>
            </div>
         </Card>
      );
   }

   const lessonIsVideo = isVideoLesson(lesson);
   const externalVideo = isExternalVideoLesson(lesson);
   const isPracticalActivity = Boolean(lesson.requires_submission);
   const watchPercent = Math.max(lessonWatchProgress?.percent ?? 0, livePercent);
   const nextLessonHref = watchHistory.next_watching_id
      ? route('course.player', {
           type: watchHistory.next_watching_type,
           watch_history: watchHistory.id,
           lesson_id: watchHistory.next_watching_id,
        })
      : null;

   return (
      <Card
         className={cn(
            'group relative w-full rounded-none border-none',
            lessonIsVideo ? 'lesson-container' : isPracticalActivity ? 'practical-activity-lesson' : 'lesson-content-card',
         )}
      >
         <LessonControl className="opacity-100 md:opacity-0 md:transition-all md:duration-300 md:group-hover:opacity-100" />

         {/* Always render this badge and toggle visibility with CSS. Mounting /
             unmounting a node beside the Plyr video (which rewrites its own DOM)
             makes React's insertBefore crash and white-screens the player. */}
         {lessonIsVideo && (
            <div
               className={cn(
                  'bg-muted/90 absolute top-2 right-2 z-20 rounded-md px-3 py-1 text-xs transition-opacity',
                  !isLessonComplete && watchPercent > 0 && watchPercent < 95 ? 'opacity-100' : 'pointer-events-none opacity-0',
               )}
               aria-hidden={isLessonComplete || watchPercent <= 0 || watchPercent >= 95}
            >
               Watch progress: {Math.round(watchPercent)}%
            </div>
         )}

         {lessonIsVideo && (
            <VideoPlayer
               protectDownload
               secureStream={Boolean(lesson.stream_protected)}
               lessonId={lesson.id}
               initialPlayback={lesson.video_playback ?? null}
               source={{
                  type: 'video' as const,
                  sources: [
                     {
                        src: lesson.lesson_src || '',
                        type: 'video/mp4' as const,
                     },
                  ],
               }}
               translate={translate}
               onEnded={handleVideoEnded}
               onWatchProgress={reportWatchProgress}
            />
         )}

         {lesson.lesson_type === 'document' && <DocumentViewer src={lesson.lesson_src || ''} protectedMode />}

         {lesson.lesson_type === 'embed' && <EmbedViewer src={lesson.lesson_src || ''} />}

         {lesson.lesson_type === 'text' && (
            <div className="w-full p-6">
               {isPracticalActivity && lesson.summary && (
                  <p className="text-muted-foreground mb-4 text-sm">{lesson.summary}</p>
               )}
               <Renderer value={lesson.lesson_src || ''} />
            </div>
         )}

         {lesson.lesson_type === 'image' && (
            <div className="flex h-full w-full items-center justify-center p-6">
               <img src={lesson.lesson_src || ''} alt={lesson.title} className="max-h-[70vh] rounded-lg object-contain" />
            </div>
         )}

         {isPracticalActivity && <PracticalActivityPanel lesson={lesson} isLessonComplete={isLessonComplete} />}

         {!isPracticalActivity && (
         <div className="border-t p-4">
            {isLessonComplete ? (
               <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-2 text-sm font-medium text-green-600">
                     <CheckCircle2 className="h-5 w-5" />
                     <span>Lesson completed</span>
                  </div>

                  {nextLessonHref ? (
                     <Button asChild>
                        <Link href={nextLessonHref}>Continue to next lesson</Link>
                     </Button>
                  ) : (
                     <p className="text-muted-foreground text-sm">You have reached the last item in this module.</p>
                  )}
               </div>
            ) : lessonIsVideo ? (
               <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <p className="text-muted-foreground text-sm">
                     {!canMarkProgress
                        ? 'This lesson is read-only while your subscription is inactive.'
                        : hasVideoEnded
                          ? 'Finishing lesson...'
                          : externalVideo
                            ? 'This lesson completes automatically when the video ends.'
                            : watchPercent >= 95
                              ? 'You watched enough of this video. Mark it complete to continue.'
                              : `This lesson is marked complete automatically when the video ends. If it does not, you can mark it complete here (${Math.round(watchPercent)}% watched).`}
                  </p>

                  {canMarkProgress ? <Button onClick={markLessonComplete}>Mark lesson as complete</Button> : null}
               </div>
            ) : (
               <div className="flex justify-end">
                  {canMarkProgress ? (
                     <Button onClick={markLessonComplete}>Mark lesson as complete</Button>
                  ) : (
                     <p className="text-muted-foreground text-sm">This lesson is read-only while your subscription is inactive.</p>
                  )}
               </div>
            )}
         </div>
         )}
      </Card>
   );
};

export default LessonViewer;
