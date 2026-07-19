import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Download } from 'lucide-react';

interface Props {
   data: any;
   setData: (key: string, value: any) => void;
   errors: any;
   isSubmit: boolean;
   setIsSubmit: (value: boolean) => void;
   setIsFileSelected: (value: boolean) => void;
   setIsFileUploaded: (value: boolean) => void;
}

const FileSubmissionForm = ({ data, setData, errors, isSubmit, setIsSubmit, setIsFileSelected, setIsFileUploaded }: Props) => {
   const planUrl = data.options?.plan_file_url;
   const planName = data.options?.plan_file_name || 'Download plan';

   return (
      <div className="space-y-4">
         <div>
            <Label>Instructions</Label>
            <div className="rounded-md border border-blue-500/30 bg-blue-500/10 p-3 text-sm text-blue-900 dark:text-blue-100">
               <p className="mb-1 font-medium">How file submission questions work:</p>
               <p>• Upload the plan or worksheet students must complete</p>
               <p>• Students download your file, complete it offline, then re-upload their answer</p>
               <p>• You manually grade each submission before the attempt is marked pass/fail</p>
            </div>
         </div>

         <div className="space-y-3">
            <Label>Plan / worksheet file *</Label>
            {planUrl && (
               <div className="flex items-center gap-2 rounded-md border border-border bg-muted p-3">
                  <span className="flex-1 truncate text-sm">{planName}</span>
                  <Button type="button" variant="outline" size="sm" asChild>
                     <a href={planUrl} target="_blank" rel="noopener noreferrer">
                        <Download className="mr-1 h-4 w-4" />
                        Current file
                     </a>
                  </Button>
               </div>
            )}
            <ChunkedUploaderInput
               isSubmit={isSubmit}
               filetype="document"
               delayUpload={true}
               onFileSelected={() => setIsFileSelected(true)}
               onFileUploaded={(fileData) => {
                  setIsFileUploaded(true);
                  setData('options', {
                     ...data.options,
                     new_plan_file_url: fileData.file_url,
                     new_plan_file_name: fileData.file_name,
                  });
               }}
               onError={() => {
                  setIsSubmit(false);
                  setIsFileSelected(false);
               }}
               onCancelUpload={() => {
                  setIsSubmit(false);
                  setIsFileSelected(false);
               }}
            />
            <p className="text-xs text-muted-foreground">PDF, Excel, Word, images, or ZIP (max size per platform limits)</p>
         </div>

         <div>
            <Label>Grading rubric (optional)</Label>
            <Textarea
               placeholder="Describe how you will evaluate the submitted work..."
               rows={4}
               value={data.options?.grading_rubric || ''}
               onChange={(e) =>
                  setData('options', {
                     ...data.options,
                     grading_rubric: e.target.value,
                  })
               }
            />
         </div>

         <InputError message={errors.options} />
      </div>
   );
};

export default FileSubmissionForm;
