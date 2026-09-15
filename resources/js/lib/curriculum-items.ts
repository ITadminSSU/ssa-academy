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
   sortCurriculumSections(sections).flatMap((section) =>
      mergeCurriculumItems(section).map((item) =>
         item.kind === 'lesson'
            ? { id: item.lesson.id, type: 'lesson' as const }
            : { id: item.quiz.id, type: 'quiz' as const },
      ),
   );

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
   item: CurriculumItemRef,
): boolean => completed.some((entry) => isSameCurriculumItem(entry, item));

/**
 * Matches CourseCompletionGateService: the first item is open, later items
 * unlock only after every previous lesson/quiz is complete.
 */
export const canAccessCurriculumItem = (
   sections: CurriculumSectionLike[],
   completed: Array<{ id: string | number; type: string }>,
   item: CurriculumItemRef,
   options?: { staffPreview?: boolean; completedOnly?: boolean },
): boolean => {
   if (options?.staffPreview) {
      return true;
   }

   if (isCurriculumItemComplete(completed, item)) {
      return true;
   }

   if (options?.completedOnly) {
      return false;
   }

   const items = flattenCurriculumItems(sections);
   const targetIndex = items.findIndex((entry) => isSameCurriculumItem(entry, item));

   if (targetIndex < 0) {
      return false;
   }

   if (targetIndex === 0) {
      return true;
   }

   return items.slice(0, targetIndex).every((entry) => isCurriculumItemComplete(completed, entry));
};

export const resolveCurriculumAccess = (
   props: {
      modules?: CurriculumSectionLike[] | null;
      quizzes?: CurriculumSectionLike[] | null;
      course?: { sections?: CurriculumSectionLike[] | null } | null;
      subscriptionAccess?: { staff_preview?: boolean; mode?: string } | null;
   },
   completed: Array<{ id: string | number; type: string }>,
   item: CurriculumItemRef,
): boolean =>
   canAccessCurriculumItem(getPageCurriculumSections(props), completed, item, {
      staffPreview: props.subscriptionAccess?.staff_preview ?? false,
      completedOnly: props.subscriptionAccess?.mode === 'completed_only',
   });
