import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { Star } from 'lucide-react';
import { CourseDetailsProps } from '../show';

const CourseReviewStrip = () => {
   const { reviews } = usePage<CourseDetailsProps>().props;
   const items = reviews?.data ?? [];

   if (items.length === 0) {
      return null;
   }

   return (
      <div className="container pt-8">
         <div className="flex snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain pb-2">
            {items.map((review) => (
               <article
                  key={review.id}
                  className="border-border/70 bg-card w-[min(100%,20rem)] shrink-0 snap-start rounded-xl border p-4 lg:w-[calc((100%-2rem)/3)]"
               >
                  <div className="flex items-center gap-2">
                     <Avatar className="h-8 w-8">
                        <AvatarImage src={review.user?.photo || ''} alt={review.user?.name || ''} className="object-cover" />
                        <AvatarFallback>{review.user?.name?.charAt(0) || '?'}</AvatarFallback>
                     </Avatar>
                     <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">{review.user?.name}</p>
                        <div className="flex items-center gap-2">
                           <div className="flex gap-0.5">
                              {[1, 2, 3, 4, 5].map((star) => (
                                 <Star
                                    key={star}
                                    className={cn('h-3.5 w-3.5', star <= review.rating ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40')}
                                 />
                              ))}
                           </div>
                           <p className="text-muted-foreground text-xs">{format(new Date(review.created_at), 'MMM d, yyyy h:mm a')}</p>
                        </div>
                     </div>
                  </div>
                  <p className="mt-3 line-clamp-4 text-sm">{review.review}</p>
               </article>
            ))}
         </div>
      </div>
   );
};

export default CourseReviewStrip;
