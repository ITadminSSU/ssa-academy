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
         <div className="container grid items-stretch gap-12 px-4 lg:grid-cols-2 lg:gap-16">
            <div className="flex flex-col justify-center">
               <SheetKicker label="The field note" index="01" />
               <h2 className="ssu-pub-display mt-5 text-[clamp(2.6rem,5.4vw,5rem)] text-[color:var(--ssu-ink)]">
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
               <h3 className="ssu-pub-display mt-8 text-2xl text-[color:var(--ssu-navy)] md:text-[1.85rem]">
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

            <div className="relative flex min-h-[28rem] flex-col overflow-hidden border border-[color:var(--ssu-line)] bg-[#f4f1e8] p-6 md:min-h-[32rem] md:p-8 lg:min-h-full">
               <div
                  className="pointer-events-none absolute inset-0"
                  aria-hidden
                  style={{
                     backgroundImage:
                        'linear-gradient(to right, rgb(26 52 79 / 10%) 1px, transparent 1px), linear-gradient(to bottom, rgb(26 52 79 / 10%) 1px, transparent 1px)',
                     backgroundSize: '40px 40px',
                  }}
               />
               <svg
                  className="pointer-events-none absolute inset-0 h-full w-full"
                  viewBox="0 0 640 400"
                  preserveAspectRatio="none"
                  aria-hidden
               >
                  <path
                     d="M0 372 L90 318 L170 268 L268 148 L348 188 L430 168 L520 78 L640 42 L640 400 L0 400 Z"
                     fill="#e89a1b"
                     fillOpacity="0.42"
                  />
                  <path
                     d="M0 372 L90 318 L170 268 L268 148 L348 188 L430 168 L520 78 L640 42"
                     fill="none"
                     stroke="#e89a1b"
                     strokeWidth="3.5"
                     vectorEffect="non-scaling-stroke"
                  />
               </svg>

               <p className="relative font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-muted)] uppercase">
                  SSU / Field translation
               </p>

               <ol className="relative mt-8 flex flex-1 flex-col justify-center">
                  {steps.map((step, index) => (
                     <li
                        key={step}
                        className={`flex items-center justify-between px-3 py-3.5 md:px-4 md:py-4 ${
                           index === 2 ? 'bg-[color:var(--ssu-gold)]' : ''
                        }`}
                     >
                        <span className="flex items-center gap-3 md:gap-4">
                           <span
                              className={`h-3 w-3 shrink-0 rounded-full border-[1.5px] border-[color:var(--ssu-navy)] ${
                                 index === 2 ? 'bg-[color:var(--ssu-navy)]' : 'bg-transparent'
                              }`}
                           />
                           <span className="ssu-pub-display text-[1.15rem] text-[color:var(--ssu-navy)] md:text-[1.45rem]">
                              {String(index + 1).padStart(2, '0')} {step}
                           </span>
                        </span>
                        <span className="text-lg text-[color:var(--ssu-gold)] md:text-xl">›</span>
                     </li>
                  ))}
               </ol>

               <p className="relative mt-4 text-right font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">
                  Foundation / build on it
               </p>
            </div>
         </div>
      </section>
   );
};

export default FieldNote;
