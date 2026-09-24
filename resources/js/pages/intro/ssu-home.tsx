import LandingLayout from '@/layouts/landing-layout';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import CallToAction from './partials/ssu-home/call-to-action';
import FeaturedCourses from './partials/ssu-home/featured-courses';
import Gap from './partials/ssu-home/gap';
import Hero from './partials/ssu-home/hero';
import InsideAcademy from './partials/ssu-home/inside-academy';
import LandingOverlay from './partials/ssu-home/landing-overlay';
import Pillars from './partials/ssu-home/pillars';

const SsuHome = ({ system, landingOverlay, landingOverlayForce }: IntroPageProps) => {
   return (
      <LandingLayout navbarHeight={true} customizable={false}>
         <Head title={system.fields.name} />

         {landingOverlay && <LandingOverlay overlay={landingOverlay} force={Boolean(landingOverlayForce)} />}

         <div className="ssu-page-shell">
            <Hero />
            <Gap />
            <Pillars />
            <InsideAcademy />
            <FeaturedCourses />
            <CallToAction />
         </div>
      </LandingLayout>
   );
};

export default SsuHome;
