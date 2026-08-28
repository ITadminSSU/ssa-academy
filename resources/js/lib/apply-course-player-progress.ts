import { CoursePlayerProps } from '@/types/page';
import { router } from '@inertiajs/react';

export type CoursePlayerProgressPayload = {
   watchHistory: WatchHistory;
   courseGates?: CoursePlayerProps['courseGates'];
   lessonWatchProgress?: CoursePlayerProps['lessonWatchProgress'];
};

/**
 * Merge player progress into the current Inertia page without visiting the
 * player URL. A visit remounts the Bunny iframe and crashes the lesson pane.
 */
export function applyCoursePlayerProgress(payload: CoursePlayerProgressPayload): void {
   router.replace({
      preserveState: true,
      preserveScroll: true,
      props: (current: CoursePlayerProps) => ({
         ...current,
         watchHistory: payload.watchHistory,
         courseGates: payload.courseGates ?? current.courseGates,
         lessonWatchProgress: payload.lessonWatchProgress ?? current.lessonWatchProgress,
      }),
   });
}
