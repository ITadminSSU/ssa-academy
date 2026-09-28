import PublicFaqAccordion from '@/components/ssu-public/faq-accordion';
import { GoldCta, SheetKicker } from '@/components/ssu-public/chrome';
import { homeFaqs } from '@/lib/ssu-faqs';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';

const workflowItems = [
   'Construction plans',
   'PDF plan sets',
   'Estimating',
   'Takeoffs',
   'Terminology',
   'Project documentation',
   'Software',
   'Remote collaboration',
];

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

const whyItems = [
   { title: 'Construction focus', description: 'Training designed specifically around construction.' },
   { title: 'U.S. workflow', description: 'Develop familiarity with U.S. project processes and terminology.' },
   { title: 'Practical skills', description: 'Focus on skills that can be applied beyond the classroom.' },
   { title: 'Career development', description: 'Build knowledge, confidence, credentials, and professional readiness.' },
];

export const Workflow = () => (
   <section id="how-it-works" className="bg-[color:var(--ssu-navy)] py-20 text-white">
      <div className="container px-4">
         <SheetKicker label="Workflow translation" index="05" tone="gold" />
         <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.4rem)] text-white">
            Get familiar with how U.S. construction teams work.
         </h2>
         <p className="mt-6 max-w-3xl text-sm leading-relaxed text-white/70 md:text-base">
            The Academy does not simply teach individual software tools. It helps learners understand how plans, PDFs, estimates,
            takeoffs, terminology, documentation, software, and remote collaboration fit together.
         </p>
         <div className="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {workflowItems.map((item, index) => (
               <div key={item} className="border border-white/15 bg-white/5 px-4 py-5">
                  <p className="font-mono text-[10px] text-[color:var(--ssu-gold)]">{String(index + 1).padStart(2, '0')}</p>
                  <p className="ssu-pub-display mt-2 text-lg text-white">{item}</p>
               </div>
            ))}
         </div>
         <p className="ssu-pub-display mt-8 text-3xl text-[color:var(--ssu-gold)]">One workflow</p>
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
         <SheetKicker label="The sequence" index="07" />
         <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            A clear route from <span className="text-[color:var(--ssu-gold)]">interest to practice.</span>
         </h2>
         <div className="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            {sequence.map((step, index) => (
               <article key={step.title}>
                  <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)]">{String(index + 1).padStart(2, '0')}</p>
                  <h3 className="font-display mt-3 text-lg font-semibold tracking-wide text-[color:var(--ssu-navy)] uppercase">
                     {step.title}
                  </h3>
                  <p className="mt-2 text-sm leading-relaxed text-[color:var(--ssu-muted)]">{step.description}</p>
               </article>
            ))}
         </div>
      </div>
   </section>
);

export const Credential = () => (
   <section className="border-y border-[color:var(--ssu-line)] bg-[color:var(--ssu-navy)] py-20 text-white">
      <div className="container grid items-center gap-12 px-4 lg:grid-cols-2">
         <div>
            <SheetKicker label="Proof of practice" index="08" tone="gold" />
            <h2 className="ssu-pub-display mt-5 text-[clamp(2.2rem,5vw,4.4rem)]">
               Learn it. Prove it.
               <br />
               Build your credentials.
            </h2>
            <p className="mt-6 max-w-xl text-sm leading-relaxed text-white/75 md:text-base">
               Complete your program and earn an SSU-verified credential with a unique reference number.
            </p>
            <div className="mt-8 flex flex-wrap gap-3 font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">
               <span className="border border-[color:var(--ssu-gold)]/40 px-3 py-2">SSU-verified</span>
               <span className="border border-white/20 px-3 py-2">Reference number</span>
               <span className="border border-white/20 px-3 py-2">Digital record</span>
            </div>
         </div>
         <div className="border border-white/15 bg-[#14283d] p-8">
            <p className="font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">SSU-verified credential</p>
            <h3 className="font-display mt-8 text-2xl">Learner Name</h3>
            <p className="mt-2 text-sm text-white/60">has completed</p>
            <p className="font-display mt-3 text-xl">Course / Program Name</p>
            <div className="mt-8 flex justify-between font-mono text-[10px] tracking-[0.14em] text-white/45 uppercase">
               <span>Completion date / —</span>
               <span>Ref / SSU-000000</span>
            </div>
         </div>
      </div>
   </section>
);

export const Roadmap = () => (
   <section id="roadmap" className="bg-[color:var(--ssu-cream)] py-20">
      <div className="container px-4">
         <SheetKicker label="The roadmap" index="09" />
         <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            Your path from experience to U.S.-ready skills.
         </h2>
         <div className="mt-12 grid gap-6 md:grid-cols-5">
            {path.map((step, index) => (
               <article key={step.title} className="border-t-2 border-[color:var(--ssu-gold)] pt-5">
                  <p className="font-mono text-[10px] text-[color:var(--ssu-gold)]">{String(index + 1).padStart(2, '0')}</p>
                  <h3 className="font-display mt-2 text-lg font-semibold tracking-wide text-[color:var(--ssu-navy)] uppercase">{step.title}</h3>
                  <p className="mt-2 text-sm text-[color:var(--ssu-muted)]">{step.description}</p>
               </article>
            ))}
         </div>
      </div>
   </section>
);

