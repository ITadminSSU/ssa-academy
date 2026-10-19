import RatingStars from '@/components/rating-stars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Link } from '@inertiajs/react';

const Instructor = ({ course }: { course: Course }) => {
   const { instructor } = course;
   const { user } = instructor;
   const designation = instructor.designation?.trim() || 'Trainer';
   const heading = (course.instructor_card_name?.trim() || `${user.name}, ${designation}`).toUpperCase();
   const biography = course.instructor_card_biography?.trim() || instructor.biography?.trim() || '';
   const photo = course.instructor_card_photo?.trim() || user.photo || '';
   const rating = instructor.total_average_rating ? Number(instructor.total_average_rating).toFixed(1) : '0.0';
   const paragraphs = biography
      .split(/\n+/)
      .map((paragraph) => paragraph.trim())
      .filter(Boolean);

   return (
      <article className="rounded-xl border border-[color:var(--ssu-line,#d5d0c4)] bg-[#fffcf7] px-5 py-6 shadow-sm sm:px-7 sm:py-7">
         <div className="flex flex-col items-center gap-5 sm:grid sm:grid-cols-[auto_minmax(0,1fr)] sm:items-start sm:gap-x-6 sm:gap-y-0">
            <Link href={route('instructors.show', instructor.id)} className="shrink-0 no-underline sm:col-start-1 sm:row-start-2 sm:mt-3">
               <Avatar className="h-44 w-44 sm:h-52 sm:w-52">
                  <AvatarImage src={photo} alt={user.name} className="object-cover" />
                  <AvatarFallback className="bg-[color:var(--ssu-navy,#1a344f)] text-4xl font-semibold text-white">
                     {user.name?.charAt(0)}
                  </AvatarFallback>
               </Avatar>
            </Link>

            <Link
               href={route('instructors.show', instructor.id)}
               className="block w-full rounded-full bg-[color:var(--ssu-navy,#1a344f)] px-4 py-2.5 text-center no-underline hover:bg-[color:var(--ssu-navy-deep,#14283d)] sm:col-start-2 sm:row-start-1"
            >
               <h3 className="text-sm font-bold uppercase tracking-[0.06em] text-white sm:text-[0.95rem]">{heading}</h3>
            </Link>

            <div className="w-full min-w-0 sm:col-start-2 sm:row-start-2">
               <div className="mt-3 flex items-center justify-center gap-2 text-[color:var(--ssu-navy,#1a344f)]">
                  <span className="text-lg font-semibold tabular-nums">{rating}</span>
                  <RatingStars
                     rating={instructor.total_average_rating || 0}
                     starClass="h-5 w-5"
                     filledClass="fill-[color:var(--ssu-gold,#e89a1b)] text-[color:var(--ssu-gold,#e89a1b)]"
                     emptyClass="fill-transparent text-[color:var(--ssu-gold,#e89a1b)]/40"
                  />
               </div>

               {paragraphs.length > 0 ? (
                  <div className="mt-3">
                     {paragraphs.map((paragraph, index) => (
                        <p key={index} className="mb-3 text-[13px] leading-relaxed text-[#4a5966] last:mb-0">
                           {paragraph}
                        </p>
                     ))}
                  </div>
               ) : null}
            </div>
         </div>
      </article>
   );
};

export default Instructor;
