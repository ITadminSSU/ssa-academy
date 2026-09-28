import { SheetKicker } from '@/components/ssu-public/chrome';
import SamplePlan from '@/components/ssu-public/sample-plan';

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
   <section className="border-y border-[color:var(--ssu-line)] bg-[#f3f1ea] py-20">
      <div className="container grid items-center gap-12 px-4 lg:grid-cols-2">
         <div>
            <SheetKicker label="Inside the work" index="04" />
            <h2 className="ssu-pub-display mt-5 text-4xl text-[color:var(--ssu-ink)] md:text-5xl">
               Not just videos. Build skills you can use.
            </h2>
            <p className="mt-6 max-w-xl text-sm leading-relaxed text-[color:var(--ssu-muted)] md:text-base">
               The Academy is built around the documents, tools, and decisions that make construction work move. Explore a plan,
               inspect a measurement, and understand the workflow behind the answer.
            </p>
            <ul className="mt-8 grid gap-2 sm:grid-cols-2">
               {skills.map((skill) => (
                  <li key={skill} className="flex items-center gap-2 text-sm text-[color:var(--ssu-navy)]">
                     <span className="h-1.5 w-1.5 rounded-full bg-[color:var(--ssu-gold)]" />
                     {skill}
                  </li>
               ))}
            </ul>
            <p className="mt-8 font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-muted)] uppercase">
               Hover over, focus, or click a marked point to inspect this sample plan.
            </p>
         </div>
         <SamplePlan />
      </div>
   </section>
);

export default Practice;
