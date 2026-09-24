import { Button } from '@/components/ui/button';
import LandingLayout from '@/layouts/landing-layout';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';

type Props = IntroPageProps & {
   facebookAcademyUrl?: string;
   facebookVaUrl?: string;
};

const academyBenefits = [
   { tone: 'navy' as const, text: 'Exclusive community for enrolled Academy students' },
   { tone: 'red' as const, text: 'Direct communication with Academy instructors' },
   { tone: 'red' as const, text: 'Industry discussions with fellow construction professionals' },
];

const vaBenefits = [
   { tone: 'navy' as const, text: 'Community for aspiring and experienced construction professionals' },
   { tone: 'navy' as const, text: 'Early access to SMARTSOURCING Academy updates' },
   { tone: 'red' as const, text: 'Updates on courses, programs, and opportunities' },
];

const SsuConnect = ({ system, facebookAcademyUrl, facebookVaUrl }: Props) => {
   const academyUrl = facebookAcademyUrl || 'https://www.facebook.com/share/g/14ttXqLttek/';
   const vaUrl = facebookVaUrl || 'https://www.facebook.com/groups/constructionvaacademy';

   return (
      <LandingLayout navbarHeight={true} customizable={false}>
         <Head title={`Connect with us | ${system.fields.name}`} />

         <div className="ssu-page-shell">
            <section className="relative overflow-hidden bg-primary text-white">
               <div className="absolute inset-0" aria-hidden>
                  <div
                     className="absolute inset-0 bg-cover bg-center"
                     style={{
                        backgroundImage: `linear-gradient(90deg, rgba(1,18,58,0.88) 0%, rgba(1,18,58,0.72) 45%, rgba(1,18,58,0.82) 100%), url('/assets/images/ssu-about/about-hero.png')`,
                     }}
                  />
                  <div
                     className="absolute inset-0 opacity-[0.15]"
                     style={{
                        backgroundImage:
                           'linear-gradient(rgba(255,255,255,0.35) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.35) 1px, transparent 1px)',
                        backgroundSize: '40px 40px',
                     }}
                  />
               </div>

               <div className="relative container flex min-h-[280px] flex-col items-center justify-center px-4 py-16 text-center md:min-h-[340px] md:py-24">
                  <h1 className="font-display text-3xl font-bold tracking-wide uppercase sm:text-4xl md:text-5xl">Connect with us</h1>
                  <p className="mt-4 max-w-2xl text-sm leading-relaxed text-white/85 sm:text-base md:text-lg">
                     Join the SSA Facebook Community — exclusive groups for enrolled Academy students and construction
                     professionals.
                  </p>
               </div>
            </section>

            <section className="bg-[#eef3f8] py-10 md:py-16">
               <div className="container px-4">
                  <div className="relative mx-auto max-w-6xl">
                     <img
                        src="/assets/images/ssu-connect/facebook-community.png"
                        alt="SSA Facebook Community. Left: SmartSourcing USA Academy — exclusive community for enrolled Academy students, direct communication with Academy instructors, and industry discussions with fellow construction professionals. Right: Construction VA Academy — community for aspiring and experienced construction professionals, early access to SMARTSOURCING Academy updates, and updates on courses, programs, and opportunities."
                        className="h-auto w-full"
                     />
                     <a
                        href={academyUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="absolute top-[28%] left-[12%] h-[38%] w-[32%] rounded-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        aria-label="Open SmartSourcing USA Academy on Facebook"
                     />
                     <a
                        href={vaUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="absolute top-[28%] right-[12%] h-[38%] w-[32%] rounded-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                        aria-label="Open Construction VA Academy on Facebook"
                     />
                  </div>

                  <div className="mx-auto mt-8 flex max-w-3xl flex-col items-center justify-center gap-3 sm:flex-row">
                     <Button asChild className="rounded-full px-6">
                        <a href={academyUrl} target="_blank" rel="noopener noreferrer">
                           Join SmartSourcing USA Academy
                        </a>
                     </Button>
                     <Button asChild variant="outline" className="rounded-full px-6">
                        <a href={vaUrl} target="_blank" rel="noopener noreferrer">
                           Join Construction VA Academy
                        </a>
                     </Button>
                  </div>

                  <div className="mx-auto mt-12 grid max-w-5xl gap-6 md:grid-cols-2">
                     <div className="space-y-3">
                        <h2 className="font-display text-primary text-lg font-bold">SmartSourcing USA Academy</h2>
                        {academyBenefits.map((item) => (
                           <p
                              key={item.text}
                              className={
                                 item.tone === 'red'
                                    ? 'border-accent text-primary border px-4 py-3 text-sm font-semibold tracking-wide uppercase'
                                    : 'border-primary text-primary border px-4 py-3 text-sm font-semibold tracking-wide uppercase'
                              }
                           >
                              {item.text}
                           </p>
                        ))}
                     </div>
                     <div className="space-y-3">
                        <h2 className="font-display text-primary text-lg font-bold">Construction VA Academy</h2>
                        {vaBenefits.map((item) => (
                           <p
                              key={item.text}
                              className={
                                 item.tone === 'red'
                                    ? 'border-accent text-primary border px-4 py-3 text-sm font-semibold tracking-wide uppercase'
                                    : 'border-primary text-primary border px-4 py-3 text-sm font-semibold tracking-wide uppercase'
                              }
                           >
                              {item.text}
                           </p>
                        ))}
                     </div>
                  </div>
               </div>
            </section>
         </div>
      </LandingLayout>
   );
};

export default SsuConnect;
