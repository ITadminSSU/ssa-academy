import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import axios from 'axios';
import { ChangeEvent, useEffect, useRef, useState } from 'react';
import * as tus from 'tus-js-client';

interface BunnyUploadCredentials {
   video_id: string;
   library_id: string;
   authorization_signature: string;
   authorization_expire: number;
   tus_endpoint: string;
}

interface BunnyVideoUploadResult {
   bunny_video_id: string;
   duration?: string;
   thumbnail?: string | null;
}

interface Props {
   isSubmit?: boolean;
   courseId?: string | number;
   sectionId?: string | number;
   delayUpload?: boolean;
   maxFileSize?: number;
   onFileSelected?: (file: File) => void;
   onFileUploaded?: (fileData: BunnyVideoUploadResult) => void;
   onError?: (message: string) => void;
   onCancelUpload?: () => void;
}

const DEFAULT_MAX_FILE_SIZE = 1024 * 1024 * 1024;

const BunnyVideoUploaderInput = ({
   isSubmit = false,
   courseId,
   sectionId,
   delayUpload = false,
   maxFileSize = DEFAULT_MAX_FILE_SIZE,
   onFileSelected,
   onFileUploaded,
   onError,
   onCancelUpload,
}: Props) => {
   const fileRef = useRef<File | null>(null);
   const uploadRef = useRef<tus.Upload | null>(null);
   const videoIdRef = useRef<string | null>(null);

   const [file, setFile] = useState<File | null>(null);
   const [uploadProgress, setUploadProgress] = useState(0);
   const [uploadStatus, setUploadStatus] = useState<'idle' | 'initializing' | 'uploading' | 'completing' | 'completed' | 'error'>('idle');
   const [errorMessage, setErrorMessage] = useState('');

   useEffect(() => {
      axios.defaults.withCredentials = true;

      const token = document.cookie
         .split('; ')
         .find((row) => row.startsWith('XSRF-TOKEN='))
         ?.split('=')[1];

      if (token) {
         axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(token);
      }
   }, []);

   useEffect(() => {
      if (isSubmit && fileRef.current) {
         initiateUpload(fileRef.current);
      }
   }, [isSubmit]);

   const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
      if (!event.target.files?.length) {
         return;
      }

      const selectedFile = event.target.files[0];

      if (selectedFile.size > maxFileSize) {
         const message = `File is too large. Maximum file size is ${(maxFileSize / (1024 * 1024)).toFixed(0)} MB`;
         setErrorMessage(message);
         onError?.(message);
         return;
      }

      fileRef.current = selectedFile;
      setFile(selectedFile);
      setErrorMessage('');
      setUploadStatus('idle');
      setUploadProgress(0);
      onFileSelected?.(selectedFile);

      if (!delayUpload) {
         initiateUpload(selectedFile);
      }
   };

   const abortUpload = async () => {
      uploadRef.current?.abort(true);
      uploadRef.current = null;

      if (videoIdRef.current) {
         try {
            await axios.delete(route('bunny.upload.abort'), {
               data: { video_id: videoIdRef.current },
            });
         } catch {
            // Best-effort cleanup.
         }
      }

      videoIdRef.current = null;
      setUploadStatus('idle');
      setUploadProgress(0);
      onCancelUpload?.();
   };

   const initiateUpload = async (uploadFile?: File) => {
      const activeFile = uploadFile ?? fileRef.current ?? file;

      if (!activeFile) {
         return;
      }

      setUploadStatus('initializing');
      setErrorMessage('');

      try {
         const initResponse = await axios.post(route('bunny.upload.initialize'), {
            filename: activeFile.name,
            filesize: activeFile.size,
            mimetype: activeFile.type || 'video/mp4',
            course_id: courseId,
            course_section_id: sectionId,
         });

         const credentials = initResponse.data.upload as BunnyUploadCredentials;
         const videoId = initResponse.data.video_id as string;
         videoIdRef.current = videoId;

         setUploadStatus('uploading');

         await new Promise<void>((resolve, reject) => {
            const upload = new tus.Upload(activeFile, {
               endpoint: credentials.tus_endpoint,
               retryDelays: [0, 1000, 3000, 5000],
               metadata: {
                  filetype: activeFile.type || 'video/mp4',
                  title: activeFile.name,
               },
               headers: {
                  AuthorizationSignature: credentials.authorization_signature,
                  AuthorizationExpire: String(credentials.authorization_expire),
                  LibraryId: credentials.library_id,
                  VideoId: credentials.video_id,
               },
               onError: (error) => {
                  reject(error);
               },
               onProgress: (bytesUploaded, bytesTotal) => {
                  if (bytesTotal > 0) {
                     setUploadProgress(Math.round((bytesUploaded / bytesTotal) * 100));
                  }
               },
               onSuccess: () => {
                  resolve();
               },
            });

            uploadRef.current = upload;
            upload.start();
         });

         setUploadStatus('completing');

         const completeResponse = await axios.post(route('bunny.upload.complete'), {
            video_id: videoId,
         });

         setUploadStatus('completed');
         setUploadProgress(100);

         onFileUploaded?.({
            bunny_video_id: completeResponse.data.bunny_video_id,
            duration: completeResponse.data.duration,
            thumbnail: completeResponse.data.thumbnail,
         });
      } catch (error) {
         const message = error instanceof Error ? error.message : 'Failed to upload video to Bunny Stream.';
         setUploadStatus('error');
         setErrorMessage(message);
         onError?.(message);
      }
   };

   return (
      <div className="space-y-3">
         <Input type="file" accept="video/*" onChange={handleFileChange} disabled={uploadStatus === 'uploading' || uploadStatus === 'completing'} />

         {file && (
            <p className="text-muted-foreground text-sm">
               Selected: {file.name} ({(file.size / (1024 * 1024)).toFixed(1)} MB)
            </p>
         )}

         {uploadStatus !== 'idle' && uploadStatus !== 'completed' && uploadStatus !== 'error' && (
            <div className="space-y-2">
               <Progress value={uploadProgress} />
               <p className="text-muted-foreground text-sm">
                  {uploadStatus === 'initializing' && 'Preparing Bunny Stream upload...'}
                  {uploadStatus === 'uploading' && `Uploading to Bunny Stream... ${uploadProgress}%`}
                  {uploadStatus === 'completing' && 'Finalizing upload...'}
               </p>
            </div>
         )}

         {uploadStatus === 'completed' && <p className="text-sm text-green-600">Video uploaded to Bunny Stream successfully.</p>}

         {errorMessage && <p className="text-sm text-red-500">{errorMessage}</p>}

         {(uploadStatus === 'uploading' || uploadStatus === 'initializing' || uploadStatus === 'completing') && (
            <Button type="button" variant="outline" onClick={abortUpload}>
               Cancel upload
            </Button>
         )}
      </div>
   );
};

export default BunnyVideoUploaderInput;
