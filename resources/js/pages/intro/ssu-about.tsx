import { GoldCta, PublicPageHero, SheetKicker } from '@/components/ssu-public/chrome';
import LandingLayout from '@/layouts/landing-layout';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import { BadgeCheck, BookOpen, Target } from 'lucide-react';
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

interface TeamMember {
   id: number;
   name: string;
   role: string;
   short_description?: string | null;
   photo: string | null;
   sort_order: number;
}

interface AboutProps extends IntroPageProps {
   teamMembers: TeamMember[];
}

const SsuAbout = ({ system, teamMembers }: AboutProps) => {
   return (
      <LandingLayout navbarHeight={true} customizable={false}>
         <Head title={`About Us | ${system.fields.name}`} />

         <div className="ssu-page-shell">
            <PublicPageHero
               kicker="About"
               title="Building skills that matter in the real world"
               description="SMARTSOURCING USA ACADEMY is a professional learning platform for individuals and teams who want structured training, hands-on practice, and verified certification — all in one place."
            />

            <section className="py-20">
               <div className="container grid items-center gap-12 px-4 md:grid-cols-2">
                  <div>
                     <SheetKicker label="Our mission" index="01" />
                     <h2 className="ssu-pub-display mt-5 text-3xl text-[color:var(--ssu-ink)] md:text-4xl">Upskill. Certify. Grow.</h2>
                     <p className="mt-5 text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
                        We believe professional growth should be accessible, structured, and measurable. SMARTSOURCING USA
                        Academy combines video-based learning, practical assessments, and opportunities to gain U.S. industry
                        experience, helping professionals build relevant skills, strengthen their credentials, and become more
                        U.S.-ready.
                     </p>
                     <p className="mt-4 text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
                        Whether you are advancing your own career or preparing for remote opportunities with U.S. companies, our
                        platform is designed to help you learn with purpose, gain practical experience, and build a stronger
                        professional profile.
                     </p>
                     <div className="mt-8">
                        <GoldCta href={route('category.courses', { category: 'all' })}>Explore programs</GoldCta>
                     </div>
                  </div>
                  <div className="overflow-hidden border border-[color:var(--ssu-line)]">
                     <img
                        src="/assets/images/ssu-about/about-hero.png"
                        alt="Professionals learning in a modern training environment"
                        className="aspect-[16/10] h-full w-full object-cover"
                     />
                  </div>
               </div>
            </section>

            <section className="border-y border-[color:var(--ssu-line)] bg-[#f3f1ea] py-20">
               <div className="container space-y-10 px-4">
                  <div>
                     <SheetKicker label="What we stand for" index="02" />
                     <h2 className="ssu-pub-display mt-5 text-3xl text-[color:var(--ssu-ink)]">Why learners choose us</h2>
                  </div>
                  <div className="grid gap-6 md:grid-cols-3">
                     {values.map(({ icon: Icon, title, description }) => (
                        <div key={title} className="border border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)] p-6">
                           <Icon className="h-5 w-5 text-[color:var(--ssu-gold)]" />
                           <h3 className="font-display mt-4 text-lg font-semibold text-[color:var(--ssu-navy)]">{title}</h3>
                           <p className="mt-2 text-sm leading-relaxed text-[color:var(--ssu-muted)]">{description}</p>
                        </div>
                     ))}
                  </div>
               </div>
            </section>

            <section className="py-20">
               <div className="container space-y-10 px-4">
                  <div>
                     <SheetKicker label="The practitioners" index="03" />
                     <h2 className="ssu-pub-display mt-5 text-3xl text-[color:var(--ssu-ink)]">The people behind the academy</h2>
                     <p className="mt-3 max-w-2xl text-sm text-[color:var(--ssu-muted)]">
                        Experienced educators and industry professionals dedicated to delivering training that makes a difference.
                     </p>
                  </div>

                  {teamMembers.length > 0 && (
                     <div className="space-y-6">
                        {teamMembers.map((member) => (
                           <article
                              key={member.id}
                              id={`team-member-${member.id}`}
                              className="overflow-hidden border border-[color:var(--ssu-line)] bg-white md:grid md:grid-cols-[180px_minmax(0,1fr)]"
                           >
                              <div className="relative aspect-[3/4] w-full overflow-hidden bg-[color:var(--ssu-paper)] md:aspect-auto md:min-h-full">
                                 {member.photo ? (
                                    <img
                                       src={member.photo}
                                       alt={member.name}
                                       className="absolute inset-0 block h-full w-full object-cover object-center"
                                    />
                                 ) : (
                                    <div className="absolute inset-0 bg-[color:var(--ssu-paper)]" />
                                 )}
                              </div>
                              <div className="p-5 md:p-8">
                                 <h3 className="font-display text-xl font-semibold text-[color:var(--ssu-navy)]">{member.name}</h3>
                                 <p className="mt-1 text-sm text-[color:var(--ssu-muted)]">{member.role}</p>
                                 {member.short_description?.trim() ? (
                                    <p className="mt-4 max-w-prose text-sm leading-relaxed break-words whitespace-pre-wrap text-[color:var(--ssu-muted)]">
                                       {member.short_description}
                                    </p>
                                 ) : null}
                              </div>
                           </article>
                        ))}
                     </div>
                  )}
               </div>
            </section>

            <CallToAction />
         </div>
      </LandingLayout>
   );
};

export default SsuAbout;
