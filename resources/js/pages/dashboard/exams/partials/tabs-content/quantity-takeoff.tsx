import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { router, usePage } from '@inertiajs/react';
import { BarChart3, CheckCircle2, Clock, Download, FileSpreadsheet, FileUp, PlayCircle, Settings } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ExamUpdateProps } from '../../update';

type TakeoffLineItem = {
   key: string;
   item: string;
   unit: string;
   expected_qty: number;
   tolerance_override?: number | null;
};

type UploadedFile = {
   file_url: string;
   file_name: string;
};

const fieldError = (errors: Record<string, unknown>, key: string): string | undefined => {
   const value = errors[key];

   if (!value) {
      return undefined;
   }

   return Array.isArray(value) ? String(value[0]) : String(value);
};

const QuantityTakeoff = () => {
   const { props } = usePage<ExamUpdateProps>();
   const { exam, takeoffAnalytics } = props;
   const pageErrors = (props.errors ?? {}) as Record<string, unknown>;

   const takeoffConfig = (exam.takeoff_config || {}) as {
      answer_key_file_url?: string;
      answer_key_file_name?: string;
      line_items?: TakeoffLineItem[];
      parsed_at?: string;
      tutorial_video_url?: string;
      tutorial_video_name?: string;
      student_template_file_url?: string;
      student_template_file_name?: string;
   };

   const [pendingAnswerKey, setPendingAnswerKey] = useState<UploadedFile | null>(null);
   const [pendingStudentTemplate, setPendingStudentTemplate] = useState<UploadedFile | null>(null);
   const [pendingTutorial, setPendingTutorial] = useState<UploadedFile | null>(null);
   const [toleranceDraft, setToleranceDraft] = useState<Record<string, string>>({});
   const [importingAnswerKey, setImportingAnswerKey] = useState(false);
   const [savingStudentTemplate, setSavingStudentTemplate] = useState(false);
   const [savingTutorial, setSavingTutorial] = useState(false);
   const [savingTolerances, setSavingTolerances] = useState(false);

   const lineItems = takeoffConfig.line_items || [];

   useEffect(() => {
      const draft: Record<string, string> = {};

      lineItems.forEach((line) => {
         draft[line.key] =
            line.tolerance_override !== null && line.tolerance_override !== undefined ? String(line.tolerance_override) : '';
      });

      setToleranceDraft(draft);
   }, [lineItems]);

   const defaultToleranceHint = useMemo(() => 'Leave blank to use the default rule (1% or unit floor).', []);

   const handleImportAnswerKey = () => {
      if (!pendingAnswerKey?.file_url) {
         return;
      }

      setImportingAnswerKey(true);
      router.post(
         route('exams.takeoff.answer-key', exam.id),
         {
            answer_key_file_url: pendingAnswerKey.file_url,
            answer_key_file_name: pendingAnswerKey.file_name,
         },
         {
            preserveScroll: true,
            onSuccess: () => setPendingAnswerKey(null),
            onFinish: () => setImportingAnswerKey(false),
         },
      );
   };

   const handleSaveStudentTemplate = () => {
      if (!pendingStudentTemplate?.file_url) {
         return;
      }

      setSavingStudentTemplate(true);
      router.post(
         route('exams.takeoff.student-template', exam.id),
         {
            student_template_file_url: pendingStudentTemplate.file_url,
            student_template_file_name: pendingStudentTemplate.file_name,
         },
         {
            preserveScroll: true,
            onSuccess: () => setPendingStudentTemplate(null),
            onFinish: () => setSavingStudentTemplate(false),
         },
      );
   };

   const handleSaveTutorial = () => {
      if (!pendingTutorial?.file_url) {
         return;
      }

      setSavingTutorial(true);
      router.post(
         route('exams.takeoff.tutorial', exam.id),
         {
            tutorial_video_url: pendingTutorial.file_url,
            tutorial_video_name: pendingTutorial.file_name,
         },
         {
            preserveScroll: true,
            onSuccess: () => setPendingTutorial(null),
            onFinish: () => setSavingTutorial(false),
         },
      );
   };

   const handleSaveTolerances = () => {
      setSavingTolerances(true);
      router.post(
         route('exams.takeoff.tolerances', exam.id),
         {
            tolerances: lineItems.map((line) => ({
               key: line.key,
               tolerance_override: toleranceDraft[line.key]?.trim() ? Number(toleranceDraft[line.key]) : null,
            })),
         },
         {
            preserveScroll: true,
            onFinish: () => setSavingTolerances(false),
         },
      );
   };

   return (
      <div className="space-y-6">
         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <Settings className="h-5 w-5" />
                  Exam timing and attempts
               </CardTitle>
               <CardDescription>
                  Duration, pass mark, and max attempts are managed on the Settings tab. Quantity take-off exams default to an 85%
                  pass mark.
               </CardDescription>
            </CardHeader>
            <CardContent>
               <Button type="button" variant="outline" onClick={() => router.get(route('exams.edit', { exam: exam.id, tab: 'settings' }))}>
                  <Clock className="mr-2 h-4 w-4" />
                  Open exam settings
               </Button>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <FileSpreadsheet className="h-5 w-5" />
                  Quantity Take-Off Answer Key
               </CardTitle>
               <CardDescription>
                  Upload a filled Excel answer key using the Estimator Notes template. The system validates the layout, reads the
                  Quantity Summary section, and builds the in-system student grid automatically.
               </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               <div className="rounded-md border border-blue-500/30 bg-blue-500/10 p-4 text-sm text-blue-900 dark:text-blue-100">
                  <p className="font-medium">Validation checks</p>
                  <ul className="mt-2 list-disc space-y-1 pl-5">
                     <li>File must be .xlsx</li>
                     <li>Sheet: Estimator Notes</li>
                     <li>Section: Quantity Summary with Item / Quantity headers</li>
                     <li>At least one quantity value in column B</li>
                     <li>Duplicate item names are rejected</li>
                  </ul>
               </div>

               {takeoffConfig.answer_key_file_url && (
                  <div className="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted p-3">
                     <span className="flex-1 truncate text-sm">{takeoffConfig.answer_key_file_name || 'Current answer key'}</span>
                     <Button type="button" variant="outline" size="sm" asChild>
                        <a href={takeoffConfig.answer_key_file_url} target="_blank" rel="noopener noreferrer">
                           <Download className="mr-1 h-4 w-4" />
                           View current key
                        </a>
                     </Button>
                  </div>
               )}

               <div className="space-y-3">
                  <ChunkedUploaderInput
                     isSubmit={false}
                     filetype="document"
                     delayUpload={false}
                     onFileSelected={() => setPendingAnswerKey(null)}
                     onFileUploaded={(fileData) => {
                        if (!fileData?.file_url) {
                           setPendingAnswerKey(null);
                           return;
                        }

                        setPendingAnswerKey({
                           file_url: fileData.file_url,
                           file_name: fileData.file_name,
                        });
                     }}
                     onError={() => setPendingAnswerKey(null)}
                     onCancelUpload={() => setPendingAnswerKey(null)}
                  />
                  <p className="text-xs text-muted-foreground">Upload .xlsx answer key (max 20MB). Wait for “Completed upload” before importing.</p>

                  {pendingAnswerKey && (
                     <p className="flex items-center gap-2 text-sm text-green-600">
                        <CheckCircle2 className="h-4 w-4" />
                        Ready to import: {pendingAnswerKey.file_name}
                     </p>
                  )}

                  <InputError message={fieldError(pageErrors, 'answer_key_file_url')} />
               </div>

               <div className="flex flex-wrap gap-3">
                  <LoadingButton
                     type="button"
                     loading={importingAnswerKey}
                     disabled={!pendingAnswerKey?.file_url}
                     onClick={handleImportAnswerKey}
                  >
                     Validate and import answer key
                  </LoadingButton>

                  <Button type="button" variant="ghost" onClick={() => router.get(route('exams.edit', { exam: exam.id, tab: 'resources' }))}>
                     Manage reference drawings
                  </Button>
               </div>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <FileUp className="h-5 w-5" />
                  Blank student template
               </CardTitle>
               <CardDescription>
                  Upload the blank Estimator Notes workbook students can download during the exam. Save a copy of your answer key with
                  quantities cleared in column B. Grading still uses the filled answer key above — this file is for student reference only.
               </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               {takeoffConfig.student_template_file_url && (
                  <div className="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted p-3">
                     <span className="flex-1 truncate text-sm">
                        {takeoffConfig.student_template_file_name || 'Current student template'}
                     </span>
                     <Button type="button" variant="outline" size="sm" asChild>
                        <a href={route('exams.takeoff.template', exam.id)}>
                           <Download className="mr-1 h-4 w-4" />
                           Download current template
                        </a>
                     </Button>
                  </div>
               )}

               <ChunkedUploaderInput
                  isSubmit={false}
                  filetype="document"
                  delayUpload={false}
                  onFileSelected={() => setPendingStudentTemplate(null)}
                  onFileUploaded={(fileData) => {
                     if (!fileData?.file_url) {
                        setPendingStudentTemplate(null);
                        return;
                     }

                     setPendingStudentTemplate({
                        file_url: fileData.file_url,
                        file_name: fileData.file_name,
                     });
                  }}
                  onError={() => setPendingStudentTemplate(null)}
                  onCancelUpload={() => setPendingStudentTemplate(null)}
               />
               <p className="text-xs text-muted-foreground">Upload blank .xlsx template (max 20MB). Wait for “Completed upload” before saving.</p>

               {pendingStudentTemplate && (
                  <p className="flex items-center gap-2 text-sm text-green-600">
                     <CheckCircle2 className="h-4 w-4" />
                     Ready to save: {pendingStudentTemplate.file_name}
                  </p>
               )}

               <InputError message={fieldError(pageErrors, 'student_template_file_url')} />

               <LoadingButton
                  type="button"
                  loading={savingStudentTemplate}
                  disabled={!pendingStudentTemplate?.file_url}
                  onClick={handleSaveStudentTemplate}
               >
                  Save blank student template
               </LoadingButton>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <PlayCircle className="h-5 w-5" />
                  Walkthrough tutorial video
               </CardTitle>
               <CardDescription>
                  Upload a project walkthrough. Students only see this after they submit the exam, on their results page.
               </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               {takeoffConfig.tutorial_video_url && (
                  <div className="space-y-3">
                     <p className="text-sm font-medium">{takeoffConfig.tutorial_video_name || 'Current tutorial video'}</p>
                     <video
                        src={takeoffConfig.tutorial_video_url}
                        controls
                        className="w-full max-w-2xl rounded-lg border"
                        preload="metadata"
                     />
                  </div>
               )}

               <ChunkedUploaderInput
                  isSubmit={false}
                  filetype="video"
                  delayUpload={false}
                  onFileSelected={() => setPendingTutorial(null)}
                  onFileUploaded={(fileData) => {
                     if (!fileData?.file_url) {
                        setPendingTutorial(null);
                        return;
                     }

                     setPendingTutorial({
                        file_url: fileData.file_url,
                        file_name: fileData.file_name,
                     });
                  }}
                  onError={() => setPendingTutorial(null)}
                  onCancelUpload={() => setPendingTutorial(null)}
               />
               <p className="text-xs text-muted-foreground">Upload MP4 or other supported video formats. Wait for “Completed upload” before saving.</p>

               {pendingTutorial && (
                  <p className="flex items-center gap-2 text-sm text-green-600">
                     <CheckCircle2 className="h-4 w-4" />
                     Ready to save: {pendingTutorial.file_name}
                  </p>
               )}

               <InputError message={fieldError(pageErrors, 'tutorial_video_url')} />

               <LoadingButton type="button" loading={savingTutorial} disabled={!pendingTutorial?.file_url} onClick={handleSaveTutorial}>
                  Save tutorial video
               </LoadingButton>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle>Parsed line items and tolerances</CardTitle>
               <CardDescription>
                  {lineItems.length > 0
                     ? `${lineItems.length} graded quantity line(s) detected. Override tolerance per line if needed. ${defaultToleranceHint}`
                     : 'Import an answer key to preview the line items students will complete.'}
               </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               {lineItems.length > 0 ? (
                  <>
                     <div className="overflow-x-auto">
                        <Table>
                           <TableHeader>
                              <TableRow>
                                 <TableHead>#</TableHead>
                                 <TableHead>Item</TableHead>
                                 <TableHead>Quantity</TableHead>
                                 <TableHead>Unit</TableHead>
                                 <TableHead>Custom ± tolerance</TableHead>
                              </TableRow>
                           </TableHeader>
                           <TableBody>
                              {lineItems.map((line, index) => (
                                 <TableRow key={line.key}>
                                    <TableCell>{index + 1}</TableCell>
                                    <TableCell className="max-w-md whitespace-normal">{line.item}</TableCell>
                                    <TableCell>{line.expected_qty}</TableCell>
                                    <TableCell>
                                       <Badge variant="outline">{line.unit || '—'}</Badge>
                                    </TableCell>
                                    <TableCell>
                                       <Input
                                          type="number"
                                          min="0"
                                          step="0.01"
                                          placeholder="Default"
                                          value={toleranceDraft[line.key] ?? ''}
                                          onChange={(event) =>
                                             setToleranceDraft((prev) => ({
                                                ...prev,
                                                [line.key]: event.target.value,
                                             }))
                                          }
                                          className="w-28"
                                       />
                                    </TableCell>
                                 </TableRow>
                              ))}
                           </TableBody>
                        </Table>
                     </div>
                     <LoadingButton type="button" loading={savingTolerances} onClick={handleSaveTolerances}>
                        Save per-line tolerances
                     </LoadingButton>
                  </>
               ) : (
                  <p className="text-sm text-muted-foreground">No answer key imported yet.</p>
               )}
            </CardContent>
         </Card>

         {takeoffAnalytics && takeoffAnalytics.length > 0 && (
            <Card>
               <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                     <BarChart3 className="h-5 w-5" />
                     Line item miss analytics
                  </CardTitle>
                  <CardDescription>Which quantity lines students miss most often across completed attempts.</CardDescription>
               </CardHeader>
               <CardContent>
                  <div className="overflow-x-auto">
                     <Table>
                        <TableHeader>
                           <TableRow>
                              <TableHead>Item</TableHead>
                              <TableHead>Unit</TableHead>
                              <TableHead>Attempts</TableHead>
                              <TableHead>Misses</TableHead>
                              <TableHead>Miss rate</TableHead>
                           </TableRow>
                        </TableHeader>
                        <TableBody>
                           {takeoffAnalytics.map((row) => (
                              <TableRow key={row.key}>
                                 <TableCell className="max-w-md whitespace-normal">{row.item}</TableCell>
                                 <TableCell>
                                    <Badge variant="outline">{row.unit || '—'}</Badge>
                                 </TableCell>
                                 <TableCell>{row.attempts}</TableCell>
                                 <TableCell>{row.misses}</TableCell>
                                 <TableCell>
                                    <span className={row.miss_rate >= 50 ? 'font-semibold text-red-600' : ''}>{row.miss_rate}%</span>
                                 </TableCell>
                              </TableRow>
                           ))}
                        </TableBody>
                     </Table>
                  </div>
               </CardContent>
            </Card>
         )}
      </div>
   );
};

export default QuantityTakeoff;
