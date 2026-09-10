import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { CheckCircle2 } from 'lucide-react';

type UploadedFile = { file_url: string; file_name: string };

interface Props {
   pdf: UploadedFile | null;
   answerKey: UploadedFile | null;
   onPdfChange: (file: UploadedFile | null) => void;
   onAnswerKeyChange: (file: UploadedFile | null) => void;
   pdfError?: string;
   answerKeyError?: string;
}

const QuizTakeoffFields = ({ pdf, answerKey, onPdfChange, onAnswerKeyChange, pdfError, answerKeyError }: Props) => {
   return (
      <div className="space-y-4">
         <p className="text-muted-foreground text-sm">
            Upload the PDF plans and the Excel answer key (Estimator Notes / Quantity Summary). Students download a blank copy of
            that Excel with quantities cleared, then submit their takeoff PDF and filled BOQ. Auto-check uses the same ±2%
            line tolerance as Build Your US Experience. This quiz can only contain this one question.
         </p>

         <div className="space-y-2">
            <Label>Takeoff PDF</Label>
            <ChunkedUploaderInput
               isSubmit={false}
               filetype="document"
               delayUpload={false}
               onFileSelected={() => onPdfChange(null)}
               onFileUploaded={(fileData) => {
                  if (!fileData?.file_url) {
                     onPdfChange(null);
                     return;
                  }
                  onPdfChange({ file_url: fileData.file_url, file_name: fileData.file_name });
               }}
               onError={() => onPdfChange(null)}
               onCancelUpload={() => onPdfChange(null)}
            />
            {pdf && (
               <p className="flex items-center gap-2 text-sm text-green-600">
                  <CheckCircle2 className="h-4 w-4" />
                  {pdf.file_name}
               </p>
            )}
            <InputError message={pdfError} />
         </div>

         <div className="space-y-2">
            <Label>Excel answer key (.xlsx)</Label>
            <ChunkedUploaderInput
               isSubmit={false}
               filetype="document"
               delayUpload={false}
               onFileSelected={() => onAnswerKeyChange(null)}
               onFileUploaded={(fileData) => {
                  if (!fileData?.file_url) {
                     onAnswerKeyChange(null);
                     return;
                  }
                  onAnswerKeyChange({ file_url: fileData.file_url, file_name: fileData.file_name });
               }}
               onError={() => onAnswerKeyChange(null)}
               onCancelUpload={() => onAnswerKeyChange(null)}
            />
            {answerKey && (
               <p className="flex items-center gap-2 text-sm text-green-600">
                  <CheckCircle2 className="h-4 w-4" />
                  {answerKey.file_name}
               </p>
            )}
            <InputError message={answerKeyError} />
         </div>
      </div>
   );
};

export default QuizTakeoffFields;
export type { UploadedFile };
