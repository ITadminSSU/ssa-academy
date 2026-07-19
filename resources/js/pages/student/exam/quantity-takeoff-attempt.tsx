import {
   AlertDialog,
   AlertDialogAction,
   AlertDialogCancel,
   AlertDialogContent,
   AlertDialogDescription,
   AlertDialogFooter,
   AlertDialogHeader,
   AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import { CheckCircle2 } from 'lucide-react';
import Footer from '@/layouts/footer';
import Main from '@/layouts/main';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Download, FileText } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import AttemptNavbar from './partials/attempt-navbar';
import TimerComponent from './partials/timer-component';

interface TakeoffLineItem {
   key: string;
   item: string;
   unit: string;
}

interface GradingRules {
   tolerance_percent: number;
   unit_floors: Record<string, number>;
   pass_mark: number;
}

interface Props {
   attempt: ExamAttempt;
   lineItems: TakeoffLineItem[];
   gradingRules: GradingRules;
   templateDownloadUrl?: string | null;
   allowSupportingUpload?: boolean;
}

type SupportingFile = {
   file_url: string;
   file_name: string;
};

const QuantityTakeoffAttempt = ({ attempt, lineItems, gradingRules, templateDownloadUrl, allowSupportingUpload = true }: Props) => {
   const [quantities, setQuantities] = useState<Record<string, string>>({});
   const [supportingFile, setSupportingFile] = useState<SupportingFile | null>(null);
   const [showSubmitDialog, setShowSubmitDialog] = useState(false);
   const [isSubmitting, setIsSubmitting] = useState(false);

   const question = attempt.exam.questions?.[0];
   const resources = attempt.exam.resources || [];

   const durationSeconds = ((attempt.exam.duration_hours || 0) * 60 + (attempt.exam.duration_minutes || 0)) * 60;
   const attemptStart = attempt.start_time ? new Date(attempt.start_time).getTime() : Date.now();
   const effectiveDuration = durationSeconds > 0 ? durationSeconds : 60 * 60;
   const computedDeadline = attempt.end_time ? attempt.end_time : new Date(attemptStart + effectiveDuration * 1000).toISOString();

   useEffect(() => {
      const saved = localStorage.getItem(`exam-attempt-${attempt.id}`);
      if (!saved) {
         return;
      }

      try {
         const parsed = JSON.parse(saved);
         setQuantities(parsed.quantities || {});
         setSupportingFile(parsed.supportingFile || null);
      } catch {
         // ignore invalid cache
      }
   }, [attempt.id]);

   useEffect(() => {
      const interval = setInterval(() => {
         localStorage.setItem(
            `exam-attempt-${attempt.id}`,
            JSON.stringify({
               quantities,
               supportingFile,
               lastSaved: new Date().toISOString(),
            }),
         );
      }, 30000);

      return () => clearInterval(interval);
   }, [attempt.id, quantities, supportingFile]);

   const filledCount = useMemo(
      () => lineItems.filter((line) => String(quantities[line.key] ?? '').trim() !== '').length,
      [lineItems, quantities],
   );

   const unansweredCount = lineItems.length - filledCount;

   const handleQuantityChange = (key: string, value: string) => {
      setQuantities((prev) => ({
         ...prev,
         [key]: value,
      }));
   };

   const handleSubmit = (event?: FormEvent) => {
      event?.preventDefault();
      setIsSubmitting(true);

      localStorage.setItem(
         `exam-attempt-${attempt.id}`,
         JSON.stringify({
            quantities,
            supportingFile,
            lastSaved: new Date().toISOString(),
         }),
      );

      router.post(
         route('exam-attempts.submit', attempt.id),
         {
            exam_attempt_id: attempt.id,
            answers: [
               {
                  exam_question_id: question?.id,
                  answer_data: {
                     quantities,
                     ...(supportingFile?.file_url
                        ? {
                             supporting_file_url: supportingFile.file_url,
                             supporting_file_name: supportingFile.file_name,
                          }
                        : {}),
                  },
               },
            ],
         },
         {
            onSuccess: () => {
               localStorage.removeItem(`exam-attempt-${attempt.id}`);
            },
            onFinish: () => {
               setIsSubmitting(false);
               setShowSubmitDialog(false);
            },
         },
      );
   };

   return (
      <Main>
         <Head title={`Quantity Take-Off: ${attempt.exam.title}`} />

         <main className="flex min-h-screen flex-col justify-between overflow-x-hidden">
            <AttemptNavbar attempt={attempt} questionIndex={0} />

            <div className="container space-y-6 py-12">
               <TimerComponent attempt={attempt} endTime={computedDeadline} questionIndex={0} />

               <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                  <Card className="lg:col-span-1">
                     <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                           <FileText className="h-5 w-5" />
                           Reference drawings
                        </CardTitle>
                        <CardDescription>Download and review the project files while completing your quantities.</CardDescription>
                     </CardHeader>
                     <CardContent className="space-y-3">
                        {resources.length === 0 ? (
                           <p className="text-sm text-muted-foreground">No reference files uploaded for this exam.</p>
                        ) : (
                           resources.map((resource) => (
                              <div key={resource.id} className="flex items-center justify-between gap-3 rounded-md border p-3">
                                 <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{resource.title}</p>
                                    <p className="text-xs text-muted-foreground uppercase">{resource.type}</p>
                                 </div>
                                 <Button type="button" size="sm" variant="outline" asChild>
                                    <a href={route('exam-resources.download', resource.id)}>
                                       <Download className="mr-1 h-4 w-4" />
                                       Download
                                    </a>
                                 </Button>
                              </div>
                           ))
                        )}
                     </CardContent>
                  </Card>

                  <Card className="lg:col-span-2">
                     <CardHeader>
                        <CardTitle>Grading rules</CardTitle>
                        <CardDescription>
                           Quantities are graded with tolerance, not exact match. You need at least {gradingRules.pass_mark}% of line items
                           correct to pass.
                        </CardDescription>
                     </CardHeader>
                     <CardContent className="grid gap-2 text-sm text-muted-foreground md:grid-cols-2">
                        <p>Area (SF/SY): within {gradingRules.tolerance_percent}% or {gradingRules.unit_floors.SF}, whichever is larger</p>
                        <p>Linear (LF): within {gradingRules.tolerance_percent}% or {gradingRules.unit_floors.LF}, whichever is larger</p>
                        <p>Each (EA): within ±{gradingRules.unit_floors.EA}</p>
                        <p>Other units: within {gradingRules.tolerance_percent}% or ±{gradingRules.unit_floors.default}</p>
                     </CardContent>
                     {templateDownloadUrl && (
                        <CardContent className="border-t pt-4">
                           <p className="mb-2 text-sm text-muted-foreground">
                              Download the blank Estimator Notes workbook for offline take-off. Enter your final answers in the grid below.
                           </p>
                           <Button type="button" variant="outline" size="sm" asChild>
                              <a href={templateDownloadUrl}>
                                 <Download className="mr-1 h-4 w-4" />
                                 Download blank Excel template
                              </a>
                           </Button>
                        </CardContent>
                     )}
                  </Card>
               </div>

               {allowSupportingUpload && (
                  <Card>
                     <CardHeader>
                        <CardTitle>Supporting work (optional)</CardTitle>
                        <CardDescription>
                           Upload your completed Excel template or a PDF showing how you arrived at your quantities. This does not change
                           your score — trainers can review it alongside your submitted answers.
                        </CardDescription>
                     </CardHeader>
                     <CardContent className="space-y-3">
                        <ChunkedUploaderInput
                           isSubmit={false}
                           filetype="document"
                           delayUpload={false}
                           onFileSelected={() => setSupportingFile(null)}
                           onFileUploaded={(fileData) => {
                              if (!fileData?.file_url) {
                                 setSupportingFile(null);
                                 return;
                              }

                              setSupportingFile({
                                 file_url: fileData.file_url,
                                 file_name: fileData.file_name,
                              });
                           }}
                           onError={() => setSupportingFile(null)}
                           onCancelUpload={() => setSupportingFile(null)}
                        />
                        <p className="text-xs text-muted-foreground">Accepted: .xlsx or .pdf (max 20MB)</p>
                        {supportingFile && (
                           <p className="flex items-center gap-2 text-sm text-green-600">
                              <CheckCircle2 className="h-4 w-4" />
                              Attached: {supportingFile.file_name}
                           </p>
                        )}
                     </CardContent>
                  </Card>
               )}

               <Card>
                  <CardHeader>
                     <CardTitle>Quantity Summary</CardTitle>
                     <CardDescription>
                        Enter your quantity for each line item. {filledCount} of {lineItems.length} lines completed.
                     </CardDescription>
                  </CardHeader>
                  <CardContent>
                     <form onSubmit={handleSubmit}>
                        <div className="overflow-x-auto">
                           <Table>
                              <TableHeader>
                                 <TableRow>
                                    <TableHead className="w-12">#</TableHead>
                                    <TableHead>Item</TableHead>
                                    <TableHead className="w-40">Quantity</TableHead>
                                    <TableHead className="w-24">Unit</TableHead>
                                 </TableRow>
                              </TableHeader>
                              <TableBody>
                                 {lineItems.map((line, index) => (
                                    <TableRow key={line.key}>
                                       <TableCell>{index + 1}</TableCell>
                                       <TableCell className="whitespace-normal">{line.item}</TableCell>
                                       <TableCell>
                                          <Input
                                             type="text"
                                             inputMode="decimal"
                                             value={quantities[line.key] ?? ''}
                                             onChange={(event) => handleQuantityChange(line.key, event.target.value)}
                                             placeholder="0"
                                          />
                                       </TableCell>
                                       <TableCell>{line.unit || '—'}</TableCell>
                                    </TableRow>
                                 ))}
                              </TableBody>
                           </Table>
                        </div>

                        <div className="mt-6 flex justify-end">
                           <Button type="button" onClick={() => setShowSubmitDialog(true)}>
                              Submit exam
                           </Button>
                        </div>
                     </form>
                  </CardContent>
               </Card>
            </div>

            <AlertDialog open={showSubmitDialog} onOpenChange={setShowSubmitDialog}>
               <AlertDialogContent>
                  <AlertDialogHeader>
                     <AlertDialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-yellow-600" />
                        Submit quantity take-off?
                     </AlertDialogTitle>
                     <AlertDialogDescription className="space-y-3">
                        <p>Your quantities will be scored immediately. This action cannot be undone.</p>
                        {unansweredCount > 0 && (
                           <div className="rounded-lg bg-yellow-500/10 p-3 text-sm text-yellow-800">
                              You still have {unansweredCount} blank line{unansweredCount > 1 ? 's' : ''}. Blank answers count as incorrect.
                           </div>
                        )}
                        <p className="text-sm">
                           Completed: {filledCount} / {lineItems.length}
                        </p>
                     </AlertDialogDescription>
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                     <AlertDialogCancel>Cancel</AlertDialogCancel>
                     <AlertDialogAction onClick={() => handleSubmit()} disabled={isSubmitting}>
                        {isSubmitting ? 'Submitting...' : 'Yes, submit'}
                     </AlertDialogAction>
                  </AlertDialogFooter>
               </AlertDialogContent>
            </AlertDialog>

            <Footer />
         </main>
      </Main>
   );
};

export default QuantityTakeoffAttempt;
