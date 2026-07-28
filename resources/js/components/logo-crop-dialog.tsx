import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Slider } from '@/components/ui/slider';
import { blobToFile, getCroppedImageBlob, getFullImageCropArea, getLogoExportLimits, type CropArea } from '@/lib/crop-image';
import type { LogoPlacement } from '@/lib/logo-placements';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import Cropper, { type Area } from 'react-easy-crop';

interface LogoCropDialogProps {
   open: boolean;
   imageSrc: string | null;
   fileName?: string;
   placement: LogoPlacement;
   onOpenChange: (open: boolean) => void;
   onConfirm: (file: File, previewUrl: string) => void;
}

const LogoCropDialog = ({ open, imageSrc, fileName = 'logo.png', placement, onOpenChange, onConfirm }: LogoCropDialogProps) => {
   const [crop, setCrop] = useState({ x: 0, y: 0 });
   const [zoom, setZoom] = useState(1);
   const [croppedAreaPixels, setCroppedAreaPixels] = useState<CropArea | null>(null);
   const [processing, setProcessing] = useState(false);

   const onCropComplete = useCallback((_croppedArea: Area, pixels: Area) => {
      setCroppedAreaPixels(pixels);
   }, []);

   useEffect(() => {
      if (!open) {
         setCrop({ x: 0, y: 0 });
         setZoom(1);
         setCroppedAreaPixels(null);
         return;
      }

      if (!imageSrc) {
         return;
      }

      void getFullImageCropArea(imageSrc).then(setCroppedAreaPixels);
   }, [open, imageSrc]);

   const handleConfirm = async () => {
      if (!imageSrc) {
         return;
      }

      setProcessing(true);

      try {
         const cropArea = croppedAreaPixels ?? (await getFullImageCropArea(imageSrc));
         const limits = getLogoExportLimits(placement);
         const blob = await getCroppedImageBlob(imageSrc, cropArea, limits);
         const file = blobToFile(blob, fileName);
         const previewUrl = URL.createObjectURL(file);
         onConfirm(file, previewUrl);
         onOpenChange(false);
      } catch {
         toast.error('Could not process that image. Try a smaller PNG, JPG, or WebP file.');
      } finally {
         setProcessing(false);
      }
   };

   return (
      <Dialog open={open} onOpenChange={onOpenChange}>
         <DialogContent className="max-w-2xl">
            <DialogHeader>
               <DialogTitle>Crop &amp; adjust logo</DialogTitle>
               <DialogDescription>Drag to reposition, then use zoom to fit the logo before saving.</DialogDescription>
            </DialogHeader>

            <div className="relative h-72 overflow-hidden rounded-lg bg-muted">
               {imageSrc ? (
                  <Cropper
                     image={imageSrc}
                     crop={crop}
                     zoom={zoom}
                     aspect={undefined}
                     onCropChange={setCrop}
                     onZoomChange={setZoom}
                     onCropComplete={onCropComplete}
                     objectFit="contain"
                  />
               ) : null}
            </div>

            <div className="space-y-2">
               <p className="text-muted-foreground text-sm">Zoom</p>
               <Slider min={1} max={3} step={0.05} value={[zoom]} onValueChange={(values) => setZoom(values[0])} />
            </div>

            <DialogFooter>
               <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                  Cancel
               </Button>
               <Button type="button" onClick={handleConfirm} disabled={!imageSrc || processing}>
                  {processing ? 'Processing…' : 'Use cropped image'}
               </Button>
            </DialogFooter>
         </DialogContent>
      </Dialog>
   );
};

export default LogoCropDialog;
