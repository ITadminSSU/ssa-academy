import { GoldCta, SheetKicker } from '@/components/ssu-public/chrome';
import { ArrowRight } from 'lucide-react';

const paths = [
   {
      title: 'Students & fresh graduates',
      description: 'Build foundational construction and digital skills.',
      image: '/assets/images/ssu-home/path-students.png',
      alt: 'Student in a hard hat reviewing a building model on a tablet in class',
   },
   {
      title: 'Construction professionals',
      description: 'Expand existing experience with U.S.-focused workflows and tools.',
      image: '/assets/images/ssu-home/path-professionals.png',
      alt: 'Construction crew reviewing blueprints on a job site',
   },
   {
      title: 'Aspiring construction VAs',
      description: 'Develop practical skills for remote construction support roles.',
      image: '/assets/images/ssu-home/path-vas.png',
      alt: 'Remote construction coordinator on a video call at a home desk',
   },
   {
      title: 'Experienced specialists',
      description: 'Strengthen technical knowledge and adapt skills to new workflows.',
      image: '/assets/images/ssu-home/path-specialists.png',
      alt: 'Specialist presenting a building model on a large display',
   },
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
            {paths.map((path) => (
               <article key={path.title} className="overflow-hidden border border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream)]">
                  <div className="aspect-[16/10] overflow-hidden bg-[color:var(--ssu-navy)]">
                     <img src={path.image} alt={path.alt} className="h-full w-full object-cover" />
                  </div>
                  <div className="relative p-5 pr-12">
                     <h3 className="ssu-pub-display text-xl text-[color:var(--ssu-navy)]">{path.title}</h3>
                     <p className="mt-3 text-sm leading-relaxed text-[color:var(--ssu-muted)]">{path.description}</p>
                     <ArrowRight className="absolute right-5 bottom-5 h-4 w-4 text-[color:var(--ssu-gold)]" aria-hidden />
                  </div>
               </article>
            ))}
         </div>
      </div>
   </section>
);

export default Audience;
