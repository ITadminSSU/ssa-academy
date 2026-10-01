import { LEGAL_PAGE_SLUGS } from '@/lib/legal-page-copy';
import { SharedData } from '@/types/global';
import { Head } from '@inertiajs/react';
import { Renderer } from 'richtor';
import 'richtor/styles';
import Layout from '../intro/partials/layout';
import Hero from './partials/hero';
import LegalPage from './partials/legal-page';
import Sections from './sections';
import Career from './sections/career';

export interface InnerPageProps extends SharedData {
   innerPage: Page;
   topInstructors: Instructor[];
   jobCirculars: Pagination<JobCircular>;
}

const Index = ({ innerPage, jobCirculars }: InnerPageProps) => {
   const isLegalPage = LEGAL_PAGE_SLUGS.includes(innerPage.slug as (typeof LEGAL_PAGE_SLUGS)[number]);

   if (isLegalPage) {
      return (
         <Layout page={innerPage} navbarHeight={true}>
            <Head title={innerPage.name} />
            <LegalPage innerPage={innerPage} />
         </Layout>
      );
   }

   return (
      <Layout page={innerPage} navbarHeight={false}>
         <Head title={innerPage.name} />

         <Hero innerPage={innerPage} />

         {innerPage.sections.length > 0 && <Sections sections={innerPage.sections} />}

         {innerPage.slug === 'careers' && jobCirculars && <Career jobCirculars={jobCirculars} />}

         <div className="container">
            {innerPage.description && (
               <div className="bg-muted mx-auto my-20 max-w-3xl rounded-2xl px-6 py-10 md:px-20">
                  <Renderer value={innerPage.description} />
               </div>
            )}
         </div>
      </Layout>
   );
};

export default Index;
