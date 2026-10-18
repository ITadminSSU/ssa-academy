import RatingStars from '@/components/rating-stars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Link } from '@inertiajs/react';

const Instructor = ({ course }: { course: Course }) => {
   const { instructor } = course;
   const { user } = instructor;
   const biography = instructor.biography?.trim() || '';
   const designation = instructor.designation?.trim() || 'Trainer';
   const heading = `${user.name}, ${designation}`.toUpperCase();
   const rating = instructor.total_average_rating ? Number(instructor.total_average_rating).toFixed(1) : '0.0';
   const paragraphs = biography
      .split(/\n+/)
      .map((paragraph) => paragraph.trim())
      .filter(Boolean);

   return (
      <article className="overflow-hidden rounded-xl border border-[color:var(--ssu-line,#d5d0c4)] bg-[#fffcf7] shadow-sm">
         <header className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 bg-[color:var(--ssu-navy,#1a344f)] px-4 py-3 text-white sm:px-5">
            <Link href={route('instructors.show', instructor.id)} className="min-w-0 hover:underline">
               <h3 className="text-sm font-bold uppercase tracking-[0.04em] sm:text-[0.95rem]">{heading}</h3>
            </Link>

            <div className="flex shrink-0 items-center gap-2">
               <span className="text-sm font-semibold tabular-nums sm:text-base">{rating}</span>
               <RatingStars
                  rating={instructor.total_average_rating || 0}
                  starClass="h-4 w-4"
                  filledClass="fill-[color:var(--ssu-gold,#e89a1b)] text-[color:var(--ssu-gold,#e89a1b)]"
                  emptyClass="fill-transparent text-white/35"
               />
            </div>
         </header>

         <div className="px-4 py-5 text-sm leading-relaxed text-[#4a5966] sm:px-5 sm:py-6">
            <Link href={route('instructors.show', instructor.id)} className="float-left mb-2 mr-4 mt-0.5 no-underline sm:mr-5">
               <Avatar className="h-28 w-28 ring-[3px] ring-white sm:h-36 sm:w-36">
                  <AvatarImage src={user.photo || ''} alt={user.name} className="object-cover" />
                  <AvatarFallback className="bg-[color:var(--ssu-navy,#1a344f)] text-3xl font-semibold text-white">
                     {user.name.charAt(0)}
                  </AvatarFallback>
               </Avatar>
            </Link>

            {paragraphs.map((paragraph, index) => (
               <p key={index} className="mb-4 last:mb-0">
                  {paragraph}
               </p>
            ))}

            <div className="clear-both" aria-hidden />
         </div>
      </article>
   );
};

export default Instructor;
