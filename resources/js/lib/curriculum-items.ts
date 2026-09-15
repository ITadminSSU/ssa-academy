export type MergedCurriculumItem =
   | { kind: 'lesson'; sort: number; lesson: SectionLesson }
   | { kind: 'quiz'; sort: number; quiz: SectionQuiz };

export const mergeCurriculumItems = (section: {
   section_lessons?: SectionLesson[];
   section_quizzes?: SectionQuiz[];
}): MergedCurriculumItem[] => {
   const lessons = (section.section_lessons ?? []).map((lesson) => ({
      kind: 'lesson' as const,
      sort: Number(lesson.sort ?? 0),
      lesson,
   }));

   const quizzes = (section.section_quizzes ?? []).map((quiz) => ({
      kind: 'quiz' as const,
      sort: Number((quiz as SectionQuiz & { sort?: number }).sort ?? 0),
      quiz,
   }));

   const hasQuizSort = quizzes.some((item) => item.sort > 0);

   if (!hasQuizSort) {
      const lessonMax = lessons.reduce((max, item) => Math.max(max, item.sort), 0);
      quizzes.forEach((item, index) => {
         item.sort = lessonMax + index + 1;
      });
   }

   return [...lessons, ...quizzes].sort((a, b) => a.sort - b.sort || a.kind.localeCompare(b.kind));
};

export const toSortableCurriculumItems = (section: {
   section_lessons?: SectionLesson[];
   section_quizzes?: SectionQuiz[];
}) =>
   mergeCurriculumItems(section).map((item, index) =>
      item.kind === 'lesson'
         ? {
              id: `lesson-${item.lesson.id}`,
              sort: index + 1,
              item_type: 'lesson' as const,
              item_id: item.lesson.id,
              title: item.lesson.title,
           }
         : {
              id: `quiz-${item.quiz.id}`,
              sort: index + 1,
              item_type: 'quiz' as const,
              item_id: item.quiz.id,
              title: item.quiz.title,
           },
   );

export type CurriculumSectionLike = {
   id?: string | number;
   sort?: number | string | null;
   section_lessons?: SectionLesson[];
   section_quizzes?: SectionQuiz[];
};

export const sortCurriculumSections = <T extends CurriculumSectionLike>(sections: T[]): T[] =>
   [...sections].sort((left, right) => {
      const sortDelta = Number(left.sort ?? 0) - Number(right.sort ?? 0);

      if (sortDelta !== 0) {
         return sortDelta;
      }

      return Number(left.id ?? 0) - Number(right.id ?? 0);
   });

export type CurriculumItemRef = {
   id: string | number;
   type: 'lesson' | 'quiz';
};

export const isSameCurriculumItem = (
   left: { id: string | number; type: string },
   right: { id: string | number; type: string },
): boolean => left.type === right.type && String(left.id) === String(right.id);

export const flattenCurriculumItems = (sections: CurriculumSectionLike[]): CurriculumItemRef[] =>
   flattenCurriculumItemsDetailed(sections).map(({ id, type }) => ({ id, type }));

export type FlattenedCurriculumItem = CurriculumItemRef & {
   title: string;
   lessonType?: string | null;
   lessonProvider?: string | null;
   lessonSrc?: string | null;
};

export const flattenCurriculumItemsDetailed = (sections: CurriculumSectionLike[]): FlattenedCurriculumItem[] =>
   sortCurriculumSections(sections).flatMap((section) =>
      mergeCurriculumItems(section).map((item) =>
         item.kind === 'lesson'
            ? {
                 id: item.lesson.id,
                 type: 'lesson' as const,
                 title: item.lesson.title,
                 lessonType: item.lesson.lesson_type,
                 lessonProvider: item.lesson.lesson_provider,
                 lessonSrc: item.lesson.lesson_src,
              }
            : { id: item.quiz.id, type: 'quiz' as const, title: item.quiz.title },
      ),
   );

type LessonWatchProgressMap = Record<string, { percent?: number; max_seconds?: number; duration_seconds?: number }>;

const VIDEO_LESSON_TYPES = ['video', 'video_url'];
const WATCH_COMPLETE_PERCENT = 95;

const isExternalVideoLesson = (item: FlattenedCurriculumItem): boolean => {
   const provider = String(item.lessonProvider ?? '');
   const src = String(item.lessonSrc ?? '');

   return (
      provider === 'youtube' ||
      provider === 'vimeo' ||
      src.includes('youtube.com') ||
      src.includes('youtu.be') ||
      src.includes('vimeo.com')
   );
};

const requiresWatchedVideo = (item: FlattenedCurriculumItem): boolean =>
   item.type === 'lesson' && VIDEO_LESSON_TYPES.includes(item.lessonType ?? '') && !isExternalVideoLesson(item);

