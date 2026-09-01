import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { CourseUpdateProps } from '../update';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, FileSpreadsheet, FileUp, PlayCircle, Settings, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

type UploadedFile = { file_url: string; file_name: string };

const fieldError = (errors: Record<string, unknown>, key: string): string | undefined => {
   const value = errors[key];
   if (!value) return undefined;
   return Array.isArray(value) ? String(value[0]) : String(value);
};

const isPercentOverride = (line: UsExperienceLineItem) =>
   line.tolerance_override != null && line.tolerance_override_mode === 'percent';

const isLegacyAbsolute = (line: UsExperienceLineItem) =>
   line.tolerance_override != null && line.tolerance_override_mode !== 'percent';

const quantityBand = (expected: number, percent: number) => Math.abs(expected) * (percent / 100);

const toleranceSavePayload = (line: UsExperienceLineItem, draft: string) => {
   const trimmed = draft.trim();

   if (trimmed) {
      return { key: line.key, tolerance_override: Number(trimmed), tolerance_override_mode: 'percent' };
   }

   if (isLegacyAbsolute(line)) {
      return { key: line.key, tolerance_override: line.tolerance_override, tolerance_override_mode: 'absolute' };
   }

   return { key: line.key, tolerance_override: null, tolerance_override_mode: null };
};

