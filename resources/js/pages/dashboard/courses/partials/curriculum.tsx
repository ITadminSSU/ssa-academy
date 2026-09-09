import DataSortModal from '@/components/data-sort-modal';
import DeleteByInertia from '@/components/inertia/delete-modal';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { mergeCurriculumItems, toSortableCurriculumItems } from '@/lib/curriculum-items';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, FolderOpen, ListOrdered, Pencil, Trash2 } from 'lucide-react';
import { CourseUpdateProps } from '../update';
import LessonForm from './forms/lesson-form';
import QuestionQuestions from './forms/question-questions';
import QuizForm from './forms/quiz-form';
import SectionForm from './forms/section-form';
import ResourceModal from './resource-modal';

const Curriculum = () => {
   const { props } = usePage<CourseUpdateProps>();
   const { translate } = props;
   const { button, dashboard } = translate;

   return (
      <Card className="p-4 sm:p-6">
         <div className="flex flex-wrap items-center gap-4">
            <SectionForm
               title={button.add_section}
               handler={
                  <Button variant="ghost" className="bg-muted hover:bg-muted-foreground/10">
                     {button.add_section}
                  </Button>
               }
            />

            <DataSortModal
               title={button.sort}
               data={props.course.sections}
               translate={translate}
               handler={
                  <Button variant="ghost" className="bg-muted hover:bg-muted-foreground/10">
                     {button.sort_section}
                  </Button>
               }
               onOrderChange={(newOrder) => {
                  router.post(
                     route('section.sort'),
                     {
                        sortedData: newOrder,
                     },
                     { preserveScroll: true },
                  );
               }}
               renderContent={(item) => (
                  <Card className="w-full px-4 py-3">
                     <p>{item.title}</p>
                  </Card>
               )}
            />
         </div>

         <Separator className="my-6" />

         <Accordion type="single" collapsible className="space-y-4">
            {props.course.sections.map((section, index) => (
               <AccordionItem key={section.id} value={section.id as string} className="w-full overflow-hidden rounded-lg border">
                  <AccordionTrigger className="[&[data-state=open]]:bg-muted px-4 py-3 text-base hover:no-underline">
                     <div className="flex w-full items-center justify-between pr-4">
                        <span>
                           {index + 1}. {section.title}
                        </span>

                        <div onClick={(e) => e.stopPropagation()}>
                           <Popover>
                              <PopoverTrigger>
                                 <Button variant="ghost" className="bg-muted hover:bg-muted-foreground/10 px-2.5">
                                    <span>Section Menu</span>
                                    <ChevronDown className="h-4 w-4" />
                                 </Button>
                              </PopoverTrigger>
                              <PopoverContent align="end" className="flex w-[160px] flex-col space-y-1 p-2">
                                 <LessonForm
                                    title={button.add_lesson}
                                    sectionId={section.id}
                                    handler={
                                       <Button variant="ghost" className="bg-muted hover:bg-muted-foreground/10 h-8 w-full">
                                          <span>{button.add_lesson}</span>
                                       </Button>
                                    }
                                 />

                                 <DataSortModal
                                    title={dashboard.sort_items ?? 'Sort lessons and quizzes'}
                                    data={toSortableCurriculumItems(section)}
                                    translate={translate}
                                    handler={
                                       <Button variant="ghost" className="bg-muted hover:bg-muted-foreground/10 h-8 w-full">
                                          <span>Sort lessons & quizzes</span>
                                       </Button>
                                    }
                                    onOrderChange={(newOrder) => {
                                       router.post(
                                          route('curriculum.sort'),
                                          {
                                             sortedData: newOrder,
                                          },
                                          { preserveScroll: true },
                                       );
                                    }}
                                    renderContent={(item) => (
                                       <Card className="w-full px-4 py-3">
                                          <p>
                                             {item.item_type === 'quiz' ? 'Quiz · ' : ''}
                                             {item.title}
                                          </p>
                                       </Card>
                                    )}
                                 />

                                 <QuizForm
                                    title={button.add_quiz}
                                    sectionId={section.id}
                                    handler={
                                       <Button variant="ghost" className="bg-muted hover:bg-muted-foreground/10 h-8 w-full">
                                          <span>{button.add_quiz}</span>
                                       </Button>
                                    }
                                 />

                                 <SectionForm
                                    title={dashboard.update_section}
                                    section={section}
                                    handler={
                                       <Button variant="secondary" className="h-8 w-full">
                                          <span>{dashboard.update_section}</span>
                                       </Button>
                                    }
                                 />

                                 <DeleteByInertia
                                    routePath={route('section.delete', {
                                       id: section.id,
                                    })}
                                    actionComponent={
                                       <Button
                                          variant="ghost"
                                          className="bg-destructive/8 hover:bg-destructive/6 text-destructive hover:text-destructive h-8 w-full"
                                       >
                                          {button.delete_section}
                                       </Button>
                                    }
                                 />
                              </PopoverContent>
                           </Popover>
                        </div>
                     </div>
                  </AccordionTrigger>

                  <AccordionContent className="space-y-4 p-4">
                     <SectionCurriculumItems section={section} dashboard={dashboard} button={button} />
                  </AccordionContent>
               </AccordionItem>
            ))}
         </Accordion>
      </Card>
   );
};

