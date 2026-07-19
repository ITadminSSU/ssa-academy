import { cn } from '@/lib/utils';
import { ReactNode } from 'react';

export interface CurriculumSection {
   id: string | number;
   title: string;
   section_lessons?: SectionLesson[];
   section_quizzes?: SectionQuiz[];
}

interface CurriculumSectionListProps {
   sections: CurriculumSection[];
   className?: string;
   includeLessons?: boolean;
   includeQuizzes?: boolean;
   renderLesson?: (lesson: SectionLesson, index: number) => ReactNode;
   renderQuiz?: (quiz: SectionQuiz, index: number) => ReactNode;
   emptyMessage?: string;
}

const CurriculumSectionList = ({
   sections,
   className,
   includeLessons = true,
   includeQuizzes = true,
   renderLesson,
   renderQuiz,
   emptyMessage = 'There is no section added',
}: CurriculumSectionListProps) => {
   if (sections.length === 0) {
      return <div className="text-muted-foreground p-6 text-center text-sm">{emptyMessage}</div>;
   }

   let itemNumber = 0;

   return (
      <div className={cn('ssu-curriculum-panel', className)}>
         {sections.map((section, sectionIndex) => (
            <div key={section.id}>
               <p className="text-muted-foreground px-4 pt-4 pb-2 text-xs leading-snug">
                  Section {sectionIndex + 1} — {section.title}
               </p>

               {includeLessons &&
                  section.section_lessons?.map((lesson) => {
                     itemNumber += 1;
                     return renderLesson?.(lesson, itemNumber);
                  })}

               {includeQuizzes &&
                  section.section_quizzes?.map((quiz) => {
                     itemNumber += 1;
                     return renderQuiz?.(quiz, itemNumber);
                  })}

               {includeLessons &&
                  includeQuizzes &&
                  (section.section_lessons?.length ?? 0) === 0 &&
                  (section.section_quizzes?.length ?? 0) === 0 && (
                     <p className="text-muted-foreground px-4 pb-3 text-sm">There is no lesson added</p>
                  )}
            </div>
         ))}
      </div>
   );
};

export default CurriculumSectionList;
