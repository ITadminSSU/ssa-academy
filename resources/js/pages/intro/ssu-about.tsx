import { Button } from '@/components/ui/button';
import LandingLayout from '@/layouts/landing-layout';
import { IntroPageProps } from '@/types/page';
import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, BookOpen, Target, Users } from 'lucide-react';
import CallToAction from './partials/ssu-home/call-to-action';

const values = [
   {
      icon: BookOpen,
      title: 'Practical learning',
      description: 'Courses built around real skills — video lessons, assignments, and assessments that mirror the work you do.',
   },
   {
      icon: Target,
      title: 'Clear outcomes',
      description: 'Every program is designed with a path to completion and credentials you can point to with confidence.',
   },
   {
      icon: BadgeCheck,
      title: 'Verified credentials',
      description: 'Earn SSU-verified certificates with unique reference numbers when you complete your program.',
   },
];

const team = [
   {
      name: 'Maria Santos',
      role: 'Lead Training Specialist',
      image: '/assets/images/ssu-about/about-team-1.png',
   },
   {
      name: 'James Mitchell',
      role: 'Program Director',
      image: '/assets/images/ssu-about/about-team-2.png',
   },
   {
      name: 'Elena Park',
      role: 'Curriculum Designer',
      image: '/assets/images/ssu-about/about-team-3.png',
   },
];

const SsuAbout = ({ system }: IntroPageProps) => {
   return (
      <LandingLayout navbarHeight={false} customizable={false}>
         <Head title={`About Us | ${system.fields.name}`} />

         <div className="ssu-page-shell">
            <section className="relative overflow-hidden bg-[oklch(0.22_0.04_255)] text-white">
               <div className="pointer-events-none absolute inset-0">
                  <div className="bg-primary/25 absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
                  <div className="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-[oklch(0.55_0.18_25)]/30 blur-3xl" />
               </div>

               <div className="relative container grid items-center gap-10 px-4 py-20 md:grid-cols-2 md:gap-16 md:py-28">
                  <div className="space-y-6">
                     <p className="ssu-kicker !text-white/80">About us</p>
                     <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-5xl">
                        Building skills that matter in the real world
                     </h1>
                     <p className="text-base leading-relaxed text-white/80 md:text-lg">
                        Smart Sourcing Academy is a professional learning platform for individuals and teams who want structured
                        training, hands-on practice, and verified certification — all in one place.
                     </p>
                     <Button asChild size="lg" className="bg-primary hover:bg-primary/90 rounded-full px-8">
                        <Link href={route('category.courses', { category: 'all' })}>Explore programs</Link>
                     </Button>
                  </div>

                  <div className="relative">
                     <div className="overflow-hidden rounded-2xl border border-white/10 shadow-2xl">
                        <img
                           src="/assets/images/ssu-about/about-hero.png"
                           alt="Professionals learning in a modern training environment"
                           className="aspect-[16/10] h-full w-full object-cover"
                        />
                     </div>
                  </div>
               </div>
            </section>

            <section className="py-20">
               <div className="container grid items-center gap-12 px-4 md:grid-cols-2">
                  <div className="space-y-5">
                     <p className="ssu-kicker">Our mission</p>
                     <h2 className="font-display text-2xl font-bold md:text-3xl">Upskill. Certify. Grow.</h2>
                     <p className="text-muted-foreground leading-relaxed">
                        We believe professional growth should be accessible, structured, and measurable. Smart Sourcing Academy
                        brings together video-based instruction, practical assignments, and gated assessments so learners can build
                        confidence — and prove it with credentials employers trust.
                     </p>
                     <p className="text-muted-foreground leading-relaxed">
                        Whether you are advancing your own career or supporting a team&apos;s development, our platform is built to
                        help you learn with purpose and finish with results.
                     </p>
                  </div>

                  <div className="ssu-surface-card p-8">
                     <div className="bg-primary/10 text-primary mb-4 inline-flex rounded-xl p-3">
                        <Users className="h-6 w-6" />
                     </div>
                     <h3 className="font-display mb-3 text-xl font-semibold">Who we serve</h3>
                     <ul className="text-muted-foreground space-y-3 text-sm leading-relaxed">
                        <li>Professionals seeking industry-relevant skills and certification</li>
                        <li>Teams looking for structured, trackable training programs</li>
                        <li>Partners and organizations investing in workforce development</li>
                     </ul>
                  </div>
               </div>
            </section>

            <section className="border-border/60 border-y bg-[oklch(0.97_0.01_255)] py-20 dark:bg-muted/20">
               <div className="container space-y-10 px-4">
                  <div className="mx-auto max-w-2xl text-center">
                     <p className="ssu-kicker">What we stand for</p>
                     <h2 className="font-display text-2xl font-bold md:text-3xl">Why learners choose us</h2>
                  </div>

                  <div className="grid gap-6 md:grid-cols-3">
                     {values.map(({ icon: Icon, title, description }) => (
                        <div key={title} className="ssu-surface-card p-6">
                           <div className="bg-primary/10 text-primary mb-4 inline-flex rounded-xl p-3">
                              <Icon className="h-6 w-6" />
                           </div>
                           <h3 className="font-display mb-2 text-lg font-semibold">{title}</h3>
                           <p className="text-muted-foreground text-sm leading-relaxed">{description}</p>
                        </div>
                     ))}
                  </div>
               </div>
            </section>

            <section className="py-20">
               <div className="container space-y-10 px-4">
                  <div className="mx-auto max-w-2xl text-center">
                     <p className="ssu-kicker">Our team</p>
                     <h2 className="font-display text-2xl font-bold md:text-3xl">The people behind the academy</h2>
                     <p className="text-muted-foreground mt-3 text-sm md:text-base">
                        Experienced educators and industry professionals dedicated to delivering training that makes a difference.
                     </p>
                  </div>

                  <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                     {team.map((member) => (
                        <div key={member.name} className="ssu-surface-card group overflow-hidden">
                           <div className="aspect-[3/4] overflow-hidden">
                              <img
                                 src={member.image}
                                 alt={member.name}
                                 className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                              />
                           </div>
                           <div className="p-5 text-center">
                              <h3 className="font-display text-lg font-semibold">{member.name}</h3>
                              <p className="text-muted-foreground text-sm">{member.role}</p>
                           </div>
                        </div>
                     ))}
                  </div>
               </div>
            </section>

            <CallToAction />
         </div>
      </LandingLayout>
   );
};

export default SsuAbout;
