import { getPageSection, getPropertyArray } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';

const defaultTitle = 'INSIDE THE ACADEMY';
const defaultTagline = 'Explore the Learning, Stories, and Opportunities Within';

const hrefFor = (link: string): string => {
   const trimmed = link.trim();

   if (!trimmed) {
      return '';
   }

   return /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
};

const formatViews = (views: string): string => {
   const raw = views.replace(/,/g, '').trim();

   if (/^\d+$/.test(raw)) {
      return Number(raw).toLocaleString();
   }

   return views.trim();
};

const InsideAcademy = () => {
   const { props } = usePage<IntroPageProps>();
   const section = props.page?.sections ? getPageSection(props.page, 'inside_academy') : undefined;
   const posts = getPropertyArray(section).filter((item) => String(item.image ?? '').trim());

   if (!posts.length) {
      return null;
   }

   const title = section?.title?.trim() || defaultTitle;
   const tagline = section?.sub_title?.trim() || defaultTagline;

   return (
      <section className="py-16 md:py-20">
         <div className="container space-y-10 px-4">
            <div className="mx-auto max-w-4xl space-y-3 text-center">
               <div className="flex items-center justify-center gap-4 md:gap-6">
                  <span className="bg-primary h-px w-10 shrink-0 sm:w-16 md:w-24" aria-hidden />
                  <h2 className="font-display text-primary text-2xl font-bold tracking-tight md:text-3xl">{title}</h2>
                  <span className="bg-primary h-px w-10 shrink-0 sm:w-16 md:w-24" aria-hidden />
               </div>
               {tagline ? <p className="text-primary text-base italic md:text-lg">{tagline}</p> : null}
            </div>

            <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-4">
               {posts.map((post, index) => {
                  const image = String(post.image ?? '').trim();
                  const href = hrefFor(String(post.link ?? ''));
                  const views = formatViews(String(post.views ?? ''));
                  const cardClassName =
                     'ssu-surface-card group relative block overflow-hidden focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none';

                  const card = (
                     <>
                        <div className="aspect-[3/4] overflow-hidden bg-muted">
                           <img
                              src={image}
                              alt={views ? `Academy Instagram post, ${views} views` : 'Academy Instagram post'}
                              className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                           />
                        </div>
                        {views ? (
                           <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent px-3 py-3">
                              <span className="flex items-center gap-1.5 text-sm font-medium text-white">
                                 <Eye className="h-4 w-4" aria-hidden />
                                 {views}
                              </span>
                           </div>
                        ) : null}
                     </>
                  );

                  if (!href) {
                     return (
                        <div key={`${image}-${index}`} className={cardClassName}>
                           {card}
                        </div>
                     );
                  }

                  return (
                     <a
                        key={`${href}-${index}`}
                        href={href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={cardClassName}
                     >
                        {card}
                     </a>
                  );
               })}
            </div>
         </div>
      </section>
   );
};

export default InsideAcademy;
