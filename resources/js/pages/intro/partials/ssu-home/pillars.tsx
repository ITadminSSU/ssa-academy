import { getPageSection, getPropertyArray } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { BadgeCheck, Globe, LucideIcon, Users } from 'lucide-react';

const iconMap: Record<string, LucideIcon> = {
   users: Users,
   globe: Globe,
   'badge-check': BadgeCheck,
};

const Pillars = () => {
   const { props } = usePage<IntroPageProps>();
   const pillarsSection = getPageSection(props.page, 'pillars');
   const pillars = getPropertyArray(pillarsSection).filter((item) => item.title);

   if (!pillars.length) {
      return null;
   }

   return (
      <section className="ssu-page-shell py-20">
         <div className="container space-y-10 px-4">
            {pillarsSection?.title && <h2 className="font-display text-center text-2xl font-bold md:text-3xl">{pillarsSection.title}</h2>}

            <div className="grid gap-6 md:grid-cols-3">
               {pillars.map((pillar, index) => {
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
