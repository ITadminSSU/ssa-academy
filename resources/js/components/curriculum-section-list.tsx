import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { mergeCurriculumItems } from '@/lib/curriculum-items';
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
   defaultOpenSectionIds?: Array<string | number>;
   renderLesson?: (lesson: SectionLesson, index: number) => ReactNode;
   renderQuiz?: (quiz: SectionQuiz, index: number) => ReactNode;
   emptyMessage?: string;
}

const CurriculumSectionList = ({
   sections,
   className,
   includeLessons = true,
   includeQuizzes = true,
   defaultOpenSectionIds,
   renderLesson,
   renderQuiz,
   emptyMessage = 'There is no section added',
}: CurriculumSectionListProps) => {
   if (sections.length === 0) {
      return <div className="text-muted-foreground p-6 text-center text-sm">{emptyMessage}</div>;
   }

   let itemNumber = 0;
   const defaultOpen = (defaultOpenSectionIds?.length ? defaultOpenSectionIds : [sections[0].id]).map(String);

   return (
      <Accordion type="multiple" defaultValue={defaultOpen} className={cn('ssu-curriculum-panel', className)}>
         {sections.map((section, sectionIndex) => (
            <AccordionItem key={section.id} value={String(section.id)} className="border-border/60">
               <AccordionTrigger className="text-muted-foreground hover:no-underline px-4 py-3 text-xs font-normal leading-snug">
                  Section {sectionIndex + 1} — {section.title}
               </AccordionTrigger>
               <AccordionContent className="pb-2 pt-0">
                  {mergeCurriculumItems(section).map((item) => {
                     if (item.kind === 'lesson') {
                        if (!includeLessons) {
                           return null;
                        }

                        itemNumber += 1;
                        return renderLesson?.(item.lesson, itemNumber);
                     }

                     if (!includeQuizzes) {
                        return null;
                     }

                     itemNumber += 1;
                     return renderQuiz?.(item.quiz, itemNumber);
                  })}

                  {includeLessons &&
                     includeQuizzes &&
                     (section.section_lessons?.length ?? 0) === 0 &&
                     (section.section_quizzes?.length ?? 0) === 0 && (
                        <p className="text-muted-foreground px-4 pb-3 text-sm">There is no lesson added</p>
                     )}
               </AccordionContent>
            </AccordionItem>
         ))}
      </Accordion>
   );
};

export default CurriculumSectionList;
