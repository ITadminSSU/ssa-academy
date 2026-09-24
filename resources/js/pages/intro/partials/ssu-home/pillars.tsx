import { BadgeCheck, BookOpen, Clock, LucideIcon } from 'lucide-react';
import { getPageSection, getPropertyArray } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';

const iconMap: Record<string, LucideIcon> = {
   'book-open': BookOpen,
   clock: Clock,
   'badge-check': BadgeCheck,
};

const defaultPillars = [
   {
      icon: 'book-open',
      title: 'Structured Learning Paths',
      description: 'Step-by-step courses with video lessons and quizzes — designed to build skills you can apply on the job.',
   },
   {
      icon: 'clock',
      title: 'Learn at Your Pace',
      description: 'Access training anytime, track your progress, and pick up exactly where you left off — on desktop or mobile.',
   },
   {
      icon: 'badge-check',
      title: 'Verified Certification',
      description: 'Complete every lesson and quiz to earn SSA-verified credentials with unique reference numbers.',
   },
];

const defaultTagline = 'We help you build skills that matter in the real world.';

const Pillars = () => {
   const { props } = usePage<IntroPageProps>();
   const pillarsSection = props.page?.sections ? getPageSection(props.page, 'pillars') : undefined;
   const pillars = getPropertyArray(pillarsSection).filter((item) => item.title);
   const items = pillars.length ? pillars : defaultPillars;

   const rawTitle = pillarsSection?.title?.trim() || 'WHY SMARTSOURCING USA ACADEMY?';
   const sectionTitle = /^why\b/i.test(rawTitle)
      ? rawTitle.replace(/^why\b/i, 'WHY').replace(/\?*$/, '') + '?'
      : rawTitle;
   const tagline = pillarsSection?.sub_title?.trim() || defaultTagline;

   return (
      <section className="ssu-page-shell py-20">
         <div className="container space-y-10 px-4">
            <div className="mx-auto max-w-4xl space-y-3 text-center">
               <div className="flex items-center justify-center gap-4 md:gap-6">
                  <span className="bg-primary h-px w-10 shrink-0 sm:w-16 md:w-24" aria-hidden />
                  <h2 className="font-display text-primary text-2xl font-bold tracking-tight md:text-3xl">{sectionTitle}</h2>
                  <span className="bg-primary h-px w-10 shrink-0 sm:w-16 md:w-24" aria-hidden />
               </div>
               {tagline ? <p className="text-primary text-base italic md:text-lg">{tagline}</p> : null}
            </div>

            <div className="grid gap-6 md:grid-cols-3">
               {items.map((pillar, index) => {
                  const Icon = iconMap[pillar.icon as string] || BadgeCheck;

                  return (
                     <div key={index} className="ssu-surface-card relative overflow-hidden p-6">
                        <div className="bg-accent/10 text-accent mb-4 inline-flex rounded-xl p-3">
                           <Icon className="h-6 w-6" />
                        </div>
                        <h3 className="font-display mb-2 text-lg font-semibold">{pillar.title}</h3>
                        <p className="text-muted-foreground text-sm leading-relaxed">{pillar.description}</p>
                     </div>
                  );
               })}
            </div>
         </div>
      </section>
   );
};

export default Pillars;
