import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useCallback, useEffect, useRef, useState } from 'react';

interface ScrollToAcceptDocumentProps {
   title: string;
   html: string;
   checkboxId: string;
   checkboxLabel: string;
   checked: boolean;
   onCheckedChange: (checked: boolean) => void;
   disabled?: boolean;
   error?: string;
}

const SCROLL_THRESHOLD = 8;

const ScrollToAcceptDocument = ({
   title,
   html,
   checkboxId,
   checkboxLabel,
   checked,
   onCheckedChange,
   disabled = false,
   error,
}: ScrollToAcceptDocumentProps) => {
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

      if (!reached && checked) {
         onCheckedChange(false);
      }
   }, [checked, onCheckedChange]);

   useEffect(() => {
      evaluateScroll();

      const handleResize = () => evaluateScroll();
      window.addEventListener('resize', handleResize);

      return () => window.removeEventListener('resize', handleResize);
   }, [html, evaluateScroll]);

   const handleScroll = () => {
      evaluateScroll();
   };

   const checkboxDisabled = disabled || !hasReachedBottom;

   return (
      <div className="space-y-3 rounded-lg border p-4">
         <div className="flex items-center justify-between gap-3">
            <h3 className="font-semibold">{title}</h3>
            {!hasReachedBottom && <span className="text-muted-foreground text-xs">Scroll to the bottom to continue</span>}
         </div>

         <div
            ref={scrollRef}
            onScroll={handleScroll}
            className={cn('h-56 overflow-y-auto rounded-md border bg-background p-4', 'prose prose-sm dark:prose-invert max-w-none')}
         >
            <div dangerouslySetInnerHTML={{ __html: html }} />
         </div>

         <div className="flex items-start gap-3">
            <Checkbox
               id={checkboxId}
               checked={checked}
               onCheckedChange={(value) => onCheckedChange(Boolean(value))}
               disabled={checkboxDisabled}
            />
            <Label
               htmlFor={checkboxId}
               className={cn('text-sm leading-relaxed', checkboxDisabled ? 'text-muted-foreground cursor-not-allowed' : 'cursor-pointer')}
            >
               {checkboxLabel}
            </Label>
         </div>

         {error && <p className="text-destructive text-sm">{error}</p>}
      </div>
   );
};

export default ScrollToAcceptDocument;
