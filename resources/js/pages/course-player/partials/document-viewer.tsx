import { HTMLAttributes, useMemo } from 'react';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';

interface Props extends HTMLAttributes<HTMLDivElement> {
   src: string;
   fileName?: string;
   protectedMode?: boolean;
}

type DocumentType = 'pdf' | 'office' | 'image' | 'text' | 'unsupported';

const DocumentViewer = ({ src, fileName, protectedMode = false, className, ...props }: Props) => {
   const { props: pageProps } = usePage<SharedData>();
   const { translate } = pageProps;
   const { frontend } = translate;

   const documentInfo = useMemo(() => {
      const extensionFromName = (value: string): string => {
         const base = decodeURIComponent(value.split('?')[0].split('#')[0]).split('/').pop() || '';
         if (!base.includes('.')) {
            return '';
         }

         const extension = base.split('.').pop()?.toLowerCase() || '';

         return /^[a-z0-9]{2,5}$/.test(extension) && !/^\d+$/.test(extension) ? extension : '';
      };

      const getFileExtension = (url: string, name?: string): string => {
         const fromName = name ? extensionFromName(name) : '';
         if (fromName) {
            return fromName;
         }

         try {
            const parsed = new URL(url, 'https://academy.invalid');
            const fromQuery = parsed.searchParams.get('filename') || parsed.searchParams.get('name') || '';
            const queryExtension = fromQuery ? extensionFromName(fromQuery) : '';
            if (queryExtension) {
               return queryExtension;
            }

            const pathExtension = extensionFromName(parsed.pathname);
            if (pathExtension) {
               return pathExtension;
            }
         } catch {
            const fallback = extensionFromName(url.split('?')[0]);
            if (fallback) {
               return fallback;
            }
         }

         return /\/play-course\/media\/\d+/i.test(url) ? 'pdf' : '';
      };

      const getDocumentType = (extension: string): DocumentType => {
         const pdfFormats = ['pdf'];
         const officeFormats = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];
         const imageFormats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
         const textFormats = ['txt', 'rtf', 'csv'];

         if (pdfFormats.includes(extension)) return 'pdf';
         if (officeFormats.includes(extension)) return 'office';
         if (imageFormats.includes(extension)) return 'image';
         if (textFormats.includes(extension)) return 'text';
         return 'unsupported';
      };

      const extension = getFileExtension(src, fileName);
      const type = getDocumentType(extension);

      return { extension, type };
   }, [src, fileName]);

   const renderDocument = () => {
      const baseClassName = 'h-full max-h-[calc(100vh-4rem)] min-h-[80vh] w-full';

      switch (documentInfo.type) {
         case 'pdf':
            return (
               <iframe
                  src={src}
                  width="100%"
                  height="100%"
                  allowFullScreen
                  title={frontend.pdf_document}
                  className={baseClassName}
               />
            );

         case 'office': {
            const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(src)}`;
            return (
               <iframe
                  src={officeViewerUrl}
                  width="100%"
                  height="100%"
                  allowFullScreen
                  title={`${documentInfo.extension.toUpperCase()} Document`}
                  className={baseClassName}
               />
            );
         }

         case 'image':
            return (
               <div className="flex h-full items-center justify-center bg-muted">
                  <img
                     src={src}
                     alt={fileName || frontend.document}
                     className="max-h-full max-w-full object-contain"
                     draggable={false}
                     onContextMenu={protectedMode ? (e) => e.preventDefault() : undefined}
                  />
               </div>
            );

         case 'text':
            return <iframe src={src} width="100%" height="100%" title={frontend.text_document} className={baseClassName} />;

         default:
            return (
               <div className="flex h-full flex-col items-center justify-center bg-muted text-muted-foreground">
                  <AlertCircle className="mb-4 h-16 w-16 text-muted-foreground/70" />
                  <h3 className="mb-2 text-lg font-medium">{frontend.unsupported_document_format}</h3>
                  <p className="max-w-md text-center text-sm">
                     {frontend.document_format_cannot_be_previewed.replace('{extension}', documentInfo.extension)}
                  </p>
               </div>
            );
      }
   };

   return (
      <div className={`h-full ${className || ''}`} onContextMenu={protectedMode ? (e) => e.preventDefault() : undefined} {...props}>
         {renderDocument()}
      </div>
   );
};

export default DocumentViewer;
