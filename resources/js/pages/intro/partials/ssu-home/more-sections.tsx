import PublicFaqAccordion from '@/components/ssu-public/faq-accordion';
import { GoldCta, SheetKicker } from '@/components/ssu-public/chrome';
import WorkflowRadar from '@/components/ssu-public/workflow-radar';
import { BRAND_LOGOS } from '@/lib/branding';
import { homeFaqs } from '@/lib/ssu-faqs';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, BarChart3, Compass, FileText, Hammer, Hash, Scan } from 'lucide-react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState, type CSSProperties, type KeyboardEvent as ReactKeyboardEvent, type Ref } from 'react';

const tools = ['PlanSwift', 'Bluebeam', 'On-Screen Takeoff', 'Primavera', 'ZZ Takeoff', 'AutoCAD', 'Revit', 'Procore'];

const sequence = [
   { title: 'Create your account', description: 'Preview the Academy and set up your learning path.' },
   { title: 'Choose your course', description: 'Select a construction-focused topic that fits your next step.' },
   { title: 'Learn + practice', description: 'Work through lessons, plans, documents, and assessments.' },
   { title: 'Complete + earn your credential', description: 'Review your completion and applicable credential details.' },
];

const path = [
   { title: 'Start', description: 'Assess where you are today.' },
   { title: 'Learn', description: 'Build technical and professional skills.' },
   { title: 'Practice', description: 'Work through construction-focused scenarios.' },
   { title: 'Certify', description: 'Complete your assessment and earn your credential.' },
   { title: 'Grow', description: 'Use your strengthened skills as you pursue new opportunities.' },
];

const credentialPoints = [
   { icon: BadgeCheck, label: 'SSU-verified' },
   { icon: Hash, label: 'Reference number' },
   { icon: FileText, label: 'Digital record' },
];

const whyItems = [
   { title: 'Construction focus', description: 'Training designed specifically around construction.', icon: Hammer },
   { title: 'U.S. workflow', description: 'Develop familiarity with U.S. project processes and terminology.', icon: Scan },
   { title: 'Practical skills', description: 'Focus on skills that can be applied beyond the classroom.', icon: BarChart3 },
   { title: 'Career development', description: 'Build knowledge, confidence, credentials, and professional readiness.', icon: Compass },
];

export const Workflow = () => (
   <section id="how-it-works" className="bg-[color:var(--ssu-navy)] py-20 text-white">
      <div className="container grid items-center gap-8 px-4 sm:gap-10 lg:grid-cols-[minmax(0,0.4fr)_minmax(0,0.6fr)] lg:gap-16">
         <div>
            <SheetKicker label="Workflow translation" index="05" tone="gold" />
            <h2 className="ssu-pub-display mt-5 text-[clamp(2.15rem,8vw,4.6rem)] text-white">
               Get familiar
               <br />
               with how U.S.
               <br />
               construction
               <br />
               <span className="text-[color:var(--ssu-gold)]">teams work.</span>
            </h2>
            <p className="mt-6 max-w-sm text-sm leading-relaxed text-white/70 md:text-base">
               The Academy does not simply teach individual software tools. It helps learners understand how plans, PDFs, estimates,
               takeoffs, terminology, documentation, software, and remote collaboration fit together.
            </p>
         </div>
         <WorkflowRadar />
      </div>
   </section>
);

export const Tools = () => (
   <section id="resources" className="border-y border-[color:var(--ssu-line)] bg-[#f3f1ea] py-20">
      <div className="container px-4">
         <SheetKicker label="Toolset" index="06" />
         <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            Get comfortable with the tools behind the work.
         </h2>
         <p className="mt-6 max-w-3xl text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
            Explore training designed around commonly used construction software and workflows. Tool coverage varies by course;
            review each course for its specific focus.
         </p>
         <div className="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {tools.map((tool, index) => (
               <div key={tool} className="border border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)] px-4 py-5">
                  <p className="font-mono text-[10px] text-[color:var(--ssu-gold)]">{String(index + 1).padStart(2, '0')}</p>
                  <p className="font-display mt-2 text-lg font-semibold text-[color:var(--ssu-navy)]">{tool}</p>
               </div>
            ))}
         </div>
      </div>
   </section>
);

