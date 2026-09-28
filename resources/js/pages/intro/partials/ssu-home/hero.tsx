import HeroVideoPlayer from '@/components/hero-video-player';
import SamplePlan from '@/components/ssu-public/sample-plan';
import { GhostCta, GoldCta } from '@/components/ssu-public/chrome';
import { getPageSection } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { ClipboardList, Compass, KeyRound, ShieldCheck, Zap } from 'lucide-react';

const pillars = [
   { icon: KeyRound, title: 'Construction-focused', description: 'Training built around real construction workflows.' },
   { icon: Compass, title: 'U.S.-ready', description: 'Develop familiarity with U.S. terminology and project documentation.' },
   { icon: ClipboardList, title: 'Practical', description: 'Learn through lessons, assessments, and project scenarios.' },
   { icon: ShieldCheck, title: 'Verified', description: 'SSU-verified credentials with unique reference numbers.' },
   { icon: Zap, title: 'Flexible', description: 'Learn at your own pace through online training.' },
];

const Hero = () => {
   const { props } = usePage<IntroPageProps>();
   const heroSection = getPageSection(props.page, 'hero');
   const videoUrl = heroSection?.video_url?.trim() || null;
   const posterUrl = heroSection?.thumbnail?.trim() || null;

   return (
      <section id="academy">
         <div className="relative overflow-hidden bg-[color:var(--ssu-navy)] text-[color:var(--ssu-hero-type)]">
            <div className="pointer-events-none absolute inset-0" aria-hidden>
               <div className="absolute -right-24 top-[-6rem] h-[34rem] w-[34rem] rounded-full border border-[color:var(--ssu-gold)]/20" />
               <div className="absolute right-[-4rem] top-10 h-[26rem] w-[26rem] rounded-full border border-[color:var(--ssu-gold)]/30" />
            </div>

            <div className="relative container grid items-center gap-10 px-4 py-12 lg:grid-cols-[1.05fr_0.95fr] lg:py-16">
               <div className="max-w-3xl">
                  <p className="font-mono text-[11px] tracking-[0.22em] text-[color:var(--ssu-gold)] uppercase">
                     — SmartSourcing USA / Academy
                  </p>
                  <h1 className="ssu-pub-display mt-6 text-[clamp(2.4rem,6vw,4.6rem)] text-[#d8dee8]">
                     Build skills for the way U.S. construction works.
                  </h1>
                  <p className="mt-6 max-w-xl text-sm leading-relaxed text-white/75 md:text-base">
                     Build practical skills in U.S. construction workflows, estimating, software, and professional development.
                     Designed to help construction professionals become more prepared for remote and global opportunities.
                  </p>
                  <div className="mt-8 flex flex-wrap gap-3">
                     <GoldCta href={route('category.courses', { category: 'all' })}>Explore courses</GoldCta>
                     <GhostCta href="#how-it-works">How it works</GhostCta>
                  </div>
               </div>

               <div className="relative">
                  {videoUrl ? (
                     <div className="overflow-hidden border border-white/15 bg-black/30 shadow-2xl">
                        <HeroVideoPlayer videoUrl={videoUrl} posterUrl={posterUrl} className="aspect-video h-full w-full rounded-none border-0" />
                     </div>
                  ) : (
                     <SamplePlan className="min-h-[280px]" />
                  )}
                  <p className="mt-3 font-mono text-[10px] tracking-[0.18em] text-white/45 uppercase">U.S. workflow / welcome video</p>
               </div>
            </div>

            <div className="relative border-t border-white/10">
               <div className="container flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-[11px] tracking-wide text-white/70">
                  <div className="flex flex-wrap gap-x-6 gap-y-2">
                     <span>✓ Construction-focused training</span>
                     <span>✓ Practical learning</span>
                     <span>✓ Verified credentials</span>
                  </div>
                  <a href="#path" className="font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">
                     Scroll to review ↓
                  </a>
               </div>
            </div>
         </div>

         <div className="border-b border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)]">
            <div className="container grid gap-0 px-0 sm:grid-cols-2 lg:grid-cols-5">
               {pillars.map(({ icon: Icon, title, description }, index) => (
                  <div
                     key={title}
                     className="border-[color:var(--ssu-line)] px-5 py-7 sm:border-r lg:border-r last:border-r-0"
                  >
                     <div className="mb-4 flex items-start justify-between">
                        <Icon className="h-4 w-4 text-[color:var(--ssu-gold)]" />
                        <span className="font-mono text-[10px] text-[color:var(--ssu-muted)]">{String(index + 1).padStart(2, '0')}</span>
                     </div>
                     <h2 className="font-display text-sm font-semibold tracking-wide text-[color:var(--ssu-navy)] uppercase">{title}</h2>
                     <p className="mt-2 text-xs leading-relaxed text-[color:var(--ssu-muted)]">{description}</p>
                  </div>
               ))}
            </div>
         </div>
      </section>
   );
};

export default Hero;
