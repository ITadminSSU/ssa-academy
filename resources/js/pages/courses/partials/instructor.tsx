import RatingStars from '@/components/rating-stars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Link } from '@inertiajs/react';

const Instructor = ({ course }: { course: Course }) => {
   const { instructor } = course;
   const { user } = instructor;
   const biography = instructor.biography?.trim() || '';
   const designation = instructor.designation?.trim() || '';
   const heading = designation ? `${user.name}, ${designation}` : user.name;
   const rating = instructor.total_average_rating ? Number(instructor.total_average_rating).toFixed(1) : '0';

   return (
      <section className="ssu-surface-card overflow-hidden">
         <div className="flex flex-wrap items-center justify-between gap-3 bg-primary px-5 py-3 text-white">
            <h2 className="text-sm font-bold tracking-wide uppercase md:text-base">
               <Link href={route('instructors.show', instructor.id)} className="hover:underline">
                  {heading}
               </Link>
            </h2>

            <div className="flex items-center gap-2">
               <span className="text-sm font-semibold">{rating}</span>
               <RatingStars rating={instructor.total_average_rating || 0} starClass="h-4 w-4" />
            </div>
         </div>

         <div className="flex flex-col gap-5 p-5 sm:flex-row sm:items-start md:p-6">
            <Link href={route('instructors.show', instructor.id)} className="shrink-0">
               <Avatar className="h-28 w-28">
                  <AvatarImage src={user.photo || ''} alt={user.name} className="object-cover" />
                  <AvatarFallback className="text-2xl">{user.name?.charAt(0)}</AvatarFallback>
               </Avatar>
            </Link>

            {biography ? (
               <p className="text-muted-foreground min-w-0 flex-1 text-sm leading-relaxed whitespace-pre-wrap">{biography}</p>
            ) : null}
         </div>
      </section>
   );
};

export default Instructor;
