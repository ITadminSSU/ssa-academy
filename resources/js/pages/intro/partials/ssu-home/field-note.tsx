import { GoldCta, SheetKicker } from '@/components/ssu-public/chrome';

const steps = [
   'Your experience',
   'U.S. workflows',
   'Practical training',
   'Assessment',
   'Verified credential',
   'Career readiness',
];

const FieldNote = () => {
   return (
      <section id="path" className="bg-[color:var(--ssu-cream)] py-20">
         <div className="container grid items-center gap-12 px-4 lg:grid-cols-2">
            <div>
               <SheetKicker label="The field note" index="01" />
               <h2 className="ssu-pub-display mt-5 text-4xl text-[color:var(--ssu-ink)] md:text-5xl">
                  Your experience matters.{' '}
                  <span className="text-[color:var(--ssu-gold)]">But every market works differently.</span>
               </h2>
               <p className="mt-6 max-w-xl text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
                  Construction professionals may already understand the fundamentals. The next layer is familiarity with U.S.
                  construction workflows, estimating methods, terminology, digital tools, project documentation, remote
                  collaboration, and communication expectations.
               </p>
               <h3 className="font-display mt-8 text-xl font-semibold text-[color:var(--ssu-navy)] uppercase">
                  SmartSourcing USA Academy closes the gap.
               </h3>
               <p className="mt-3 max-w-xl text-sm leading-relaxed text-[color:var(--ssu-muted)]">
                  Build on what you already know. Learn how U.S. construction teams work. Develop practical skills you can apply
                  with confidence.
               </p>
               <div className="mt-8">
                  <GoldCta href="#roadmap">See the learning path</GoldCta>
               </div>
            </div>

            <div className="relative border border-[color:var(--ssu-line)] bg-[color:var(--ssu-paper)] p-6">
               <p className="font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-muted)] uppercase">SSU / Field translation</p>
               <ol className="mt-4">
                  {steps.map((step, index) => (
                     <li
                        key={step}
                        className={`flex items-center justify-between border-b border-[color:var(--ssu-line)] px-3 py-3 ${
                           index === 2 ? 'bg-[color:var(--ssu-gold)]/15' : ''
                        }`}
                     >
                        <span className="flex items-center gap-3">
                           <span className="h-2.5 w-2.5 rounded-full border border-[color:var(--ssu-navy)] bg-white" />
                           <span className="font-display text-sm font-semibold tracking-wide text-[color:var(--ssu-navy)] uppercase">
                              {String(index + 1).padStart(2, '0')} {step}
                           </span>
                        </span>
                        <span className="text-[color:var(--ssu-gold)]">›</span>
                     </li>
                  ))}
               </ol>
               <p className="mt-6 text-right font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">
                  Foundation / build on it
               </p>
            </div>
         </div>
      </section>
   );
};

export default FieldNote;
