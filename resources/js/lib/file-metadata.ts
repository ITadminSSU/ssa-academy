interface FileMetadata {
   duration?: string;
   thumbnail?: string;
   dimensions?: { width: number; height: number };
   size: string;
   name: string;
   type: string;
}

const formatDuration = (seconds: number): string => {
   if (!Number.isFinite(seconds) || seconds < 0) {
      return '00:00:00';
   }

   const total = Math.floor(seconds);
   const hrs = Math.floor(total / 3600);
   const mins = Math.floor((total % 3600) / 60);
   const secs = total % 60;

   return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const formatFileSize = (bytes: number): string => {
   if (bytes === 0) {
      return '0 Bytes';
   }

   const k = 1024;
   const sizes = ['Bytes', 'KB', 'MB', 'GB'];
   const i = Math.floor(Math.log(bytes) / Math.log(k));

   return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
};

const createVideoThumbnail = (video: HTMLVideoElement, objectUrl: string): Promise<string | undefined> => {
   return new Promise((resolve) => {
      let settled = false;

      const capture = () => {
         if (settled) {
            return;
         }
         settled = true;

         try {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth || 320;
            canvas.height = video.videoHeight || 180;
            const ctx = canvas.getContext('2d');
            ctx?.drawImage(video, 0, 0, canvas.width, canvas.height);
            resolve(canvas.toDataURL('image/jpeg'));
         } catch {
            resolve(undefined);
         } finally {
            URL.revokeObjectURL(objectUrl);
         }
      };

      if (!Number.isFinite(video.duration) || video.duration <= 0) {
         capture();
         return;
      }

      const seekTo = Math.min(1, Math.max(0.1, video.duration / 10));
      video.currentTime = seekTo;
      video.onseeked = () => capture();
      // Fallback if seek never fires
      window.setTimeout(capture, 1500);
   });
};

export const getFileMetadata = (file: File): Promise<FileMetadata> => {
   return new Promise((resolve, reject) => {
      try {
         const fileType = file.type.split('/')[0];
         const size = formatFileSize(file.size);

         if (fileType === 'video') {
            const video = document.createElement('video');
            const videoUrl = URL.createObjectURL(file);
            video.preload = 'metadata';
            video.muted = true;
            video.playsInline = true;

            video.onloadedmetadata = () => {
               const duration = formatDuration(video.duration);

               createVideoThumbnail(video, videoUrl).then((thumbnailUrl) => {
                  resolve({
                     duration,
                     dimensions: {
                        width: video.videoWidth,
                        height: video.videoHeight,
                     },
                     size,
                     thumbnail: thumbnailUrl,
                     name: file.name.replace(/\.[^/.]+$/, ''),
                     type: file.type,
                  });
               });
            };

            video.onerror = () => {
               URL.revokeObjectURL(videoUrl);
               // Still allow upload; duration can be filled from Bunny later.
               resolve({
                  duration: '00:00:00',
                  size,
                  name: file.name.replace(/\.[^/.]+$/, ''),
                  type: file.type,
               });
            };

            video.src = videoUrl;
            return;
         }

         if (fileType === 'image') {
            const img = new Image();
            const imageUrl = URL.createObjectURL(file);

            img.onload = () => {
               resolve({
                  dimensions: {
                     width: img.width,
                     height: img.height,
                  },
                  size,
                  thumbnail: imageUrl,
                  name: file.name,
                  type: file.type,
               });
            };

            img.onerror = () => {
               URL.revokeObjectURL(imageUrl);
               reject(new Error('Error loading image metadata'));
            };

            img.src = imageUrl;
            return;
         }

         resolve({
            size,
            name: file.name,
            type: file.type,
         });
      } catch (error) {
         reject(error);
      }
   });
};
