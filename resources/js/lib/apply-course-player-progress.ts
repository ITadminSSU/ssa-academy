import { CoursePlayerProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';

export type CoursePlayerProgressPayload = {
   watchHistory: WatchHistory;
   courseGates?: CoursePlayerProps['courseGates'];
   lessonWatchProgress?: CoursePlayerProps['lessonWatchProgress'];
};

type ProgressSnapshot = CoursePlayerProgressPayload | null;

let overlay: ProgressSnapshot = null;
const listeners = new Set<() => void>();

function emit(): void {
   listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
   listeners.add(listener);
   return () => listeners.delete(listener);
}

function getSnapshot(): ProgressSnapshot {
   return overlay;
}

/**
 * Merge player progress into a local store. Do not use Inertia replace/visit
 * here: updating the page while the Bunny iframe is mounted crashes React
 * (insertBefore) and shows "This lesson could not be shown".
 */
export function applyCoursePlayerProgress(payload: CoursePlayerProgressPayload): void {
   if (!payload?.watchHistory) {
      return;
   }

   overlay = payload;
   emit();
}

export function clearCoursePlayerProgress(): void {
   if (!overlay) {
      return;
   }

   overlay = null;
   emit();
}

export function useCoursePlayerProgress(): {
   watchHistory: WatchHistory;
   courseGates: CoursePlayerProps['courseGates'];
   lessonWatchProgress: CoursePlayerProps['lessonWatchProgress'];
} {
   const { props } = usePage<CoursePlayerProps>();
   const snapshot = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
   const sameHistory = snapshot?.watchHistory?.id === props.watchHistory?.id;

   useEffect(() => {
      if (snapshot && snapshot.watchHistory?.id !== props.watchHistory?.id) {
         clearCoursePlayerProgress();
      }
   }, [props.watchHistory?.id, snapshot]);

   return {
      watchHistory: sameHistory && snapshot?.watchHistory ? snapshot.watchHistory : props.watchHistory,
      courseGates: sameHistory && snapshot?.courseGates ? snapshot.courseGates : props.courseGates,
      // Keep page progress for the lesson on screen. Overlay progress is for
      // the item that just finished and would leak onto the next lesson.
      lessonWatchProgress: props.lessonWatchProgress,
   };
}
