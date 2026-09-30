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

const GOLD = '#E89A1F';
const CREAM = '#F7F3EA';
const NAVY = '#1A2742';
const WARM = '#5A564E';
const WIDTH = 1280;
const HEIGHT = 720;
const BORDER = 11;
const SANS = '"Source Sans 3", sans-serif';
const SERIF = '"Playfair Display", Palatino, "Palatino Linotype", serif';
const LOCKUP_SRC = '/assets/branding/ssa-certificate-lockup.png';

const LABELS = {
   certificateId: 'Certificate ID',
   completionDate: 'Completion Date',
   trainingHours: 'Training Hours',
   instructor: 'Instructor',
   verificationCode: 'Verification Code',
};

const setLetterSpacing = (ctx: CanvasRenderingContext2D, value: string) => {
   (ctx as CanvasRenderingContext2D & { letterSpacing: string }).letterSpacing = value;
};

const wrapCanvasLines = (ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string[] => {
   const words = text.trim().split(/\s+/).filter(Boolean);

   if (words.length === 0) {
      return [''];
   }

   const lines: string[] = [];
   let current = words[0];

   for (let index = 1; index < words.length; index++) {
      const next = `${current} ${words[index]}`;

      if (ctx.measureText(next).width <= maxWidth) {
         current = next;
      } else {
         lines.push(current);
         current = words[index];
      }
   }

   lines.push(current);

   return lines;
};

const fitWrappedText = (
   ctx: CanvasRenderingContext2D,
   text: string,
   maxWidth: number,
   startSize: number,
   minSize: number,
   maxLines: number,
   font: string,
): { lines: string[]; fontSize: number; lineHeight: number } => {
   let fontSize = startSize;

   while (fontSize >= minSize) {
      ctx.font = font.replace('SIZE', String(fontSize));
      const lineHeight = Math.round(fontSize * 1.25);
      const lines = wrapCanvasLines(ctx, text, maxWidth);
      const fits = lines.length > 0 && lines.length <= maxLines && lines.every((line) => ctx.measureText(line).width <= maxWidth);

      if (fits) {
         return { lines, fontSize, lineHeight };
      }

      fontSize -= 1;
   }

   ctx.font = font.replace('SIZE', String(minSize));

   return {
      lines: wrapCanvasLines(ctx, text, maxWidth).slice(0, maxLines),
      fontSize: minSize,
      lineHeight: Math.round(minSize * 1.25),
   };
};

const formatTrainingHours = (value?: string | null): string | null => {
   const text = (value || '').trim();

   if (!text) {
      return null;
   }

   if (/hour/i.test(text)) {
      return text.toUpperCase();
   }

   if (/^\d+(\.\d+)?$/.test(text)) {
      return `${text} HOURS`;
   }

   return text.toUpperCase();
};

const ensureCertificateFonts = async () => {
   if (typeof document === 'undefined' || !document.fonts) {
      return;
   }

   if (!document.getElementById('certificate-playfair')) {
      const link = document.createElement('link');
      link.id = 'certificate-playfair';
      link.rel = 'stylesheet';
      link.href = 'https://fonts.bunny.net/css?family=playfair-display:400,500,600,400i,500i,600i';
      document.head.appendChild(link);
   }

   try {
      await Promise.all([
         document.fonts.load(`italic 500 72px ${SERIF}`),
         document.fonts.load(`600 36px ${SANS}`),
         document.fonts.load(`400 30px ${SANS}`),
         document.fonts.load(`500 20px ${SANS}`),
      ]);
   } catch {
      // Generic serif and sans are used if the webfonts do not load.
   }
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
   const fallbackLogo = useMemo(() => resolveLogo(template.logo_path, 'certificate'), [template.logo_path]);

   const loadImage = (src: string): Promise<HTMLImageElement> => {
      return new Promise((resolve, reject) => {
         const img = new Image();
         img.crossOrigin = 'anonymous';
         img.onload = () => resolve(img);
         img.onerror = reject;
         img.src = src;
      });
   };

   const drawMetaLine = (ctx: CanvasRenderingContext2D, label: string, value: string | null | undefined, x: number, y: number, align: 'left' | 'right') => {
      const text = `${label} / ${value || '—'}`.toUpperCase();
      ctx.font = `500 20px ${SANS}`;
      ctx.fillStyle = NAVY;
      ctx.textBaseline = 'alphabetic';
      ctx.textAlign = align;
      setLetterSpacing(ctx, '1.8px');
      ctx.fillText(text, x, y);
      setLetterSpacing(ctx, '0px');
   };

   const drawCorner = (ctx: CanvasRenderingContext2D) => {
      const arm = 52;
      const cornerX = WIDTH - 37;
      const cornerY = HEIGHT - 38;

      ctx.strokeStyle = GOLD;
      ctx.lineWidth = 5;
      ctx.lineCap = 'square';
      ctx.lineJoin = 'miter';
      ctx.beginPath();
      ctx.moveTo(cornerX - arm, cornerY);
      ctx.lineTo(cornerX, cornerY);
      ctx.lineTo(cornerX, cornerY - arm);
      ctx.stroke();
   };

   const drawCertificate = (ctx: CanvasRenderingContext2D, logoImage: HTMLImageElement | null) => {
      ctx.fillStyle = GOLD;
      ctx.fillRect(0, 0, WIDTH, HEIGHT);
      ctx.fillStyle = CREAM;
      ctx.fillRect(BORDER, BORDER, WIDTH - BORDER * 2, HEIGHT - BORDER * 2);

      if (logoImage) {
         const targetHeight = 82;
         const aspect = logoImage.width / logoImage.height;
         const drawHeight = targetHeight;
         const drawWidth = drawHeight * aspect;
         ctx.drawImage(logoImage, (WIDTH - drawWidth) / 2, 88, drawWidth, drawHeight);
      }

      ctx.textAlign = 'center';
      ctx.textBaseline = 'alphabetic';
      setLetterSpacing(ctx, '0.55em');
      ctx.font = `500 15px ${SANS}`;
      ctx.fillStyle = WARM;
      ctx.fillText('CREDENTIAL', WIDTH / 2, 230);
      setLetterSpacing(ctx, '0px');

      const recipient = (studentName || '').trim();
      const nameFit = fitWrappedText(ctx, recipient, WIDTH - 200, 72, 28, 1, `italic 500 SIZEpx ${SERIF}`);
      ctx.font = `italic 500 ${nameFit.fontSize}px ${SERIF}`;
      ctx.fillStyle = NAVY;
      const nameStart = 356 - ((nameFit.lines.length - 1) * nameFit.lineHeight) / 2;
      nameFit.lines.forEach((line, index) => {
         ctx.fillText(line, WIDTH / 2, nameStart + index * nameFit.lineHeight);
      });

      ctx.font = `400 30px ${SANS}`;
      ctx.fillStyle = WARM;
      ctx.fillText('has completed', WIDTH / 2, 430);

      const courseFit = fitWrappedText(ctx, courseName || '', WIDTH - 180, 36, 20, 2, `600 SIZEpx ${SANS}`);
      ctx.font = `600 ${courseFit.fontSize}px ${SANS}`;
      ctx.fillStyle = NAVY;
      const courseStart = 502 - ((courseFit.lines.length - 1) * courseFit.lineHeight) / 2;
      courseFit.lines.forEach((line, index) => {
         ctx.fillText(line, WIDTH / 2, courseStart + index * courseFit.lineHeight);
      });

      const hours = formatTrainingHours(trainingHours);
      const dateLabel = (completionDate || '').toUpperCase();
      drawMetaLine(ctx, LABELS.certificateId, certificateId, 92, 580, 'left');
      drawMetaLine(ctx, LABELS.completionDate, dateLabel, 92, 616, 'left');

      if (hours) {
         drawMetaLine(ctx, LABELS.trainingHours, hours, 92, 650, 'left');
      }

      const rightX = WIDTH - 102;

      if (instructorName?.trim()) {
         drawMetaLine(ctx, LABELS.instructor, instructorName, rightX, 580, 'right');
         drawMetaLine(ctx, LABELS.verificationCode, verificationReference, rightX, 616, 'right');
      } else {
         drawMetaLine(ctx, LABELS.verificationCode, verificationReference, rightX, 580, 'right');
      }

      drawCorner(ctx);
   };

   const loadLogo = async (): Promise<HTMLImageElement | null> => {
      try {
         return await loadImage(LOCKUP_SRC);
      } catch {
         try {
            return fallbackLogo ? await loadImage(fallbackLogo) : null;
         } catch {
            return null;
         }
      }
   };

   useEffect(() => {
      let cancelled = false;

      const render = async () => {
         const canvas = previewRef.current;
         if (!canvas) return;
         const ctx = canvas.getContext('2d');
         if (!ctx) return;

         await ensureCertificateFonts();
         const logoImage = await loadLogo();

         if (cancelled) return;
         drawCertificate(ctx, logoImage);
      };

      render();

      return () => {
         cancelled = true;
      };
   }, [fallbackLogo, courseName, studentName, completionDate, verificationReference, certificateId, trainingHours, instructorName]);

   const getRenderedCanvas = async (): Promise<HTMLCanvasElement> => {
      const canvas = document.createElement('canvas');
      canvas.width = WIDTH;
      canvas.height = HEIGHT;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Canvas not supported');

      await ensureCertificateFonts();
      drawCertificate(ctx, await loadLogo());
      return canvas;
   };

   const handleDownloadCertificate = async () => {
      if (downloadFormat === 'pdf') {
         await downloadAsPDF();
      } else {
         await downloadAsPNG();
      }
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
         <canvas ref={previewRef} width={WIDTH} height={HEIGHT} className="h-auto w-full rounded-lg shadow-lg" />

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