export const Sequence = () => (
   <section className="bg-[color:var(--ssu-cream)] py-20">
      <div className="container px-4">
         <div className="flex justify-center">
            <SheetKicker label="The sequence" index="07" />
         </div>
         <h2 className="ssu-pub-display mt-5 text-center text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            A clear route from <span className="text-[color:var(--ssu-gold)]">interest to</span>
            <br />
            <span className="text-[color:var(--ssu-gold)]">to practice.</span>
         </h2>

         <ol className="ssu-sequence mt-14">
            {sequence.map((step, index) => (
               <li key={step.title} className="ssu-sequence__step">
                  <span className="ssu-sequence__mark">{String(index + 1).padStart(2, '0')}</span>
                  <h3 className="ssu-pub-display mt-5 text-xl text-[color:var(--ssu-navy)]">{step.title}</h3>
                  <p className="mt-2 max-w-[16rem] text-sm leading-relaxed text-[color:var(--ssu-muted)]">{step.description}</p>
               </li>
            ))}
         </ol>
      </div>
   </section>
);

export const Credential = () => (
   <section className="bg-[color:var(--ssu-navy)] py-20 text-white">
      <div className="container grid items-center gap-12 px-4 lg:grid-cols-2 lg:gap-16">
         <div>
            <SheetKicker label="Proof of practice" index="08" tone="gold" />
            <h2 className="ssu-pub-display mt-5 text-[clamp(2.2rem,5vw,4.6rem)]">
               <span className="text-white">Learn it. Prove it.</span>
               <br />
               <span className="text-[color:var(--ssu-gold)]">
                  Build your
                  <br />
                  credentials.
               </span>
            </h2>
            <p className="mt-6 max-w-md text-sm leading-relaxed text-white/75 md:text-base">
               Complete your program and earn an SSU-verified credential with a unique reference number.
            </p>
            <ul className="mt-8 space-y-3">
               {credentialPoints.map((point) => (
                  <li key={point.label} className="flex items-center gap-3 font-mono text-[11px] tracking-[0.16em] text-white/70 uppercase">
                     <point.icon className="h-4 w-4 text-[color:var(--ssu-gold)]" strokeWidth={1.75} aria-hidden />
                     {point.label}
                  </li>
               ))}
            </ul>
         </div>

         <div className="bg-[color:var(--ssu-gold)] p-1.5 shadow-[0_24px_50px_rgb(0_0_0_/_28%)]">
            <div className="relative bg-[#f6f1e6] px-8 py-9 text-center text-[color:var(--ssu-navy)]">
               <img src={BRAND_LOGOS.dark} alt="SMARTSOURCING USA ACADEMY" className="mx-auto h-10 w-auto object-contain" />
               <p className="mt-4 font-mono text-[10px] tracking-[0.2em] text-[color:var(--ssu-muted)] uppercase">Credential</p>
               <p className="mt-6 font-serif text-3xl text-[color:var(--ssu-navy)] italic sm:text-4xl">Learner Name</p>
               <p className="mt-2 text-sm text-[color:var(--ssu-muted)]">has completed</p>
               <p className="mt-3 text-base font-semibold tracking-wide text-[color:var(--ssu-navy)]">Course / Program Name</p>
               <div className="mt-10 flex items-end justify-between font-mono text-[9px] tracking-[0.14em] text-[color:var(--ssu-muted)] uppercase">
                  <span>Completion date / —</span>
                  <span>Ref / SSU-000000</span>
               </div>
               <span className="absolute right-3 bottom-3 h-6 w-6 border-r-2 border-b-2 border-[color:var(--ssu-gold)]" aria-hidden />
            </div>
         </div>
      </div>
   </section>
);

