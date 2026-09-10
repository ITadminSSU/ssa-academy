import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import QuantityTakeoffBreakdown from '@/components/exam/quantity-takeoff-breakdown';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { CoursePlayerProps } from '@/types/page';
import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, Upload } from 'lucide-react';
import { useState } from 'react';
import LessonControl from './lesson-control';

type UploadedFile = { file_url: string; file_name: string };

const parseJsonValue = (value: any) => {
   if (typeof value === 'string') {
      try {
         return JSON.parse(value);
      } catch {
         return null;
      }
   }
   return value ?? null;
};

const TakeoffQuizViewer = ({ quiz }: { quiz: SectionQuiz }) => {
   const { auth, translate, subscriptionAccess } = usePage<CoursePlayerProps>().props;
   const { frontend } = translate;
   const canMarkProgress = subscriptionAccess?.can_mark_progress ?? true;
   const submissions = quiz.quiz_submissions;
   const question = quiz.quiz_questions.find((item) => item.type === 'quantity_takeoff') ?? quiz.quiz_questions[0];
   const latestAnswer = question?.answers?.[0];
   const answerPayload = parseJsonValue(latestAnswer?.answers);
   const breakdown = answerPayload?.grading_breakdown ?? null;

   const [pdf, setPdf] = useState<UploadedFile | null>(null);
   const [excel, setExcel] = useState<UploadedFile | null>(null);
   const [submitting, setSubmitting] = useState(false);

   const submit = () => {
      if (!pdf?.file_url || !excel?.file_url || !canMarkProgress) return;
      setSubmitting(true);
      router.post(
         route('quiz-submissions.takeoff'),
         {
            section_quiz_id: quiz.id,
            user_id: auth.user.id,
            takeoff_pdf_url: pdf.file_url,
            takeoff_pdf_name: pdf.file_name,
            boq_xlsx_url: excel.file_url,
            boq_xlsx_name: excel.file_name,
         },
         {
            preserveScroll: true,
            onSuccess: () => {
               setPdf(null);
               setExcel(null);
            },
            onFinish: () => setSubmitting(false),
         },
      );
   };

   const attemptsUsed = submissions[0]?.attempts || 0;
   const attemptsLeft = Math.max(0, quiz.retake - attemptsUsed);

   return (
      <Card className="group relative h-full max-h-[80vh] w-full overflow-y-auto rounded-lg">
         <LessonControl className="opacity-0 transition-all duration-300 group-hover:opacity-100" />

         <p className="p-6 text-center text-lg font-bold">{quiz.title}</p>
         <Separator />

         <div className="space-y-6 p-6">
            <div className="grid gap-6 md:grid-cols-2">
               <div className="space-y-2">
                  <p className="font-medium">{frontend.summery}</p>
                  <p className="text-muted-foreground text-sm">Quantity takeoff · {question?.takeoff?.line_count ?? 0} lines</p>
                  <p className="text-sm">
                     {frontend.total_marks}: {quiz.total_mark}
                  </p>
                  <p className="text-sm">
                     {frontend.pass_marks}: {quiz.pass_mark}
                  </p>
                  <p className="text-sm">
                     {frontend.retake}: {quiz.retake}
                  </p>
               </div>
               <div className="space-y-2">
                  <p className="font-medium">{frontend.result}</p>
                  <p className="text-sm">
                     {frontend.retake_attempts}: {attemptsUsed}
                  </p>
                  <p className="text-sm">
                     Lines correct: {submissions[0]?.correct_answers || 0}
                  </p>
                  <p className="text-sm">
                     {frontend.total_marks}: {submissions[0]?.total_marks || 0}
                  </p>
                  <p className="text-sm">Status: {submissions[0]?.is_passed ? frontend.passed : frontend.not_passed}</p>
               </div>
            </div>

            <p className="text-muted-foreground text-sm">
               Download the PDF plans and blank Excel, fill the Quantity Summary, then submit your takeoff PDF and BOQ. Auto-check
               uses ±2% of each line, same as Build Your US Experience.
            </p>

            <div className="flex flex-wrap gap-2">
               <Button variant="outline" asChild>
                  <a href={route('quiz.takeoff.pack', quiz.id)}>
                     <Download className="h-4 w-4" />
                     Download pack
                  </a>
               </Button>
            </div>

            {breakdown && (
               <QuantityTakeoffBreakdown
                  breakdown={breakdown}
                  linesCorrect={answerPayload?.lines_correct}
                  linesTotal={answerPayload?.lines_total}
                  viewer="student"
               />
            )}

            {!canMarkProgress ? (
               <p className="text-muted-foreground text-sm">This quiz is read-only while your subscription is inactive.</p>
            ) : attemptsLeft <= 0 ? (
               <Button type="button" disabled>
                  {frontend.quiz_submitted}
               </Button>
            ) : (
               <div className="space-y-5 rounded-lg border p-4">
                  <p className="flex items-center gap-2 text-sm font-medium">
                     <Upload className="h-4 w-4" />
                     Submit takeoff ({attemptsLeft} attempt{attemptsLeft === 1 ? '' : 's'} left)
                  </p>
                  <div className="space-y-2">
                     <Label>Takeoff PDF</Label>
                     <ChunkedUploaderInput
                        isSubmit={false}
                        filetype="document"
                        delayUpload={false}
                        onFileSelected={() => setPdf(null)}
                        onFileUploaded={(fileData) => {
                           if (!fileData?.file_url) {
                              setPdf(null);
                              return;
                           }
                           setPdf({ file_url: fileData.file_url, file_name: fileData.file_name });
                        }}
                        onError={() => setPdf(null)}
                        onCancelUpload={() => setPdf(null)}
                     />
                     {pdf && (
                        <p className="flex items-center gap-2 text-sm text-green-600">
                           <CheckCircle2 className="h-4 w-4" />
                           {pdf.file_name}
                        </p>
                     )}
                  </div>
                  <div className="space-y-2">
                     <Label>Excel BOQ (.xlsx)</Label>
                     <ChunkedUploaderInput
                        isSubmit={false}
                        filetype="document"
                        delayUpload={false}
                        onFileSelected={() => setExcel(null)}
                        onFileUploaded={(fileData) => {
                           if (!fileData?.file_url) {
                              setExcel(null);
                              return;
                           }
                           setExcel({ file_url: fileData.file_url, file_name: fileData.file_name });
                        }}
                        onError={() => setExcel(null)}
                        onCancelUpload={() => setExcel(null)}
                     />
                     {excel && (
                        <p className="flex items-center gap-2 text-sm text-green-600">
                           <CheckCircle2 className="h-4 w-4" />
                           {excel.file_name}
                        </p>
                     )}
                  </div>
                  <LoadingButton type="button" loading={submitting} disabled={!pdf?.file_url || !excel?.file_url} onClick={submit}>
                     Submit for auto-grade
                  </LoadingButton>
               </div>
            )}
         </div>
      </Card>
   );
};

export default TakeoffQuizViewer;
