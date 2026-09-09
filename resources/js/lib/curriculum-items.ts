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
