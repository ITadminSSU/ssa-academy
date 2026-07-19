import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import TagInput from '@/components/tag-input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useState } from 'react';
import { Editor } from 'richtor';
import 'richtor/styles';

type QuestionType = 'single' | 'multiple' | 'boolean';

interface DraftQuestion {
   key: string;
   title: string;
   type: QuestionType;
   options: string[];
   answer: string[];
}

interface Props {
   quiz: SectionQuiz;
   handler: React.ReactNode;
}

const getQuestionTypes = (translate: any) => [
   { value: 'single', label: translate.dashboard.single_choice },
   { value: 'multiple', label: translate.dashboard.multiple_choice },
   { value: 'boolean', label: translate.dashboard.true_false },
];

const createDraftQuestion = (type: QuestionType = 'single'): DraftQuestion => ({
   key: nanoid(),
   title: '',
   type,
   options: [],
   answer: type === 'boolean' ? ['True'] : [],
});

const QuestionBuilder = ({ quiz, handler }: Props) => {
   const [open, setOpen] = useState(false);
   const [submitting, setSubmitting] = useState(false);
   const { props } = usePage<SharedData>();
   const { translate, errors } = props as SharedData & { errors?: Record<string, string> };
   const { dashboard, input, frontend, button } = translate;

   const questionTypes = getQuestionTypes(translate);

   const [questions, setQuestions] = useState<DraftQuestion[]>([createDraftQuestion()]);

   const savedCount = quiz.quiz_questions?.length ?? 0;

   const updateQuestion = (key: string, patch: Partial<DraftQuestion>) => {
      setQuestions((prev) => prev.map((q) => (q.key === key ? { ...q, ...patch } : q)));
   };

   const addQuestion = () => {
      setQuestions((prev) => [...prev, createDraftQuestion()]);
   };

   const removeQuestion = (key: string) => {
      setQuestions((prev) => (prev.length === 1 ? prev : prev.filter((q) => q.key !== key)));
   };

   const moveQuestion = (index: number, direction: -1 | 1) => {
      setQuestions((prev) => {
         const nextIndex = index + direction;
         if (nextIndex < 0 || nextIndex >= prev.length) {
            return prev;
         }
         const next = [...prev];
         const temp = next[index];
         next[index] = next[nextIndex];
         next[nextIndex] = temp;
         return next;
      });
   };

   const handleTypeChange = (key: string, type: QuestionType) => {
      updateQuestion(key, {
         type,
         answer: type === 'boolean' ? ['True'] : [],
         options: type === 'boolean' ? [] : [],
      });
   };

   const errorFor = (index: number, field: string) => {
      if (!errors) return undefined;
      return errors[`questions.${index}.${field}`] ?? errors[`questions.${index}`];
   };

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      const payload = {
         section_quiz_id: quiz.id,
         questions: questions.map(({ title, type, options, answer }) => ({ title, type, options, answer })),
      };

      setSubmitting(true);

      router.post(route('quiz.questions.bulk.store'), payload, {
         preserveScroll: true,
         onSuccess: () => {
            setSubmitting(false);
            setOpen(false);
            setQuestions([createDraftQuestion()]);
         },
         onError: () => {
            setSubmitting(false);
         },
      });
   };

   return (
      <Dialog
         open={open}
         onOpenChange={(next) => {
            setOpen(next);
            if (!next) {
               setQuestions([createDraftQuestion()]);
            }
         }}
      >
         <DialogTrigger asChild>{handler}</DialogTrigger>

         <DialogContent className="p-0">
            <ScrollArea className="max-h-[90vh] p-6">
               <DialogHeader className="mb-4">
                  <DialogTitle>Add Questions — {quiz.title}</DialogTitle>
                  <p className="text-muted-foreground text-sm">
                     Compose all questions in one window, then save once. {savedCount > 0 ? `${savedCount} already saved.` : ''}
                  </p>
               </DialogHeader>

               <form onSubmit={handleSubmit} className="space-y-4">
                  {questions.map((question, index) => (
                     <div key={question.key} className="space-y-4 rounded-lg border border-border bg-card p-4">
                        <div className="flex items-center justify-between gap-3">
                           <span className="text-muted-foreground text-xs font-semibold uppercase tracking-wide">
                              Question {index + 1}
                           </span>
                           <div className="flex items-center gap-1">
                              <Button
                                 type="button"
                                 size="icon"
                                 variant="ghost"
                                 className="h-7 w-7"
                                 disabled={index === 0}
                                 onClick={() => moveQuestion(index, -1)}
                                 title="Move up"
                              >
                                 <ArrowUp className="h-3.5 w-3.5" />
                              </Button>
                              <Button
                                 type="button"
                                 size="icon"
                                 variant="ghost"
                                 className="h-7 w-7"
                                 disabled={index === questions.length - 1}
                                 onClick={() => moveQuestion(index, 1)}
                                 title="Move down"
                              >
                                 <ArrowDown className="h-3.5 w-3.5" />
                              </Button>
                              <Button
                                 type="button"
                                 size="icon"
                                 variant="ghost"
                                 className="text-destructive hover:bg-destructive/10 h-7 w-7"
                                 disabled={questions.length === 1}
                                 onClick={() => removeQuestion(question.key)}
                                 title="Remove"
                              >
                                 <Trash2 className="h-3.5 w-3.5" />
                              </Button>
                           </div>
                        </div>

                        <div>
                           <Label>{dashboard.question_type}</Label>
                           <Select value={question.type} onValueChange={(value) => handleTypeChange(question.key, value as QuestionType)}>
                              <SelectTrigger>
                                 <SelectValue placeholder={input.question_type} />
                              </SelectTrigger>
                              <SelectContent>
                                 {questionTypes.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                       {type.label}
                                    </SelectItem>
                                 ))}
                              </SelectContent>
                           </Select>
                        </div>

                        <div>
                           <Label>{dashboard.question_title}</Label>
                           <Editor
                              ssr={true}
                              output="html"
                              placeholder={{
                                 paragraph: 'Type your question here...',
                                 imageCaption: 'Type caption for image (optional)',
                              }}
                              contentMinHeight={120}
                              contentMaxHeight={320}
                              initialContent={question.title}
                              onContentChange={(value) => updateQuestion(question.key, { title: value as string })}
                           />
                           <InputError message={errorFor(index, 'title')} />
                        </div>

                        {question.type !== 'boolean' && (
                           <>
                              <div>
                                 <Label>{input.options}</Label>
                                 <TagInput
                                    defaultTags={question.options}
                                    placeholder={input.question_options_placeholder}
                                    onChange={(values: any) => updateQuestion(question.key, { options: values })}
                                 />
                                 <InputError message={errorFor(index, 'options')} />
                              </div>

                              {question.type === 'multiple' ? (
                                 <div>
                                    <Label>{input.answer}</Label>
                                    <TagInput
                                       defaultTags={question.answer}
                                       whitelist={question.options}
                                       enforceWhitelist
                                       placeholder={input.answer_options_placeholder}
                                       onChange={(values) => updateQuestion(question.key, { answer: values })}
                                    />
                                    <InputError message={errorFor(index, 'answer')} />
                                 </div>
                              ) : (
                                 <div>
                                    <Label>{input.answer}</Label>
                                    <Input
                                       type="text"
                                       value={question.answer[0] ?? ''}
                                       placeholder={input.answer_placeholder}
                                       onChange={(e) => updateQuestion(question.key, { answer: [e.target.value] })}
                                    />
                                    <InputError message={errorFor(index, 'answer')} />
                                 </div>
                              )}
                           </>
                        )}

                        {question.type === 'boolean' && (
                           <div>
                              <Label>{input.answer}</Label>
                              <Tabs
                                 value={question.answer[0] ?? 'True'}
                                 onValueChange={(value) => updateQuestion(question.key, { answer: [value] })}
                              >
                                 <TabsList className="w-full">
                                 <TabsTrigger value="True" className="w-full">
                                    {frontend.true}
                                 </TabsTrigger>
                                 <TabsTrigger value="False" className="w-full">
                                    {frontend.false}
                                 </TabsTrigger>
                                 </TabsList>
                              </Tabs>
                              <InputError message={errorFor(index, 'answer')} />
                           </div>
                        )}
                     </div>
                  ))}

                  <Button type="button" variant="outline" className="w-full" onClick={addQuestion}>
                     <Plus className="mr-2 h-4 w-4" />
                     Add Another Question
                  </Button>

                  <DialogFooter className="gap-2 pt-2">
                     <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={submitting}>
                        {button.close}
                     </Button>
                     <LoadingButton type="submit" loading={submitting} disabled={submitting}>
                        Save Questions ({questions.length})
                     </LoadingButton>
                  </DialogFooter>
               </form>
            </ScrollArea>
         </DialogContent>
      </Dialog>
   );
};

export default QuestionBuilder;
