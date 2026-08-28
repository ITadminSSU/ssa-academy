import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { CheckCircle2, Upload } from 'lucide-react';
import { useState } from 'react';

type UploadedFile = { file_url: string; file_name: string };

const UsExperienceSubmitDialog = ({ plan }: { plan: UsExperienceStudentPlan }) => {
   const [open, setOpen] = useState(false);
   const [pdf, setPdf] = useState<UploadedFile | null>(null);
   const [excel, setExcel] = useState<UploadedFile | null>(null);
   const [submitting, setSubmitting] = useState(false);

   const submit = () => {
      if (!pdf?.file_url || !excel?.file_url) return;
      setSubmitting(true);
      router.post(
         route('us-experience.submit', plan.id),
         {
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
               setOpen(false);
            },
            onFinish: () => setSubmitting(false),
         },
      );
   };

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>
            <Button size="sm">
               <Upload className="h-4 w-4" />
               Submit
            </Button>
         </DialogTrigger>
         <DialogContent className="sm:max-w-lg">
            <DialogHeader>
               <DialogTitle>Submit {plan.title}</DialogTitle>
            </DialogHeader>
            <div className="space-y-5">
               <p className="text-muted-foreground text-sm">
                  Upload your marked-up takeoff PDF and the filled Excel BOQ (Estimator Notes / Quantity Summary). Grading uses the Excel;
                  quantities within ±2 of the key pass unless the trainer set a tighter band.
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
            </div>
            <DialogFooter>
               <LoadingButton type="button" loading={submitting} disabled={!pdf?.file_url || !excel?.file_url} onClick={submit}>
                  Submit for auto-grade
               </LoadingButton>
            </DialogFooter>
         </DialogContent>
      </Dialog>
   );
};

export default UsExperienceSubmitDialog;
