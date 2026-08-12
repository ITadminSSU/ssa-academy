import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import { CheckCircle, Loader2 } from 'lucide-react';
import { ChangeEvent, FC, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from './input-error';

interface ChunkedUploaderInputProps {
   storage?: 's3' | 'local';
   isSubmit: boolean;
   filetype: string;
   courseId?: string | number;
   sectionId?: string | number;
   delayUpload?: boolean;
   onError?: (message: string) => void;
   onCancelUpload?: () => void;
   onFileSelected?: (file: File) => void;
   onFileUploaded?: (fileData: any) => void;
}

export interface UploadedFileData {
   file_path: string;
   file_url: string;
   signed_url: string;
   mime_type: string;
   file_name: string;
   file_size: number;
}

const FILETYPE_MAX_BYTES: Record<string, number> = {
   audio: 100 * 1024 * 1024,
   video: 1024 * 1024 * 1024,
   document: 20 * 1024 * 1024,
   image: 2 * 1024 * 1024,
   zip: 256 * 1024 * 1024,
};

const mimeTypeFromFilename = (filename: string): string => {
   const extension = filename.split('.').pop()?.toLowerCase();

   return (
      {
         xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
         xls: 'application/vnd.ms-excel',
         pdf: 'application/pdf',
         txt: 'text/plain',
      }[extension ?? ''] ?? ''
   );
};

const ChunkedUploaderInput: FC<ChunkedUploaderInputProps> = ({
   storage,
   isSubmit,
   courseId,
   sectionId,
   filetype,
   delayUpload = false,
   onError,
   onCancelUpload,
   onFileSelected,
   onFileUploaded,
}) => {
   // Use external file if provided, or manage internally
   const [file, setFile] = useState<File | null>(null);
   const [uploadId, setUploadId] = useState<number | null>(null);
   const [errorMessage, setErrorMessage] = useState<string>('');
   const [uploadProgress, setUploadProgress] = useState<number>(0);
   const [uploadStatus, setUploadStatus] = useState<'idle' | 'initializing' | 'uploading' | 'completing' | 'completed' | 'error'>('idle');

   const fileInputRef = useRef<HTMLInputElement>(null);
   const fileRef = useRef<File | null>(null);
   const abortControllerRef = useRef<AbortController | null>(null);
   const maxFileSize = FILETYPE_MAX_BYTES[filetype] ?? 1024 * 1024 * 1024;
   // Cloudflare R2/S3 require every multipart part except the last to be >= 5MB.
   const DEFAULT_CHUNK_SIZE = 5 * 1024 * 1024;

   const formatUploadError = (error: any, fallback: string): string => {
      if (error?.response?.status === 413) {
         return 'Upload rejected (413): raise Forge Nginx client_max_body_size to at least 20M, or use direct R2 upload (redeploy latest). Or paste a YouTube/Vimeo URL.';
      }

      const awsMessage = error?.response?.data?.message || error?.message || '';
      if (typeof awsMessage === 'string' && awsMessage.includes('EntityTooSmall')) {
         return 'Upload failed: Cloudflare R2 rejected parts smaller than 5MB. Redeploy latest upload fix, hard-refresh, and retry — or paste a YouTube/Vimeo URL.';
      }

      return error?.response?.data?.message || error?.message || fallback;
   };

   // Configure axios to automatically handle CSRF tokens
   useEffect(() => {
      // Set up axios defaults for CSRF protection
      axios.defaults.withCredentials = true;

      // Get CSRF token from cookie if available
      const token = document.cookie
         .split('; ')
         .find((row) => row.startsWith('XSRF-TOKEN='))
         ?.split('=')[1];

      if (token) {
         axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(token);
      } else {
         // If no XSRF-TOKEN cookie, try to get it from meta tag as fallback
         const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
         if (metaToken) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = metaToken;
         }
      }
   }, []);

   // Start upload when parent signals submit (delayed-upload flow).
   useEffect(() => {
      if (isSubmit && fileRef.current) {
         initiateUpload(fileRef.current);
      }
   }, [isSubmit]);

   const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
      if (event.target.files && event.target.files.length > 0) {
         const selectedFile = event.target.files[0];

         if (selectedFile.size > maxFileSize) {
            setErrorMessage(`File is too large. Maximum file size is ${(maxFileSize / (1024 * 1024)).toFixed(0)} MB`);
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
      }
   };

   const initiateUpload = async (uploadFile?: File) => {
      const activeFile = uploadFile ?? fileRef.current ?? file;

      if (!activeFile) {
         return;
      }

      setUploadStatus('initializing');
      setErrorMessage('');

      const mimetype = activeFile.type || mimeTypeFromFilename(activeFile.name);

      try {
         // Always use R2/S3-safe 5MB parts (server also returns chunk_size to confirm).
         const chunkSize = DEFAULT_CHUNK_SIZE;
         const totalChunks = Math.ceil(activeFile.size / chunkSize);

         const response = await axios.post(
            '/dashboard/uploads/chunked/initialize',
            {
               storage: storage,
               filename: activeFile.name,
               mimetype,
               filesize: (activeFile.size || 0) / 1024,
               filetype: filetype,
               total_chunks: totalChunks,
               course_id: courseId,
               course_section_id: sectionId,
            },
            {
               timeout: 120000,
            },
         );

         if (response.data.success) {
            setUploadId(response.data.upload_id);
            const resolvedChunkSize = Number(response.data.chunk_size) || chunkSize;
            const resolvedTotalChunks = Math.ceil(activeFile.size / resolvedChunkSize);
            await uploadChunks(response.data.upload_id, resolvedTotalChunks, activeFile, resolvedChunkSize, Boolean(response.data.direct_to_storage));
         } else {
            throw new Error(response.data.message || 'Failed to initialize upload');
         }
      } catch (error: any) {
         setUploadStatus('error');
         const message = formatUploadError(error, 'Failed to initialize upload');
         setErrorMessage(message);
         if (onError) onError(message);
      }
   };

   const uploadChunkViaServer = async (
      uploadId: number,
      partNumber: number,
      chunk: Blob,
      activeFile: File,
      mimetype: string,
      signal: AbortSignal,
   ) => {
      const formData = new FormData();
      if (storage) {
         formData.append('storage', storage);
      }
      formData.append('part_number', String(partNumber));
      formData.append('filename', activeFile.name);
      formData.append('mimetype', mimetype);
      formData.append('chunk', chunk, `chunk-${partNumber}.bin`);

      const response = await axios.post(`/dashboard/uploads/chunked/${uploadId}/chunk`, formData, {
         signal,
         timeout: 120000,
         maxContentLength: Infinity,
         maxBodyLength: Infinity,
         headers: {
            'Content-Type': 'multipart/form-data',
         },
      });

      if (!response.data.success) {
         throw new Error(response.data.message || 'Failed to upload chunk');
      }

      return response.data;
   };

   const uploadChunkDirectToStorage = async (
      uploadId: number,
      partNumber: number,
      chunk: Blob,
      signal: AbortSignal,
   ): Promise<boolean> => {
      const urlResponse = await axios.post(
         `/dashboard/uploads/chunked/${uploadId}/part-url`,
         { part_number: partNumber },
         { signal, timeout: 60000 },
      );

      if (!urlResponse.data.success || !urlResponse.data.url) {
         return false;
      }

      const putResponse = await fetch(urlResponse.data.url, {
         method: 'PUT',
         body: chunk,
         signal,
      });

      if (!putResponse.ok) {
         return false;
      }

      const etag = putResponse.headers.get('etag') || putResponse.headers.get('ETag');
      if (!etag) {
         // R2 CORS must expose ETag; fall back to server upload if missing.
         return false;
      }

      const ackResponse = await axios.post(
         `/dashboard/uploads/chunked/${uploadId}/part-ack`,
         {
            part_number: partNumber,
            etag,
            size: chunk.size,
         },
         { signal, timeout: 60000 },
      );

      return Boolean(ackResponse.data.success);
   };

   const uploadChunks = async (
      uploadId: number,
      totalChunks: number,
      activeFile: File,
      chunkSize: number = DEFAULT_CHUNK_SIZE,
      directToStorage: boolean = false,
   ) => {

      setUploadStatus('uploading');

      const mimetype = activeFile.type || mimeTypeFromFilename(activeFile.name);

      // Create a new AbortController for this upload
      abortControllerRef.current = new AbortController();
      const signal = abortControllerRef.current.signal;

      try {
         let uploadedChunks = 0;

         // Process chunks sequentially to avoid overwhelming the server
         for (let chunkIndex = 0; chunkIndex < totalChunks && !signal.aborted; chunkIndex++) {
            const start = chunkIndex * chunkSize;
            const end = Math.min(start + chunkSize, activeFile.size);
            const chunk = activeFile.slice(start, end);
            const partNumber = chunkIndex + 1;
            const isLast = partNumber === totalChunks;

            if (!isLast && chunk.size < DEFAULT_CHUNK_SIZE) {
               throw new Error('Upload part is smaller than the 5MB minimum required by Cloudflare R2.');
            }

            let uploaded = false;

            if (directToStorage) {
               try {
                  uploaded = await uploadChunkDirectToStorage(uploadId, partNumber, chunk, signal);
               } catch {
                  uploaded = false;
               }
            }

            if (!uploaded) {
               await uploadChunkViaServer(uploadId, partNumber, chunk, activeFile, mimetype, signal);
            }

            uploadedChunks++;
            setUploadProgress(Math.round((uploadedChunks / totalChunks) * 100));
         }

         if (signal.aborted) {
            return; // Upload was cancelled
         }

         // Complete the upload
         await completeUpload(uploadId);
      } catch (error: any) {
         if (signal.aborted) {
            setUploadStatus('idle');
            setUploadProgress(0);
            return;
         }

         setUploadStatus('error');
         const message = formatUploadError(error, 'Failed to upload file chunks');
         setErrorMessage(message);
         if (onError) onError(message);
      }
   };

   const completeUpload = async (uploadId: number) => {
      setUploadStatus('completing');

      try {
         const response = await axios.post(
            `/dashboard/uploads/chunked/${uploadId}/complete`,
            {
               storage: storage,
            },
            {
               timeout: 120000, // 2 minute timeout for completion request
            },
         );

         if (response.data.success) {
            setUploadStatus('completed');

            const fileData: UploadedFileData = {
               file_path: response.data.file_path,
               file_url: response.data.file_url,
               signed_url: response.data.signed_url,
               mime_type: response.data.mime_type,
               file_name: response.data.file_name,
               file_size: response.data.file_size,
            };

            onFileUploaded?.(fileData);

            // Reset the uploader state for potential future uploads
            setTimeout(() => {
               if (fileInputRef.current) fileInputRef.current.value = '';
               fileRef.current = null;
               setFile(null);
               setUploadId(null);
               setUploadProgress(0);
               setUploadStatus('idle');
            }, 3000);
         } else {
            throw new Error(response.data.message || 'Failed to complete upload');
         }
      } catch (error: any) {
         setUploadStatus('error');
         setErrorMessage(error.response?.data?.message || error.message || 'Failed to complete upload');
         if (onError) onError(error.response?.data?.message || error.message || 'Failed to complete upload');
      }
   };

   const cancelUpload = async () => {
      if (uploadId && uploadStatus !== 'idle' && uploadStatus !== 'completed') {
         onCancelUpload?.();

         // Abort any in-progress network requests
         if (abortControllerRef.current) {
            abortControllerRef.current.abort();
         }

         try {
            // Inform the server to abort the multipart upload
            await axios.delete(`/dashboard/uploads/chunked/${uploadId}/abort`);
         } catch (error) {
            toast.error('Error aborting upload:' + error);
         }

         // Reset UI state
         setUploadStatus('idle');
         setUploadProgress(0);
         if (fileInputRef.current) fileInputRef.current.value = '';
         fileRef.current = null;
         setFile(null);
      }
   };

   const renderStatus = () => {
      switch (uploadStatus) {
         case 'initializing':
            return (
               <div className="flex items-center">
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Initializing upload...
               </div>
            );
         case 'uploading':
            return (
               <div className="flex items-center">
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Uploading file chunks...
               </div>
            );
         case 'completing':
            return (
               <div className="flex items-center">
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Finalizing upload...
               </div>
            );
         case 'completed':
            return (
               <div className="text-secondary-foreground flex items-center">
                  <CheckCircle className="mr-2 h-4 w-4" />
                  Completed upload
               </div>
            );
         // case 'error':
         //    return (
         //       <div className="text-destructive flex items-center text-sm">
         //          <AlertCircle className="mr-2 h-4 w-4" />
         //          Error: {errorMessage}
         //       </div>
         //    );
         default:
            return null;
      }
   };

   return (
      <div>
         <div className="relative overflow-hidden rounded-sm">
            <Input ref={fileInputRef} type="file" name="file" onChange={handleFileChange} />

            {file && uploadStatus !== 'idle' && uploadStatus !== 'error' && (
               <div className="absolute top-0 left-0 z-10 flex h-full w-full items-center justify-between">
                  <div className="relative h-full w-full overflow-hidden bg-muted">
                     <div
                        className="bg-secondary absolute top-0 left-0 h-full transition-all duration-300 ease-in-out"
                        style={{ width: `${uploadProgress}%` }}
                     />
                     <div className="relative z-10 flex h-full items-center justify-between gap-2 px-2 text-xs">
                        <span>{uploadProgress}%</span>
                        {renderStatus()}
                        <span className="text-foreground">Size: ({(file ? file.size / (1024 * 1024) : 0).toFixed(2)} MB)</span>
                     </div>
                  </div>

                  <div className="bg-muted">
                     <Button type="button" variant="destructive" onClick={cancelUpload} className="text-xs">
                        Cancel
                     </Button>
                  </div>
               </div>
            )}
         </div>

         {errorMessage && <InputError message={errorMessage} />}
      </div>
   );
};

export default ChunkedUploaderInput;