export const getCurriculumLockReason = (
   sections: CurriculumSectionLike[],
   completed: Array<{ id: string | number; type: string }>,
   item: CurriculumItemRef,
   options?: {
      staffPreview?: boolean;
      completedOnly?: boolean;
      isSubscriptionCourse?: boolean;
      watchProgress?: LessonWatchProgressMap | null;
   },
): string | null => {
   const items = flattenCurriculumItemsDetailed(sections);
   const target = items.find((entry) => isSameCurriculumItem(entry, item)) ?? item;

   if (options?.staffPreview || isCurriculumItemComplete(completed, target, options?.watchProgress)) {
      return null;
   }

   if (options?.completedOnly) {
      return options.isSubscriptionCourse
         ? item.type === 'quiz'
            ? 'This quiz is locked. Resubscribe to continue learning.'
            : 'This lesson is locked. Resubscribe to continue learning.'
         : item.type === 'quiz'
           ? 'This quiz is locked.'
           : 'This lesson is locked.';
   }

   const targetIndex = items.findIndex((entry) => isSameCurriculumItem(entry, item));

   if (targetIndex <= 0) {
      return item.type === 'quiz'
         ? 'Complete the previous item before continuing.'
         : 'Complete the previous lesson before continuing.';
   }

   const predecessor = items
      .slice(0, targetIndex)
      .find((entry) => !isCurriculumItemComplete(completed, entry, options?.watchProgress));

   if (predecessor?.title) {
      return `Finish "${predecessor.title}" before this ${item.type === 'quiz' ? 'quiz' : 'lesson'}.`;
   }

   return item.type === 'quiz'
      ? 'Complete the previous item before continuing.'
      : 'Complete the previous lesson before continuing.';
};

export const resolveCurriculumLockReason = (
   props: {
      modules?: CurriculumSectionLike[] | null;
      quizzes?: CurriculumSectionLike[] | null;
      course?: { sections?: CurriculumSectionLike[] | null } | null;
      watchHistory?: { lesson_watch_progress?: LessonWatchProgressMap | null } | null;
      subscriptionAccess?: { staff_preview?: boolean; mode?: string; is_subscription_course?: boolean } | null;
   },
   completed: Array<{ id: string | number; type: string }>,
   item: CurriculumItemRef,
): string | null => {
   if (resolveCurriculumAccess(props, completed, item)) {
      return null;
   }

   return getCurriculumLockReason(getPageCurriculumSections(props), completed, item, {
      staffPreview: props.subscriptionAccess?.staff_preview ?? false,
      completedOnly: props.subscriptionAccess?.mode === 'completed_only',
      isSubscriptionCourse: props.subscriptionAccess?.is_subscription_course ?? false,
      watchProgress: props.watchHistory?.lesson_watch_progress,
   });
};

export const getPageCurriculumSections = (props: {
   modules?: CurriculumSectionLike[] | null;
   quizzes?: CurriculumSectionLike[] | null;
   course?: { sections?: CurriculumSectionLike[] | null } | null;
}): CurriculumSectionLike[] => {
   const candidates = [props.modules, props.course?.sections, props.quizzes];

   const sections = candidates.find((candidate) => Array.isArray(candidate) && candidate.length > 0) ?? [];

   return sortCurriculumSections(sections);
};

export const isCurriculumItemComplete = (
   completed: Array<{ id: string | number; type: string }>,
   item: CurriculumItemRef | FlattenedCurriculumItem,
   watchProgress?: LessonWatchProgressMap | null,
): boolean => {
   if (!completed.some((entry) => isSameCurriculumItem(entry, item))) {
      return false;
   }

   if (!requiresWatchedVideo(item as FlattenedCurriculumItem)) {
      return true;
   }

   return Number(watchProgress?.[String(item.id)]?.percent ?? 0) >= WATCH_COMPLETE_PERCENT;
};

/**
 * Matches CourseCompletionGateService: the first item is open, later items
 * unlock only after every previous lesson/quiz is complete.
 */
export const canAccessCurriculumItem = (
   sections: CurriculumSectionLike[],
   completed: Array<{ id: string | number; type: string }>,
   item: CurriculumItemRef,
   options?: { staffPreview?: boolean; completedOnly?: boolean; watchProgress?: LessonWatchProgressMap | null },
): boolean => {
   if (options?.staffPreview) {
      return true;
   }

   const items = flattenCurriculumItemsDetailed(sections);
   const target = items.find((entry) => isSameCurriculumItem(entry, item)) ?? item;

   if (isCurriculumItemComplete(completed, target, options?.watchProgress)) {
      return true;
   }

   if (options?.completedOnly) {
      return false;
   }

   const targetIndex = items.findIndex((entry) => isSameCurriculumItem(entry, item));

   if (targetIndex < 0) {
      return false;
   }

   if (targetIndex === 0) {
      return true;
   }

   return items
      .slice(0, targetIndex)
      .every((entry) => isCurriculumItemComplete(completed, entry, options?.watchProgress));
};

export const resolveCurriculumAccess = (
   props: {
      modules?: CurriculumSectionLike[] | null;
      quizzes?: CurriculumSectionLike[] | null;
      course?: { sections?: CurriculumSectionLike[] | null } | null;
      watchHistory?: { lesson_watch_progress?: LessonWatchProgressMap | null } | null;
      subscriptionAccess?: { staff_preview?: boolean; mode?: string } | null;
   },
   completed: Array<{ id: string | number; type: string }>,
   item: CurriculumItemRef,
): boolean =>
   canAccessCurriculumItem(getPageCurriculumSections(props), completed, item, {
      staffPreview: props.subscriptionAccess?.staff_preview ?? false,
      completedOnly: props.subscriptionAccess?.mode === 'completed_only',
      watchProgress: props.watchHistory?.lesson_watch_progress,
   });
