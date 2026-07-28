export interface CropArea {
   x: number;
   y: number;
   width: number;
   height: number;
}

const createImage = (url: string): Promise<HTMLImageElement> =>
   new Promise((resolve, reject) => {
      const image = new Image();
      image.addEventListener('load', () => resolve(image));
      image.addEventListener('error', (error) => reject(error));
      image.setAttribute('crossOrigin', 'anonymous');
      image.src = url;
   });

export async function getCroppedImageBlob(imageSrc: string, crop: CropArea, mimeType = 'image/png'): Promise<Blob> {
   const image = await createImage(imageSrc);
   const canvas = document.createElement('canvas');
   const context = canvas.getContext('2d');

   if (!context) {
      throw new Error('Could not get canvas context');
   }

   canvas.width = crop.width;
   canvas.height = crop.height;

   context.drawImage(image, crop.x, crop.y, crop.width, crop.height, 0, 0, crop.width, crop.height);

   return new Promise((resolve, reject) => {
      canvas.toBlob((blob) => {
         if (!blob) {
            reject(new Error('Canvas export failed'));
            return;
         }

         resolve(blob);
      }, mimeType);
   });
}

export function blobToFile(blob: Blob, filename: string): File {
   return new File([blob], filename, { type: blob.type || 'image/png' });
}

export async function getFullImageCropArea(imageSrc: string): Promise<CropArea> {
   const image = await createImage(imageSrc);

   return {
      x: 0,
      y: 0,
      width: image.naturalWidth,
      height: image.naturalHeight,
   };
}
