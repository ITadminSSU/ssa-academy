import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const ReviewCarouselPicture = ({ src }: { src: string }) => {
   const triggerRef = useRef<HTMLButtonElement>(null);
   const closeRef = useRef<HTMLButtonElement>(null);
   const [hovered, setHovered] = useState(false);
   const [open, setOpen] = useState(false);
   const label = 'View larger review picture';

   const close = () => {
      setOpen(false);
      triggerRef.current?.focus();
   };

   useEffect(() => {
      if (!open) {
         return;
      }

      closeRef.current?.focus();
      const previousOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';

      const onKeyDown = (event: KeyboardEvent) => {
         if (event.key === 'Escape') {
            setOpen(false);
            triggerRef.current?.focus();
         }
      };

      document.addEventListener('keydown', onKeyDown);

      return () => {
         document.body.style.overflow = previousOverflow;
         document.removeEventListener('keydown', onKeyDown);
      };
   }, [open]);

   return (
      <>
         <button
            ref={triggerRef}
            type="button"
            className="flex h-full w-full cursor-zoom-in items-center justify-center border-0 bg-transparent p-0"
            aria-haspopup="dialog"
            aria-expanded={open}
            aria-label={label}
            onPointerEnter={(event) => event.pointerType === 'mouse' && setHovered(true)}
            onPointerLeave={(event) => event.pointerType === 'mouse' && setHovered(false)}
            onClick={() => {
               setHovered(false);
               setOpen(true);
            }}
         >
            <img src={src} alt="Course review" className="max-h-full max-w-full object-contain" />
         </button>
         {hovered && !open
            ? createPortal(
                 <div className="pointer-events-none fixed top-1/2 left-1/2 z-[70] -translate-x-1/2 -translate-y-1/2">
                    <img
                       src={src}
                       alt=""
                       className="pointer-events-none h-auto max-h-[70vh] w-auto max-w-[min(960px,92vw)] bg-[#f7f6f3] object-contain shadow-2xl"
                    />
                 </div>,
                 document.body,
              )
            : null}
         {open
            ? createPortal(
                 <div
                    className="fixed inset-0 z-[80] flex items-center justify-center bg-[#f7f6f3] p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-label={label}
                    onClick={close}
                 >
                    <button
                       ref={closeRef}
                       type="button"
                       className="absolute top-4 right-4 grid h-10 w-10 place-items-center rounded-full bg-white text-[#10243f] shadow"
                       aria-label="Close review picture"
                       onClick={(event) => {
                          event.stopPropagation();
                          close();
                       }}
                    >
                       <X className="h-5 w-5" />
                    </button>
                    <img
                       src={src}
                       alt="Course review"
                       className="max-h-[85vh] w-auto max-w-[min(1100px,92vw)] object-contain"
                       onClick={(event) => event.stopPropagation()}
                    />
                 </div>,
                 document.body,
              )
            : null}
      </>
   );
};

const CourseReviewCarousel = ({ images }: { images: string[] }) => {
   const slides = images.map((image) => image.trim()).filter((image) => image !== '');
   const [visible, setVisible] = useState(3);
   const [start, setStart] = useState(0);

   useEffect(() => {
      const query = window.matchMedia('(min-width: 768px)');
      const update = () => setVisible(query.matches ? 3 : 1);
      update();
      query.addEventListener('change', update);

      return () => query.removeEventListener('change', update);
   }, []);

   useEffect(() => {
      setStart((current) => Math.min(current, Math.max(0, slides.length - visible)));
   }, [slides.length, visible]);

   if (slides.length === 0) {
      return null;
   }

   const maxStart = Math.max(0, slides.length - visible);
   const shown = slides.slice(start, start + visible);

   return (
      <div className="relative">
         <Button
            type="button"
            variant="outline"
            size="icon"
            className="absolute top-1/2 left-0 z-10 -translate-y-1/2 rounded-full bg-white shadow-md"
            aria-label="Previous reviews"
            disabled={start === 0}
            onClick={() => setStart((current) => Math.max(0, current - 1))}
         >
            <ChevronLeft />
         </Button>

         <ul className="grid grid-cols-1 gap-4 px-12 md:grid-cols-3">
            {shown.map((src, index) => (
               <li key={`${src}-${start + index}`} className="border-border/70 overflow-hidden rounded-2xl border bg-white shadow-sm">
                  <div className="flex h-72 items-center justify-center bg-white p-3">
                     <ReviewCarouselPicture src={src} />
                  </div>
               </li>
            ))}
         </ul>

         <Button
            type="button"
            variant="outline"
            size="icon"
            className="absolute top-1/2 right-0 z-10 -translate-y-1/2 rounded-full bg-white shadow-md"
            aria-label="Next reviews"
            disabled={start >= maxStart}
            onClick={() => setStart((current) => Math.min(maxStart, current + 1))}
         >
            <ChevronRight />
         </Button>
      </div>
   );
};

export default CourseReviewCarousel;
