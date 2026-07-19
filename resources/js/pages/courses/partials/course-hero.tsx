import CourseBannerPlaceholder from '@/components/course-banner-placeholder';
import RatingStars from '@/components/rating-stars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import courseLanguages from '@/data/course-languages';
import { getCourseDuration } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { Clock, GraduationCap, Languages, Users } from 'lucide-react';

const Chip = ({ icon: Icon, children }: { icon: typeof Clock; children: React.ReactNode }) => (
   <span className="inline-flex items-center gap-2 rounded-full bg-card/10 px-3 py-1.5 text-sm font-medium text-white ring-1 ring-white/15 backdrop-blur-sm">
      <Icon className="h-4 w-4 opacity-90" />
      {children}
   </span>
);

const CourseHero = ({ course }: { course: Course }) => {
   const { props } = usePage<SharedData>();
   const { frontend } = props.translate;
   const { title, short_description, instructor, average_rating } = course;
   const courseLanguage = courseLanguages.find((language) => language.value === course.language);
   const backdrop = course.banner || course.thumbnail;

   return (
      <section className="relative isolate overflow-hidden bg-gradient-to-br from-[oklch(0.20_0.003_255)] via-[oklch(0.24_0.003_257)] to-[oklch(0.30_0.004_255)] text-white">
         {backdrop ? (
            <>
               <img src={backdrop} alt="" className="absolute inset-0 h-full w-full object-cover opacity-20" />
               <div className="absolute inset-0 bg-gradient-to-r from-[oklch(0.20_0.003_255)]/95 via-[oklch(0.22_0.003_255)]/85 to-[oklch(0.22_0.003_255)]/60" />
            </>
         ) : (
            <div
               className="absolute inset-0 opacity-[0.10]"
               style={{
                  backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)',
                  backgroundSize: '20px 20px',
               }}
            />
         )}

         <div className="relative container py-14 md:py-20">
            <div className="max-w-3xl space-y-5">
               {course.course_category?.title && <p className="ssu-kicker !text-white/70">{course.course_category.title}</p>}

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-5xl">{title}</h1>

               {short_description && <p className="max-w-2xl text-base text-white/80 md:text-lg">{short_description}</p>}

               <div className="flex flex-wrap items-center gap-4 pt-1">
                  <Link href={route('instructors.show', instructor.id)} className="group flex items-center gap-2">
                     <Avatar className="ring-2 ring-white/30">
                        <AvatarImage src={instructor.user.photo || ''} alt={instructor.user.name} className="object-cover" />
                        <AvatarFallback className="text-foreground">{instructor.user.name[0]}</AvatarFallback>
                     </Avatar>
                     <span className="font-medium text-white group-hover:underline">{instructor.user.name}</span>
                  </Link>

                  <div className="flex items-center gap-1">
                     <span className="font-semibold">{average_rating ? Number(average_rating).toFixed(1) : 0}</span>
                     <RatingStars rating={average_rating || 0} starClass="h-4 w-4" />
                  </div>
               </div>

               <div className="flex flex-wrap gap-2.5 pt-2">
                  <Chip icon={Languages}>{courseLanguage?.label}</Chip>
                  <Chip icon={Clock}>{getCourseDuration(course)}</Chip>
                  <Chip icon={Users}>
                     {course.enrollments_count || 0} {frontend.enrolled_students}
                  </Chip>
                  <Chip icon={GraduationCap}>{frontend.course_certificate}</Chip>
               </div>
            </div>
         </div>

         {!backdrop && (
            <CourseBannerPlaceholder
               showTitle={false}
               className="pointer-events-none absolute right-0 bottom-0 hidden h-40 w-40 rounded-tl-[3rem] opacity-40 lg:block"
            />
         )}
      </section>
   );
};

export default CourseHero;
