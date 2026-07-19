import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { resolveLogo } from '@/lib/branding';
import jsPDF from 'jspdf';
import { Download, FileImage, FileText } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

interface DynamicCertificateProps {
   template: CertificateTemplate;
   courseName: string;
   studentName: string;
   completionDate: string;
   verificationReference?: string | null;
   certificateId?: string | null;
   trainingHours?: string | null;
   instructorName?: string | null;
}

const BLUE = '#2D537C';
const RED = '#E94448';
const DARK = '#1A1B1B';
const FONT = 'Helvetica, Arial, sans-serif';
const WIDTH = 1200;
const HEIGHT = 800;

const FIXED_TEXT = {
   course: {
      title: 'CERTIFICATE OF COMPLETION',
      subtitle: 'Awarded for successfully completing the professional certification program.',
      connective: 'has successfully completed the requirements for',
   },
   exam: {
      title: 'CERTIFICATE OF EXAMINATION',
      subtitle: 'This certificate is proudly presented to',
      connective: 'for outstanding performance in the examination',
   },
   footerName: 'SMART SOURCING UNIVERSITY',
   footerTagline: 'Building Future Construction Professionals',
   labels: {
      certificateId: 'Certificate ID:',
      dateIssued: 'Date Issued:',
      trainingHours: 'Training Hours:',
      instructor: 'Instructor:',
      verificationCode: 'Verification Code:',
   },
};

