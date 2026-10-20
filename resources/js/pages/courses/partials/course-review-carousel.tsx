import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

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
                     <img src={src} alt="Course review" className="max-h-full max-w-full object-contain" />
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
