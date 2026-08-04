import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/jpg'];
const MAX_FILE_SIZE = 15 * 1024 * 1024;

type UseAvatarCropOptions = {
   onPhotoReady: (file: File, previewUrl: string) => void;
   invalidTypeMessage?: string;
   tooLargeMessage?: string;
};

export function useAvatarCrop({ onPhotoReady, invalidTypeMessage, tooLargeMessage }: UseAvatarCropOptions) {
   const fileInputRef = useRef<HTMLInputElement>(null);
   const [cropOpen, setCropOpen] = useState(false);
   const [cropImageSrc, setCropImageSrc] = useState<string | null>(null);

   const resetFileInput = useCallback(() => {
      if (fileInputRef.current) {
         fileInputRef.current.value = '';
      }
   }, []);

   const handleFileSelect = useCallback(
      (event: React.ChangeEvent<HTMLInputElement>) => {
         const file = event.target.files?.[0];
         if (!file) {
            return;
         }

         if (!ACCEPTED_TYPES.includes(file.type)) {
            toast.error(invalidTypeMessage ?? 'Please upload a JPG or PNG image.');
            resetFileInput();
            return;
         }

         if (file.size > MAX_FILE_SIZE) {
            toast.error(tooLargeMessage ?? 'Image must be smaller than 15MB.');
            resetFileInput();
            return;
         }

         const reader = new FileReader();
         reader.onload = () => {
            setCropImageSrc(reader.result as string);
            setCropOpen(true);
         };
         reader.onerror = () => {
            toast.error('Failed to read image file.');
            resetFileInput();
         };
         reader.readAsDataURL(file);
      },
      [invalidTypeMessage, tooLargeMessage, resetFileInput],
   );

   const handleCropCancel = useCallback(() => {
      setCropOpen(false);
      setCropImageSrc(null);
      resetFileInput();
   }, [resetFileInput]);

   const handleCropApply = useCallback(
      (file: File) => {
         const previewUrl = URL.createObjectURL(file);
         onPhotoReady(file, previewUrl);
         setCropOpen(false);
         setCropImageSrc(null);
         resetFileInput();
      },
      [onPhotoReady, resetFileInput],
   );

   return {
      fileInputRef,
      cropOpen,
      cropImageSrc,
      handleFileSelect,
      handleCropCancel,
      handleCropApply,
   };
}