const SectionCurriculumItems = ({
   section,
   dashboard,
   button,
}: {
   section: CourseSection;
   dashboard: any;
   button: any;
}) => {
   const items = mergeCurriculumItems(section);

   if (items.length === 0) {
      return <div className="text-muted-foreground py-4 text-center text-sm">No lessons or quizzes in this section.</div>;
   }

   return (
      <>
         {items.map((item) =>
            item.kind === 'lesson' ? (
               <div
                  key={`lesson-${item.lesson.id}`}
                  className="group border-border flex w-full items-center justify-between rounded-md border px-4 py-3"
               >
                  <p>{item.lesson.title}</p>

                  <div className="invisible flex items-center gap-2 group-hover:visible">
                     <ResourceModal
                        lesson={item.lesson}
                        title="Lesson Resources"
                        handler={
                           <Button variant="secondary" className="h-7 px-2">
                              <FolderOpen className="h-3 w-3" /> <span>Resource</span>
                           </Button>
                        }
                     />

                     <LessonForm
                        lesson={item.lesson}
                        sectionId={section.id}
                        title={dashboard.update_lesson}
                        handler={
                           <Button size="icon" variant="secondary" className="h-7 w-7">
                              <Pencil className="h-3 w-3" />
                           </Button>
                        }
                     />

                     <DeleteByInertia
                        routePath={route('lesson.delete', {
                           id: item.lesson.id,
                        })}
                        actionComponent={
                           <Button size="icon" variant="secondary" className="text-destructive h-7 w-7">
                              <Trash2 className="h-3 w-3" />
                           </Button>
                        }
                     />
                  </div>
               </div>
            ) : (
               <div
                  key={`quiz-${item.quiz.id}`}
                  className="group border-border flex w-full items-center justify-between rounded-md border px-4 py-3"
               >
                  <p>
                     <span className="text-muted-foreground mr-2 text-xs font-semibold uppercase">Quiz</span>
                     {item.quiz.title}
                  </p>

                  <div className="invisible flex items-center gap-2 group-hover:visible">
                     <QuestionQuestions
                        quiz={item.quiz}
                        title={button.quiz_questions}
                        handler={
                           <Button size="icon" variant="secondary" className="h-7 w-7">
                              <ListOrdered className="h-3 w-3" />
                           </Button>
                        }
                     />

                     <DeleteByInertia
                        routePath={route('quiz.delete', {
                           id: item.quiz.id,
                        })}
                        actionComponent={
                           <Button size="icon" variant="secondary" className="text-destructive h-7 w-7">
                              <Trash2 className="h-3 w-3" />
                           </Button>
                        }
                     />

                     <QuizForm
                        quiz={item.quiz}
                        title={dashboard.update_quiz}
                        sectionId={section.id}
                        handler={
                           <Button size="icon" variant="secondary" className="h-7 w-7">
                              <Pencil className="h-3 w-3" />
                           </Button>
                        }
                     />
                  </div>
               </div>
            ),
         )}
      </>
   );
};

export default Curriculum;
