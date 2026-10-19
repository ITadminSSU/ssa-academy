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
      <article className="rounded-xl border border-[color:var(--ssu-line,#d5d0c4)] bg-[#fffcf7] px-5 py-6 shadow-sm sm:px-7 sm:py-7">
         <div className="flex justify-center">
            <Link
               href={route('instructors.show', instructor.id)}
               className="max-w-full rounded-full bg-[color:var(--ssu-navy,#1a344f)] px-5 py-2 text-center hover:underline sm:px-8"
            >
               <h3 className="text-sm font-bold uppercase tracking-[0.06em] text-white sm:text-[0.95rem]">{heading}</h3>
            </Link>
         </div>

         <div className="mt-6 flex flex-col items-center gap-6 sm:flex-row sm:items-start">
            <Link href={route('instructors.show', instructor.id)} className="shrink-0 no-underline">
               <Avatar className="h-40 w-40 sm:h-44 sm:w-44">
                  <AvatarImage src={user.photo || ''} alt={user.name} className="object-cover" />
                  <AvatarFallback className="bg-[color:var(--ssu-navy,#1a344f)] text-4xl font-semibold text-white">
                     {user.name?.charAt(0)}
                  </AvatarFallback>
               </Avatar>
            </Link>

            <div className="min-w-0 flex-1">
               <div className="mb-4 flex items-center justify-center gap-2 text-[color:var(--ssu-navy,#1a344f)]">
                  <span className="text-lg font-semibold tabular-nums">{rating}</span>
                  <RatingStars
                     rating={instructor.total_average_rating || 0}
                     starClass="h-5 w-5"
                     filledClass="fill-[color:var(--ssu-gold,#e89a1b)] text-[color:var(--ssu-gold,#e89a1b)]"
                     emptyClass="fill-transparent text-[color:var(--ssu-gold,#e89a1b)]/40"
                  />
               </div>

               {paragraphs.map((paragraph, index) => (
                  <p key={index} className="mb-4 text-sm leading-relaxed text-[#4a5966] last:mb-0">
                     {paragraph}
                  </p>
               ))}
            </div>
         </div>
      </article>
   );
};

export default Instructor;
