import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import { DateTimePicker } from '@/components/datetime-picker';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { onHandleChange } from '@/lib/inertia';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Editor } from 'richtor';
import 'richtor/styles';
import { CourseUpdateProps } from '../../update';

interface Props {
   title: string;
   assignment?: CourseAssignment;
   handler: React.ReactNode;
}

const AssignmentForm = ({ title, assignment, handler }: Props) => {
   const [open, setOpen] = useState(false);
   const [isSubmit, setIsSubmit] = useState(false);
   const [isFileUploaded, setIsFileUploaded] = useState(false);
   const [hasNewFile, setHasNewFile] = useState(false);
   const [resourceError, setResourceError] = useState('');

   const { props } = usePage<CourseUpdateProps>();
   const { translate } = props;
   const { dashboard, input, button } = translate;

   const { data, setData, post, put, reset, errors, processing, clearErrors } = useForm({
      title: assignment?.title || '',
      course_id: props.course.id,
      total_mark: assignment?.total_mark || '',
      pass_mark: assignment?.pass_mark || '',
      retake: assignment?.retake || 1,
      summary: assignment?.summary || '',
      sample_project_type: assignment?.sample_project_type || 'url',
      sample_project_path: assignment?.sample_project_path || '',
      deadline: assignment?.deadline ? new Date(assignment.deadline) : new Date(),
      late_submission: assignment?.late_submission || false,
      late_total_mark: assignment?.late_total_mark || 0,
      late_deadline: assignment?.late_deadline ? new Date(assignment.late_deadline) : '',
   });

   const submitForm = () => {
      clearErrors();
      setResourceError('');

      if (assignment) {
         put(route('assignment.update', assignment.id), {
            onSuccess: () => {
               reset();
               setOpen(false);
               setIsSubmit(false);
               setHasNewFile(false);
            },
            onError: () => {
               setIsSubmit(false);
            },
         });
      } else {
         post(route('assignment.store'), {
            onSuccess: () => {
               reset();
               setOpen(false);
               setIsSubmit(false);
               setHasNewFile(false);
            },
            onError: () => {
               setIsSubmit(false);
            },
         });
      }
   };

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      if (data.sample_project_type === 'file') {
         const needsUpload = hasNewFile || !data.sample_project_path;

         if (needsUpload && !hasNewFile) {
            setResourceError('Please select a file to upload.');
            return;
         }

         if (needsUpload) {
            setIsSubmit(true);
            return;
         }
      }

      submitForm();
   };

   useEffect(() => {
      if (data.sample_project_path && isFileUploaded) {
         submitForm();
         setIsFileUploaded(false);
      }
   }, [data.sample_project_path, isFileUploaded]);

   const handleResourceTypeChange = (type: 'url' | 'file') => {
      setData((prev) => ({
         ...prev,
         sample_project_type: type,
         sample_project_path: type === prev.sample_project_type ? prev.sample_project_path : '',
      }));
      setHasNewFile(false);
      setResourceError('');
   };

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger>{handler}</DialogTrigger>

         <DialogContent className="p-0">
            <ScrollArea className="max-h-[90vh] p-6">
               <DialogHeader className="mb-6">
                  <DialogTitle>{title}</DialogTitle>
               </DialogHeader>

               <form onSubmit={handleSubmit} className="space-y-4 p-0.5">
                  <div>
                     <Label>{input.title}</Label>
                     <Input
                        required
                        type="text"
                        name="title"
                        value={data.title}
                        placeholder={'Enter assignment title'}
                        onChange={(e) => onHandleChange(e, setData)}
                     />
                     <InputError message={errors.title} />
                  </div>

                  <div>
                     <Label>{'Deadline'}</Label>
                     <DateTimePicker date={data.deadline} setDate={(date) => setData('deadline', date)} />
                     <InputError message={errors.deadline} />
                  </div>

                  <div className="grid grid-cols-3 gap-4">
                     <div>
                        <Label>{dashboard.total_mark}</Label>
                        <Input required type="number" name="total_mark" value={data.total_mark} onChange={(e) => onHandleChange(e, setData)} />
                        <InputError message={errors.total_mark} />
                     </div>
                     <div>
                        <Label>{dashboard.pass_mark}</Label>
                        <Input required type="number" name="pass_mark" value={data.pass_mark} onChange={(e) => onHandleChange(e, setData)} />
                        <InputError message={errors.pass_mark} />
                     </div>
                     <div>
                        <Label>{input.retake_attempts}</Label>
                        <Input
                           min="1"
                           required
                           type="number"
                           name="retake"
                           value={data.retake}
                           placeholder="00"
                           onChange={(e) => onHandleChange(e, setData)}
                        />
                        <InputError message={errors.retake} />
                     </div>
                  </div>

                  <div className="space-y-4 rounded-lg border p-4">
                     <div>
                        <Label className="text-foreground">Student Resource (optional)</Label>
                        <p className="text-muted-foreground mt-1 text-sm">
                           Upload a PDF or file students can download before submitting their work.
                        </p>
                     </div>

                     <div>
                        <Label>Resource Type</Label>
                        <Select value={data.sample_project_type} onValueChange={(value) => handleResourceTypeChange(value as 'url' | 'file')}>
                           <SelectTrigger className="w-full">
                              <SelectValue placeholder="Select resource type" />
                           </SelectTrigger>
                           <SelectContent>
                              <SelectItem value="url">External URL</SelectItem>
                              <SelectItem value="file">Upload File</SelectItem>
                           </SelectContent>
                        </Select>
                        <InputError message={errors.sample_project_type} />
                     </div>

                     {data.sample_project_type === 'url' ? (
                        <div>
                           <Label>Resource URL</Label>
                           <Input
                              type="url"
                              value={data.sample_project_path}
                              onChange={(e) => setData('sample_project_path', e.target.value)}
                              placeholder="https://example.com/sample-project.pdf"
                           />
                           <InputError message={errors.sample_project_path} />
                        </div>
                     ) : (
                        <div className="space-y-2">
                           <Label>Upload Resource File</Label>

                           {assignment?.sample_project_path && !hasNewFile && (
                              <p className="text-muted-foreground text-sm">
                                 A resource file is already attached. Choose a new file below to replace it.
                              </p>
                           )}

                           <ChunkedUploaderInput
                              isSubmit={isSubmit}
                              courseId={props.course.id}
                              filetype="document"
                              delayUpload={true}
                              onFileSelected={() => {
                                 setHasNewFile(true);
                                 setResourceError('');
                              }}
                              onFileUploaded={(fileData) => {
                                 setIsFileUploaded(true);
                                 setData('sample_project_path', fileData.file_url);
                              }}
                              onError={() => {
                                 setIsSubmit(false);
                              }}
                              onCancelUpload={() => {
                                 setIsSubmit(false);
                              }}
                           />
                           <InputError message={errors.sample_project_path || resourceError} />
                           <p className="text-muted-foreground text-xs">Formats: PDF, DOC, DOCX, PNG, JPEG, ZIP (max 20 MB)</p>
                        </div>
                     )}
                  </div>

                  <div>
                     <Label htmlFor="summary">{'Summary'}</Label>
                     <Editor
                        ssr={true}
                        output="html"
                        placeholder={{
                           paragraph: 'Type assignment summary here...',
                           imageCaption: 'Type caption for image (optional)',
                        }}
                        contentMinHeight={256}
                        contentMaxHeight={640}
                        initialContent={data.summary}
                        onContentChange={(value) =>
                           setData((prev) => ({
                              ...prev,
                              summary: value as string,
                           }))
                        }
                     />
                     <InputError message={errors.summary} />
                  </div>

                  <div className="flex items-center space-x-2">
                     <Checkbox
                        id="late_submission"
                        checked={data.late_submission}
                        onCheckedChange={(checked) =>
                           setData((prev) => ({
                              ...prev,
                              late_submission: checked as boolean,
                           }))
                        }
                     />
                     <Label htmlFor="late_submission" className="cursor-pointer">
                        {'Allow Late Submission'}
                     </Label>
                  </div>

                  {data.late_submission && (
                     <>
                        <div>
                           <Label>{'Late Submission Mark'}</Label>
                           <Input
                              type="number"
                              name="late_total_mark"
                              value={data.late_total_mark}
                              placeholder="Enter marks for late submission"
                              onChange={(e) => onHandleChange(e, setData)}
                           />
                           <InputError message={errors.late_total_mark} />
                        </div>

                        <div>
                           <Label>{'Late Submission Deadline'}</Label>
                           <DateTimePicker
                              date={data.late_deadline ? new Date(data.late_deadline) : new Date()}
                              setDate={(date) => setData('late_deadline', date)}
                           />
                           <InputError message={errors.late_deadline} />
                        </div>
                     </>
                  )}

                  <DialogFooter className="flex justify-end space-x-2 pt-4">
                     <DialogClose asChild>
                        <Button type="button" variant="outline">
                           {button.close}
                        </Button>
                     </DialogClose>

                     <LoadingButton loading={processing || isSubmit}>{button.submit}</LoadingButton>
                  </DialogFooter>
               </form>
            </ScrollArea>
         </DialogContent>
      </Dialog>
   );
};

export default AssignmentForm;
