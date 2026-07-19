import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { getPageSection } from '@/lib/page';
import { cn } from '@/lib/utils';
import { IntroPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';

const Hero = () => {
   const { props } = usePage<IntroPageProps>();
   const heroSection = getPageSection(props.page, 'hero');

   return (
      <section className="relative overflow-hidden bg-[oklch(0.22_0.04_255)] text-white">
         <div className="pointer-events-none absolute inset-0">
            <div className="bg-primary/25 absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
            <div className="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-[oklch(0.55_0.18_25)]/30 blur-3xl" />
         </div>

         <div className={cn('relative container flex flex-col items-center gap-10 px-4 py-20 md:flex-row md:gap-16 md:py-28')}>
            <div className="w-full space-y-6 md:max-w-xl">
               <AppLogo theme="dark" className="ssu-nav-logo" />

               {heroSection?.title && <p className="ssu-kicker text-primary-foreground/90 !text-white/80">{heroSection.title}</p>}

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-5xl lg:leading-[1.15]">
                  {heroSection?.sub_title}
               </h1>

               {heroSection?.description && <p className="text-base leading-relaxed text-white/80 md:text-lg">{heroSection.description}</p>}

               <div className="flex flex-wrap gap-3 pt-2">
                  {heroSection?.properties?.button_text && (
                     <Button asChild size="lg" className="bg-primary hover:bg-primary/90 rounded-full px-8">
                        <Link href={heroSection.properties.button_link || route('category.courses', { category: 'all' })}>
                           {heroSection.properties.button_text}
                        </Link>
                     </Button>
                  )}

                  {heroSection?.properties?.secondary_button_text && (
                     <Button
                        asChild
                        size="lg"
                        variant="outline"
                        className="rounded-full border-white/30 bg-transparent px-8 text-white hover:bg-white/10 hover:text-white"
                     >
                        <Link href={heroSection.properties.secondary_button_link || route('login')}>
                           {heroSection.properties.secondary_button_text}
                        </Link>
                     </Button>
                  )}
               </div>
            </div>

            <div className="relative w-full max-w-lg">
               <div className="ssu-surface-card border-white/10 bg-white/5 p-8 backdrop-blur-sm">
                  <div className="space-y-5">
                     <div className="flex items-center gap-3">
                        <div className="bg-primary h-2 w-2 rounded-full" />
                        <p className="text-sm font-medium text-white/90">Linear learning workflow</p>
                     </div>
                     <ul className="space-y-3 text-sm text-white/75">
                        <li>1. Complete assigned video lessons</li>
                        <li>2. Submit assignments for trainer review</li>
                        <li>3. Pass gated quizzes with leaderboard tracking</li>
                        <li>4. Earn SSU-verified certificates</li>
                     </ul>
                  </div>
               </div>
            </div>
         </div>
      </section>
   );
};

export default Hero;
