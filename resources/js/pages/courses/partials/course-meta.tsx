import RatingStars from '@/components/rating-stars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import courseLanguages from '@/data/course-languages';
import { getCourseDuration } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { Clock, GraduationCap, Languages, Users } from 'lucide-react';
import { ReactNode } from 'react';

const MetaItem = ({ children }: { children: ReactNode }) => (
   <li className="flex min-w-0 items-center gap-2 px-3 py-3 md:flex-1 md:justify-center md:border-l md:border-border/70 md:px-4 md:first:border-l-0">{children}</li>
);

const CourseMetaStrip = ({ course }: { course: Course }) => {
   const { props } = usePage<SharedData>();
   const { frontend } = props.translate;
   const { instructor, average_rating } = course;
   const courseLanguage = courseLanguages.find((language) => language.value === course.language);
   const rating = average_rating ? Number(average_rating).toFixed(1) : '0';

   return (
      <div className="ssu-surface-card relative z-20 -mt-10 mb-8 shadow-md md:-mt-12">
         <ul className="flex flex-wrap items-center md:flex-nowrap">
            {instructor?.user ? (
               <MetaItem>
                  <Link href={route('instructors.show', instructor.id)} className="group flex min-w-0 items-center gap-2">
                     <Avatar className="h-9 w-9">
                        <AvatarImage src={instructor.user.photo || ''} alt={instructor.user.name} className="object-cover" />
                        <AvatarFallback>{instructor.user.name?.[0]}</AvatarFallback>
                     </Avatar>
                     <span className="truncate text-sm font-medium group-hover:underline">{instructor.user.name}</span>
                  </Link>
               </MetaItem>
            ) : null}

            <MetaItem>
               <span className="text-sm font-semibold">{rating}</span>
               <RatingStars rating={average_rating || 0} starClass="h-4 w-4" />
            </MetaItem>

            {courseLanguage?.label ? (
               <MetaItem>
                  <Languages className="text-primary h-4 w-4 shrink-0" />
                  <span className="text-sm font-medium">{courseLanguage.label}</span>
               </MetaItem>
            ) : null}

            <MetaItem>
               <Clock className="text-primary h-4 w-4 shrink-0" />
               <span className="text-sm font-medium">{getCourseDuration(course)}</span>
            </MetaItem>

            <MetaItem>
               <Users className="text-primary h-4 w-4 shrink-0" />
               <span className="text-sm font-medium">
                  {course.enrollments_count || 0} {frontend.enrolled_students}
               </span>
            </MetaItem>

            <MetaItem>
               <GraduationCap className="text-primary h-4 w-4 shrink-0" />
               <span className="text-sm font-medium">{frontend.course_certificate}</span>
            </MetaItem>
         </ul>
      </div>
   );
};

export default CourseMetaStrip;
