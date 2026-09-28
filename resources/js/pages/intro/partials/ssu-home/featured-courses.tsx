import { GoldCta, SheetKicker } from '@/components/ssu-public/chrome';
import { courseTabFor } from '@/lib/ssu-public';
import { IntroPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const tabs = [
   { id: 'featured' as const, label: 'Featured' },
   { id: 'trade' as const, label: 'Trade estimating' },
   { id: 'software' as const, label: 'Software training' },
   { id: 'professional' as const, label: 'Professional development' },
];

const PublicCourseCard = ({ course, index }: { course: Course; index: number }) => {
   const detailsUrl = route('course.details', { slug: course.slug, id: course.id });
   const category = course.course_category?.title || 'Course';

   return (
      <article className="flex flex-col border border-[color:var(--ssu-line)] bg-white">
         <div className="relative aspect-[16/10] overflow-hidden bg-[color:var(--ssu-paper)]">
            <img
               src={course.thumbnail || '/assets/images/blank-image.jpg'}
               alt={course.title}
               className="h-full w-full object-cover"
               onError={(event) => {
                  (event.target as HTMLImageElement).src = '/assets/images/blank-image.jpg';
               }}
            />
            <span className="absolute top-3 left-3 font-mono text-[10px] tracking-[0.16em] text-white uppercase">
               {String(index + 1).padStart(2, '0')}
            </span>
         </div>
         <div className="flex flex-1 flex-col p-5">
            <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">{category}</p>
            <h3 className="font-display mt-2 text-xl font-semibold text-[color:var(--ssu-navy)]">{course.title}</h3>
            <p className="mt-2 line-clamp-3 flex-1 text-sm leading-relaxed text-[color:var(--ssu-muted)]">
               {course.short_description || 'Construction-focused training with lessons, plans, and practical assessments.'}
            </p>
            <p className="mt-4 font-mono text-[10px] tracking-[0.14em] text-[color:var(--ssu-muted)] uppercase">
               {course.level ? `${course.level} · ` : ''}
               Credential details on the course page
            </p>
            <Link href={detailsUrl} className="ssu-pub-cta mt-5 self-start">
               View course
            </Link>
         </div>
      </article>
   );
};

const FeaturedCourses = () => {
   const { props } = usePage<IntroPageProps>();
   const featured = props.topCourses ?? [];
   const catalog = props.catalogCourses ?? featured;
   const [tab, setTab] = useState<(typeof tabs)[number]['id']>('featured');

   const visible = useMemo(() => {
      if (tab === 'featured') {
         return featured.length ? featured : catalog.slice(0, 6);
      }

      const filtered = catalog.filter((course) => courseTabFor(course) === tab);
      return filtered.length ? filtered : catalog;
   }, [catalog, featured, tab]);

   return (
      <section id="courses" className="bg-[color:var(--ssu-cream)] py-20">
         <div id="course-list" className="container px-4">
            <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
               <div>
                  <SheetKicker label="The catalog" index="03" />
                  <h2 className="ssu-pub-display mt-5 max-w-3xl text-4xl text-[color:var(--ssu-ink)] md:text-5xl">
                     Learn the skills the industry uses.
                  </h2>
               </div>
               <GoldCta href={route('category.courses', { category: 'all' })}>View all courses</GoldCta>
            </div>

            <div className="mt-10 flex flex-wrap gap-2 border-b border-[color:var(--ssu-line)] pb-px" role="tablist">
               {tabs.map((item) => (
                  <button
                     key={item.id}
                     type="button"
                     role="tab"
                     aria-selected={tab === item.id}
                     onClick={() => setTab(item.id)}
                     className={`font-mono px-3 py-3 text-[11px] tracking-[0.16em] uppercase transition ${
                        tab === item.id
                           ? 'border-b-2 border-[color:var(--ssu-gold)] text-[color:var(--ssu-navy)]'
                           : 'text-[color:var(--ssu-muted)] hover:text-[color:var(--ssu-navy)]'
                     }`}
                  >
                     {item.label}
                  </button>
               ))}
            </div>

            {visible.length > 0 ? (
               <div className="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                  {visible.map((course, index) => (
                     <PublicCourseCard key={course.id} course={course} index={index} />
                  ))}
               </div>
            ) : (
               <div className="mt-10 border border-[color:var(--ssu-line)] bg-white p-10 text-center">
                  <p className="text-sm text-[color:var(--ssu-muted)]">New programs are on the way. Browse the catalog for current courses.</p>
                  <GoldCta href={route('category.courses', { category: 'all' })} className="mt-5">
                     Browse course catalog
                  </GoldCta>
               </div>
            )}
         </div>
      </section>
   );
};

export default FeaturedCourses;
