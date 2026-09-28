import { SheetKicker } from '@/components/ssu-public/chrome';
import SamplePlan from '@/components/ssu-public/sample-plan';
import { Check } from 'lucide-react';

const skills = [
   'Video lessons',
   'Interactive quizzes',
   'Practical assessments',
   'Construction plans',
   'Project documents',
   'Software-focused learning',
   'U.S. construction workflows',
   'Professional development resources',
];

const Practice = () => (
   <section className="bg-[color:var(--ssu-cream)] py-20">
      <div className="container grid items-center gap-12 px-4 lg:grid-cols-2 lg:gap-16">
         <div>
            <SheetKicker label="Inside the work" index="04" />
            <h2 className="ssu-pub-display mt-5 text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">
               Not just videos.
               <br />
               <span className="text-[color:var(--ssu-gold)]">
                  Build skills you
                  <br />
                  can use.
               </span>
            </h2>
            <p className="mt-6 max-w-xl text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
               The Academy is built around the documents, tools, and decisions that make construction work move. Explore a plan,
               inspect a measurement, and understand the workflow behind the answer.
            </p>
            <ul className="mt-8 grid gap-x-6 gap-y-2.5 sm:grid-cols-2">
               {skills.map((skill) => (
                  <li key={skill} className="flex items-start gap-2 text-sm text-[color:var(--ssu-navy)]">
                     <Check className="mt-0.5 h-4 w-4 shrink-0 text-[color:var(--ssu-navy)]" strokeWidth={1.75} aria-hidden />
                     {skill}
                  </li>
               ))}
            </ul>
         </div>
         <SamplePlan variant="sheet" />
      </div>
   </section>
);

export default Practice;
