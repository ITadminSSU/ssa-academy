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
               <h2 className="ssu-pub-display mt-5 text-[clamp(2.4rem,5vw,4.6rem)] text-[color:var(--ssu-ink)]">
                  Your experience
                  <br />
                  <span className="text-[color:var(--ssu-gold)]">
                     matters, but
                     <br />
                     every market
                     <br />
                     works differently.
                  </span>
               </h2>
               <p className="mt-6 max-w-xl text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
                  Construction professionals may already understand the fundamentals. The next layer is familiarity with U.S.
                  construction workflows, estimating methods, terminology, digital tools, project documentation, remote
                  collaboration, and communication expectations.
               </p>
               <h3 className="ssu-pub-display mt-8 text-2xl text-[color:var(--ssu-navy)] md:text-3xl">
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

            <div className="relative overflow-hidden border border-[color:var(--ssu-line)] bg-[#f4f1e8] p-6">
               <div
                  className="pointer-events-none absolute inset-0 opacity-70"
                  aria-hidden
                  style={{
                     backgroundImage:
                        'linear-gradient(to right, rgb(26 52 79 / 8%) 1px, transparent 1px), linear-gradient(to bottom, rgb(26 52 79 / 8%) 1px, transparent 1px)',
                     backgroundSize: '32px 32px',
                  }}
               />
               <svg className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 w-full" viewBox="0 0 400 180" aria-hidden>
                  <path d="M0 170 L70 140 L140 118 L210 72 L280 88 L400 28 L400 180 L0 180 Z" fill="#e89a1b" fillOpacity="0.28" />
                  <path d="M0 170 L70 140 L140 118 L210 72 L280 88 L400 28" fill="none" stroke="#e89a1b" strokeWidth="2.5" />
               </svg>
               <p className="relative font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-muted)] uppercase">
                  SSU / Field translation
               </p>
               <ol className="relative mt-5">
                  {steps.map((step, index) => (
                     <li
                        key={step}
                        className={`flex items-center justify-between px-3 py-3 ${
                           index === 2 ? 'bg-[color:var(--ssu-gold)]/80' : ''
                        }`}
                     >
                        <span className="flex items-center gap-3">
                           <span
                              className={`h-2.5 w-2.5 rounded-full border border-[color:var(--ssu-navy)] ${
                                 index === 2 ? 'bg-[color:var(--ssu-navy)]' : 'bg-white'
                              }`}
                           />
                           <span className="ssu-pub-display text-lg text-[color:var(--ssu-navy)]">
                              {String(index + 1).padStart(2, '0')} {step}
                           </span>
                        </span>
                        <span className="text-[color:var(--ssu-gold)]">›</span>
                     </li>
                  ))}
               </ol>
               <p className="relative mt-6 text-right font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">
                  Foundation / build on it
               </p>
            </div>
         </div>
      </section>
   );
};

export default FieldNote;
