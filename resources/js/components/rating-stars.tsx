import { cn } from '@/lib/utils';
import { Star } from 'lucide-react';

interface Props {
   rating: number | string | null | undefined;
   starClass?: string;
   wrapperClass?: string;
}

const RatingStars = ({ rating, starClass, wrapperClass }: Props) => {
   const numericRating = Number(rating) || 0;
   const fullStars = Math.floor(numericRating);
   const hasHalf = numericRating - fullStars >= 0.5;

   const stars = Array.from({ length: 5 }, (_, i) => {
      const isFilled = i < fullStars || (i === fullStars && hasHalf);
      return (
         <Star
            key={i}
            className={cn(
               'h-5 w-5',
               isFilled ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40',
               starClass,
            )}
         />
      );
   });

   return <div className={cn('flex items-center gap-[1px]', wrapperClass)}>{stars}</div>;
};

export default RatingStars;