export const Why = () => (
   <section id="why" className="border-y border-[color:var(--ssu-line)] bg-[#f3f1ea] py-20">
      <div className="container px-4">
         <SheetKicker label="Why the Academy" index="10" />
         <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            Built around the way construction professionals actually work.
         </h2>
         <div className="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            {whyItems.map((item, index) => (
               <article key={item.title} className="bg-[color:var(--ssu-cream)] p-6">
                  <p className="font-mono text-[10px] text-[color:var(--ssu-gold)]">{String(index + 1).padStart(2, '0')}</p>
                  <h3 className="font-display mt-3 text-lg font-semibold tracking-wide text-[color:var(--ssu-navy)] uppercase">{item.title}</h3>
                  <p className="mt-2 text-sm text-[color:var(--ssu-muted)]">{item.description}</p>
               </article>
            ))}
         </div>

         <div className="mt-16 grid gap-8 border border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)] p-8 lg:grid-cols-2">
            <div>
               <SheetKicker label="The ecosystem" index="11" />
               <h3 className="ssu-pub-display mt-4 text-3xl text-[color:var(--ssu-ink)]">Part of the SmartSourcing USA ecosystem.</h3>
               <p className="mt-4 text-sm leading-relaxed text-[color:var(--ssu-muted)]">
                  SMARTSOURCING USA ACADEMY is the learning and development platform of SMARTSOURCING USA, built to help
                  construction professionals strengthen their skills and prepare for opportunities in a global construction
                  environment.
               </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
               <div className="border border-[color:var(--ssu-line)] p-5">
                  <p className="font-mono text-[10px] text-[color:var(--ssu-gold)]">Path / A</p>
                  <h4 className="font-display mt-2 text-lg font-semibold uppercase">Academy</h4>
                  <p className="mt-2 text-sm text-[color:var(--ssu-muted)]">Learn · Practice · Certify</p>
               </div>
               <div className="border border-[color:var(--ssu-line)] p-5">
                  <p className="font-mono text-[10px] text-[color:var(--ssu-gold)]">Path / B</p>
                  <h4 className="font-display mt-2 text-lg font-semibold uppercase">SmartSourcing USA</h4>
                  <p className="mt-2 text-sm text-[color:var(--ssu-muted)]">Opportunities · Teams · Professional growth</p>
               </div>
            </div>
            <p className="text-xs text-[color:var(--ssu-muted)] lg:col-span-2">
               Academy enrollment does not guarantee employment, placement, clients, income, or employment with SmartSourcing USA.
            </p>
         </div>
      </div>
   </section>
);

export const Stats = () => (
   <section className="bg-[color:var(--ssu-cream)] py-20">
      <div className="container px-4">
         <SheetKicker label="The build" index="12" />
         <h2 className="ssu-pub-display mt-5 text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
            What learners are building.
         </h2>
         <p className="mt-4 max-w-2xl text-sm text-[color:var(--ssu-muted)]">
            A future-ready section for verified Academy data, when it is available.
         </p>
         <div className="mt-8 border border-dashed border-[color:var(--ssu-line)] bg-white px-6 py-12 text-center">
            <p className="font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">Data placeholder / editable</p>
            <p className="mt-4 text-4xl text-[color:var(--ssu-navy)]">—</p>
            <p className="mt-3 text-sm text-[color:var(--ssu-muted)]">
               Learners, completions, credentials, and training hours can be added here from verified Academy records.
            </p>
         </div>
      </div>
   </section>
);

export const Instructors = () => {
   const { props } = usePage<IntroPageProps>();
   const members = props.teamMembers ?? [];
   const cards = members.length
      ? members
      : [
           { id: 1, name: 'Name pending', role: 'Instructor profile / A', photo: null },
           { id: 2, name: 'Name pending', role: 'Instructor profile / B', photo: null },
           { id: 3, name: 'Name pending', role: 'Instructor profile / C', photo: null },
        ];

   return (
      <section className="border-y border-[color:var(--ssu-line)] bg-[#f3f1ea] py-20">
         <div className="container px-4">
            <SheetKicker label="The practitioners" index="13" />
            <h2 className="ssu-pub-display mt-5 text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
               Learn from people who know the work.
            </h2>
            <div className="mt-12 grid gap-6 md:grid-cols-3">
               {cards.map((member) => (
                  <article key={member.id} className="overflow-hidden border border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)]">
                     <div className="flex aspect-[4/5] items-center justify-center bg-[color:var(--ssu-paper)]">
                        {member.photo ? (
                           <img src={member.photo} alt={member.name} className="h-full w-full object-cover" />
                        ) : (
                           <p className="font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-muted)] uppercase">Photo pending</p>
                        )}
                     </div>
                     <div className="p-5">
                        <h3 className="font-display text-lg font-semibold text-[color:var(--ssu-navy)]">{member.name}</h3>
                        <p className="mt-1 text-sm text-[color:var(--ssu-muted)]">
                           {member.role ||
                              'Role, construction specialization, short bio, and courses taught will be added from verified practitioner details.'}
                        </p>
                     </div>
                  </article>
               ))}
            </div>
         </div>
      </section>
   );
};

export const HomeFaqs = () => (
   <section id="faqs" className="bg-[color:var(--ssu-cream)] py-20">
      <div className="container px-4">
         <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
               <SheetKicker label="Field guide" index="14" />
               <h2 className="ssu-pub-display mt-5 text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
                  Questions, answered clearly.
               </h2>
               <p className="mt-4 max-w-2xl text-sm text-[color:var(--ssu-muted)]">
                  We keep the details direct. As Academy programs expand, this guide will be updated with course-specific
                  information.
               </p>
            </div>
            <a href="#top" className="ssu-pub-login">
               Back to the top
            </a>
         </div>
         <div className="mt-10">
            <PublicFaqAccordion faqs={homeFaqs} />
         </div>
      </div>
   </section>
);
