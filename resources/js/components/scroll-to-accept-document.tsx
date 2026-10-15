import { cn } from '@/lib/utils';
import { useCallback, useEffect, useRef, useState } from 'react';

interface ScrollToAcceptDocumentProps {
   title: string;
   html: string;
   /** Fired whenever the user can / cannot yet accept (must scroll to bottom). */
   onCanAcceptChange?: (canAccept: boolean) => void;
   children?: React.ReactNode;
   /**
    * Account signup places the heading and checkboxes outside this box.
    * The course-start agreement keeps the framed card.
    */
   framed?: boolean;
}

const SCROLL_THRESHOLD = 8;

const ScrollToAcceptDocument = ({ title, html, onCanAcceptChange, children, framed = true }: ScrollToAcceptDocumentProps) => {
   const scrollRef = useRef<HTMLDivElement>(null);
   const [hasReachedBottom, setHasReachedBottom] = useState(false);

   const evaluateScroll = useCallback(() => {
      const element = scrollRef.current;

      if (!element) {
         return;
      }

      const noScrollNeeded = element.scrollHeight <= element.clientHeight + 1;
      const atBottom = element.scrollTop + element.clientHeight >= element.scrollHeight - SCROLL_THRESHOLD;
      const reached = noScrollNeeded || atBottom;

      setHasReachedBottom(reached);
      onCanAcceptChange?.(reached);
   }, [onCanAcceptChange]);

   useEffect(() => {
      evaluateScroll();

      const handleResize = () => evaluateScroll();
      window.addEventListener('resize', handleResize);

      return () => window.removeEventListener('resize', handleResize);
   }, [html, evaluateScroll]);

   const documentBox = (
      <div
         ref={scrollRef}
         onScroll={evaluateScroll}
         className={cn('bg-background h-56 overflow-y-auto rounded-md border p-4', 'prose prose-sm dark:prose-invert max-w-none')}
      >
         <div dangerouslySetInnerHTML={{ __html: html }} />
      </div>
   );

   if (!framed) {
      return documentBox;
   }

   return (
      <div className="space-y-3 rounded-lg border p-4">
         <div className="flex items-center justify-between gap-3">
            <h3 className="font-semibold">{title}</h3>
            {!hasReachedBottom && <span className="text-muted-foreground text-xs">Scroll to the bottom to continue</span>}
         </div>

         {documentBox}

         <div className={cn('space-y-3', !hasReachedBottom && 'pointer-events-none opacity-60')}>{children}</div>
      </div>
   );
};

export default ScrollToAcceptDocument;
