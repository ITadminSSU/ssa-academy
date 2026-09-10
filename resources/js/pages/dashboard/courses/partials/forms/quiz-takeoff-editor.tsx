import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';
import { CheckCircle2, FileSpreadsheet, PlayCircle, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { UploadedFile } from './quiz-takeoff-fields';

type TakeoffLineItem = {
   key: string;
   item: string;
   unit: string;
   expected_qty: number;
   tolerance_override?: number | null;
   tolerance_override_mode?: 'percent' | 'absolute' | null;
};

type TakeoffOptions = {
   drawings?: UploadedFile[];
   pdf_url?: string;
   pdf_name?: string;
   answer_key_file_url?: string;
   answer_key_file_name?: string;
   tutorial_video_url?: string;
   tutorial_video_name?: string;
   line_items?: TakeoffLineItem[];
   tolerance_percent?: number;
   parsed_at?: string;
};

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

const takeoffOptions = (question: QuizQuestion): TakeoffOptions => {
   const options = parseJsonValue(question.options);
   if (!options || Array.isArray(options)) {
      return {};
   }
   return options as TakeoffOptions;
};

const drawingsFromOptions = (options: TakeoffOptions): UploadedFile[] => {
   if (Array.isArray(options.drawings) && options.drawings.length > 0) {
      return options.drawings.filter((drawing) => drawing?.file_url);
   }
   if (options.pdf_url) {
      return [{ file_url: options.pdf_url, file_name: options.pdf_name || 'plans.pdf' }];
   }
   return [];
};

const isPercentOverride = (line: TakeoffLineItem) =>
   line.tolerance_override != null && line.tolerance_override_mode === 'percent';

const isLegacyAbsolute = (line: TakeoffLineItem) =>
   line.tolerance_override != null && line.tolerance_override_mode !== 'percent';

const quantityBand = (expected: number, percent: number) => Math.abs(expected) * (percent / 100);

const toleranceSavePayload = (line: TakeoffLineItem, draft: string) => {
   const trimmed = draft.trim();

   if (trimmed) {
      return { key: line.key, tolerance_override: Number(trimmed), tolerance_override_mode: 'percent' as const };
   }

   if (isLegacyAbsolute(line)) {
      return { key: line.key, tolerance_override: line.tolerance_override, tolerance_override_mode: 'absolute' as const };
   }

   return { key: line.key, tolerance_override: null, tolerance_override_mode: null };
};

const fieldError = (errors: Record<string, unknown>, key: string): string | undefined => {
   const value = errors[key];
   if (!value) return undefined;
   return Array.isArray(value) ? String(value[0]) : String(value);
};

interface Props {
   question: QuizQuestion;
   defaultTolerancePercent: number;
}

const QuizTakeoffEditor = ({ question, defaultTolerancePercent }: Props) => {
   const { props } = usePage<SharedData>();
   const pageErrors = (props.errors ?? {}) as Record<string, unknown>;
   const options = takeoffOptions(question);
   const drawings = drawingsFromOptions(options);
   const lineItems = options.line_items ?? [];
   const questionId = question.id;

   const [pendingDrawing, setPendingDrawing] = useState<UploadedFile | null>(null);
   const [pendingAnswerKey, setPendingAnswerKey] = useState<UploadedFile | null>(null);
   const [pendingTutorial, setPendingTutorial] = useState<UploadedFile | null>(null);
   const [toleranceDraft, setToleranceDraft] = useState<Record<string, string>>({});
   const [saving, setSaving] = useState<string | null>(null);

   useEffect(() => {
      const draft: Record<string, string> = {};
      (takeoffOptions(question).line_items ?? []).forEach((line) => {
         draft[line.key] = isPercentOverride(line) ? String(line.tolerance_override) : '';
      });
      setToleranceDraft(draft);
   }, [question.id, options.parsed_at, lineItems.length]);

   const postFile = (name: string, urlName: string, pending: UploadedFile | null, onDone?: () => void) => {
      if (!pending?.file_url) return;
      setSaving(name);
      router.post(
         route(urlName, { id: questionId }),
         { file_url: pending.file_url, file_name: pending.file_name },
         {
            preserveScroll: true,
            onSuccess: () => onDone?.(),
            onFinish: () => setSaving(null),
         },
      );
   };

   return (
      <div className="space-y-5">
         <div className="rounded-lg border p-4">
            <p className="font-medium">Reference drawings (PDF plans)</p>
            <p className="text-muted-foreground mt-1 text-sm">
               Students download these with the blank Excel as one pack. Add one PDF at a time.
            </p>
            {drawings.length > 0 && (
               <ul className="mt-3 space-y-2">
                  {drawings.map((drawing) => (
                     <li key={drawing.file_url} className="flex items-center justify-between gap-3 rounded-md border p-3">
                        <a
                           href={route('quiz.question.takeoff.drawing.view', { id: questionId, file_url: drawing.file_url })}
                           target="_blank"
                           rel="noreferrer"
                           className="truncate text-sm underline"
                        >
                           {drawing.file_name}
                        </a>
                        <Button
                           type="button"
                           size="icon"
                           variant="ghost"
                           onClick={() =>
                              router.post(
                                 route('quiz.question.takeoff.drawings.destroy', { id: questionId }),
                                 { file_url: drawing.file_url },
                                 { preserveScroll: true },
                              )
                           }
                        >
                           <Trash2 className="h-4 w-4" />
                        </Button>
                     </li>
                  ))}
               </ul>
            )}
            <div className="mt-3 space-y-3">
               <ChunkedUploaderInput
                  isSubmit={false}
                  filetype="document"
                  delayUpload={false}
                  onFileSelected={() => setPendingDrawing(null)}
                  onFileUploaded={(fileData) => {
                     if (!fileData?.file_url) {
                        setPendingDrawing(null);
                        return;
                     }
                     setPendingDrawing({ file_url: fileData.file_url, file_name: fileData.file_name });
                  }}
                  onError={() => setPendingDrawing(null)}
                  onCancelUpload={() => setPendingDrawing(null)}
               />
               {pendingDrawing && (
                  <p className="flex items-center gap-2 text-sm text-green-600">
                     <CheckCircle2 className="h-4 w-4" />
                     Ready: {pendingDrawing.file_name}
                  </p>
               )}
               <LoadingButton
                  type="button"
                  loading={saving === 'drawing'}
                  disabled={!pendingDrawing?.file_url}
                  onClick={() => postFile('drawing', 'quiz.question.takeoff.drawings.store', pendingDrawing, () => setPendingDrawing(null))}
               >
                  Save drawing
               </LoadingButton>
            </div>
         </div>

         <div className="rounded-lg border p-4">
            <p className="flex items-center gap-2 font-medium">
               <FileSpreadsheet className="h-5 w-5" />
               Quantity Take-Off Answer Key
            </p>
            <p className="text-muted-foreground mt-1 text-sm">
               Upload a filled Excel answer key using the Estimator Notes template. The system validates the layout and scores
               student BOQs against it.
            </p>
            <div className="mt-3 rounded-md border border-blue-500/30 bg-blue-500/10 p-4 text-sm text-blue-900 dark:text-blue-100">
               <p className="font-medium">Validation checks</p>
               <ul className="mt-2 list-disc space-y-1 pl-5">
                  <li>File must be .xlsx</li>
                  <li>Sheet: Estimator Notes</li>
                  <li>Section: Quantity Summary with Item / Quantity headers</li>
                  <li>At least one quantity value in column B</li>
                  <li>Duplicate item names are rejected</li>
               </ul>
            </div>
            {options.answer_key_file_url && (
               <div className="mt-3 flex flex-wrap items-center gap-2 rounded-md border bg-muted p-3">
                  <span className="flex-1 truncate text-sm">{options.answer_key_file_name || 'Current answer key'}</span>
                  <Button type="button" variant="outline" size="sm" asChild>
                     <a href={route('quiz.question.takeoff.answer-key.view', { id: questionId })} target="_blank" rel="noreferrer">
                        View current key
                     </a>
                  </Button>
               </div>
            )}
            <div className="mt-3 space-y-3">
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
                     setPendingAnswerKey({ file_url: fileData.file_url, file_name: fileData.file_name });
                  }}
                  onError={() => setPendingAnswerKey(null)}
                  onCancelUpload={() => setPendingAnswerKey(null)}
               />
               {pendingAnswerKey && (
                  <p className="flex items-center gap-2 text-sm text-green-600">
                     <CheckCircle2 className="h-4 w-4" />
                     Ready to import: {pendingAnswerKey.file_name}
                  </p>
               )}
               <InputError message={fieldError(pageErrors, 'file_url')} />
               <LoadingButton
                  type="button"
                  loading={saving === 'answer-key'}
                  disabled={!pendingAnswerKey?.file_url}
                  onClick={() =>
                     postFile('answer-key', 'quiz.question.takeoff.answer-key', pendingAnswerKey, () => setPendingAnswerKey(null))
                  }
               >
                  Validate and import answer key
               </LoadingButton>
            </div>
         </div>

         <div className="rounded-lg border p-4">
            <p className="flex items-center gap-2 font-medium">
               <PlayCircle className="h-5 w-5" />
               Walkthrough tutorial video
            </p>
            <p className="text-muted-foreground mt-1 text-sm">Optional. Students see this only after they submit the quiz.</p>
            {options.tutorial_video_url && (
               <div className="mt-3 rounded-md border bg-muted p-3 text-sm">{options.tutorial_video_name || 'Current tutorial video'}</div>
            )}
            <div className="mt-3 space-y-3">
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
                     setPendingTutorial({ file_url: fileData.file_url, file_name: fileData.file_name });
                  }}
                  onError={() => setPendingTutorial(null)}
                  onCancelUpload={() => setPendingTutorial(null)}
               />
               <LoadingButton
                  type="button"
                  loading={saving === 'tutorial'}
                  disabled={!pendingTutorial?.file_url}
                  onClick={() => postFile('tutorial', 'quiz.question.takeoff.tutorial', pendingTutorial, () => setPendingTutorial(null))}
               >
                  Save tutorial video
               </LoadingButton>
            </div>
         </div>

         <div className="rounded-lg border p-4">
            <p className="font-medium">Parsed line items and tolerances</p>
            <p className="text-muted-foreground mt-1 text-sm">
               {lineItems.length > 0
                  ? `${lineItems.length} graded quantity line(s) detected. Default is ${defaultTolerancePercent}% of each quantity. Leave Custom ± blank to keep the default, or type a percent for that line only.`
                  : 'Import an answer key to preview the line items students will be graded on.'}
            </p>
            {lineItems.length > 0 ? (
               <div className="mt-3 space-y-4">
                  <div className="overflow-x-auto">
                     <Table>
                        <TableHeader>
                           <TableRow>
                              <TableHead>#</TableHead>
                              <TableHead>Item</TableHead>
                              <TableHead>Quantity</TableHead>
                              <TableHead>Unit</TableHead>
                              <TableHead>Custom ± tolerance (%)</TableHead>
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
                                    <div className="flex min-w-40 flex-col gap-1">
                                       <Input
                                          type="number"
                                          min="0"
                                          max="100"
                                          step="0.01"
                                          placeholder={`Default (${defaultTolerancePercent}%)`}
                                          value={toleranceDraft[line.key] ?? ''}
                                          onChange={(event) =>
                                             setToleranceDraft((prev) => ({
                                                ...prev,
                                                [line.key]: event.target.value,
                                             }))
                                          }
                                          className="w-36"
                                       />
                                       <span className="text-muted-foreground text-xs">
                                          {toleranceDraft[line.key]?.trim() && !Number.isNaN(Number(toleranceDraft[line.key]))
                                             ? `→ ±${quantityBand(line.expected_qty, Number(toleranceDraft[line.key])).toFixed(2)} ${line.unit || ''}`.trim()
                                             : isLegacyAbsolute(line)
                                               ? `Current: ±${line.tolerance_override} ${line.unit || ''} (quantity). Enter a % to replace.`
                                               : `→ ±${quantityBand(line.expected_qty, defaultTolerancePercent).toFixed(2)} ${line.unit || ''}`.trim()}
                                       </span>
                                    </div>
                                 </TableCell>
                              </TableRow>
                           ))}
                        </TableBody>
                     </Table>
                  </div>
                  <LoadingButton
                     type="button"
                     loading={saving === 'tolerances'}
                     onClick={() => {
                        setSaving('tolerances');
                        router.post(
                           route('quiz.question.takeoff.tolerances', { id: questionId }),
                           {
                              tolerances: lineItems.map((line) => toleranceSavePayload(line, toleranceDraft[line.key] ?? '')),
                           },
                           {
                              preserveScroll: true,
                              onFinish: () => setSaving(null),
                           },
                        );
                     }}
                  >
                     Save per-line tolerances
                  </LoadingButton>
               </div>
            ) : (
               <p className="text-muted-foreground mt-3 text-sm">No answer key imported yet.</p>
            )}
         </div>
      </div>
   );
};

export default QuizTakeoffEditor;
