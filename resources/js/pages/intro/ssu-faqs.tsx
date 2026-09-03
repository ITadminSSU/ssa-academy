import LandingLayout from '@/layouts/landing-layout';
import { cn } from '@/lib/utils';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

type FaqBlock = {
   question: string;
   paragraphs: string[];
   bullets?: string[];
   after?: string[];
};

const faqs: FaqBlock[] = [
   {
      question: 'What is SmartSourcing USA Academy?',
      paragraphs: [
         'SmartSourcing USA Academy is a construction-focused training platform designed to help aspiring and current construction VAs and construction professionals develop practical skills for remote construction work.',
         'The Academy focuses on U.S. construction workflows, industry tools, terminology, project documents, and practical training to help learners prepare for real-world remote construction opportunities.',
      ],
   },
   {
      question: 'Who is SmartSourcing USA Academy for?',
      paragraphs: [
         'SmartSourcing USA Academy is for engineers, architects, estimators, quantity surveyors, construction professionals, students, fresh graduates, aspiring construction VAs, and anyone interested in building practical skills for a remote construction career.',
         'You do not need to have previous U.S. construction experience to start learning.',
      ],
   },
   {
      question: 'What makes SMARTSOURCING USA Academy unique?',
      paragraphs: [
         'SmartSourcing USA Academy focuses on practical construction training for people who want to build skills relevant to remote and U.S.-based construction workflows.',
         'Instead of focusing only on theory or software tutorials, the Academy aims to help learners understand how construction professionals work with plans, specifications, takeoffs, estimating processes, terminology, project documents, industry tools, and remote workflows.',
         'The goal is to help learners build practical skills, confidence, and industry familiarity that they can apply as they prepare for real-world construction opportunities.',
      ],
   },
   {
      question: 'Do I need to be an engineer to enroll in SmartSourcing USA Academy?',
      paragraphs: [
         'No. You do not need to be a licensed engineer to enroll.',
         'SmartSourcing USA Academy is open to anyone who wants to develop construction-related skills and learn more about working remotely in the construction industry.',
         'Some courses may be easier to understand if you already have a basic construction background, but this is not a requirement for all programs.',
      ],
   },
   {
      question: 'What skills will I learn from SmartSourcing USA Academy?',
      paragraphs: [
         'Training topics may include practical skills commonly used in remote construction work, such as:',
      ],
      bullets: [
         'Construction estimating',
         'Quantity takeoff',
         'Blueprint and plan reading',
         'U.S. construction terminology',
         'Project specifications and construction documents',
         'Construction workflows',
         'Estimating and takeoff software',
         'Documentation and project organization',
         'Remote communication and collaboration',
         'Trade-specific construction skills',
      ],
      after: ['Available courses and training topics may vary as new programs are introduced.'],
   },
   {
      question: 'Will SmartSourcing USA Academy have live classes or recorded lessons?',
      paragraphs: [
         'SmartSourcing USA Academy courses are pre-recorded but interactive, allowing learners to study at their own pace while still staying connected throughout the training.',
         'Depending on the course, learners may have access to:',
      ],
      bullets: [
         'Pre-recorded lessons',
         'Interactive quizzes and assessments',
         'Opportunities to communicate and engage with instructors through the platform',
         'An exclusive Facebook community for Academy learners',
         'Additional learning resources and activities',
      ],
      after: [
         'This gives learners the flexibility of self-paced training while still providing access to instructor support and an active learning community.',
      ],
   },
   {
      question: 'When will SmartSourcing USA Academy launch?',
      paragraphs: [
         'Pre-registration for SmartSourcing USA Academy opens on August 15, 2026.',
         'Additional information regarding available courses, schedules, enrollment, and the official start of training will be announced through SmartSourcing USA and the Construction VA Academy community.',
      ],
   },
   {
      question: 'How much do SMARTSOURCING USA Academy training courses cost?',
      paragraphs: [
         'Pricing vary depending on the training course.',
         'Complete details, including the price, inclusions, schedule, and enrollment options will be provided when each course is officially announced.',
      ],
   },
   {
      question: 'After enrolling in a course, do I need to purchase each module/lesson separately?',
      paragraphs: [
         'No. Each training course per trade has a one-time enrollment fee that gives you access to all modules and lessons included in your chosen course. Your access does not expire, so you can learn at your own pace and revisit the lessons whenever you need to.',
      ],
   },
   {
      question: 'Does completing SMARTSOURCING USA Academy courses guarantee a job?',
      paragraphs: [
         'Any engagement opportunity will still depend on factors such as required qualifications, skills, experience, interview performance, client requirements, available positions, and the applicable hiring or selection process.',
      ],
   },
   {
      question: 'Can students join SmartSourcing USA Academy?',
      paragraphs: [
         'Yes. Students and fresh graduates who are interested in construction and remote career opportunities are welcome to join.',
         'The Academy can help them begin developing practical skills, become familiar with industry workflows, and better understand the skills used in remote construction work.',
      ],
   },
   {
      question: 'Can current construction professionals join SmartSourcing USA Academy?',
      paragraphs: [
         'Yes. SmartSourcing USA Academy is also designed for experienced construction professionals who want to expand their skills, learn U.S. construction workflows, become familiar with industry tools, or prepare to transition into remote construction work.',
         'Existing construction knowledge can serve as a valuable foundation when learning remote construction processes.',
      ],
   },
   {
      question: 'How do I join the Construction VA Academy Facebook community?',
      paragraphs: [
         'You may join the Construction VA Academy Facebook community through this link:',
         'https://www.facebook.com/groups/constructionvaacademy',
         'Please make sure to answer all membership questions, as completing them is required for membership approval.',
      ],
   },
   {
      question: 'How can I stay updated about SmartSourcing USA Academy?',
      paragraphs: ['Follow SmartSourcing USA and join the Construction VA Academy Facebook community to receive updates about:'],
      bullets: [
         'Pre-registration and enrollment',
         'New courses and training programs',
         'Class schedules',
         'Academy announcements',
         'Construction learning resources',
         'Future Academy activities',
      ],
   },
];

