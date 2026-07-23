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
      description:
         'Step-by-step courses with video lessons, assignments, and quizzes — designed to build skills you can apply on the job.',
   },
   {
      icon: 'clock',
      title: 'Learn at Your Pace',
      description: 'Access training anytime, track your progress, and pick up exactly where you left off — on desktop or mobile.',
   },
   {
      icon: 'badge-check',
      title: 'Verified Certification',
      description: 'Complete every lesson, assignment, and quiz to earn SSU-verified credentials with unique reference numbers.',
   },
];



const Pillars = () => {

   const { props } = usePage<IntroPageProps>();

   const pillarsSection = getPageSection(props.page, 'pillars');

   const pillars = getPropertyArray(pillarsSection).filter((item) => item.title);

   const items = pillars.length ? pillars : defaultPillars;

   const sectionTitle = pillarsSection?.title || 'Why SMART SOURCING ACADEMY';



   return (

      <section className="ssu-page-shell py-20">

         <div className="container space-y-10 px-4">

            <h2 className="font-display text-center text-2xl font-bold md:text-3xl">{sectionTitle}</h2>



            <div className="grid gap-6 md:grid-cols-3">

               {items.map((pillar, index) => {

                  const Icon = iconMap[pillar.icon as string] || BadgeCheck;



                  return (

                     <div key={index} className="ssu-surface-card relative overflow-hidden p-6">

                        <div className="bg-primary/10 text-primary mb-4 inline-flex rounded-xl p-3">

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

