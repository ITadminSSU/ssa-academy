import type { LogoPlacement } from '@/lib/logo-placements';

export interface CropArea {
   x: number;
   y: number;
   width: number;
   height: number;
}

export interface OptimizeLogoOptions {
   maxWidth?: number;
   maxHeight?: number;
   quality?: number;
}

const EXPORT_LIMITS: Record<LogoPlacement, { maxWidth: number; maxHeight: number }> = {
   navbar: { maxWidth: 480, maxHeight: 240 },
   footer: { maxWidth: 720, maxHeight: 480 },
   auth: { maxWidth: 960, maxHeight: 720 },
   dashboard: { maxWidth: 720, maxHeight: 480 },
   certificate: { maxWidth: 960, maxHeight: 960 },
};

const createImage = (url: string): Promise<HTMLImageElement> =>
   new Promise((resolve, reject) => {
      const image = new Image();
      image.addEventListener('load', () => resolve(image));
      image.addEventListener('error', (error) => reject(error));
      image.setAttribute('crossOrigin', 'anonymous');
      image.src = url;
   });

const scaleDimensions = (width: number, height: number, maxWidth: number, maxHeight: number) => {
   const scale = Math.min(1, maxWidth / width, maxHeight / height);

   return {
      width: Math.max(1, Math.round(width * scale)),
      height: Math.max(1, Math.round(height * scale)),
   };
};

export async function getCroppedImageBlob(
   imageSrc: string,
   crop: CropArea,
   options: OptimizeLogoOptions = {},
): Promise<Blob> {
   const image = await createImage(imageSrc);
   const canvas = document.createElement('canvas');
   const context = canvas.getContext('2d');

   if (!context) {
      throw new Error('Could not get canvas context');
   }

   const maxWidth = options.maxWidth ?? 1200;
   const maxHeight = options.maxHeight ?? 1200;
   const quality = options.quality ?? 0.92;
   const outputSize = scaleDimensions(crop.width, crop.height, maxWidth, maxHeight);

   canvas.width = outputSize.width;
   canvas.height = outputSize.height;

   context.clearRect(0, 0, outputSize.width, outputSize.height);
   context.drawImage(image, crop.x, crop.y, crop.width, crop.height, 0, 0, outputSize.width, outputSize.height);

   const blob = await exportCanvas(canvas, quality);

   if (blob.size <= 2 * 1024 * 1024) {
      return blob;
   }

   const smaller = scaleDimensions(outputSize.width, outputSize.height, Math.round(outputSize.width * 0.75), Math.round(outputSize.height * 0.75));
   canvas.width = smaller.width;
   canvas.height = smaller.height;
   context.clearRect(0, 0, smaller.width, smaller.height);
   context.drawImage(image, crop.x, crop.y, crop.width, crop.height, 0, 0, smaller.width, smaller.height);

   return exportCanvas(canvas, Math.min(quality, 0.85));
}

async function exportCanvas(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
   const webpBlob = await canvasToBlob(canvas, 'image/webp', quality);

   if (webpBlob) {
      return webpBlob;
   }

   const pngBlob = await canvasToBlob(canvas, 'image/png');

   if (!pngBlob) {
      throw new Error('Canvas export failed');
   }

   return pngBlob;
}

function canvasToBlob(canvas: HTMLCanvasElement, type: string, quality?: number): Promise<Blob | null> {
   return new Promise((resolve) => {
      canvas.toBlob((blob) => resolve(blob), type, quality);
   });
}

export function blobToFile(blob: Blob, filename: string): File {
   const extension = blob.type === 'image/webp' ? 'webp' : blob.type === 'image/jpeg' ? 'jpg' : 'png';
   const baseName = filename.replace(/\.[^.]+$/, '');

   return new File([blob], `${baseName}.${extension}`, { type: blob.type || 'image/png' });
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

export function getLogoExportLimits(placement: LogoPlacement) {
   return EXPORT_LIMITS[placement];
}

export const MAX_LOGO_UPLOAD_BYTES = 2 * 1024 * 1024;
