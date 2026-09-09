import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Separator } from '@/components/ui/separator';
import { mergeCurriculumItems } from '@/lib/curriculum-items';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { File, FileQuestion, FileText, Image, Video } from 'lucide-react';

const Curriculum = ({ course }: { course: Course }) => {
   const { canViewCurriculum, translate } = usePage<SharedData & { canViewCurriculum?: boolean }>().props;
   const { frontend, button } = translate;
   const videoTypes = ['video', 'video_url'];
   const sections = course.sections ?? [];

   return (
      <>
         <h6 className="mb-4 text-xl font-semibold">{button.curriculum ?? 'Curriculum'}</h6>

         <Separator className="my-6" />

         {!canViewCurriculum ? (
            <p className="text-muted-foreground">
               {frontend.curriculum_enrolled_only ?? 'Course content is available exclusively to enrolled students.'}
            </p>
         ) : (
            <Accordion
               type="single"
               collapsible
               className="space-y-4"
               defaultValue={sections.length > 0 ? (sections[0].id as string) : ''}
            >
               {sections.map((section, index) => {
                  const items = mergeCurriculumItems(section);

                  return (
                  <AccordionItem key={section.id} value={section.id as string} className="overflow-hidden rounded-lg border">
                     <AccordionTrigger className="[&[data-state=open]]:!bg-muted px-4 py-3 text-base hover:no-underline">
                        {index + 1}. {section.title}
                     </AccordionTrigger>
                     <AccordionContent className="space-y-1 p-4">
                        {items.length > 0 ? (
                           items.map((item) =>
                              item.kind === 'lesson' ? (
                                 <div key={`lesson-${item.lesson.id}`} className="flex items-center justify-between gap-3 py-2">
                                    <div className="flex items-center gap-2">
                                       <div className="bg-secondary flex h-6 w-6 items-center justify-center rounded-full">
                                          {videoTypes.includes(item.lesson.lesson_type) && <Video className="h-4 w-4" />}

                                          {['document', 'iframe'].includes(item.lesson.lesson_type) && <File className="h-4 w-4" />}

                                          {item.lesson.lesson_type === 'text' && <FileText className="h-4 w-4" />}

                                          {item.lesson.lesson_type === 'image' && <Image className="h-4 w-4" />}
                                       </div>

                                       <p>{item.lesson.title}</p>
                                    </div>

                                    {videoTypes.includes(item.lesson.lesson_type) && <span>{item.lesson.duration}</span>}
                                 </div>
                              ) : (
                                 <div key={`quiz-${item.quiz.id}`} className="flex items-center justify-between gap-3 py-2">
                                    <div className="flex items-center gap-2">
                                       <div className="bg-secondary flex h-6 w-6 items-center justify-center rounded-full">
                                          <FileQuestion className="h-4 w-4" />
                                       </div>

                                       <p>{item.quiz.title}</p>
                                    </div>

                                    <span>{item.quiz.duration}</span>
                                 </div>
                              ),
                           )
                        ) : (
                           <div className="px-4 py-3 text-center">
                              <p>{frontend.there_is_no_lesson_added}</p>
                           </div>
                        )}
                     </AccordionContent>
                  </AccordionItem>
                  );
               })}
            </Accordion>
         )}
      </>
   );
};

export default Curriculum;
