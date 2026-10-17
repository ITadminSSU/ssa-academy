import CourseBannerPlaceholder from '@/components/course-banner-placeholder';
import CourseLaunchCountdown from '@/components/course-launch-countdown';
import RatingStars from '@/components/rating-stars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import HeroVideoPlayer from '@/components/hero-video-player';
import courseLanguages from '@/data/course-languages';
import { heroHeadlineParts, type CourseHeroContent } from '@/lib/course-hero-text';
import { canPreviewCourseBeforeLaunch, formatCourseLaunchDate, isCourseComingSoon } from '@/lib/course-launch';
import { getCourseDuration } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { Clock, GraduationCap, Languages, Users } from 'lucide-react';

const Chip = ({ icon: Icon, children }: { icon: typeof Clock; children: React.ReactNode }) => (
   <span className="inline-flex items-center gap-2 rounded-full bg-card/10 px-3 py-1.5 text-sm font-medium text-white ring-1 ring-white/15 [transform:translateZ(0)]">
      <Icon className="h-4 w-4 opacity-90" />
      {children}
   </span>
);

const CourseHero = ({ course }: { course: Course }) => {
   const { props } = usePage<SharedData & { courseHero?: CourseHeroContent }>();
   const { frontend } = props.translate;
   const hero = props.courseHero;
   const { instructor, average_rating } = course;
   const headline = hero?.headline || course.title;
   const paragraph = hero?.paragraph || course.short_description;
   const eyebrow = hero?.eyebrow || course.course_category?.title;
   const courseLanguage = courseLanguages.find((language) => language.value === course.language);
   const backdrop = hero?.photo || course.banner || course.thumbnail;
   const video = hero?.video || course.preview;
   const comingSoon = isCourseComingSoon(course);
   const launchDate = formatCourseLaunchDate(course);
   const staffPreview = canPreviewCourseBeforeLaunch(course);
   const headlineParts = heroHeadlineParts(headline, hero?.highlights ?? []);

   return (
      <section className="relative isolate overflow-hidden bg-gradient-to-br from-primary via-primary-light to-primary text-white">
         {backdrop ? (
            <>
               <img src={backdrop} alt="" className="absolute inset-0 h-full w-full object-cover" />
               <div className="absolute inset-0 bg-gradient-to-r from-primary via-primary/90 to-primary/45" />
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

         <div className="relative container grid items-center gap-10 py-14 md:grid-cols-[minmax(0,1.05fr)_minmax(280px,0.95fr)] md:py-20">
            {video ? (
               <div className="overflow-hidden rounded-2xl border border-white/15 bg-black/40">
                  <HeroVideoPlayer videoUrl={video} posterUrl={backdrop} className="aspect-video" />
               </div>
            ) : null}

            <div className={video ? 'space-y-5' : 'max-w-3xl space-y-5 md:col-span-2'}>
               {eyebrow ? <p className="text-accent text-sm font-extrabold tracking-[0.14em] uppercase">{eyebrow}</p> : null}

               {comingSoon ? (
                  <div className="space-y-3">
                     <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex rounded-full bg-amber-400 px-3 py-1 text-xs font-bold tracking-wide text-amber-950 uppercase">
                           {frontend.coming_soon ?? 'Coming Soon'}
                        </span>
                        {staffPreview ? (
                           <span className="inline-flex rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide text-white uppercase">
                              {frontend.staff_preview ?? 'Staff preview'}
                           </span>
                        ) : null}
                        {launchDate ? (
                           <span className="text-sm font-medium text-amber-100">
                              {(frontend.launches_on ?? 'Launches {date}').replace('{date}', launchDate)}
                           </span>
                        ) : null}
                     </div>
                     {course.launch_at ? (
                        <div className="space-y-2">
                           <p className="text-xs font-semibold tracking-wide text-white/70 uppercase">{frontend.starts_in ?? 'Starts in'}</p>
                           <CourseLaunchCountdown launchAt={course.launch_at} />
                        </div>
                     ) : null}
                  </div>
               ) : null}

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-5xl">
                  {headlineParts.map((part, index) => (
                     <span key={`${part.text}-${index}`} className={part.accent ? 'text-accent' : undefined}>
                        {part.text}
                     </span>
                  ))}
               </h1>

               {paragraph ? <p className="max-w-2xl text-base text-white/80 md:text-lg">{paragraph}</p> : null}

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
