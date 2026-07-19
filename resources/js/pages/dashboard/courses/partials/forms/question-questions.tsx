import DataSortModal from '@/components/data-sort-modal';
import DeleteByInertia from '@/components/inertia/delete-modal';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Renderer } from 'richtor';
import 'richtor/styles';
import QuestionBuilder from './question-builder';
import QuestionForm from './question-form';

interface Props {
   title: string;
   handler: React.ReactNode;
   quiz: SectionQuiz;
}

const QuestionQuestions = ({ title, handler, quiz }: Props) => {
   const [open, setOpen] = useState(false);
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { button, dashboard, frontend } = translate;

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger>{handler}</DialogTrigger>

         <DialogContent className="p-0">
            <ScrollArea className="max-h-[90vh] p-6">
               <DialogHeader className="mb-6">
                  <DialogTitle>{title}</DialogTitle>
               </DialogHeader>

               <div className="space-y-7">
                  <div className="flex items-center gap-4">
                     <QuestionBuilder
                        quiz={quiz}
                        handler={
                           <Button className="h-8 text-xs">
                              <Plus className="mr-1 h-3.5 w-3.5" />
                              {button.add_question}
                           </Button>
                        }
                     />

                     <DataSortModal
                        title={button.sort}
                        data={quiz.quiz_questions}
                        handler={
                           <Button variant="secondary" className="h-8 text-xs" disabled={quiz.quiz_questions.length < 2}>
                              {button.sort}
                           </Button>
                        }
                        onOrderChange={(newOrder) => {
                           router.post(
                              route('quiz.question.sort'),
                              {
                                 sortedData: newOrder,
                              },
                              { preserveScroll: true },
                           );
                        }}
                        renderContent={(item) => (
                           <Card className="w-full px-4 py-3">
                              <div
                                 dangerouslySetInnerHTML={{
                                    __html: item.title,
                                 }}
                              />
                           </Card>
                        )}
                     />
                  </div>

                  <div className="space-y-2">
                     {quiz.quiz_questions.length > 0 ? (
                        quiz.quiz_questions.map((question: QuizQuestion) => (
                           <div
                              key={question.id}
                              className="group border-border flex w-full items-center justify-between rounded-md border px-4 py-3"
                           >
                              <Renderer value={question.title} />

                              <div className="invisible flex items-center gap-2 group-hover:visible">
                                 <DeleteByInertia
                                    routePath={route('quiz.question.delete', {
                                       id: question.id,
                                    })}
                                    actionComponent={
                                       <Button size="icon" variant="secondary" className="text-destructive h-7 w-7">
                                          <Trash2 className="h-3 w-3" />
                                       </Button>
                                    }
                                 />

                                 <QuestionForm
                                    quiz={quiz}
                                    title={dashboard.edit_question}
                                    question={question}
                                    handler={
                                       <Button size="icon" variant="secondary" className="h-7 w-7">
                                          <Pencil className="h-3 w-3" />
                                       </Button>
                                    }
                                 />
                              </div>
                           </div>
                        ))
                     ) : (
                        <div className="flex items-center justify-center">
                           <p className="text-muted-foreground text-sm">{frontend.no_results}</p>
                        </div>
                     )}
                  </div>
               </div>

               <DialogFooter className="w-full justify-between space-x-2 pt-8">
                  <DialogClose asChild>
                     <Button type="button" variant="outline">
                        {button.close}
                     </Button>
                  </DialogClose>
               </DialogFooter>
            </ScrollArea>
         </DialogContent>
      </Dialog>
   );
};

export default QuestionQuestions;
