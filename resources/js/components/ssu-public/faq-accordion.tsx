import { PublicFaq } from '@/lib/ssu-faqs';
import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

const renderParagraph = (text: string, key: string) => {
   if (text.startsWith('https://')) {
      return (
         <p key={key}>
            <a
               href={text}
               target="_blank"
               rel="noopener noreferrer"
               className="font-medium text-[color:var(--ssu-navy)] underline decoration-[color:var(--ssu-gold)] underline-offset-4"
            >
               {text}
            </a>
         </p>
      );
   }

   return (
      <p key={key} className="leading-relaxed text-[color:var(--ssu-muted)]">
         {text}
      </p>
   );
};

const PublicFaqAccordion = ({ faqs }: { faqs: PublicFaq[] }) => {
   const [openIndex, setOpenIndex] = useState<number | null>(0);

   return (
      <div className="divide-y divide-[color:var(--ssu-navy)]/12 border-y border-[color:var(--ssu-navy)]/12">
         {faqs.map((faq, index) => {
            const isOpen = openIndex === index;
            const number = String(index + 1).padStart(2, '0');

            return (
               <div key={faq.question}>
                  <button
                     type="button"
                     aria-expanded={isOpen}
                     onClick={() => setOpenIndex(isOpen ? null : index)}
                     className="flex w-full items-center gap-4 py-4 text-left"
                  >
                     <span className="w-8 shrink-0 font-mono text-[10px] tracking-[0.16em] text-[color:var(--ssu-gold)]">{number}</span>
                     <span className="flex-1 text-sm font-medium text-[color:var(--ssu-navy)] md:text-[0.95rem]">{faq.question}</span>
                     <ChevronDown
                        className={cn(
                           'h-4 w-4 shrink-0 text-[color:var(--ssu-gold)] transition-transform duration-200',
                           isOpen && 'rotate-180',
                        )}
                        aria-hidden
                     />
                  </button>
                  <div className={cn('grid transition-[grid-template-rows] duration-300 ease-out', isOpen ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]')}>
                     <div className="overflow-hidden">
                        <div className="space-y-3 pb-5 pl-12 pr-8 text-sm">
                           {faq.paragraphs.map((paragraph, pIndex) => renderParagraph(paragraph, `${index}-p-${pIndex}`))}
                           {faq.bullets && faq.bullets.length > 0 && (
                              <ul className="list-disc space-y-1.5 pl-5 text-[color:var(--ssu-muted)]">
                                 {faq.bullets.map((bullet) => (
                                    <li key={bullet}>{bullet}</li>
                                 ))}
                              </ul>
                           )}
                           {faq.after?.map((paragraph, aIndex) => renderParagraph(paragraph, `${index}-a-${aIndex}`))}
                        </div>
                     </div>
                  </div>
               </div>
            );
         })}
      </div>
   );
};

export default PublicFaqAccordion;
