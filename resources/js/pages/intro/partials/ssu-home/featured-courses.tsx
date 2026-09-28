import { GoldCta, SheetKicker } from '@/components/ssu-public/chrome';
import { courseTabFor } from '@/lib/ssu-public';
import { IntroPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState, type CSSProperties } from 'react';

const FALLBACK_THUMB = '/assets/images/blank-image.svg';

const tabs = [
   { id: 'featured' as const, label: 'Featured' },
   { id: 'trade' as const, label: 'Trade estimating' },
   { id: 'software' as const, label: 'Software training' },
   { id: 'professional' as const, label: 'Professional development' },
];

const usePrefersReducedMotion = () => {
   const [reduced, setReduced] = useState(false);

   useEffect(() => {
      const media = window.matchMedia('(prefers-reduced-motion: reduce)');
      const sync = () => setReduced(media.matches);
      sync();
      media.addEventListener('change', sync);

      return () => media.removeEventListener('change', sync);
   }, []);

   return reduced;
};

const PublicCourseCard = ({ course, echo = false }: { course: Course; echo?: boolean }) => {
   const detailsUrl = route('course.details', { slug: course.slug, id: course.id });
   const category = course.course_category?.title || 'Estimating';

   const body = (
      <>
         <div className="aspect-[16/10] overflow-hidden bg-[color:var(--ssu-paper)]">
            <img
               src={course.thumbnail || FALLBACK_THUMB}
               alt={echo ? '' : course.title}
               className="h-full w-full object-cover"
               onError={(event) => {
                  (event.target as HTMLImageElement).src = FALLBACK_THUMB;
               }}
            />
         </div>
         <div className="flex flex-1 flex-col p-5">
            <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">{category}</p>
            <h3 className="ssu-pub-display mt-2 text-2xl text-[color:var(--ssu-navy)]">{course.title}</h3>
            <p className="mt-2 line-clamp-3 flex-1 text-sm leading-relaxed text-[color:var(--ssu-muted)]">
               {course.short_description || 'Construction-focused training with lessons, plans, and practical assessments.'}
            </p>
            <p className="mt-4 font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-muted)] uppercase">Software</p>
            <p className="mt-1 font-mono text-[10px] tracking-[0.14em] text-[color:var(--ssu-muted)] uppercase">
               Credential details on the course page
            </p>
            <span className="ssu-pub-cta mt-5 self-start">View course</span>
         </div>
      </>
   );

   if (echo) {
      return (
         <div className="ssu-catalog-card" aria-hidden>
            {body}
         </div>
      );
   }

   return (
      <Link href={detailsUrl} className="ssu-catalog-card">
         {body}
      </Link>
   );
};

const FeaturedCourses = () => {
   const { props } = usePage<IntroPageProps>();
   const featured = props.topCourses ?? [];
   const catalog = props.catalogCourses ?? featured;
   const [tab, setTab] = useState<(typeof tabs)[number]['id']>('featured');
   const prefersReducedMotion = usePrefersReducedMotion();

   const visible = useMemo(() => {
      if (tab === 'featured') {
         return featured.length ? featured : catalog.slice(0, 6);
      }

      const filtered = catalog.filter((course) => courseTabFor(course) === tab);

      return filtered.length ? filtered : catalog;
   }, [catalog, featured, tab]);

   const useMarquee = visible.length >= 3 && !prefersReducedMotion;
   const marqueeSeconds = Math.max(28, visible.length * 9);

   return (
      <section id="courses" className="overflow-hidden bg-[color:var(--ssu-navy)] py-20 text-white">
         <div id="course-list" className="container px-4">
            <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
               <div>
                  <SheetKicker label="The catalog" index="03" tone="gold" />
                  <h2 className="ssu-pub-display mt-5 max-w-3xl text-[clamp(2.2rem,5vw,4.4rem)] text-white">
                     Learn the skills the industry
                     <br />
                     uses.
                  </h2>
               </div>
               <GoldCta href={route('category.courses', { category: 'all' })}>View all courses</GoldCta>
            </div>

            <div className="mt-10 flex flex-wrap gap-2 border-b border-white/15 pb-px" role="tablist">
               {tabs.map((item) => (
                  <button
                     key={item.id}
                     type="button"
                     role="tab"
                     aria-selected={tab === item.id}
                     onClick={() => setTab(item.id)}
                     className={`px-3 py-3 text-[11px] font-semibold tracking-[0.16em] uppercase transition ${
                        tab === item.id
                           ? 'border-b-2 border-[color:var(--ssu-gold)] text-white'
                           : 'text-white/50 hover:text-white'
                     }`}
                  >
                     {item.label}
                  </button>
               ))}
            </div>
         </div>

         {visible.length > 0 ? (
            useMarquee ? (
               <div className="ssu-catalog-marquee mt-8">
                  <div
                     className="ssu-catalog-marquee__track"
                     style={{ '--ssu-marquee-duration': `${marqueeSeconds}s` } as CSSProperties}
                  >
                     <div className="ssu-catalog-marquee__set">
                        {visible.map((course) => (
                           <PublicCourseCard key={course.id} course={course} />
                        ))}
                     </div>
                     <div className="ssu-catalog-marquee__set" aria-hidden>
                        {visible.map((course) => (
                           <PublicCourseCard key={`${course.id}-echo`} course={course} echo />
                        ))}
                     </div>
                  </div>
               </div>
            ) : (
               <div className="container px-4">
                  <div className="ssu-catalog-grid mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                     {visible.map((course) => (
                        <PublicCourseCard key={course.id} course={course} />
                     ))}
                  </div>
               </div>
            )
         ) : (
            <div className="container px-4">
               <div className="mt-10 border border-white/15 bg-white/5 p-10 text-center">
                  <p className="text-sm text-white/70">New programs are on the way. Browse the catalog for current courses.</p>
                  <GoldCta href={route('category.courses', { category: 'all' })} className="mt-5">
                     Browse course catalog
                  </GoldCta>
               </div>
            </div>
         )}
      </section>
   );
};

export default FeaturedCourses;
