import AppLogo from '@/components/app-logo';
import FraudTrainingTiplineMark from '@/components/fraud-training-tipline-mark';
import SocialMediaIcon from '@/components/social-media-icon';
import { Button } from '@/components/ui/button';
import { homeSectionHref } from '@/lib/ssu-public';
import { SystemProps } from '@/pages/dashboard/settings/system';
import { Link, usePage } from '@inertiajs/react';

const Index = () => {
   const { props, url } = usePage<SystemProps>();
   const { footer } = props;
   const pathname = url.split('#')[0].split('?')[0];

   const sortedItems = footer.footer_items.sort((a, b) => a.sort - b.sort);
   const listItems = sortedItems.filter((item) => item.type === 'list' && item.active);
   const copyrightItem = sortedItems.find((item) => item.type === 'copyright' && item.active);
   const socialMediaItem = sortedItems.find((item) => item.type === 'social_media' && item.active);
   const paymentMethodsItem = sortedItems.find((item) => item.type === 'payment_methods' && item.active);

   return (
      <footer className="border-t border-[color:var(--ssu-line)] bg-[color:var(--ssu-cream-nav)]">
         <div className="container space-y-10 pt-16 pb-8">
            <div className="flex flex-col items-start gap-12 md:flex-row md:flex-wrap lg:flex-nowrap lg:justify-between">
               <div className="w-max max-w-[280px] shrink-0 space-y-5">
                  <Link href={route('home')} className="ssu-logo-frame ssu-logo-frame--footer inline-flex h-24 max-w-[240px]">
                     <AppLogo variant="footer" className="ssu-footer-logo" />
                  </Link>
                  <p className="text-sm leading-relaxed text-[color:var(--ssu-muted)]">
                     Practical construction skills for a more U.S.-ready professional future.
                  </p>
                  {socialMediaItem && (
                     <div className="flex flex-wrap gap-3">
                        {socialMediaItem.items &&
                           Array.isArray(socialMediaItem.items) &&
                           socialMediaItem.items.map((socialItem: { url: string; icon: string; title: string }, idx: number) => (
                              <Button
                                 key={idx}
                                 size="icon"
                                 variant="ghost"
                                 className="rounded-full border border-[color:var(--ssu-line)] bg-white text-[color:var(--ssu-navy)] hover:bg-[color:var(--ssu-gold)] hover:text-[color:var(--ssu-navy)]"
                                 asChild
                              >
                                 <a href={socialItem.url} target="_blank" rel="noopener noreferrer">
                                    <SocialMediaIcon name={socialItem.icon} title={socialItem.title} url={socialItem.url} />
                                    <span className="sr-only">{socialItem.title}</span>
                                 </a>
                              </Button>
                           ))}
                     </div>
                  )}
               </div>

               <div className="relative w-max max-w-[220px] shrink-0">
                  <p className="mb-3 font-mono text-[11px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">Explore</p>
                  <ul className="flex flex-col gap-2 text-sm text-[color:var(--ssu-muted)]">
                     <li>
                        <Link href={route('category.courses', { category: 'all' })} className="hover:text-[color:var(--ssu-navy)]">
                           Courses
                        </Link>
                     </li>
                     <li>
                        <a href={homeSectionHref('how-it-works', pathname)} className="hover:text-[color:var(--ssu-navy)]">
                           How it works
                        </a>
                     </li>
                     <li>
                        <Link href={route('about')} className="hover:text-[color:var(--ssu-navy)]">
                           About
                        </Link>
                     </li>
                     <li>
                        <Link href={route('faqs')} className="hover:text-[color:var(--ssu-navy)]">
                           FAQs
                        </Link>
                     </li>
                     <li>
                        <Link href={route('connect')} className="hover:text-[color:var(--ssu-navy)]">
                           Resources
                        </Link>
                     </li>
                  </ul>
               </div>

               {listItems.map((section) => (
                  <div key={section.id} className="relative w-max max-w-[260px] shrink-0">
                     <p className="mb-3 font-mono text-[11px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">{section.title}</p>
                     <ul className="flex flex-col gap-2 text-sm text-[color:var(--ssu-muted)]">
                        {section.items
                           ?.filter((item) => item.title?.trim())
                           .map((item, itemIndex) =>
                              section.slug === 'address' ? (
                                 <li key={`item-${itemIndex}`} className="break-words">
                                    {item.title.startsWith('Email:') ? (
                                       <a href="mailto:training@smartsourcingusa.com" className="hover:text-[color:var(--ssu-navy)]">
                                          {item.title}
                                       </a>
                                    ) : (
                                       item.title
                                    )}
                                 </li>
                              ) : (
                                 <li key={`item-${itemIndex}`}>
                                    {item.title === 'Contact Us' || item.title === 'Contact' || item.url?.includes('/contact') ? (
                                       <a href="https://smartsourcingusa.com/contact" target="_blank" rel="noopener noreferrer">
                                          {item.title}
                                       </a>
                                    ) : (
                                       <Link href={item.url} className="hover:text-[color:var(--ssu-navy)]">
                                          {item.title}
                                       </Link>
                                    )}
                                 </li>
                              ),
                           )}
                        {section.slug === 'address' && (
                           <li className="mt-3 list-none">
                              <Link
                                 href={route('fraud-training-tipline')}
                                 className="group inline-flex max-w-full flex-col items-start rounded-lg transition-opacity hover:opacity-90"
                                 aria-label="Fraud Training Tipline — click here to report a suspicious site"
                              >
                                 <FraudTrainingTiplineMark variant="footer" />
                                 <span className="mt-2 text-left text-xs font-medium text-[color:var(--ssu-navy)] group-hover:underline">
                                    Click here to report a suspicious site
                                 </span>
                              </Link>
                           </li>
                        )}
                     </ul>
                  </div>
               ))}
            </div>

            {paymentMethodsItem && (
               <div className="space-y-3">
                  <h3 className="font-mono text-[11px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">{paymentMethodsItem.title}</h3>
                  <div className="flex flex-wrap gap-3">
                     {paymentMethodsItem.items &&
                        Array.isArray(paymentMethodsItem.items) &&
                        paymentMethodsItem.items.map((paymentItem: { image?: string }, idx: number) => (
                           <div key={idx} className="flex h-7 items-center justify-center gap-5 md:justify-start">
                              {paymentItem.image && (
                                 <img src={paymentItem.image} alt={`Payment method ${idx + 1}`} className="h-full w-auto object-contain" />
                              )}
                           </div>
                        ))}
                  </div>
               </div>
            )}
         </div>

         {copyrightItem && (
            <div className="border-t border-[color:var(--ssu-line)] px-6 py-6 text-center">
               <p className="text-sm text-[color:var(--ssu-muted)]">{copyrightItem.title}</p>
               <a href="#top" className="mt-2 inline-block font-mono text-[11px] tracking-[0.16em] text-[color:var(--ssu-navy)] uppercase">
                  Back to top ↑
               </a>
            </div>
         )}
      </footer>
   );
};

export default Index;
