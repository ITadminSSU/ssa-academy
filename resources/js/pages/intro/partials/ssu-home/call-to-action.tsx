import { Button } from '@/components/ui/button';

import { getPageSection } from '@/lib/page';

import { IntroPageProps } from '@/types/page';

import { Link, usePage } from '@inertiajs/react';



const CallToAction = () => {

   const { props } = usePage<IntroPageProps>();

   const ctaSection = getPageSection(props.page, 'call_to_action');



   const rawKicker = ctaSection?.sub_title || 'Join SMARTSOURCING USA ACADEMY Today';
   const kicker = rawKicker.replace(/SSU\s+ACADEMY/gi, 'SMARTSOURCING USA ACADEMY');

   const title = ctaSection?.title || 'Ready to start learning?';

   const description =

      ctaSection?.description || 'Create your free account, explore the catalog, and start your next course today.';

   const buttonText = ctaSection?.properties?.button_text || 'Get Started';

   const buttonLink = ctaSection?.properties?.button_link || route('register');



   return (

      <section className="py-20">

         <div className="container px-4">

            <div className="border-accent relative overflow-hidden rounded-2xl border-2 bg-primary px-6 py-14 text-center text-white md:px-12">

               <div className="bg-accent/25 pointer-events-none absolute -top-10 right-0 h-40 w-40 rounded-full blur-3xl" />

               <div className="pointer-events-none absolute bottom-0 left-0 h-40 w-40 rounded-full bg-white/10 blur-3xl" />



               <div className="relative mx-auto max-w-2xl space-y-5">

                  <p className="ssu-kicker !text-white/70">{kicker}</p>

                  <h2 className="font-display text-2xl font-bold md:text-3xl">{title}</h2>

                  <p className="text-white/80">{description}</p>



                  <Button asChild size="lg" className="mt-4 rounded-full px-10">

                     <Link href={buttonLink}>{buttonText}</Link>

                  </Button>

               </div>

            </div>

         </div>

      </section>

   );

};



export default CallToAction;

