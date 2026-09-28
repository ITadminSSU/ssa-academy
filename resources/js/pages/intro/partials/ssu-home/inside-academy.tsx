import { SheetKicker } from '@/components/ssu-public/chrome';
import { getPageSection, getPropertyArray } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';

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

   const title = section?.title?.trim() || 'Inside the Academy';
   const tagline = section?.sub_title?.trim() || 'Explore the learning, stories, and opportunities within.';

   return (
      <section className="bg-[color:var(--ssu-cream)] py-16 md:py-20">
         <div className="container space-y-10 px-4">
            <div>
               <SheetKicker label="Inside the Academy" index="IG" />
               <h2 className="ssu-pub-display mt-5 text-[clamp(2.2rem,5vw,4.4rem)] text-[color:var(--ssu-ink)]">{title}</h2>
               {tagline ? <p className="mt-3 max-w-2xl text-sm text-[color:var(--ssu-muted)] md:text-base">{tagline}</p> : null}
            </div>

            <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-4">
               {posts.map((post, index) => {
                  const image = String(post.image ?? '').trim();
                  const href = hrefFor(String(post.link ?? ''));
                  const views = formatViews(String(post.views ?? ''));
                  const cardClassName = 'group relative block overflow-hidden border border-[color:var(--ssu-line)] bg-white';

                  const card = (
                     <>
                        <div className="aspect-[3/4] overflow-hidden bg-[color:var(--ssu-paper)]">
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

                  return href ? (
                     <a key={`${image}-${index}`} href={href} target="_blank" rel="noopener noreferrer" className={cardClassName}>
                        {card}
                     </a>
                  ) : (
                     <div key={`${image}-${index}`} className={cardClassName}>
                        {card}
                     </div>
                  );
               })}
            </div>
         </div>
      </section>
   );
};

export default InsideAcademy;
