import type { Area } from 'react-easy-crop';

const OUTPUT_SIZE = 512;

function createImage(url: string): Promise<HTMLImageElement> {
   return new Promise((resolve, reject) => {
      const image = new Image();
      image.addEventListener('load', () => resolve(image));
      image.addEventListener('error', () => reject(new Error('Failed to load image')));
      image.setAttribute('crossOrigin', 'anonymous');
      image.src = url;
   });
}

export async function getCroppedImageFile(
   imageSrc: string,
   pixelCrop: Area,
   outputWidth = OUTPUT_SIZE,
   outputHeight = outputWidth,
   fileName = 'cropped-image.jpg',
): Promise<File> {
   const image = await createImage(imageSrc);
   const canvas = document.createElement('canvas');
   const context = canvas.getContext('2d');

   if (!context) {
      throw new Error('Could not get canvas context');
   }

   canvas.width = outputWidth;
   canvas.height = outputHeight;

   context.drawImage(image, pixelCrop.x, pixelCrop.y, pixelCrop.width, pixelCrop.height, 0, 0, outputWidth, outputHeight);

   const blob = await new Promise<Blob | null>((resolve) => {
      canvas.toBlob((result) => resolve(result), 'image/jpeg', 0.92);
   });

   if (!blob) {
      throw new Error('Failed to create image');
   }

   return new File([blob], fileName, { type: 'image/jpeg' });
}
