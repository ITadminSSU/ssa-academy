import DynamicCertificate from '@/components/dynamic-certificate';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';

const SAMPLE_DATA = {
   studentName: 'Jane A. Dela Cruz',
   courseName: 'Civil Engineering Fundamentals',
   examName: 'Professional Certification Examination',
   completionDate: 'January 15, 2026',
   certificateId: 'SSU-CERT-2026-0042',
   verificationReference: 'VER-8F3K2M9Q',
   trainingHours: '40 hours',
   instructorName: 'Engr. Michael Santos',
};

interface Props {
   template: CertificateTemplate;
   open: boolean;
   onOpenChange: (open: boolean) => void;
   logoUrl?: string | null;
}

const LiveCertificatePreviewDialog = ({ template, open, onOpenChange, logoUrl }: Props) => {
   const isExam = template.type === 'exam';
   const previewTemplate: CertificateTemplate = {
      ...template,
      logo_path: logoUrl ?? template.logo_path,
   };

   return (
      <Dialog open={open} onOpenChange={onOpenChange}>
         <DialogContent className="w-full gap-0 overflow-hidden p-0 sm:max-w-4xl">
            <ScrollArea className="max-h-[92vh]">
               <div className="p-6">
                  <DialogHeader className="mb-4">
                     <DialogTitle>Live certificate preview</DialogTitle>
                     <DialogDescription>
                        This matches the certificate learners receive when they complete a {isExam ? 'exam' : 'course'}. Sample data is
                        shown below.
                     </DialogDescription>
                  </DialogHeader>

                  <DynamicCertificate
                     template={previewTemplate}
                     studentName={SAMPLE_DATA.studentName}
                     courseName={isExam ? SAMPLE_DATA.examName : SAMPLE_DATA.courseName}
                     completionDate={SAMPLE_DATA.completionDate}
                     certificateId={SAMPLE_DATA.certificateId}
                     verificationReference={SAMPLE_DATA.verificationReference}
                     trainingHours={isExam ? null : SAMPLE_DATA.trainingHours}
                     instructorName={isExam ? null : SAMPLE_DATA.instructorName}
                  />
               </div>
            </ScrollArea>
         </DialogContent>
      </Dialog>
   );
};

export default LiveCertificatePreviewDialog;
