import { GoldCta, SheetKicker } from '@/components/ssu-public/chrome';

const paths = [
   { title: 'Students & fresh graduates', description: 'Build foundational construction and digital skills.' },
   { title: 'Construction professionals', description: 'Expand existing experience with U.S.-focused workflows and tools.' },
   { title: 'Aspiring construction VAs', description: 'Develop practical skills for remote construction support roles.' },
   { title: 'Experienced specialists', description: 'Strengthen technical knowledge and adapt skills to new workflows.' },
];

const Audience = () => (
   <section className="border-y border-[color:var(--ssu-line)] bg-[#f3f1ea] py-20">
      <div className="container px-4">
         <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
               <SheetKicker label="The workbench" index="02" />
               <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
                  Wherever you are in your construction journey,{' '}
                  <span className="text-[color:var(--ssu-gold)]">there&apos;s room to grow.</span>
               </h2>
            </div>
            <GoldCta href="#courses">Find your path</GoldCta>
         </div>

         <div className="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {paths.map((path, index) => (
               <article key={path.title} className="overflow-hidden border border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)]">
                  <div className="relative flex aspect-[16/10] items-end bg-[color:var(--ssu-navy)] p-4">
                     <div
                        className="pointer-events-none absolute inset-0 opacity-40"
                        aria-hidden
                        style={{
                           backgroundImage:
                              'linear-gradient(to right, rgb(232 154 27 / 35%) 1px, transparent 1px), linear-gradient(to bottom, rgb(232 154 27 / 25%) 1px, transparent 1px)',
                           backgroundSize: '24px 24px',
                        }}
                     />
                     <p className="relative font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">
                        {String(index + 1).padStart(2, '0')} / Path
                     </p>
                  </div>
                  <div className="p-5">
                     <h3 className="ssu-pub-display text-xl text-[color:var(--ssu-navy)]">{path.title}</h3>
                     <p className="mt-3 text-sm leading-relaxed text-[color:var(--ssu-muted)]">{path.description}</p>
                  </div>
               </article>
            ))}
         </div>
      </div>
   </section>
);

export default Audience;
