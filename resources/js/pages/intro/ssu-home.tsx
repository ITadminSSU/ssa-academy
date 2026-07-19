import LandingLayout from '@/layouts/landing-layout';
import { IntroPageProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import CallToAction from './partials/ssu-home/call-to-action';
import FeaturedCourses from './partials/ssu-home/featured-courses';
import Hero from './partials/ssu-home/hero';
import Pillars from './partials/ssu-home/pillars';

const SsuHome = ({ system }: IntroPageProps) => {
   return (
      <LandingLayout navbarHeight={false} customizable={false}>
         <Head title={system.fields.name} />

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