export const Roadmap = () => (
   <section id="roadmap" className="bg-[#e6ebe8] py-20">
      <div className="container px-4">
         <div className="flex items-start justify-between gap-6">
            <SheetKicker label="The roadmap" index="09" />
            <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-navy)]/40 uppercase">Drawing / 09-A</p>
         </div>
         <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            Your path from experience
            <br />
            <span className="text-[color:var(--ssu-gold)]">to U.S.-ready skills.</span>
         </h2>

         <div className="relative mt-14">
            <ol className="ssu-roadmap">
               {path.map((step, index) => (
                  <li key={step.title} className="ssu-roadmap__step">
                     {index > 0 ? <span className="ssu-roadmap__dot" aria-hidden /> : null}
                     <span className="ssu-roadmap__mark">{String(index + 1).padStart(2, '0')}</span>
                     <h3 className="ssu-pub-display mt-5 text-xl text-[color:var(--ssu-navy)]">{step.title}</h3>
                     <p className="mt-2 max-w-[13rem] text-sm leading-relaxed text-[color:var(--ssu-muted)]">{step.description}</p>
                  </li>
               ))}
            </ol>
         </div>
      </div>
   </section>
);

export const Why = () => (
   <section id="why" className="bg-[color:var(--ssu-cream)] py-20">
      <div className="container px-4">
         <div className="flex justify-center">
            <SheetKicker label="Why the Academy" index="10" />
         </div>
         <h2 className="ssu-pub-display mx-auto mt-5 max-w-4xl text-center text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            Built around the way
            <br />
            construction
            <br />
            professionals <span className="text-[color:var(--ssu-gold)]">actually</span>
            <br />
            <span className="text-[color:var(--ssu-gold)]">work.</span>
         </h2>
         <div className="mt-14 grid divide-y divide-[color:var(--ssu-navy)]/12 border-y border-[color:var(--ssu-navy)]/12 md:grid-cols-2 md:divide-x xl:grid-cols-4 xl:divide-y-0">
            {whyItems.map((item, index) => (
               <article key={item.title} className="px-5 py-8 md:px-7">
                  <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)]">
                     {String(index + 1).padStart(2, '0')}
                  </p>
                  <item.icon className="mt-5 h-7 w-7 text-[color:var(--ssu-gold)]" strokeWidth={1.5} aria-hidden />
                  <h3 className="ssu-pub-display mt-5 text-xl text-[color:var(--ssu-navy)]">{item.title}</h3>
                  <p className="mt-2 max-w-[16rem] text-sm leading-relaxed text-[color:var(--ssu-muted)]">{item.description}</p>
               </article>
            ))}
         </div>
      </div>
   </section>
);