const UsExperiencePlanEditor = () => {
   const { props } = usePage<CourseUpdateProps>();
   const { course, usExperiencePlan, usExperienceDefaultTolerancePercent = 2 } = props;
   const pageErrors = (props.errors ?? {}) as Record<string, unknown>;
   const plan = usExperiencePlan;

   const settings = useForm({
      group_name: plan?.group_name || '',
      group_description: plan?.group_description || '',
      title: plan?.title || '',
      pass_mark: plan?.pass_mark ?? 85,
      max_attempts: plan?.max_attempts ?? 10,
      published: plan?.published ?? false,
   });
   settings.transform((form) => ({
      ...form,
      published: form.published ? 1 : 0,
   }));

   const [pendingDrawing, setPendingDrawing] = useState<UploadedFile | null>(null);
   const [pendingAnswerKey, setPendingAnswerKey] = useState<UploadedFile | null>(null);
   const [pendingStudentTemplate, setPendingStudentTemplate] = useState<UploadedFile | null>(null);
   const [pendingTutorial, setPendingTutorial] = useState<UploadedFile | null>(null);
   const [toleranceDraft, setToleranceDraft] = useState<Record<string, string>>({});
   const [saving, setSaving] = useState<string | null>(null);

   const lineItems = plan?.line_items || [];
   const drawings = plan?.drawings || [];

   useEffect(() => {
      if (!plan) return;
      settings.setData({
         group_name: plan.group_name,
         group_description: plan.group_description || '',
         title: plan.title,
         pass_mark: plan.pass_mark,
         max_attempts: plan.max_attempts,
         published: plan.published,
      });
      const draft: Record<string, string> = {};
      (plan.line_items || []).forEach((line) => {
         draft[line.key] = isPercentOverride(line) ? String(line.tolerance_override) : '';
      });
      setToleranceDraft(draft);
   }, [plan?.id, plan?.parsed_at]);

   if (!plan) {
      return null;
   }

   const postFile = (name: string, urlName: string, pending: UploadedFile | null, onDone?: () => void) => {
      if (!pending?.file_url) return;
      setSaving(name);
      router.post(
         route(urlName, { course: course.id, plan: plan.id }),
         { file_url: pending.file_url, file_name: pending.file_name },
         {
            preserveScroll: true,
            onSuccess: () => onDone?.(),
            onFinish: () => setSaving(null),
         },
      );
   };

   return (
      <div className="space-y-6">
         <div className="flex flex-wrap items-center justify-between gap-3">
            <Button type="button" variant="ghost" asChild>
               <Link href={route('courses.edit', { course: course.id, tab: 'us-experience' })}>
                  <ArrowLeft className="mr-2 h-4 w-4" />
                  All plans
               </Link>
            </Button>
            <div className="text-muted-foreground text-sm">
               {plan.is_ready ? 'Ready for students when published.' : 'Needs PDF drawings, blank template, and an imported answer key.'}
            </div>
         </div>

         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <Settings className="h-5 w-5" />
                  Plan settings
               </CardTitle>
               <CardDescription>
                  Untimed practice. Pass mark and max attempts live here. Duration is not used. Default quantity tolerance is{' '}
                  {usExperienceDefaultTolerancePercent}% of each line.
               </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                     <Label>Accordion group</Label>
                     <Input value={settings.data.group_name} onChange={(event) => settings.setData('group_name', event.target.value)} />
                     <InputError message={settings.errors.group_name} />
                  </div>
                  <div className="space-y-2">
                     <Label>Plan title</Label>
                     <Input value={settings.data.title} onChange={(event) => settings.setData('title', event.target.value)} />
                     <InputError message={settings.errors.title} />
                  </div>
               </div>
               <div className="space-y-2">
                  <Label>Group description</Label>
                  <Textarea
                     value={settings.data.group_description}
                     onChange={(event) => settings.setData('group_description', event.target.value)}
                     rows={2}
                  />
               </div>
               <div className="grid gap-4 sm:grid-cols-3">
                  <div className="space-y-2">
                     <Label>Pass mark %</Label>
                     <Input
                        type="number"
                        min={1}
                        max={100}
                        value={settings.data.pass_mark}
                        onChange={(event) => settings.setData('pass_mark', Number(event.target.value))}
                     />
                  </div>
                  <div className="space-y-2">
                     <Label>Max attempts</Label>
                     <Input
                        type="number"
                        min={1}
                        max={100}
                        value={settings.data.max_attempts}
                        onChange={(event) => settings.setData('max_attempts', Number(event.target.value))}
                     />
                  </div>
                  <div className="flex items-end justify-between rounded-md border p-3">
                     <div>
                        <Label>Published</Label>
                        <p className="text-muted-foreground text-xs">Visible once files are ready</p>
                     </div>
                     <Switch checked={settings.data.published} onCheckedChange={(checked) => settings.setData('published', checked)} />
                  </div>
               </div>
               <LoadingButton
                  type="button"
                  loading={settings.processing}
                  onClick={() =>
                     settings.put(route('courses.us-experience.update', { course: course.id, plan: plan.id }), { preserveScroll: true })
                  }
               >
                  Save plan settings
               </LoadingButton>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle>Reference drawings (PDF plans)</CardTitle>
               <CardDescription>Students download these with the blank Excel as one pack. Add one PDF at a time.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               {drawings.length > 0 && (
                  <ul className="space-y-2">
                     {drawings.map((drawing) => (
                        <li key={drawing.file_url} className="flex items-center justify-between gap-3 rounded-md border p-3">
                           <a href={drawing.file_url} target="_blank" rel="noreferrer" className="truncate text-sm underline">
                              {drawing.file_name}
                           </a>
                           <Button
                              type="button"
                              size="icon"
                              variant="ghost"
                              onClick={() =>
                                 router.post(route('courses.us-experience.drawings.destroy', { course: course.id, plan: plan.id }), {
                                    file_url: drawing.file_url,
                                 }, { preserveScroll: true })
                              }
                           >
                              <Trash2 className="h-4 w-4" />
                           </Button>
                        </li>
                     ))}
                  </ul>
               )}
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
                  onClick={() => postFile('drawing', 'courses.us-experience.drawings.store', pendingDrawing, () => setPendingDrawing(null))}
               >
                  Save drawing
               </LoadingButton>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <FileSpreadsheet className="h-5 w-5" />
                  Quantity Take-Off Answer Key
               </CardTitle>
               <CardDescription>
                  Upload a filled Excel answer key using the Estimator Notes template. The system validates the layout and scores student
                  BOQs against it.
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
               {plan.answer_key_file_url && (
                  <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted p-3">
                     <span className="flex-1 truncate text-sm">{plan.answer_key_file_name || 'Current answer key'}</span>
                     <Button type="button" variant="outline" size="sm" asChild>
                        <a href={plan.answer_key_file_url} target="_blank" rel="noreferrer">
                           View current key
                        </a>
                     </Button>
                  </div>
               )}
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
                     postFile('answer-key', 'courses.us-experience.answer-key', pendingAnswerKey, () => setPendingAnswerKey(null))
                  }
               >
                  Validate and import answer key
               </LoadingButton>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <FileUp className="h-5 w-5" />
                  Blank student template
               </CardTitle>
               <CardDescription>
                  Upload the blank Estimator Notes workbook students download and fill. Save a copy of the answer key with quantities
                  cleared in column B.
               </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               {plan.blank_template_file_url && (
                  <div className="rounded-md border bg-muted p-3 text-sm">{plan.blank_template_file_name || 'Current student template'}</div>
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
                     setPendingStudentTemplate({ file_url: fileData.file_url, file_name: fileData.file_name });
                  }}
                  onError={() => setPendingStudentTemplate(null)}
                  onCancelUpload={() => setPendingStudentTemplate(null)}
               />
               <LoadingButton
                  type="button"
                  loading={saving === 'template'}
                  disabled={!pendingStudentTemplate?.file_url}
                  onClick={() =>
                     postFile('template', 'courses.us-experience.student-template', pendingStudentTemplate, () =>
                        setPendingStudentTemplate(null),
                     )
                  }
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
               <CardDescription>Optional. Students see this only after they submit the plan.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
               {plan.tutorial_video_url && (
                  <div className="rounded-md border bg-muted p-3 text-sm">{plan.tutorial_video_name || 'Current tutorial video'}</div>
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
                     setPendingTutorial({ file_url: fileData.file_url, file_name: fileData.file_name });
                  }}
                  onError={() => setPendingTutorial(null)}
                  onCancelUpload={() => setPendingTutorial(null)}
               />
               <LoadingButton
                  type="button"
                  loading={saving === 'tutorial'}
                  disabled={!pendingTutorial?.file_url}
                  onClick={() => postFile('tutorial', 'courses.us-experience.tutorial', pendingTutorial, () => setPendingTutorial(null))}
               >
                  Save tutorial video
               </LoadingButton>
            </CardContent>
         </Card>

         <Card>
            <CardHeader>
               <CardTitle>Parsed line items and tolerances</CardTitle>
               <CardDescription>
                  {lineItems.length > 0
                     ? `${lineItems.length} graded quantity line(s) detected. Default is ${usExperienceDefaultTolerancePercent}% of each quantity. Leave Custom ± blank to keep the default, or type a percent for that line only.`
                     : 'Import an answer key to preview the line items students will be graded on.'}
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
                                             placeholder={`Default (${usExperienceDefaultTolerancePercent}%)`}
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
                                                  : `→ ±${quantityBand(line.expected_qty, usExperienceDefaultTolerancePercent).toFixed(2)} ${line.unit || ''}`.trim()}
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
                              route('courses.us-experience.tolerances', { course: course.id, plan: plan.id }),
                              {
                                 tolerances: lineItems.map((line) =>
                                    toleranceSavePayload(line, toleranceDraft[line.key] ?? ''),
                                 ),
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
                  </>
               ) : (
                  <p className="text-muted-foreground text-sm">No answer key imported yet.</p>
               )}
            </CardContent>
         </Card>
      </div>
   );
};

export default UsExperiencePlanEditor;
