import QuestionAnswerResult from '@/components/exam/question-answer-result';
import QuantityTakeoffTutorial from '@/components/exam/quantity-takeoff-tutorial';
import QuestionStatusBadge from '@/components/exam/question-status-badge';
import QuestionStatusIcon from '@/components/exam/question-status-icon';
import QuestionTypeBadge from '@/components/exam/question-type-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { safeQuestion } from '@/lib/exam';
import { StudentExamProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Clock, TrendingUp, X } from 'lucide-react';
import { useMemo } from 'react';
import { Renderer } from 'richtor';
import 'richtor/styles';

const ExamResult = () => {
   const { attempt, tutorialVideo } = usePage<StudentExamProps>().props;
   const answers = attempt?.attempt_answers ?? [];
   const isQuantityTakeoff = attempt?.exam?.exam_mode === 'quantity_takeoff';
   const takeoffAnswer = answers.find((answer) => safeQuestion(answer).question_type === 'quantity_takeoff');

   const answersByType = useMemo(() => {
      return answers.reduce<Record<ExamQuestionType, QuestionTypeStats>>(
         (acc, answer) => {
            const question = safeQuestion(answer);
            const type = question.question_type as ExamQuestionType;
            if (!type) return acc;

            if (!acc[type]) {
               acc[type] = { correct: 0, total: 0, marks: 0, totalMarks: 0 };
            }
            acc[type].total += 1;
            acc[type].totalMarks += Number(question.marks ?? 0);
            acc[type].marks += Number(answer.marks_obtained ?? 0);
            if (answer.is_correct) acc[type].correct += 1;
            return acc;
         },
         {} as Record<ExamQuestionType, QuestionTypeStats>,
      );
   }, [answers]);

   if (!attempt) {
      return (
         <div className="flex h-full items-center justify-center p-10">
            <div className="text-center">
               <h1 className="text-2xl font-semibold text-foreground">Attempt data unavailable</h1>
               <p className="mt-2 text-sm text-muted-foreground">Please return to the exam list and try again.</p>
               <div className="mt-4">
                  <Link href={route('student.index', 'exams')}>
                     <Button variant="outline">Back to My Exams</Button>
                  </Link>
               </div>
            </div>
         </div>
      );
   }

   const isPassed = attempt.is_passed;
   const percentage = attempt.percentage || 0;
   const correctAnswers = isQuantityTakeoff
      ? Number(takeoffAnswer?.answer_data?.lines_correct ?? 0)
      : answers.filter((a) => a.is_correct).length;
   const incorrectAnswers = isQuantityTakeoff
      ? Number(takeoffAnswer?.answer_data?.lines_total ?? 0) - correctAnswers
      : answers.filter((a) => a.is_correct === false).length;
   const pendingAnswers = isQuantityTakeoff ? 0 : answers.filter((a) => a.is_correct === null).length;

   type QuestionTypeStats = { correct: number; total: number; marks: number; totalMarks: number };

   return (
      <div className="space-y-6">
         {/* Header */}
         <div className="flex items-center justify-between">
            <h6 className="text-xl font-semibold">Attempt {attempt.attempt_number} Result</h6>

            <Button asChild variant="outline">
               <Link
                  href={route('student.exam.show', {
                     id: attempt.exam_id,
                     tab: 'attempts',
                  })}
               >
                  <ArrowLeft className="h-4 w-4" />
                  Back Attempts
               </Link>
            </Button>
         </div>

         {/* Score Card */}
         <Card className={`border-2 ${isPassed ? 'border-green-500/60 bg-green-500/10' : 'border-red-500/60 bg-red-500/10'}`}>
            <CardContent className="p-6">
               <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                  <div className="text-center">
                     <h6 className={`mb-2 text-4xl font-bold ${isPassed ? 'text-green-600' : 'text-red-600'}`}>{percentage.toFixed(1)}%</h6>
                     <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">Score</p>
                  </div>
                  <div className="text-center">
                     <h6 className="mb-2 text-4xl font-bold">
                        {attempt.obtained_marks}/{attempt.total_marks}
                     </h6>
                     <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">Marks</p>
                  </div>
                  <div className="text-center">
                     <Badge variant={isPassed ? 'default' : 'destructive'} className="mb-2 px-6 py-1 text-lg">
                        {isPassed ? 'PASSED' : 'FAILED'}
                     </Badge>
                     <p className="text-sm text-muted-foreground">
                        Pass mark: <span className="font-semibold">{attempt.exam.pass_mark}</span>
                     </p>
                  </div>
                  <div className="text-center">
                     <div className="mb-2 text-3xl font-bold">
                        {Math.floor((new Date(attempt.end_time).getTime() - new Date(attempt.start_time).getTime()) / 60000)} min
                     </div>
                     <p className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">Time Taken</p>
                  </div>
               </div>
            </CardContent>
         </Card>

         {/* Statistics */}
         <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <Card>
               <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">{isQuantityTakeoff ? 'Correct Lines' : 'Correct Answers'}</CardTitle>
                  <Check className="h-4 w-4 text-green-600" />
               </CardHeader>
               <CardContent>
                  <div className="text-2xl font-bold text-green-600">{correctAnswers}</div>
                  <Progress
                     value={
                        isQuantityTakeoff
                           ? Number(takeoffAnswer?.answer_data?.lines_total ?? 0) > 0
                              ? (correctAnswers / Number(takeoffAnswer?.answer_data?.lines_total ?? 1)) * 100
                              : 0
                           : answers.length
                             ? (correctAnswers / answers.length) * 100
                             : 0
                     }
                     className="mt-2"
                  />
               </CardContent>
            </Card>

            <Card>
               <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">{isQuantityTakeoff ? 'Incorrect Lines' : 'Incorrect Answers'}</CardTitle>
                  <X className="h-4 w-4 text-red-600" />
               </CardHeader>
               <CardContent>
                  <div className="text-2xl font-bold text-red-600">{incorrectAnswers}</div>
                  <Progress
                     value={
                        isQuantityTakeoff
                           ? Number(takeoffAnswer?.answer_data?.lines_total ?? 0) > 0
                              ? (incorrectAnswers / Number(takeoffAnswer?.answer_data?.lines_total ?? 1)) * 100
                              : 0
                           : answers.length
                             ? (incorrectAnswers / answers.length) * 100
                             : 0
                     }
                     className="mt-2"
                  />
               </CardContent>
            </Card>

            {!isQuantityTakeoff && (
            <Card>
               <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">Pending Review</CardTitle>
                  <Clock className="h-4 w-4 text-yellow-600" />
               </CardHeader>
               <CardContent>
                  <div className="text-2xl font-bold text-yellow-600">{pendingAnswers}</div>
                  {pendingAnswers > 0 && <p className="mt-1 text-xs text-muted-foreground">Manual grading in progress</p>}
               </CardContent>
            </Card>
            )}
         </div>

         {tutorialVideo && attempt.status === 'completed' && <QuantityTakeoffTutorial video={tutorialVideo} />}

         {/* Performance by Question Type */}
         {!isQuantityTakeoff && Object.keys(answersByType).length > 0 && (
            <Card>
               <CardHeader className="p-5 sm:p-6">
                  <CardTitle className="flex items-center gap-2">
                     <TrendingUp className="h-5 w-5" />
                     Performance by Question Type
                  </CardTitle>
               </CardHeader>
               <CardContent className="p-4 pt-0 sm:p-6 md:pt-0">
                  <div className="space-y-4">
                     {(Object.entries(answersByType) as [ExamQuestionType, QuestionTypeStats][]).map(([type, stats]) => (
                        <div key={type}>
                           <div className="mb-2 flex flex-col justify-between sm:flex-row sm:items-center">
                              <div className="flex items-center gap-2">
                                 <QuestionTypeBadge type={type} />
                                 <span className="text-sm text-muted-foreground">
                                    {stats.correct}/{stats.total} correct
                                 </span>
                              </div>
                              <span className="text-sm font-semibold">
                                 {Number(stats.marks).toFixed(2)}/{Number(stats.totalMarks).toFixed(2)} marks
                              </span>
                           </div>
                           <Progress value={(stats.correct / stats.total) * 100} />
                        </div>
                     ))}
                  </div>
               </CardContent>
            </Card>
         )}

         {/* Question-wise Analysis */}
         <Card>
            <CardHeader className="p-5 sm:p-6">
               <CardTitle>{isQuantityTakeoff ? 'Quantity line breakdown' : 'Detailed Analysis'}</CardTitle>
            </CardHeader>
            <CardContent className="p-4 pt-0 sm:p-6 md:pt-0">
               <div className="space-y-6">
                  {answers.map((answer, index) => {
                     const question = safeQuestion(answer);

                     return (
                        <div key={answer.id ?? index} className="overflow-hidden rounded-lg border-2 border-border bg-card">
                           {/* Question Header */}
                           <div className="border-b border-border bg-muted p-4">
                              <div className="flex items-start justify-between gap-4">
                                 <div className="flex flex-1 items-start gap-3">
                                    <div className="mt-1 flex-shrink-0">
                                       <QuestionStatusIcon answer={answer} />
                                    </div>
                                    <div className="flex-1">
                                       <div className="mb-2 flex flex-wrap items-center gap-2">
                                          <span className="text-lg font-semibold">Question {index + 1}</span>
                                          {question.question_type && <QuestionTypeBadge type={question.question_type as ExamQuestionType} />}
                                          <QuestionStatusBadge answer={answer} />
                                       </div>
                                    </div>
                                 </div>
                                 <div className="flex-shrink-0 text-right">
                                    <p className="flex flex-col items-center text-lg font-bold sm:flex-row">
                                       <span className="border-primary border-b sm:border-none">{answer.marks_obtained || 0}</span>
                                       <span className="hidden sm:block">/</span>
                                       <span>{question.marks || 0}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">marks</p>
                                 </div>
                              </div>
                              <p className="text-sm font-medium text-muted-foreground">{question.title}</p>
                           </div>

                           {/* Question Content */}
                           <div className="p-4">
                              {question.description && <Renderer value={question.description} />}

                              {/* Render Question with Answer */}
                              <div className="mb-4">
                                 <QuestionAnswerResult answer={answer} question={question} />
                              </div>
                           </div>
                        </div>
                     );
                  })}
               </div>
            </CardContent>
         </Card>
      </div>
   );
};

export default ExamResult;