export const Ecosystem = () => (
   <section className="bg-[color:var(--ssu-navy)] py-20 text-white">
      <div className="container px-4">
         <div className="flex justify-center">
            <SheetKicker label="The ecosystem" index="11" tone="gold" />
         </div>
         <h2 className="ssu-pub-display mx-auto mt-5 max-w-5xl text-center text-[clamp(2.2rem,5vw,4.6rem)]">
            Part of the SmartSourcing
            <br />
            USA <span className="text-[color:var(--ssu-gold)]">ecosystem.</span>
         </h2>
         <p className="mx-auto mt-6 max-w-2xl text-center text-sm leading-relaxed text-white/70 md:text-base">
            SMARTSOURCING USA ACADEMY is the learning and development platform of SMARTSOURCING USA, built to help construction
            professionals strengthen their skills and prepare for opportunities in a global construction environment.
         </p>

         <div className="mt-14 flex flex-col items-stretch gap-4 lg:flex-row lg:items-center lg:gap-0">
            <article className="flex-1 border border-white/15 bg-[#15283c] px-8 py-10">
               <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">Path / A</p>
               <h3 className="ssu-pub-display mt-5 text-3xl text-white">Academy</h3>
               <p className="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-white/60">
                  <span>Learn</span>
                  <ArrowRight className="h-3.5 w-3.5 text-[color:var(--ssu-gold)]" aria-hidden />
                  <span>Practice</span>
                  <ArrowRight className="h-3.5 w-3.5 text-[color:var(--ssu-gold)]" aria-hidden />
                  <span>Certify</span>
               </p>
            </article>

            <div className="flex items-center justify-center gap-2 px-3 text-[color:var(--ssu-gold)] lg:px-4" aria-hidden>
               <span className="hidden h-px w-8 bg-[color:var(--ssu-gold)] lg:block" />
               <ArrowRight className="h-4 w-4" />
               <span className="hidden h-px w-8 bg-[color:var(--ssu-gold)] lg:block" />
            </div>

            <article className="flex-1 bg-[#eef1ed] px-8 py-10 text-[color:var(--ssu-navy)]">
               <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">Path / B</p>
               <h3 className="ssu-pub-display mt-5 text-3xl">SmartSourcing USA</h3>
               <p className="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[color:var(--ssu-muted)]">
                  <span>Opportunities</span>
                  <ArrowRight className="h-3.5 w-3.5 text-[color:var(--ssu-gold)]" aria-hidden />
                  <span>Teams</span>
                  <ArrowRight className="h-3.5 w-3.5 text-[color:var(--ssu-gold)]" aria-hidden />
                  <span>Professional Growth</span>
               </p>
            </article>
         </div>

         <p className="mx-auto mt-10 max-w-3xl text-center text-[11px] leading-relaxed text-white/45">
            Academy enrollment does not guarantee employment, placement, clients, income, or employment with SmartSourcing USA.
         </p>
      </div>
   </section>
);

export const Stats = () => (
   <section className="bg-[#e6ebe8] py-20">
      <div className="container grid items-center gap-12 px-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-16">
         <div>
            <SheetKicker label="The build" index="12" />
            <h2 className="ssu-pub-display mt-5 text-[clamp(2.4rem,5.2vw,4.6rem)] text-[color:var(--ssu-ink)]">
               What learners are
               <br />
               <span className="text-[color:var(--ssu-gold)]">building.</span>
            </h2>
            <p className="mt-5 max-w-md text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
               A future-ready section for verified Academy data, when it is available.
            </p>
         </div>

         <div className="border border-dashed border-[color:var(--ssu-navy)]/20 bg-[#f4f1ea] px-8 py-14 text-center">
            <BarChart3 className="mx-auto h-7 w-7 text-[color:var(--ssu-gold)]" strokeWidth={1.5} aria-hidden />
            <p className="mt-4 font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">
               Data placeholder / editable
            </p>
            <span className="mx-auto mt-5 block h-px w-10 bg-[color:var(--ssu-navy)]" aria-hidden />
            <p className="mx-auto mt-5 max-w-sm text-sm leading-relaxed text-[color:var(--ssu-muted)]">
               Learners, completions, credentials, and training hours can be added here from verified Academy records.
            </p>
         </div>
      </div>
   </section>
);

const PLACEHOLDER_BIO =
   'Role, construction specialization, short bio, and courses taught will be added from verified practitioner details.';

type PublicTeamMember = NonNullable<IntroPageProps['teamMembers']>[number];

type InstructorCardData = {
   id: number | string;
   name: string;
   photo: string | null;
   role: string;
   bio: string;
};

const PENDING_CARDS: InstructorCardData[] = (['A', 'B', 'C'] as const).map((letter) => ({
   id: letter,
   name: 'Name pending',
   photo: null,
   role: '',
   bio: PLACEHOLDER_BIO,
}));

