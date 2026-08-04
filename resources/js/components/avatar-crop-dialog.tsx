import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Slider } from '@/components/ui/slider';
import { getCroppedImageFile } from '@/lib/crop-image';
import { useCallback, useState } from 'react';
import Cropper, { type Area } from 'react-easy-crop';
import { toast } from 'sonner';

type AvatarCropDialogProps = {
   open: boolean;
   imageSrc: string | null;
   onCancel: () => void;
   onApply: (file: File) => void;
   title?: string;
   description?: string;
   zoomLabel?: string;
   applyLabel?: string;
   cancelLabel?: string;
   cropFailedMessage?: string;
};

const AvatarCropDialog = ({
   open,
   imageSrc,
   onCancel,
   onApply,
   title = 'Crop profile photo',
   description = 'Drag to reposition and use the slider to zoom. Your photo will be saved as a 512×512 image when you click Update.',
   zoomLabel = 'Zoom',
   applyLabel = 'Apply',
   cancelLabel = 'Cancel',
   cropFailedMessage = 'Failed to crop image. Please try again.',
}: AvatarCropDialogProps) => {
   const [crop, setCrop] = useState({ x: 0, y: 0 });
   const [zoom, setZoom] = useState(1);
   const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null);
   const [isApplying, setIsApplying] = useState(false);

   const onCropComplete = useCallback((_croppedArea: Area, croppedPixels: Area) => {
      setCroppedAreaPixels(croppedPixels);
   }, []);

   const handleOpenChange = (nextOpen: boolean) => {
      if (!nextOpen && !isApplying) {
         onCancel();
      }
   };

   const handleApply = async () => {
      if (!imageSrc || !croppedAreaPixels) {
         return;
      }

      setIsApplying(true);

      try {
         const file = await getCroppedImageFile(imageSrc, croppedAreaPixels);
         onApply(file);
         setCrop({ x: 0, y: 0 });
         setZoom(1);
         setCroppedAreaPixels(null);
      } catch {
         toast.error(cropFailedMessage);
      } finally {
         setIsApplying(false);
      }
   };

   return (
      <Dialog open={open} onOpenChange={handleOpenChange}>
         <DialogContent className="sm:max-w-md" onPointerDownOutside={(e) => isApplying && e.preventDefault()}>
            <DialogHeader>
               <DialogTitle>{title}</DialogTitle>
               <DialogDescription>{description}</DialogDescription>
            </DialogHeader>

            <div className="relative h-64 w-full overflow-hidden rounded-md bg-muted">
               {imageSrc && (
                  <Cropper
                     image={imageSrc}
                     crop={crop}
                     zoom={zoom}
                     aspect={1}
                     cropShape="round"
                     showGrid={false}
                     onCropChange={setCrop}
                     onZoomChange={setZoom}
                     onCropComplete={onCropComplete}
                  />
               )}
            </div>

            <div className="space-y-2">
               <p className="text-sm font-medium">{zoomLabel}</p>
               <Slider min={1} max={3} step={0.05} value={[zoom]} onValueChange={(value) => setZoom(value[0])} />
            </div>

            <DialogFooter>
               <Button type="button" variant="outline" onClick={onCancel} disabled={isApplying}>
                  {cancelLabel}
               </Button>
               <LoadingButton type="button" loading={isApplying} onClick={handleApply}>
                  {applyLabel}
               </LoadingButton>
            </DialogFooter>
         </DialogContent>
      </Dialog>
   );
};

export default AvatarCropDialog;
