import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CoursePlayerProps } from '@/types/page';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
   lesson: SectionLesson;
   isLessonComplete: boolean;
}

const PracticalActivityPanel = ({ lesson, isLessonComplete }: Props) => {
   const { watchHistory, subscriptionAccess } = usePage<CoursePlayerProps>().props;
   const canMarkProgress = subscriptionAccess?.can_mark_progress ?? true;
   const submissions = lesson.activity_submissions ?? [];
   const latestSubmission = submissions[0] ?? null;
   const maxRetakes = lesson.activity_retake ?? 1;
   const remainingAttempts = maxRetakes - submissions.length;
   const isPending = latestSubmission?.status === 'pending';
   const isApproved =
      isLessonComplete ||
      latestSubmission?.status === 'passed' ||
      latestSubmission?.status === 'approved' ||
      (latestSubmission?.status === 'graded' &&
         latestSubmission.marks_obtained !== null &&
         Number(latestSubmission.marks_obtained) >= Number(lesson.activity_pass_mark ?? 0));

   const [isSubmit, setIsSubmit] = useState(false);
   const [isFileUploaded, setIsFileUploaded] = useState(false);
   const [hasSelectedFile, setHasSelectedFile] = useState(false);

   const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
      section_lesson_id: lesson.id,
      attachment_type: 'file',
      attachment_path: '',
      comment: '',
   });

   const nextLessonHref = watchHistory.next_watching_id
      ? route('course.player', {
           type: watchHistory.next_watching_type,
           watch_history: watchHistory.id,
           lesson_id: watchHistory.next_watching_id,
        })
      : null;

   const submitForm = () => {
      clearErrors();
      post(route('lesson-activity.submission.store'), {
         preserveScroll: true,
         preserveState: true,
         only: ['watching', 'watchHistory', 'courseGates'],
         onSuccess: () => {
            reset();
            setIsSubmit(false);
            setHasSelectedFile(false);
         },
         onError: () => {
            setIsSubmit(false);
         },
      });
   };

   useEffect(() => {
      if (data.attachment_path && isFileUploaded) {
         submitForm();
         setIsFileUploaded(false);
      }
   }, [data.attachment_path, isFileUploaded]);

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      if (data.attachment_path) {
         submitForm();
         return;
      }

      if (hasSelectedFile) {
         setIsSubmit(true);
      }
   };

   const canSubmit = Boolean(data.attachment_path || hasSelectedFile);

   return (
      <div className="bg-muted/30 space-y-4 border-t p-4 md:p-6">
         <div>
            <h3 className="text-sm font-semibold">Submit your completed activity</h3>
            <p className="text-muted-foreground mt-1 text-sm">
               Download the activity PDF above, complete it, then upload your finished file. Your trainer will review it before you can continue.
            </p>
         </div>

         {isApproved ? (
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
               <div className="flex items-center gap-2 text-sm font-medium text-green-600">
                  <CheckCircle2 className="h-5 w-5" />
                  <span>Activity approved — you can continue</span>
               </div>
               {nextLessonHref ? (
                  <Button asChild>
                     <Link href={nextLessonHref}>Continue to next lesson</Link>
                  </Button>
               ) : (
                  <p className="text-muted-foreground text-sm">You have reached the last item in this module.</p>
               )}
            </div>
         ) : isPending ? (
            <Alert>
               <Clock className="h-4 w-4" />
               <AlertTitle>Awaiting trainer review</AlertTitle>
               <AlertDescription>
                  Your submission is pending. The next module will unlock once your trainer approves or grades your work.
               </AlertDescription>
            </Alert>
         ) : !canMarkProgress ? (
            <Alert>
               <AlertTitle>Read-only access</AlertTitle>
               <AlertDescription>
                  New activity submissions are locked while your subscription is inactive. Resubscribe to continue.
               </AlertDescription>
            </Alert>
         ) : remainingAttempts > 0 ? (
            <form onSubmit={handleSubmit} className="space-y-4">
               <div className="space-y-2">
                  <Label htmlFor="activity-file">Upload completed file *</Label>
                  <ChunkedUploaderInput
                     isSubmit={isSubmit}
                     courseId={lesson.course_id}
                     filetype="document"
                     delayUpload={true}
                     onFileSelected={() => setHasSelectedFile(true)}
                     onFileUploaded={(fileData) => {
                        setIsFileUploaded(true);
                        setData('attachment_path', fileData.file_url);
                     }}
                     onError={() => {
                        setIsSubmit(false);
                     }}
                     onCancelUpload={() => {
                        setIsSubmit(false);
                        setHasSelectedFile(false);
                     }}
                  />
                  <InputError message={errors.attachment_path} />
                  <p className="text-muted-foreground text-xs">Formats: PDF, DOC, DOCX, ZIP, JPG, PNG (max 10MB)</p>
               </div>

               <div className="space-y-2">
                  <Label htmlFor="activity-comment">Notes (optional)</Label>
                  <Textarea
                     id="activity-comment"
                     placeholder="Add any notes for your trainer..."
                     value={data.comment}
                     onChange={(e) => setData('comment', e.target.value)}
                     rows={3}
                  />
                  <InputError message={errors.comment} />
               </div>

               <div className="flex items-center justify-between">
                  <p className="text-muted-foreground text-sm">
                     Attempts: {submissions.length} / {maxRetakes}
                  </p>
                  <LoadingButton type="submit" loading={processing || isSubmit} disabled={!canSubmit || processing || isSubmit} className="gap-2">
                     <Upload className="h-4 w-4" />
                     Submit activity
                  </LoadingButton>
               </div>
            </form>
         ) : (
            <Alert variant="destructive">
               <AlertTitle>No attempts remaining</AlertTitle>
               <AlertDescription>Contact your trainer if you need another attempt.</AlertDescription>
            </Alert>
         )}

         {latestSubmission && !isApproved && latestSubmission.instructor_feedback && (
            <Alert>
               <AlertTitle>Trainer feedback</AlertTitle>
               <AlertDescription>{latestSubmission.instructor_feedback}</AlertDescription>
            </Alert>
         )}
      </div>
   );
};

export default PracticalActivityPanel;
