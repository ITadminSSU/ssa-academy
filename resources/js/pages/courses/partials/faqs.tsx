import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';

const Faqs = ({ faqs }: { faqs?: CourseFaq[] | ExamFaq[] | null }) => {
   return (
      <Accordion type="single" collapsible>
         <div className="border-border border-y">
            {faqs?.map((faq) => (
               <AccordionItem key={faq.id} value={String(faq.id)} className="px-4 last:border-none">
                  <AccordionTrigger className="cursor-pointer text-lg hover:no-underline [&[data-state=open]]:text-blue-500">
                     {faq.question}
                  </AccordionTrigger>
                  <AccordionContent className="pt-2">{faq.answer}</AccordionContent>
               </AccordionItem>
            ))}
         </div>
      </Accordion>
   );
};

export default Faqs;
