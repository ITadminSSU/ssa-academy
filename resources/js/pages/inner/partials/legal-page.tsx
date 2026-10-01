import { PublicPageHero } from '@/components/ssu-public/chrome';
import { stripLeadingPageTitle } from '@/lib/legal-page-copy';
import { Link } from '@inertiajs/react';
import { Renderer } from 'richtor';

const LegalPage = ({ innerPage }: { innerPage: Page }) => {
   const description = stripLeadingPageTitle(innerPage.description ?? '', innerPage.name);
   const summary = innerPage.meta_description?.trim();

   return (
      <div className="ssu-page-shell">
         <PublicPageHero kicker="Legal" title={innerPage.name} description={summary || undefined} />

         <div className="container px-4">
            <nav aria-label="Breadcrumb" className="ssu-legal-crumb">
               <ol>
                  <li>
                     <Link href="/">Home</Link>
                  </li>
                  <li aria-hidden="true">/</li>
                  <li aria-current="page">{innerPage.name}</li>
               </ol>
            </nav>

            {description ? (
               <div className="ssu-legal-copy">
                  <Renderer value={description} />
               </div>
            ) : null}
         </div>
      </div>
   );
};

export default LegalPage;
