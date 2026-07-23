import AppLogo from '@/components/app-logo';
import { getPageSection } from '@/lib/page';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { getYear } from 'date-fns';

const LandingFooter = () => {
   const { page, system } = usePage<SharedData>().props;

   const aboutUsSection = getPageSection(page, 'footer_list_1');
   const customerCareSection = getPageSection(page, 'footer_list_2');
   const contactUsSection = getPageSection(page, 'footer_list_3');

   const sections = [aboutUsSection, customerCareSection, contactUsSection]
      .filter((section): section is PageSection => section !== undefined)
      .sort((a, b) => (a.sort || 0) - (b.sort || 0));

   return (
      <div className="relative bg-gray-900 pt-20 pb-10 text-white dark:bg-gray-800">
         <div className="container">
            <div className="mb-11 flex flex-col items-start justify-between gap-10 md:flex-row">
               <div className="w-full">
                  <Link href={route('home')} className="inline-flex items-center">
                     <AppLogo theme="light" className="h-[50px] w-auto" />
                  </Link>

                  <p className="mt-5 text-sm max-w-[300px]">{system.fields.description}</p>
               </div>

               <div className="flex w-full flex-col justify-between gap-10 md:max-w-[640px] md:flex-row">
                  {sections.map(
                     (section) =>
                        section.active && (
                           <div className="relative w-full">
                              <p className="mb-3 text-lg font-semibold">{section?.title}</p>
                              <ul className="flex flex-col gap-2 text-sm">
                                 {section?.properties.array.map((item, itemIndex) =>
                                    section.slug === 'footer_list_3' ? (
                                       <li key={`item-${itemIndex}`}>{item.title}</li>
                                    ) : (
                                       <li key={`item-${itemIndex}`}>
                                          {item.title === 'Contact Us' || item.title === 'Contact' || item.url?.includes('/contact') ? (
                                             <a href="https://smartsourcingusa.com/contact" target="_blank" rel="noopener noreferrer">
                                                {item.title}
                                             </a>
                                          ) : (
                                             <Link href={item.url}>{item.title}</Link>
                                          )}
                                       </li>
                                    ),
                                 )}
                              </ul>
                           </div>
                        ),
                  )}
               </div>
            </div>

            <p className="text-center text-sm">
               Copyright © {getYear(new Date())} <a href="https://ui-lib.com/">UI Lib</a>. All rights reserved
            </p>
         </div>
      </div>
   );
};

export default LandingFooter;
