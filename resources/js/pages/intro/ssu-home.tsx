import LandingLayout from '@/layouts/landing-layout';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import CallToAction from './partials/ssu-home/call-to-action';
import FeaturedCourses from './partials/ssu-home/featured-courses';
import Hero from './partials/ssu-home/hero';
import LandingOverlay from './partials/ssu-home/landing-overlay';
import Pillars from './partials/ssu-home/pillars';

const SsuHome = ({ system, landingOverlay, landingOverlayForce }: IntroPageProps) => {
   return (
      <LandingLayout navbarHeight={true} customizable={false}>
         <Head title={system.fields.name} />

         {landingOverlay && <LandingOverlay overlay={landingOverlay} force={Boolean(landingOverlayForce)} />}

         <div className="ssu-page-shell">
            <Hero />
            <Pillars />
            <FeaturedCourses />
            <CallToAction />
         </div>
      </LandingLayout>
   );
};

export default SsuHome;

export default SsuHome;