function renderParagraph(text: string, key: string) {
   if (text.startsWith('https://')) {
      return (
         <p key={key}>
            <a
               href={text}
               target="_blank"
               rel="noopener noreferrer"
               className="font-medium text-white underline decoration-white/40 underline-offset-4 transition hover:decoration-white"
            >
               {text}
            </a>
         </p>
      );
   }

   return (
      <p key={key} className="leading-relaxed text-white/90">
         {text}
      </p>
   );
}

const SsuFaqs = ({ system }: IntroPageProps) => {
   const [openIndex, setOpenIndex] = useState<number | null>(null);

   return (
      <LandingLayout navbarHeight={true} customizable={false}>
         <Head title={`FAQs | ${system.fields.name}`} />

         <div className="ssu-page-shell">
            {/* Hero */}
            <section className="relative overflow-hidden bg-primary text-white">
               <div className="absolute inset-0" aria-hidden>
                  <div
                     className="absolute inset-0 bg-cover bg-center"
                     style={{
                        backgroundImage: `linear-gradient(90deg, rgba(1,18,58,0.88) 0%, rgba(1,18,58,0.72) 45%, rgba(1,18,58,0.82) 100%), url('/assets/images/ssu-about/about-hero.png')`,
                     }}
                  />
                  <div
                     className="absolute inset-0 opacity-[0.15]"
                     style={{
                        backgroundImage:
                           'linear-gradient(rgba(255,255,255,0.35) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.35) 1px, transparent 1px)',
                        backgroundSize: '40px 40px',
                     }}
                  />
               </div>

               <div className="relative container flex min-h-[280px] flex-col items-center justify-center px-4 py-16 text-center md:min-h-[340px] md:py-24">
                  <h1 className="font-display text-3xl font-bold tracking-wide uppercase sm:text-4xl md:text-5xl">
                     Frequently Asked Questions
                  </h1>
                  <p className="mt-4 max-w-2xl text-sm leading-relaxed text-white/85 sm:text-base md:text-lg">
                     Here are some of the most frequently asked questions about SmartSourcing USA Academy (SSA). Learn about
                     our courses, training, certification, and career opportunities.
                  </p>
               </div>
            </section>

            {/* FAQ list */}
            <section className="bg-[color:var(--brand-grey)] py-12 md:py-16">
               <div className="container space-y-4 px-4 md:space-y-5">
                  {faqs.map((faq, index) => {
                     const isOpen = openIndex === index;

                     return (
                        <div key={faq.question} className="w-full">
                           <button
                              type="button"
                              aria-expanded={isOpen}
                              onClick={() => setOpenIndex(isOpen ? null : index)}
                              className="ssu-faq-tab"
                           >
                              <span className="ssu-faq-hex" aria-hidden />
                              <span className="ssu-faq-bar">
                                 <span className="ssu-faq-question">{faq.question}</span>
                              </span>
                           </button>

                           <div
                              className={cn(
                                 'grid transition-[grid-template-rows] duration-300 ease-out',
                                 isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                              )}
                           >
                              <div className="overflow-hidden">
                                 <div className="pt-2 pl-8 sm:pl-12 md:pl-14">
                                    <div className="border border-white/20 bg-primary px-5 py-5 text-sm text-white sm:px-6 sm:py-6 sm:text-base">
                                       <div className="space-y-3">
                                          {faq.paragraphs.map((paragraph, pIndex) =>
                                             renderParagraph(paragraph, `${index}-p-${pIndex}`),
                                          )}

                                          {faq.bullets && faq.bullets.length > 0 && (
                                             <ul className="list-disc space-y-1.5 pl-5 text-white/90">
                                                {faq.bullets.map((bullet) => (
                                                   <li key={bullet}>{bullet}</li>
                                                ))}
                                             </ul>
                                          )}

                                          {faq.after?.map((paragraph, aIndex) =>
                                             renderParagraph(paragraph, `${index}-a-${aIndex}`),
                                          )}
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     );
                  })}
               </div>
            </section>
         </div>
      </LandingLayout>
   );
};

export default SsuFaqs;