const cardFromMember = (member: PublicTeamMember): InstructorCardData => {
   const role = member.role?.trim() || '';
   const description = member.short_description?.trim() || '';
   const hasRealRole = role.length > 0 && !/^instructor profile/i.test(role);

   return {
      id: member.id,
      name: member.name?.trim() || 'Name pending',
      photo: member.photo || null,
      role: hasRealRole ? role : '',
      bio: description,
   };
};

const usePrefersReducedMotion = () => {
   const [reduced, setReduced] = useState(() =>
      typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches,
   );

   useEffect(() => {
      const media = window.matchMedia('(prefers-reduced-motion: reduce)');
      const sync = () => setReduced(media.matches);
      sync();
      media.addEventListener('change', sync);

      return () => media.removeEventListener('change', sync);
   }, []);

   return reduced;
};

const useMarqueeRepeats = (enabled: boolean, itemKey: string) => {
   const wrapRef = useRef<HTMLDivElement>(null);
   const unitRef = useRef<HTMLDivElement>(null);
   const [repeats, setRepeats] = useState(2);

   useLayoutEffect(() => {
      if (!enabled) {
         return;
      }

      const update = () => {
         const unit = unitRef.current?.scrollWidth ?? 0;
         const wrap = wrapRef.current?.clientWidth ?? 0;

         if (unit < 1 || wrap < 1) {
            return;
         }

         setRepeats(Math.min(8, Math.max(1, Math.ceil(wrap / unit))));
      };

      update();

      const observer = new ResizeObserver(update);

      if (wrapRef.current) {
         observer.observe(wrapRef.current);
      }

      if (unitRef.current) {
         observer.observe(unitRef.current);
      }

      window.addEventListener('resize', update);

      return () => {
         observer.disconnect();
         window.removeEventListener('resize', update);
      };
   }, [enabled, itemKey]);

   return { wrapRef, unitRef, repeats };
};

