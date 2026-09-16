import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import BunnyVideoUploaderInput from '@/components/bunny-video-uploader-input';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import Tabs from '@/components/tabs';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TabsContent } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { getFileMetadata } from '@/lib/file-metadata';
import { onHandleChange } from '@/lib/inertia';
import { cn } from '@/lib/utils';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Editor } from 'richtor';
import 'richtor/styles';
import { CourseUpdateProps } from '../../update';

const getLessonTypes = (translate: any) => [
   // { value: 'vimeo', label: 'Vimeo Video', flag: true },
   // { value: 'drive', label: 'Google drive video', flag: true },
   { value: 'video', label: translate.dashboard.video_file, flag: false },
   { value: 'video_url', label: translate.dashboard.video_url, flag: false },
   { value: 'document', label: translate.dashboard.document_file, flag: false },
   { value: 'image', label: translate.dashboard.image_file, flag: false },
   { value: 'text', label: translate.dashboard.text_content, flag: false },
   { value: 'embed', label: translate.dashboard.embed_source, flag: false },
];

interface Props {
   title: string;
   lesson?: SectionLesson;
   handler: React.ReactNode;
   sectionId: string | number;
}

const LessonForm = ({ title, handler, lesson, sectionId }: Props) => {
   const [open, setOpen] = useState(false);
   const [isSubmit, setIsSubmit] = useState(false);
   const [lessonType, setLessonType] = useState('type');
   const [isFileSelected, setIsFileSelected] = useState(false);
   const [isFileUploaded, setIsFileUploaded] = useState(false);
   const [pickedPreviewUrl, setPickedPreviewUrl] = useState<string | null>(null);
   const [previewFailed, setPreviewFailed] = useState(false);
   const pendingSrcRef = useRef<string | null>(null);
   const pendingBunnyRef = useRef<string | null>(null);
   const uploadSubmitStarted = useRef(false);

   const { props } = usePage<CourseUpdateProps>();
   const { translate, bunnyStream } = props;
   const { dashboard, input, button } = translate;

   const lessonTypes = getLessonTypes(translate);

   const { data, setData, post, put, reset, processing, errors, clearErrors, transform } = useForm({
      title: lesson ? lesson.title : '',
      status: lesson ? lesson.status : '',
      is_free: lesson ? lesson.is_free : 0,
      description: lesson ? lesson.description : '',
      sort: lesson ? lesson.sort : props.lastLessonSort + 1,
      lesson_type: lesson ? lesson.lesson_type : 'video',
      lesson_provider: lesson ? lesson.lesson_provider : '',
      lesson_src: lesson ? lesson.lesson_src : '',
      lesson_src_new: null,
      bunny_video_id_new: null as string | null,
      embed_source: lesson ? lesson.embed_source : '',
      duration: lesson ? lesson.duration : '00:00:00',
      summary: lesson ? lesson.summary : '',
      course_id: lesson ? lesson.course_id : props.course.id,
      course_section_id: sectionId,
      requires_submission: lesson?.requires_submission ?? false,
      activity_total_mark: lesson?.activity_total_mark ?? 100,
      activity_pass_mark: lesson?.activity_pass_mark ?? 70,
      activity_retake: lesson?.activity_retake ?? 1,
   });

   const isFileUpload = ['video', 'document', 'image'].includes(data.lesson_type);
   const useBunnyForVideo = data.lesson_type === 'video' && Boolean(bunnyStream?.enabled);
   const isPrivateObjectUrl = (url?: string | null) =>
      !!url && /r2\.cloudflarestorage\.com|\.amazonaws\.com/i.test(url);
   const savedImagePreview =
      data.lesson_type === 'image'
         ? pickedPreviewUrl ||
           lesson?.media_preview_url ||
           (!isPrivateObjectUrl(lesson?.lesson_src) && !isPrivateObjectUrl(data.lesson_src)
              ? lesson?.lesson_src || data.lesson_src || null
              : null)
         : null;
   const savedFileLabel =
      data.lesson_type === 'document' && (lesson?.lesson_src || data.lesson_src)
         ? decodeURIComponent(String(lesson?.lesson_src || data.lesson_src).split('/').pop() || 'Saved file')
         : null;
   const hasSavedImage = data.lesson_type === 'image' && Boolean(lesson?.lesson_src || lesson?.media_preview_url || data.lesson_src);

   useEffect(() => {
      setPreviewFailed(false);
   }, [savedImagePreview]);

   const handleSubmit = async (e: React.FormEvent) => {
      e.preventDefault();

      if (isFileUpload && isFileSelected) {
         setIsSubmit(true);
         return;
      }

      submitForm();
   };

   const resetUploadState = () => {
      pendingSrcRef.current = null;
      pendingBunnyRef.current = null;
      uploadSubmitStarted.current = false;
      setIsFileSelected(false);
      setIsFileUploaded(false);
      setIsSubmit(false);
      setPreviewFailed(false);
      setPickedPreviewUrl((current) => {
         if (current) {
            URL.revokeObjectURL(current);
         }

         return null;
      });
   };

   const submitForm = () => {
      clearErrors();

      const payloadExtras = {
         lesson_src_new: pendingSrcRef.current || data.lesson_src_new,
         bunny_video_id_new: pendingBunnyRef.current || data.bunny_video_id_new,
      };

      const options = {
         preserveScroll: true,
         onSuccess: () => {
            resetUploadState();
            setOpen(false);
         },
         onError: () => {
            uploadSubmitStarted.current = false;
            setIsSubmit(false);
         },
      };

      transform((formData) => ({ ...formData, ...payloadExtras }));

      if (lesson) {
         put(route('lesson.update', { id: lesson.id }), options);
      } else {
         post(route('lesson.store'), options);
      }
   };

   useEffect(() => {
      if (!isFileUploaded || uploadSubmitStarted.current) {
         return;
      }

      if (!pendingSrcRef.current && !data.lesson_src_new && !pendingBunnyRef.current && !data.bunny_video_id_new) {
         return;
      }

      uploadSubmitStarted.current = true;
      submitForm();
      setIsFileUploaded(false);
   }, [isFileUploaded, data.lesson_src_new, data.bunny_video_id_new]);

   useEffect(() => {
      if (!open) {
         reset();
         setLessonType('type');
      }
   }, [open]);

   const onDurationChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const value = e.target.value;

      // Ensure the value is in the HH:mm:ss format
      const formattedTime = value.match(/^([0-2]?[0-9]):([0-5]?[0-9]):([0-5]?[0-9])$/);

      if (formattedTime) {
         setData('duration', value);
      }
   };

   return (
      <Dialog open={open} onOpenChange={(nextOpen) => {
         if (!nextOpen && !isSubmit) {
            reset();
            clearErrors();
            resetUploadState();
         }
         setOpen(nextOpen);
      }}>
         <DialogTrigger>{handler}</DialogTrigger>

         <DialogContent className="p-0">
            <ScrollArea className="max-h-[90vh] p-6">
               <DialogHeader className="mb-6">
                  <DialogTitle>{title}</DialogTitle>
               </DialogHeader>

               <form onSubmit={handleSubmit}>
                  <Tabs value={lesson ? 'form' : lessonType} onValueChange={setLessonType}>
                     <TabsContent value="type">
                        <div className="space-y-1">
                           <Label className="font-semibold">{input.lesson_type}</Label>
                           <RadioGroup
                              value={data.lesson_type}
                              onValueChange={(lesson) => setData('lesson_type', lesson)}
                              className="grid grid-cols-2 gap-3"
                           >
                              {lessonTypes.map((type) => (
                                 <Label
                                    key={type.value}
                                    className={cn(
                                       'flex items-center space-x-2 rounded-lg border p-2',
                                       type.flag ? 'cursor-not-allowed' : 'cursor-pointer',
                                    )}
                                 >
                                    <RadioGroupItem className="cursor-pointer" value={type.value} disabled={type.flag} />
                                    <span>{type.label}</span>
                                 </Label>
                              ))}
                           </RadioGroup>
                        </div>
                     </TabsContent>

                     <TabsContent value="form" className="space-y-4 p-0.5">
                        <div>
                           <Label>{input.title} *</Label>
                           <Input required name="title" value={data.title} placeholder={input.title} onChange={(e) => onHandleChange(e, setData)} />
                           <InputError message={errors.title} />
                        </div>

                        {/* Conditional Fields */}
                        {['video_url'].includes(data.lesson_type) && (
                           <>
                              <div>
                                 <Label htmlFor="lesson_provider">{input.video_url_provider}</Label>
                                 <Select
                                    required
                                    name="lesson_provider"
                                    value={data.lesson_provider}
                                    onValueChange={(provider) => setData('lesson_provider', provider)}
                                 >
                                    <SelectTrigger className="w-full">
                                       <SelectValue placeholder={input.provider_placeholder} />
                                    </SelectTrigger>
                                    <SelectContent>
                                       <SelectItem value="youtube">YouTube</SelectItem>
                                    </SelectContent>
                                 </Select>
                              </div>

                              <div>
                                 <Label>
                                    Video URL
                                    <span className="text-xs text-muted-foreground">(Provide the shareable url only)</span>
                                 </Label>
                                 <Input
                                    required
                                    name="lesson_src"
                                    value={data.lesson_src || ''}
                                    placeholder={`Type your ${data.lesson_provider} video url`}
                                    onChange={(e) => onHandleChange(e, setData)}
                                 />
                                 <InputError message={errors.lesson_src} />
                              </div>
                           </>
                        )}

                        {['video', 'document', 'image'].includes(data.lesson_type) && (
                           <div className="space-y-3">
                              <Label>
                                 {input.select} {data.lesson_type}
                              </Label>

                              {savedImagePreview && !previewFailed ? (
                                 <div className="bg-muted overflow-hidden rounded-md border">
                                    <img
                                       src={savedImagePreview}
                                       alt={data.title || 'Lesson image'}
                                       className="mx-auto max-h-56 w-full object-contain"
                                       onError={() => setPreviewFailed(true)}
                                    />
                                    <p className="text-muted-foreground px-3 py-2 text-xs">
                                       {pickedPreviewUrl ? 'New image selected. Save to replace the current file.' : 'Current saved image'}
                                    </p>
                                 </div>
                              ) : hasSavedImage && !pickedPreviewUrl ? (
                                 <div className="bg-muted rounded-md border px-3 py-2">
                                    <p className="text-sm font-medium">{data.title || 'Lesson image'}</p>
                                    <p className="text-muted-foreground text-xs">
                                       Image is saved. Choose a new file only if you want to replace it.
                                    </p>
                                 </div>
                              ) : null}

                              {savedFileLabel && !pickedPreviewUrl ? (
                                 <p className="text-muted-foreground text-sm">Current file: {savedFileLabel}</p>
                              ) : null}

                              {useBunnyForVideo ? (
                                 <BunnyVideoUploaderInput
                                    isSubmit={isSubmit}
                                    courseId={data.course_id || ''}
                                    sectionId={data.course_section_id || ''}
                                    delayUpload={true}
                                    onFileSelected={(file) => {
                                       setIsFileSelected(true);
                                       if (!lesson) {
                                          getFileMetadata(file).then((metadata) => {
                                             setData('title', metadata.name);
                                             setData('duration', metadata.duration || '00:00:00');
                                          });
                                       }
                                    }}
                                    onFileUploaded={(fileData) => {
                                       pendingBunnyRef.current = fileData.bunny_video_id;
                                       setIsFileUploaded(true);
                                       setData('bunny_video_id_new', fileData.bunny_video_id);
                                       // Don't overwrite client-detected length with Bunny's temporary 00:00:00.
                                       if (fileData.duration && fileData.duration !== '00:00:00') {
                                          setData('duration', fileData.duration);
                                       }
                                    }}
                                    onError={() => {
                                       uploadSubmitStarted.current = false;
                                       setIsSubmit(false);
                                    }}
                                    onCancelUpload={() => {
                                       uploadSubmitStarted.current = false;
                                       setIsSubmit(false);
                                    }}
                                 />
                              ) : (
                                 <ChunkedUploaderInput
                                    isSubmit={isSubmit}
                                    courseId={data.course_id || ''}
                                    sectionId={data.course_section_id || ''}
                                    filetype={data.lesson_type}
                                    accept={data.lesson_type === 'image' ? 'image/*' : undefined}
                                    delayUpload={true}
                                    onFileSelected={(file) => {
                                       setIsFileSelected(true);
                                       if (data.lesson_type === 'image') {
                                          setPickedPreviewUrl((current) => {
                                             if (current) {
                                                URL.revokeObjectURL(current);
                                             }

                                             return URL.createObjectURL(file);
                                          });
                                       }
                                       if (!lesson) {
                                          getFileMetadata(file).then((metadata) => {
                                             setData('title', metadata.name);
                                             setData('duration', metadata.duration || '00:00:00');
                                          });
                                       }
                                    }}
                                    onFileUploaded={(fileData) => {
                                       pendingSrcRef.current = fileData.file_url;
                                       setIsFileUploaded(true);
                                       setData('lesson_src_new', fileData.file_url);
                                    }}
                                    onError={() => {
                                       uploadSubmitStarted.current = false;
                                       setIsSubmit(false);
                                    }}
                                    onCancelUpload={() => {
                                       uploadSubmitStarted.current = false;
                                       setIsSubmit(false);
                                    }}
                                 />
                              )}
                              <p className="text-muted-foreground text-xs">
                                 {lesson?.lesson_src || lesson?.media_preview_url
                                    ? 'Choose a new file only if you want to replace the current one.'
                                    : 'Choose a file, then save. The upload starts when you submit.'}
                              </p>
                           </div>
                        )}

                        {['document', 'text'].includes(data.lesson_type) && (
                           <div className="space-y-4 rounded-lg border p-4">
                              <div className="flex items-center gap-2">
                                 <Checkbox
                                    id="requires_submission"
                                    checked={data.requires_submission}
                                    onCheckedChange={(checked) =>
                                       setData((prev) => ({
                                          ...prev,
                                          requires_submission: checked === true,
                                       }))
                                    }
                                 />
                                 <Label htmlFor="requires_submission" className="cursor-pointer font-medium">
                                    Practical activity (learner uploads completed file for trainer review)
                                 </Label>
                              </div>

                              {data.requires_submission && (
                                 <div className="grid gap-4 sm:grid-cols-3">
                                    <div>
                                       <Label>Total marks *</Label>
                                       <Input
                                          type="number"
                                          min={1}
                                          value={data.activity_total_mark}
                                          onChange={(e) => setData('activity_total_mark', Number(e.target.value))}
                                       />
                                       <InputError message={errors.activity_total_mark} />
                                    </div>
                                    <div>
                                       <Label>Pass marks *</Label>
                                       <Input
                                          type="number"
                                          min={0}
                                          value={data.activity_pass_mark}
                                          onChange={(e) => setData('activity_pass_mark', Number(e.target.value))}
                                       />
                                       <InputError message={errors.activity_pass_mark} />
                                    </div>
                                    <div>
                                       <Label>Attempts allowed</Label>
                                       <Input
                                          type="number"
                                          min={1}
                                          value={data.activity_retake}
                                          onChange={(e) => setData('activity_retake', Number(e.target.value))}
                                       />
                                       <InputError message={errors.activity_retake} />
                                    </div>
                                 </div>
                              )}
                           </div>
                        )}

                        {data.lesson_type === 'embed' && (
                           <div>
                              <Label>
                                 Embed source
                                 <span className="text-xs text-muted-foreground">(Provide the source url only)</span>
                              </Label>
                              <Textarea
                                 required
                                 name="embed_source"
                                 placeholder={input.embed_source_placeholder}
                                 value={data.embed_source}
                                 rows={4}
                                 onChange={(e) => onHandleChange(e, setData)}
                              />
                              <InputError message={errors.embed_source} />
                           </div>
                        )}

                        {data.lesson_type === 'text' && (
                           <div>
                              <Label>{input.your_text}</Label>
                              <Editor
                                 ssr={true}
                                 output="html"
                                 placeholder={{
                                    paragraph: 'Type your content here...',
                                    imageCaption: 'Type caption for image (optional)',
                                 }}
                                 contentMinHeight={256}
                                 contentMaxHeight={640}
                                 initialContent={data.lesson_src}
                                 onContentChange={(value) =>
                                    setData((prev) => ({
                                       ...prev,
                                       lesson_src: value as string,
                                    }))
                                 }
                              />
                              <InputError message={errors.lesson_src} />
                           </div>
                        )}

                        {data.lesson_type === 'video' && data.duration && data.duration !== '00:00:00' && (
                           <div>
                              <Label htmlFor="duration">{input.duration}</Label>
                              <Input type="text" name="duration" value={data.duration} readOnly className="bg-muted" />
                              <p className="text-muted-foreground mt-1 text-xs">Detected automatically from the uploaded video.</p>
                           </div>
                        )}

                        {data.lesson_type === 'video_url' && (
                           <div>
                              <Label htmlFor="duration">{input.duration}</Label>
                              <Input
                                 required
                                 maxLength={8}
                                 type="text"
                                 name="duration"
                                 value={data.duration}
                                 placeholder="00:00:00"
                                 onChange={onDurationChange}
                              />
                              <InputError message={errors.duration} />
                           </div>
                        )}

                        <div>
                           <Label htmlFor="summary">Summary</Label>
                           <Editor
                              ssr={true}
                              output="html"
                              placeholder={{
                                 paragraph: 'Type your content here...',
                                 imageCaption: 'Type caption for image (optional)',
                              }}
                              contentMinHeight={256}
                              contentMaxHeight={640}
                              initialContent={data.summary}
                              onContentChange={(value) => setData('summary', value as string)}
                           />
                           <InputError message={errors.summary} />
                        </div>

                        <div>
                           <Label>Lesson type</Label>
                           <RadioGroup
                              required
                              defaultValue={data.is_free ? 'free' : 'paid'}
                              className="flex items-center space-x-4 pt-2 pb-1"
                              onValueChange={(value) => setData('is_free', value === 'free' ? 1 : 0)}
                           >
                              {props.prices.map((price) => (
                                 <div key={price} className="flex items-center space-x-2">
                                    <RadioGroupItem className="cursor-pointer" id={price} value={price} />
                                    <Label htmlFor={price} className="capitalize">
                                       {price}
                                    </Label>
                                 </div>
                              ))}
                           </RadioGroup>
                           <InputError message={errors.is_free} />
                        </div>
                     </TabsContent>
                     <DialogFooter className="w-full justify-between space-x-2 pt-8">
                        <div className="flex w-full items-center gap-4">
                           <DialogClose asChild>
                              <Button type="button" variant="outline">
                                 {button.close}
                              </Button>
                           </DialogClose>

                           {!lesson &&
                              (lessonType === 'type' ? (
                                 <Button type="button" onClick={() => setLessonType('form')}>
                                    {button.next}
                                 </Button>
                              ) : (
                                 <Button type="button" onClick={() => setLessonType('type')}>
                                    {button.back}
                                 </Button>
                              ))}
                        </div>

                        {(lesson || lessonType === 'form') && (
                           <LoadingButton loading={processing || isSubmit} disabled={processing || isSubmit}>
                              {isSubmit ? 'Uploading...' : button.submit}
                           </LoadingButton>
                        )}
                     </DialogFooter>
                  </Tabs>
               </form>
            </ScrollArea>
         </DialogContent>
      </Dialog>
   );
};

export default LessonForm;
