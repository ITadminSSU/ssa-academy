import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Link } from '@inertiajs/react';

const CourseInstructorCard = ({ course }: { course: Course }) => {
   const instructor = course.instructor;

   if (!instructor?.user) {
      return null;
   }

   const name = instructor.user.name;
   const designation = instructor.designation?.trim() ?? '';
   const badge = designation ? `${name}, ${designation}` : name;
   const biography = instructor.biography?.trim() ?? '';
   const profile = route('instructors.show', instructor.id);

   return (
      <section className="ssu-surface-card flex flex-col gap-5 p-5 md:flex-row md:items-start md:p-6">
         <Link href={profile} className="shrink-0">
            <Avatar className="h-28 w-28">
               <AvatarImage src={instructor.user.photo || ''} alt={name} className="object-cover" />
               <AvatarFallback className="text-2xl">{name.charAt(0)}</AvatarFallback>
            </Avatar>
         </Link>

         <div className="min-w-0 space-y-3">
            <Link
               href={profile}
               className="bg-primary text-primary-foreground inline-flex max-w-full rounded-full px-4 py-2 text-sm font-semibold hover:underline"
            >
               {badge}
            </Link>
            {biography ? <p className="text-muted-foreground whitespace-pre-wrap text-sm leading-relaxed">{biography}</p> : null}
         </div>
      </section>
   );
};

export default CourseInstructorCard;
