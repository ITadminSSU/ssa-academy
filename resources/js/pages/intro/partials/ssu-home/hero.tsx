import { Button } from '@/components/ui/button';
import { getPageSection } from '@/lib/page';
import { cn } from '@/lib/utils';
import { IntroPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, ClipboardCheck, PlayCircle, Trophy } from 'lucide-react';

const defaultHero = {
   kicker: 'SMART SOURCING ACADEMY',
   title: 'Upskill. Certify your skills. Scale with confidence.',
   description:
      'Structured learning paths for professionals — video lessons, assignments, quizzes, and verified SSU certificates.',
   primaryText: 'Browse Courses',
   primaryLink: '/courses/all',
   secondaryText: 'Sign In',
   secondaryLink: '/login',
};

const heroHighlights = [
   {
      icon: PlayCircle,
      title: 'Watch & learn',
      description: 'Structured video lessons you can take at your own pace.',
   },
   {
      icon: ClipboardCheck,
      title: 'Practice & apply',
      description: 'Assignments that turn concepts into real-world skills.',
   },
   {
      icon: Trophy,
      title: 'Prove your progress',
      description: 'Quizzes and assessments that validate what you know.',
   },
   {
      icon: BadgeCheck,
      title: 'Earn credentials',
      description: 'SSU-verified certificates with unique reference numbers.',
   },
];

const Hero = () => {
   const { props } = usePage<IntroPageProps>();
   const heroSection = getPageSection(props.page, 'hero');

   const kicker = heroSection?.title || defaultHero.kicker;
   const title = heroSection?.sub_title || defaultHero.title;
   const description = heroSection?.description || defaultHero.description;
   const primaryText = heroSection?.properties?.button_text || defaultHero.primaryText;
   const primaryLink = heroSection?.properties?.button_link || route('category.courses', { category: 'all' });
   const secondaryText = heroSection?.properties?.secondary_button_text || defaultHero.secondaryText;
   const secondaryLink = heroSection?.properties?.secondary_button_link || route('login');

   return (
      <section className="relative overflow-hidden bg-[oklch(0.22_0.04_255)] text-white">
         <div className="pointer-events-none absolute inset-0">
            <div className="bg-primary/25 absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
            <div className="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-[oklch(0.55_0.18_25)]/30 blur-3xl" />
         </div>

         <div className={cn('relative container flex flex-col items-center gap-10 px-4 py-20 md:flex-row md:gap-16 md:py-28')}>
            <div className="w-full space-y-6 md:max-w-xl">
               <p className="ssu-kicker text-primary-foreground/90 !text-white/80">{kicker}</p>

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-5xl lg:leading-[1.15]">{title}</h1>

               <p className="text-base leading-relaxed text-white/80 md:text-lg">{description}</p>

               <div className="flex flex-wrap gap-3 pt-2">
                  <Button asChild size="lg" className="bg-primary hover:bg-primary/90 rounded-full px-8">
                     <Link href={primaryLink}>{primaryText}</Link>
                  </Button>

                  <Button
                     asChild
                     size="lg"
                     variant="outline"
                     className="rounded-full border-white/30 bg-transparent px-8 text-white hover:bg-white/10 hover:text-white"
                  >
                     <Link href={secondaryLink}>{secondaryText}</Link>
                  </Button>
               </div>
            </div>

            <div className="relative w-full max-w-lg">
               <div className="rounded-2xl border border-white/10 bg-white/10 p-6 shadow-sm backdrop-blur-sm md:p-7">
                  <p className="mb-5 text-sm font-semibold tracking-wide text-white/90 uppercase">Your path to certification</p>

                  <div className="grid gap-3 sm:grid-cols-2">
                     {heroHighlights.map(({ icon: Icon, title: highlightTitle, description: highlightDescription }) => (
                        <div
                           key={highlightTitle}
                           className="rounded-xl border border-white/10 bg-white/5 p-4 transition-colors hover:bg-white/10"
                        >
                           <div className="mb-3 inline-flex rounded-lg bg-white/20 p-2.5 text-white">
                              <Icon className="h-5 w-5" strokeWidth={2} />
                           </div>
                           <p className="mb-1 text-sm font-semibold text-white">{highlightTitle}</p>
                           <p className="text-xs leading-relaxed text-white/70">{highlightDescription}</p>
                        </div>
                     ))}
                  </div>
               </div>
            </div>
         </div>
      </section>
   );
};

export default Hero;
