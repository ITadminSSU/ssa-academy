import LandingLayout from '@/layouts/landing-layout';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import Audience from './partials/ssu-home/audience';
import CallToAction from './partials/ssu-home/call-to-action';
import FeaturedCourses from './partials/ssu-home/featured-courses';
import FieldNote from './partials/ssu-home/field-note';
import Hero from './partials/ssu-home/hero';
import InsideAcademy from './partials/ssu-home/inside-academy';
import LandingOverlay from './partials/ssu-home/landing-overlay';
import { Credential, Ecosystem, HomeFaqs, Instructors, Roadmap, Sequence, Stats, Tools, Why, Workflow } from './partials/ssu-home/more-sections';
import Practice from './partials/ssu-home/practice';

const SsuHome = ({ system, landingOverlay, landingOverlayForce }: IntroPageProps) => {
   return (
      <LandingLayout navbarHeight={true} customizable={false}>
         <Head title={system.fields.name} />

         {landingOverlay && <LandingOverlay overlay={landingOverlay} force={Boolean(landingOverlayForce)} />}

         <div className="ssu-page-shell">
            <Hero />
            <FieldNote />
            <Audience />
            <InsideAcademy />
            <FeaturedCourses />
            <Practice />
            <Workflow />
            <Tools />
            <Sequence />
            <Credential />
            <Roadmap />
            <Why />
            <Ecosystem />
            <Stats />
            <Instructors />
            <HomeFaqs />
            <CallToAction />
         </div>
      </LandingLayout>
   );
};

export default SsuHome;