const InstructorCard = ({
   card,
   flipped,
   interactive,
   onFlip,
   onUnflip,
}: {
   card: InstructorCardData;
   flipped: boolean;
   interactive: boolean;
   onFlip: () => void;
   onUnflip: () => void;
}) => {
   const bioRef = useRef<HTMLParagraphElement>(null);
   const [isClamped, setIsClamped] = useState(false);

   useLayoutEffect(() => {
      const el = bioRef.current;

      if (!el) {
         setIsClamped(false);
         return;
      }

      setIsClamped(el.scrollHeight > el.clientHeight + 1);
   }, [card.bio]);

   const handleCardKeyDown = (event: ReactKeyboardEvent<HTMLElement>) => {
      if (!interactive) {
         return;
      }

      if (event.key !== 'Enter' && event.key !== ' ') {
         return;
      }

      if ((event.target as HTMLElement).closest('button')) {
         return;
      }

      event.preventDefault();
      flipped ? onUnflip() : onFlip();
   };

   return (
      <article
         className={`ssu-practitioner-flip h-full ${flipped ? 'is-flipped' : ''}`}
         aria-hidden={interactive ? undefined : true}
         aria-expanded={interactive ? flipped : undefined}
         aria-label={interactive ? `${card.name} biography card` : undefined}
         tabIndex={interactive ? 0 : -1}
         onKeyDown={handleCardKeyDown}
      >
         <div className="ssu-practitioner-flip__inner">
            <div className="ssu-practitioner-flip__face ssu-practitioner-flip__front">
               <button
                  type="button"
                  tabIndex={interactive ? 0 : -1}
                  aria-label={`Show full biography for ${card.name}`}
                  onClick={onFlip}
                  className="relative flex aspect-[3/4] w-full items-center justify-center overflow-hidden bg-[#d5ddd8]"
               >
                  {card.photo ? (
                     <img
                        src={card.photo}
                        alt={interactive ? card.name : ''}
                        className="absolute inset-0 h-full w-full object-contain object-center"
                     />
                  ) : (
                     <>
                        <span className="absolute h-[72%] max-h-40 w-[72%] max-w-40 rounded-full border border-[#8fa09a]/55" aria-hidden />
                        <span className="absolute h-[48%] max-h-[6.75rem] w-[48%] max-w-[6.75rem] rounded-full border border-[#8fa09a]/80" aria-hidden />
                        <p className="relative font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-navy)]/45 uppercase">
                           Photo pending
                        </p>
                     </>
                  )}
               </button>
               <div className="flex flex-1 flex-col px-5 py-5">
                  <h3 className="text-xl font-semibold text-[color:var(--ssu-navy)]">{card.name}</h3>
                  {card.role ? <p className="mt-1 text-sm text-[color:var(--ssu-muted)]">{card.role}</p> : null}
                  {card.bio ? (
                     <p
                        ref={bioRef}
                        className="mt-2 line-clamp-4 min-h-[5.5rem] text-sm leading-relaxed break-words whitespace-pre-wrap text-[color:var(--ssu-muted)]"
                     >
                        {card.bio}
                     </p>
                  ) : null}
                  {isClamped ? (
                     <button
                        type="button"
                        tabIndex={interactive ? 0 : -1}
                        onClick={onFlip}
                        className="mt-auto pt-3 text-left text-[11px] font-semibold tracking-[0.16em] text-[color:var(--ssu-navy)] uppercase"
                     >
                        Read more
                     </button>
                  ) : null}
               </div>
            </div>

            <div className="ssu-practitioner-flip__face ssu-practitioner-flip__back">
               <div className="flex h-full min-h-0 flex-col px-5 py-5">
                  <h3 className="text-xl font-semibold text-[color:var(--ssu-navy)]">{card.name}</h3>
                  {card.role ? <p className="mt-1 text-sm text-[color:var(--ssu-muted)]">{card.role}</p> : null}
                  {card.bio ? (
                     <p className="mt-4 min-h-0 flex-1 overflow-y-auto text-sm leading-relaxed break-words whitespace-pre-wrap text-[color:var(--ssu-muted)]">
                        {card.bio}
                     </p>
                  ) : (
                     <div className="min-h-0 flex-1" />
                  )}
                  <button
                     type="button"
                     tabIndex={interactive ? 0 : -1}
                     onClick={onUnflip}
                     className="mt-4 text-left text-[11px] font-semibold tracking-[0.16em] text-[color:var(--ssu-navy)] uppercase"
                  >
                     Flip back
                  </button>
               </div>
            </div>
         </div>
      </article>
   );
};

const InstructorSet = ({
   cards,
   repeatKey,
   unitRef,
   flippedKey,
   interactive,
   onFlip,
   onUnflip,
}: {
   cards: InstructorCardData[];
   repeatKey: string;
   unitRef?: Ref<HTMLDivElement>;
   flippedKey: string | null;
   interactive: boolean;
   onFlip: (instanceKey: string) => void;
   onUnflip: () => void;
}) => (
   <div ref={unitRef} className="ssu-practitioners-marquee__unit">
      {cards.map((card, index) => {
         const instanceKey = interactive ? String(card.id) : `${card.id}-clone-${repeatKey}-${index}`;

         return (
            <InstructorCard
               key={instanceKey}
               card={card}
               flipped={flippedKey === instanceKey}
               interactive={interactive}
               onFlip={() => onFlip(instanceKey)}
               onUnflip={onUnflip}
            />
         );
      })}
   </div>
);

