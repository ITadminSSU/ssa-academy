export function isExternalVideoLesson(lesson: SectionLesson): boolean {
   if (lesson.lesson_provider === 'youtube' || lesson.lesson_provider === 'vimeo') {
      return true;
   }

   const src = lesson.lesson_src ?? '';

   return src.includes('youtube.com') || src.includes('youtu.be') || src.includes('vimeo.com');
}

export function isVideoLesson(lesson: SectionLesson): boolean {
   return ['video', 'video_url'].includes(lesson.lesson_type);
}
