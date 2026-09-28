import PublicFaqAccordion from '@/components/ssu-public/faq-accordion';
import { PublicPageHero } from '@/components/ssu-public/chrome';
import LandingLayout from '@/layouts/landing-layout';
import { publicFaqs } from '@/lib/ssu-faqs';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';

const SsuFaqs = ({ system }: IntroPageProps) => {
   return (
      <LandingLayout navbarHeight={true} customizable={false}>
         <Head title={`FAQs | ${system.fields.name}`} />

         <div className="ssu-page-shell">
            <PublicPageHero
               kicker="Field guide"
               title="Frequently asked questions"
               description="Here are some of the most frequently asked questions about SmartSourcing USA Academy. Learn about our courses, training, certification, and career opportunities."
            />

            <section className="py-16 md:py-20">
               <div className="container px-4">
                  <PublicFaqAccordion faqs={publicFaqs} />
               </div>
            </section>
         </div>
      </LandingLayout>
   );
};

export default SsuFaqs;
