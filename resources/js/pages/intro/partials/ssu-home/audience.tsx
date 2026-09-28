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
               <h2 className="ssu-pub-display mt-5 max-w-3xl text-4xl text-[color:var(--ssu-ink)] md:text-5xl">
                  Wherever you are in your construction journey, there&apos;s room to grow.
               </h2>
            </div>
            <GoldCta href="#courses">Find your path</GoldCta>
         </div>

         <div className="mt-12 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {paths.map((path, index) => (
               <article key={path.title} className="border border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)] p-6">
                  <p className="font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)] uppercase">
                     {String(index + 1).padStart(2, '0')} / Path
                  </p>
                  <h3 className="font-display mt-4 text-lg font-semibold tracking-wide text-[color:var(--ssu-navy)] uppercase">
                     {path.title}
                  </h3>
                  <p className="mt-3 text-sm leading-relaxed text-[color:var(--ssu-muted)]">{path.description}</p>
               </article>
            ))}
         </div>
      </div>
   </section>
);

export default Audience;
