import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, Trash2, GripVertical } from 'lucide-react';
import { useState } from 'react';
import QuestionDialog, { QuestionFormData, questionTypes } from './question-dialog';

interface Props {
   questions: QuestionFormData[];
   setQuestions: React.Dispatch<React.SetStateAction<QuestionFormData[]>>;
}

const typeLabel = (value: string) => questionTypes.find((t) => t.value === value)?.label ?? value;

const ExamQuestionComposer = ({ questions, setQuestions }: Props) => {
   const [addedKey, setAddedKey] = useState(0);

   const addDraft = (draft: QuestionFormData) => {
      setQuestions((prev) => [...prev, draft]);
      setAddedKey((k) => k + 1);
   };

   const removeDraft = (index: number) => {
      setQuestions((prev) => prev.filter((_, i) => i !== index));
   };

   return (
      <Card>
         <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <div>
               <CardTitle>Questions</CardTitle>
               <p className="text-muted-foreground mt-1 text-sm">
                  Add as many questions as you like before creating the exam. You can finish or edit them on the Questions tab after saving.
               </p>
            </div>
            <QuestionDialog
               key={addedKey}
               onAddDraft={addDraft}
               dialogTitle="Add Question"
               submitLabel="Add to Exam"
               handler={
                  <Button type="button" className="h-9">
                     <Plus className="mr-1 h-4 w-4" />
                     Add Question
                  </Button>
               }
            />
         </CardHeader>

         <CardContent className="space-y-3">
            {questions.length === 0 ? (
               <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                  No questions added yet. Click <span className="font-medium">Add Question</span> to start building your exam.
               </div>
            ) : (
               questions.map((q, index) => (
                  <div key={index} className="flex items-center gap-3 rounded-md border bg-muted/30 p-3">
                     <GripVertical className="text-muted-foreground h-4 w-4 shrink-0" />
                     <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                           <span className="bg-primary/10 text-primary rounded px-2 py-0.5 text-xs font-medium">
                              {typeLabel(q.question_type)}
                           </span>
                           <span className="text-muted-foreground text-xs">{q.marks} marks</span>
                           {q.question_options?.length > 0 && (
                              <span className="text-muted-foreground text-xs">{q.question_options.length} options</span>
                           )}
                        </div>
                        <p className="mt-1 truncate text-sm font-medium">
                           {q.title || <span className="text-muted-foreground italic">Untitled question</span>}
                        </p>
                     </div>
                     <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-destructive hover:text-destructive"
                        onClick={() => removeDraft(index)}
                     >
                        <Trash2 className="h-4 w-4" />
                     </Button>
                  </div>
               ))
            )}

            {questions.length > 0 && (
               <p className="text-muted-foreground pt-1 text-xs">
                  {questions.length} question{questions.length === 1 ? '' : 's'} ready to save with this exam.
               </p>
            )}
         </CardContent>
      </Card>
   );
};

export default ExamQuestionComposer;
