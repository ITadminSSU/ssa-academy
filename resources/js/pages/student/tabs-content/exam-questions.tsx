import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { ssuBadgeTone } from '@/lib/ssu-theme';
import { StudentExamProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { FileQuestion, CheckCircle2, XCircle } from 'lucide-react';

const ExamQuestions = () => {
   const { props } = usePage<StudentExamProps>();
   const { questions } = props;

   const typeToneIndex: Record<string, number> = {
      multiple_choice: 0,
      multiple_select: 1,
      matching: 2,
      fill_blank: 0,
      ordering: 1,
      short_answer: 2,
      listening: 0,
   };

   const getQuestionTypeBadge = (type: string) => {
      return (
         <Badge variant="outline" className={ssuBadgeTone(typeToneIndex[type] ?? 0)}>
            {type.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
         </Badge>
      );
   };

   return (
      <div className="space-y-4">
         {questions && questions.length > 0 ? (
            questions.map((question, index) => (
               <Card key={question.id} className="p-4">
                  <div className="flex items-start justify-between gap-4">
                     <div className="flex-1">
                        <div className="mb-2 flex items-center gap-2">
                           <FileQuestion className="h-4 w-4 text-muted-foreground" />
                           <span className="text-sm font-semibold">Question {index + 1}</span>
                           {getQuestionTypeBadge(question.question_type)}
                           <Badge variant="outline">{question.marks} marks</Badge>
                        </div>
                        <p className="text-base font-medium">{question.title}</p>
                        {question.description && (
                           <p className="text-muted-foreground mt-2 text-sm">{question.description}</p>
                        )}
                        {question.question_options && question.question_options.length > 0 && (
                           <div className="mt-3 space-y-1">
                              <p className="text-muted-foreground text-xs font-medium">Options:</p>
                              <ul className="text-muted-foreground ml-4 list-disc space-y-1 text-sm">
                                 {question.question_options.map((option: any, optIndex: number) => (
                                    <li key={option.id || optIndex}>
                                       {option.option_text || option.text || 'Option'}
                                       {option.is_correct && (
                                          <CheckCircle2 className="text-green-600 ml-1 inline h-3 w-3" />
                                       )}
                                    </li>
                                 ))}
                              </ul>
                           </div>
                        )}
                     </div>
                  </div>
               </Card>
            ))
         ) : (
            <Card className="p-6 text-center">
               <p className="text-muted-foreground">No questions available for this exam yet.</p>
            </Card>
         )}
      </div>
   );
};

export default ExamQuestions;

