import AppLogo from '@/components/app-logo';
import FraudTrainingTiplineMark from '@/components/fraud-training-tipline-mark';
import SocialMediaIcon from '@/components/social-media-icon';
import { Button } from '@/components/ui/button';
import { SystemProps } from '@/pages/dashboard/settings/system';
import { Link, usePage } from '@inertiajs/react';

const Index = () => {
   const { props } = usePage<SystemProps>();
   const { footer, system } = props;

   const sortedItems = footer.footer_items.sort((a, b) => a.sort - b.sort);
   const listItems = sortedItems.filter((item) => item.type === 'list' && item.active);
   const copyrightItem = sortedItems.find((item) => item.type === 'copyright' && item.active);
   const socialMediaItem = sortedItems.find((item) => item.type === 'social_media' && item.active);
   const paymentMethodsItem = sortedItems.find((item) => item.type === 'payment_methods' && item.active);

   return (
      <div className="overflow-hidden bg-[color:var(--brand-grey)]">
         <div className="container space-y-9 pt-[60px] pb-5">
            <div className="flex flex-col items-start justify-between gap-10 md:flex-row">
               <div className="w-full space-y-5 md:max-w-[min(560px,42%)]">
                  <div>
                     <Link href={route('home')} className="ssu-logo-frame ssu-logo-frame--footer inline-flex">
                        <AppLogo variant="footer" className="ssu-footer-logo" />
                     </Link>
                  </div>

                  <p className="text-muted-foreground text-sm">{system.fields.description}</p>

                  {socialMediaItem && (
                     <div className="flex flex-wrap gap-3">
                        {socialMediaItem.items &&
                           Array.isArray(socialMediaItem.items) &&
                           socialMediaItem.items.map((socialItem: any, idx: number) => (
                              <Button
                                 key={idx}
                                 size="icon"
                                 variant="ghost"
                                 className="bg-background hover:bg-accent hover:text-accent-foreground text-muted-foreground rounded-full border border-border/60 transition-colors"
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

               <div className="flex w-full flex-col justify-between gap-10 md:max-w-[640px] md:flex-row">
                  {listItems.map((section) => (
                     <div key={section.id} className="relative w-full">
                        <p className="mb-3 text-lg font-semibold">{section.title}</p>
                        <ul className="text-muted-foreground flex flex-col gap-2 text-sm">
                           {section.items?.map((item, itemIndex) =>
                              section.slug === 'address' ? (
                                 <li key={`item-${itemIndex}`}>
                                    {item.title.startsWith('Email:') ? (
                                       <a href="mailto:training@smartsourcingusa.com" className="hover:text-foreground transition-colors">
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
                                       <Link href={item.url}>{item.title}</Link>
                                    )}
                                 </li>
                              ),
                           )}
                           {section.slug === 'address' && (
                              <li className="mt-4 list-none">
                                 <Link
                                    href={route('fraud-training-tipline')}
                                    className="group inline-flex flex-col items-center rounded-lg transition-opacity hover:opacity-90"
                                    aria-label="Fraud Training Tipline — click here to report a suspicious site"
                                 >
                                    <FraudTrainingTiplineMark variant="footer" />
                                    <span className="mt-2 text-center text-xs font-medium text-[#8C2A23] group-hover:underline">
                                       Click here to report a suspicious site
                                    </span>
                                 </Link>
                              </li>
                           )}
                        </ul>
                     </div>
                  ))}
               </div>
            </div>

            {paymentMethodsItem && (
               <div className="space-y-3">
                  <h3 className="text-base font-medium">{paymentMethodsItem.title}</h3>
                  <div className="flex flex-wrap gap-3">
                     {paymentMethodsItem.items &&
                        Array.isArray(paymentMethodsItem.items) &&
                        paymentMethodsItem.items.map((paymentItem: any, idx: number) => (
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

         {/* Copyright Section */}
         {copyrightItem && (
            <div className="px-6 py-8 text-center">
               <p className="text-muted-foreground text-sm">{copyrightItem.title}</p>
            </div>
         )}
      </div>
   );
};

export default Index;
