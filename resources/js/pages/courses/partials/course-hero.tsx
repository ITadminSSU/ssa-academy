import CourseBannerPlaceholder from '@/components/course-banner-placeholder';
import CourseLaunchCountdown from '@/components/course-launch-countdown';
import HeroVideoPlayer from '@/components/hero-video-player';
import { heroHeadlineParts, type CourseHeroContent } from '@/lib/course-hero-text';
import { canPreviewCourseBeforeLaunch, formatCourseLaunchDate, isCourseComingSoon } from '@/lib/course-launch';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const CourseHero = ({ course }: { course: Course }) => {
   const { props } = usePage<SharedData & { courseHero?: CourseHeroContent }>();
   const { frontend } = props.translate;
   const hero = props.courseHero;
   const headline = hero?.headline || course.title;
   const paragraph = hero?.paragraph || course.short_description;
   const eyebrow = hero?.eyebrow || course.course_category?.title;
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

         <div className="relative container grid items-center gap-10 pt-14 pb-20 md:grid-cols-[minmax(0,1.05fr)_minmax(280px,0.95fr)] md:pt-20 md:pb-24">
            {video ? (
               <div className="overflow-hidden rounded-2xl border border-black/40 bg-black/40 shadow-lg">
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