export const Instructors = () => {
   const { props } = usePage<IntroPageProps>();
   const members = props.teamMembers ?? [];
   const prefersReducedMotion = usePrefersReducedMotion();
   const [flippedKey, setFlippedKey] = useState<string | null>(null);
   const cards = useMemo(
      () => (members.length > 0 ? members.map(cardFromMember) : PENDING_CARDS),
      [members],
   );
   const useMarquee = members.length >= 3 && !prefersReducedMotion;
   const marqueeKey = cards.map((card) => card.id).join('-');
   const { wrapRef, unitRef, repeats } = useMarqueeRepeats(useMarquee, marqueeKey);
   const marqueeSeconds = Math.max(36, cards.length * repeats * 8);

   useEffect(() => {
      if (!flippedKey) {
         return;
      }

      const onKeyDown = (event: KeyboardEvent) => {
         if (event.key === 'Escape') {
            setFlippedKey(null);
         }
      };

      window.addEventListener('keydown', onKeyDown);

      return () => window.removeEventListener('keydown', onKeyDown);
   }, [flippedKey]);

   const renderCard = (card: InstructorCardData, instanceKey: string, interactive: boolean) => (
      <InstructorCard
         key={instanceKey}
         card={card}
         flipped={flippedKey === instanceKey}
         interactive={interactive}
         onFlip={() => setFlippedKey(instanceKey)}
         onUnflip={() => setFlippedKey(null)}
      />
   );

   return (
      <section className="overflow-x-hidden bg-[color:var(--ssu-cream)] py-20">
         <div className="container px-4">
            <SheetKicker label="The practitioners" index="13" />
            <h2 className="ssu-pub-display mt-5 max-w-3xl text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
               Learn from people who
               <br />
               <span className="text-[color:var(--ssu-gold)]">know the work.</span>
            </h2>
         </div>

         {useMarquee ? (
            <div
               ref={wrapRef}
               className={`ssu-practitioners-marquee mt-12 ${flippedKey ? 'is-paused' : ''}`}
            >
               <div
                  className="ssu-practitioners-marquee__track"
                  style={{ '--ssu-marquee-duration': `${marqueeSeconds}s` } as CSSProperties}
               >
                  {[0, 1].map((copy) => (
                     <div key={copy} className="ssu-practitioners-marquee__set" aria-hidden={copy > 0 || undefined}>
                        {Array.from({ length: repeats }, (_, repeat) => (
                           <InstructorSet
                              key={`${copy}-${repeat}`}
                              cards={cards}
                              repeatKey={`${copy}-${repeat}`}
                              unitRef={copy === 0 && repeat === 0 ? unitRef : undefined}
                              flippedKey={flippedKey}
                              interactive={copy === 0 && repeat === 0}
                              onFlip={setFlippedKey}
                              onUnflip={() => setFlippedKey(null)}
                           />
                        ))}
                     </div>
                  ))}
               </div>
            </div>
         ) : (
            <div className="container px-4">
               <div className="ssu-practitioners-grid mt-12 grid items-stretch gap-5 sm:grid-cols-2 xl:grid-cols-3">
                  {cards.map((card) => renderCard(card, String(card.id), true))}
               </div>
            </div>
         )}
      </section>
   );
};

export const HomeFaqs = () => (
   <section id="faqs" className="bg-[#e6ebe8] py-20">
      <div className="container grid items-start gap-12 px-4 lg:grid-cols-[minmax(0,0.38fr)_minmax(0,0.62fr)] lg:gap-16">
         <div>
            <SheetKicker label="Field guide" index="14" />
            <h2 className="ssu-pub-display mt-5 text-[clamp(2.4rem,5vw,4.6rem)] text-[color:var(--ssu-ink)]">
               Questions,
               <br />
               <span className="text-[color:var(--ssu-gold)]">
                  answered
                  <br />
                  clearly.
               </span>
            </h2>
            <p className="mt-5 max-w-sm text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
               We keep the details direct. As Academy programs expand, this guide will be updated with course-specific information.
            </p>
            <a
               href="#top"
               className="mt-8 inline-flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] text-[color:var(--ssu-navy)] uppercase"
            >
               Back to the top
               <ArrowRight className="h-3.5 w-3.5" />
            </a>
         </div>
         <PublicFaqAccordion faqs={homeFaqs} />
      </div>
   </section>
);