const DynamicCertificate = ({
   template,
   courseName,
   studentName,
   completionDate,
   verificationReference,
   certificateId,
   trainingHours,
   instructorName,
}: DynamicCertificateProps) => {
   const [downloadFormat, setDownloadFormat] = useState('png');
   const previewRef = useRef<HTMLCanvasElement>(null);
   const logoUrl = useMemo(() => resolveLogo(template.logo_path, 'dark'), [template.logo_path]);

   const loadImage = (src: string): Promise<HTMLImageElement> => {
      return new Promise((resolve, reject) => {
         const img = new Image();
         img.crossOrigin = 'anonymous';
         img.onload = () => resolve(img);
         img.onerror = reject;
         img.src = src;
      });
   };

   // Draw a "Label: value" line. For left align, label starts at x. For right align,
   // the whole line ends at x (label printed, then value measured after).
   const drawMetaLine = (
      ctx: CanvasRenderingContext2D,
      label: string,
      value: string | null | undefined,
      x: number,
      y: number,
      align: 'left' | 'right',
   ) => {
      ctx.font = `500 18px ${FONT}`;
      ctx.textBaseline = 'alphabetic';

      const labelWidth = ctx.measureText(label).width;
      const gap = 6;
      const valueText = value || '—';
      const valueWidth = ctx.measureText(valueText).width;
      const totalWidth = labelWidth + gap + valueWidth;

      const labelX = align === 'left' ? x : x - totalWidth;
      const valueX = labelX + labelWidth + gap;

      ctx.textAlign = 'left';
      ctx.fillStyle = BLUE;
      ctx.fillText(label, labelX, y);

      ctx.fillStyle = DARK;
      ctx.fillText(valueText, valueX, y);
   };

   const drawLCorner = (ctx: CanvasRenderingContext2D, x: number, y: number, dx: number, dy: number) => {
      const arm = 70;
      ctx.strokeStyle = BLUE;
      ctx.lineWidth = 6;
      ctx.lineCap = 'square';
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.lineTo(x + dx * arm, y);
      ctx.moveTo(x, y);
      ctx.lineTo(x, y + dy * arm);
      ctx.stroke();
   };

   const drawCertificate = (ctx: CanvasRenderingContext2D, logoImage: HTMLImageElement | null) => {
      const isExam = template.type === 'exam';
      const copy = isExam ? FIXED_TEXT.exam : FIXED_TEXT.course;

      // 1. White background
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, WIDTH, HEIGHT);

      // 2. Faint watermark circle (no name underline line)
      const circleCy = 400;
      ctx.beginPath();
      ctx.arc(WIDTH / 2, circleCy, 150, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(45, 83, 124, 0.05)';
      ctx.fill();
      ctx.lineWidth = 2;
      ctx.strokeStyle = 'rgba(45, 83, 124, 0.12)';
      ctx.stroke();

      // 3. L-shaped corner accents
      drawLCorner(ctx, 30, 30, 1, 1); // top-left
      drawLCorner(ctx, WIDTH - 30, 30, -1, 1); // top-right
      drawLCorner(ctx, 30, HEIGHT - 30, 1, -1); // bottom-left
      drawLCorner(ctx, WIDTH - 30, HEIGHT - 30, -1, -1); // bottom-right

      // 4. Logo top-center (enlarged)
      if (logoImage) {
         const logoMaxHeight = 130;
         const logoMaxWidth = 480;
         const aspect = logoImage.width / logoImage.height;
         let drawWidth = Math.min(logoMaxWidth, logoMaxHeight * aspect);
         let drawHeight = drawWidth / aspect;

         if (drawHeight > logoMaxHeight) {
            drawHeight = logoMaxHeight;
            drawWidth = drawHeight * aspect;
         }

         const logoX = (WIDTH - drawWidth) / 2;
         ctx.drawImage(logoImage, logoX, 40, drawWidth, drawHeight);
      }

      // 5. Text
      ctx.textAlign = 'center';
      ctx.textBaseline = 'alphabetic';

      // Title
      ctx.font = `bold 48px ${FONT}`;
      ctx.fillStyle = BLUE;
      ctx.fillText(copy.title, WIDTH / 2, 220);

      // Subtitle / presentation line
      ctx.font = `20px ${FONT}`;
      ctx.fillStyle = BLUE;
      ctx.fillText(copy.subtitle, WIDTH / 2, 258);

      // Recipient name
      ctx.font = `bold 44px ${FONT}`;
      ctx.fillStyle = BLUE;
      ctx.fillText((studentName || '').toUpperCase(), WIDTH / 2, 408);

      // Connective text
      ctx.font = `20px ${FONT}`;
      ctx.fillStyle = BLUE;
      ctx.fillText(copy.connective, WIDTH / 2, 450);

      // Course / exam name
      ctx.font = `bold 30px ${FONT}`;
      ctx.fillStyle = RED;
      ctx.fillText((courseName || '').toUpperCase(), WIDTH / 2, 495);

      // Metadata — exam certificates use a simplified layout (no training hours or instructor)
      if (isExam) {
         drawMetaLine(ctx, FIXED_TEXT.labels.certificateId, certificateId, 90, 620, 'left');
         drawMetaLine(ctx, FIXED_TEXT.labels.dateIssued, completionDate, 90, 658, 'left');
         drawMetaLine(ctx, FIXED_TEXT.labels.verificationCode, verificationReference, WIDTH - 90, 620, 'right');
      } else {
         drawMetaLine(ctx, FIXED_TEXT.labels.certificateId, certificateId, 90, 620, 'left');
         drawMetaLine(ctx, FIXED_TEXT.labels.dateIssued, completionDate, 90, 658, 'left');
         drawMetaLine(ctx, FIXED_TEXT.labels.trainingHours, trainingHours, 90, 696, 'left');
         drawMetaLine(ctx, FIXED_TEXT.labels.instructor, instructorName, WIDTH - 90, 620, 'right');
         drawMetaLine(ctx, FIXED_TEXT.labels.verificationCode, verificationReference, WIDTH - 90, 658, 'right');
      }

      // Footer
      ctx.textAlign = 'center';
      ctx.font = `bold 16px ${FONT}`;
      ctx.fillStyle = BLUE;
      ctx.fillText(FIXED_TEXT.footerName, WIDTH / 2, 755);

      ctx.font = `13px ${FONT}`;
      ctx.fillStyle = BLUE;
      ctx.fillText(FIXED_TEXT.footerTagline, WIDTH / 2, 778);
   };

   // Render the on-screen preview canvas whenever inputs change.
   useEffect(() => {
      let cancelled = false;

      const render = async () => {
         const canvas = previewRef.current;
         if (!canvas) return;
         const ctx = canvas.getContext('2d');
         if (!ctx) return;

         let logoImage: HTMLImageElement | null = null;
         try {
            if (logoUrl) logoImage = await loadImage(logoUrl);
         } catch {
            // leave logo null
         }

         if (cancelled) return;
         drawCertificate(ctx, logoImage);
      };

      render();

      return () => {
         cancelled = true;
      };
   }, [logoUrl, template.type, courseName, studentName, completionDate, verificationReference, certificateId, trainingHours, instructorName]);

   const handleDownloadCertificate = async () => {
      if (downloadFormat === 'pdf') {
         await downloadAsPDF();
      } else {
         await downloadAsPNG();
      }
   };

   const getRenderedCanvas = async (): Promise<HTMLCanvasElement> => {
      const canvas = document.createElement('canvas');
      canvas.width = WIDTH;
      canvas.height = HEIGHT;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Canvas not supported');

      let logoImage: HTMLImageElement | null = null;
      try {
         if (logoUrl) logoImage = await loadImage(logoUrl);
      } catch {
         // leave logo null
      }

      drawCertificate(ctx, logoImage);
      return canvas;
   };

   const downloadAsPNG = async () => {
      const canvas = await getRenderedCanvas();

      canvas.toBlob((blob) => {
         if (!blob) return;

         const url = URL.createObjectURL(blob);
         const a = document.createElement('a');
         a.href = url;
         a.download = `${studentName}_${courseName}_Certificate.png`;
         document.body.appendChild(a);
         a.click();
         document.body.removeChild(a);
         URL.revokeObjectURL(url);

         toast.success('Certificate saved as PNG!');
      }, 'image/png');
   };

   const downloadAsPDF = async () => {
      const canvas = await getRenderedCanvas();

      const pdf = new jsPDF({
         orientation: 'landscape',
         unit: 'px',
         format: [WIDTH, HEIGHT],
      });

      const imgData = canvas.toDataURL('image/png');
      pdf.addImage(imgData, 'PNG', 0, 0, WIDTH, HEIGHT);
      pdf.save(`${studentName}_${courseName}_Certificate.pdf`);

      toast.success('Certificate saved as PDF!');
   };

   return (
      <Card className="mx-auto max-w-[900px] space-y-7 p-6">
         <canvas
            ref={previewRef}
            width={WIDTH}
            height={HEIGHT}
            className="h-auto w-full rounded-lg shadow-lg"
         />

         <div className="space-y-4">
            <RadioGroup value={downloadFormat} onValueChange={setDownloadFormat} className="flex justify-center space-x-6">
               <div className="flex items-center space-x-2">
                  <RadioGroupItem className="cursor-pointer" value="png" id="png" />
                  <Label htmlFor="png" className="flex cursor-pointer items-center gap-2">
                     <FileImage className="h-4 w-4" />
                     PNG Image
                  </Label>
               </div>
               <div className="flex items-center space-x-2">
                  <RadioGroupItem className="cursor-pointer" value="pdf" id="pdf" />
                  <Label htmlFor="pdf" className="flex cursor-pointer items-center gap-2">
                     <FileText className="h-4 w-4" />
                     PDF Document
                  </Label>
               </div>
            </RadioGroup>

            <Button variant="outline" className="w-full" onClick={handleDownloadCertificate}>
               <Download className="mr-2 h-4 w-4" />
               Download as {downloadFormat.toUpperCase()}
            </Button>
         </div>
      </Card>
   );
};

export default DynamicCertificate;
