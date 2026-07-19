import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle2, Download } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
   question: ExamQuestion;
   answer: any;
   onAnswerChange: (answer: any) => void;
}

const FileSubmissionQuestion = ({ question, answer, onAnswerChange }: Props) => {
   const [isSubmit, setIsSubmit] = useState(false);
   const [isFileUploaded, setIsFileUploaded] = useState(false);
   const [comment, setComment] = useState(answer?.comment || '');

   const planUrl = question.options?.plan_file_url;
   const planName = question.options?.plan_file_name || 'Download plan';
   const submissionUrl = answer?.submission_file_url;
   const submissionName = answer?.submission_file_name;

   useEffect(() => {
      if (isFileUploaded && answer?.submission_file_url) {
         setIsSubmit(false);
         setIsFileUploaded(false);
      }
   }, [isFileUploaded, answer?.submission_file_url]);

   const publishAnswer = (fileData: { file_url: string; file_name: string }) => {
      onAnswerChange({
         question_id: question.id,
         submission_file_url: fileData.file_url,
         submission_file_name: fileData.file_name,
         comment,
      });
   };

   return (
      <div className="space-y-5">
         <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3">
            <p className="text-sm text-amber-900 dark:text-amber-100">
               <span className="font-semibold">Manual grading:</span> Download the plan, complete your work, upload your answered file,
               then submit the exam. Your trainer will review it before your certificate is issued.
            </p>
         </div>

         {planUrl && (
            <div className="rounded-lg border border-border bg-muted p-4">
               <p className="mb-2 text-sm font-semibold">Step 1 — Download the plan</p>
               <Button variant="outline" asChild>
                  <a href={planUrl} target="_blank" rel="noopener noreferrer">
                     <Download className="mr-2 h-4 w-4" />
                     {planName}
                  </a>
               </Button>
            </div>
         )}

         <div className="rounded-lg border border-border p-4">
            <p className="mb-3 text-sm font-semibold">Step 2 — Upload your completed file</p>

            {submissionUrl ? (
               <div className="mb-4 flex items-center gap-2 rounded-md border border-green-500/40 bg-green-500/10 p-3 text-sm">
                  <CheckCircle2 className="h-5 w-5 shrink-0 text-green-600" />
                  <div className="min-w-0 flex-1">
                     <p className="font-medium text-green-800 dark:text-green-200">File uploaded</p>
                     <a href={submissionUrl} target="_blank" rel="noopener noreferrer" className="truncate text-green-700 underline dark:text-green-300">
                        {submissionName || 'View submission'}
                     </a>
                  </div>
               </div>
            ) : null}

            <ChunkedUploaderInput
               isSubmit={isSubmit}
               filetype="document"
               delayUpload={true}
               onFileSelected={() => setIsSubmit(true)}
               onFileUploaded={(fileData) => {
                  setIsFileUploaded(true);
                  publishAnswer(fileData);
               }}
               onError={() => setIsSubmit(false)}
               onCancelUpload={() => setIsSubmit(false)}
            />
         </div>

         <div>
            <Label htmlFor={`comment-${question.id}`}>Notes for trainer (optional)</Label>
            <Textarea
               id={`comment-${question.id}`}
               rows={3}
               className="mt-2"
               placeholder="Any comments about your submission..."
               value={comment}
               onChange={(e) => {
                  const nextComment = e.target.value;
                  setComment(nextComment);
                  if (submissionUrl) {
                     onAnswerChange({
                        question_id: question.id,
                        submission_file_url: submissionUrl,
                        submission_file_name: submissionName,
                        comment: nextComment,
                     });
                  }
               }}
            />
         </div>
      </div>
   );
};

export default FileSubmissionQuestion;
