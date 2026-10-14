import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import LandingLayout from '@/layouts/landing-layout';
import { Head } from '@inertiajs/react';
import { BarChart3, BookOpen, Globe } from 'lucide-react';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import Curriculum from '../courses/partials/curriculum';
import Details from '../courses/partials/details';
import Faqs from '../courses/partials/faqs';
import Instructor from '../courses/partials/instructor';
import GhlEmbed from './ghl-embed';
import { DrywallLandingContent, DrywallReview } from './types';

interface DrywallPageProps {
   landing: DrywallLandingContent;
   course: Course | null;
   canViewCurriculum: boolean;
   enrollUrl: string;
}

const benefits = [
   { icon: BookOpen, label: 'Beginner-friendly step-by-step lessons' },
   { icon: BarChart3, label: 'Real project examples and takeoff practice' },
   { icon: Globe, label: 'Build skills for global opportunities' },
];

const pad = (value: number) => String(value).padStart(2, '0');

const useCountdown = (endsAt: string) => {
   const [now, setNow] = useState(() => Date.now());

   useEffect(() => {
      const timer = window.setInterval(() => setNow(Date.now()), 1000);

      return () => window.clearInterval(timer);
   }, []);

   return useMemo(() => {
      const remaining = Math.max(0, new Date(endsAt).getTime() - now);
      const days = Math.floor(remaining / 86400000);
      const hours = Math.floor((remaining % 86400000) / 3600000);
      const minutes = Math.floor((remaining % 3600000) / 60000);

      return { days, hours, minutes };
   }, [endsAt, now]);
};

const videoEmbedUrl = (url: string): string | null => {
   const youtube = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{11})/);

   if (youtube) {
      return `https://www.youtube.com/embed/${youtube[1]}?autoplay=1`;
   }

   const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);

   if (vimeo) {
      return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1`;
   }

   return null;
};

const DrywallEstimating = ({ landing, course }: DrywallPageProps) => {
   const countdown = useCountdown(landing.countdown_ends_at);
   const [playing, setPlaying] = useState(false);
   const embedUrl = landing.video_url ? videoEmbedUrl(landing.video_url) : null;
   const sections = course?.sections ?? [];
   const faqs = course?.faqs ?? [];
   const hasDetails = (course?.requirements?.length ?? 0) > 0 || (course?.outcomes?.length ?? 0) > 0;

   return (
      <div className="drywall-landing">
         <Head title="Drywall Estimating">
            <meta
               name="description"
               content="A practical, step-by-step Drywall Estimating course for beginners and aspiring Virtual Assistants who want real-world U.S. estimating experience."
            />
         </Head>

         <div className="bg-black px-4 py-3 text-center text-sm text-white">
            Discount ends in{' '}
            <b className="text-[#f0b429]">
               {pad(countdown.days)}d : {pad(countdown.hours)}h : {pad(countdown.minutes)}m
            </b>
         </div>

         <section className="relative isolate overflow-hidden bg-[#10243f] text-white">
            <img
               src={landing.hero_photo}
               alt=""
               className="absolute inset-y-0 right-0 hidden h-full w-[50%] object-cover object-[62%_center] md:block"
            />
            <div className="pointer-events-none absolute inset-y-0 left-[46%] z-[1] hidden w-[10%] bg-gradient-to-r from-[#10243f] to-transparent md:block" />

            <div className="relative z-10 container grid items-center gap-8 py-10 md:grid-cols-[1.15fr_0.85fr] md:py-14">
               <div>
                  <p className="mb-3 text-[13px] font-extrabold tracking-[0.14em] text-[#f0b429] uppercase">Learn a high-demand skill</p>
                  <h1 className="max-w-xl text-4xl text-white md:text-5xl">
                     Master Drywall Estimating and Get <span className="text-[#f0b429]">Real-World U.S. Experience</span>
                  </h1>
                  <p className="mt-4 max-w-xl text-base leading-relaxed text-[#e6edf5] md:text-[17px]">
                     A practical, step-by-step course designed for beginners and aspiring Virtual Assistants who want to build valuable skills
                     and prepare for real opportunities.
                  </p>
                  <div className="mt-6 flex flex-col gap-4 sm:flex-row">
                     {benefits.map(({ icon: Icon, label }) => (
                        <div key={label} className="flex max-w-[180px] items-center gap-2 text-[13px] leading-tight text-white">
                           <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#f0b429] text-[#10243f]">
                              <Icon className="h-4 w-4" strokeWidth={2.4} />
                           </span>
                           <span>{label}</span>
                        </div>
                     ))}
                  </div>
               </div>

               <div className="relative">
                  <img src={landing.hero_photo} alt="" className="absolute inset-0 h-full w-full rounded-2xl object-cover md:hidden" />
                  <div className="relative overflow-hidden rounded-2xl bg-white shadow-2xl">
                     <GhlEmbed html={landing.embed_html} />
                  </div>
               </div>
            </div>
         </section>

         <section className="bg-white">
            <div className="container grid items-center gap-8 py-10 md:grid-cols-2 md:py-14">
               <div className="relative min-h-[260px] overflow-hidden rounded-2xl bg-[#16324d] text-white">
                  {playing && landing.video_url ? (
                     embedUrl ? (
                        <iframe
                           src={embedUrl}
                           title="Drywall Estimating video"
                           className="absolute inset-0 h-full w-full"
                           allow="autoplay; encrypted-media; picture-in-picture"
                           allowFullScreen
                        />
                     ) : (
                        <video src={landing.video_url} className="absolute inset-0 h-full w-full object-cover" controls autoPlay />
                     )
                  ) : (
                     <>
                        <div className="absolute inset-0 bg-gradient-to-t from-[#10243f] via-[#10243f]/25 to-[#24506a]/40" />
                        <p className="drywall-script absolute top-7 left-5 max-w-[210px] text-3xl leading-tight text-white">
                           See how this course prepares you for real opportunities.
                        </p>
                        <button
                           type="button"
                           className="absolute top-1/2 left-1/2 grid h-16 w-16 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white text-[#10243f] shadow-lg"
                           onClick={() => landing.video_url && setPlaying(true)}
                           aria-label="Play video"
                        >
                           <span className="ml-1 text-xl">▶</span>
                        </button>
                        <p className="absolute right-3 bottom-3 rounded-md bg-black px-2 py-0.5 text-xs">{landing.video_duration}</p>
                        <p className="absolute bottom-3 left-4 text-sm">
                           {landing.coach_line.split('·')[0]?.trim()}
                           <span className="mt-0.5 block text-[#f3d48a]">{landing.coach_line.includes('·') ? landing.coach_line.split('·').slice(1).join('·').trim() : ''}</span>
                        </p>
                     </>
                  )}
               </div>

               <div>
                  <p className="mb-2 text-xs font-extrabold tracking-[0.08em] text-[#c2410c] uppercase">Video: Real-world U.S. experience</p>
                  <h2 className="text-3xl text-[#10243f] md:text-4xl">Go Beyond Learning. Understand Real-World U.S. Work Experience.</h2>
                  <p className="mt-4 text-base leading-relaxed text-[#3d4c5e]">
                     Find out how Drywall Estimating skills are used in real projects and how this course helps you gain practical, job-ready
                     experience that gives you an advantage when applying for opportunities.
                  </p>
               </div>
            </div>
         </section>

         {landing.reviews.length > 0 ? (
            <section className="container py-10">
               <Carousel className="px-2 md:px-0" opts={{ align: 'start', loop: landing.reviews.length > 3 }}>
                  <CarouselContent>
                     {landing.reviews.map((review: DrywallReview, index) => (
                        <CarouselItem key={`${review.name}-${index}`} className="md:basis-1/2 lg:basis-1/3">
                           <blockquote className="h-full rounded-xl border border-[#e4e8ee] bg-white p-4 text-[15px] leading-relaxed text-[#344054]">
                              “{review.quote}”
                              <footer className="mt-3 text-xs font-bold text-[#10243f]">{review.name}</footer>
                           </blockquote>
                        </CarouselItem>
                     ))}
                  </CarouselContent>
                  {landing.reviews.length > 3 ? (
                     <>
                        <CarouselPrevious className="-left-3" />
                        <CarouselNext className="-right-3" />
                     </>
                  ) : null}
               </Carousel>
            </section>
         ) : null}

         <section className="container pb-16">
            <Tabs defaultValue="overview">
               <TabsList className="flex h-auto w-full flex-wrap justify-start gap-2 rounded-t-2xl rounded-b-none bg-[#10243f] p-3">
                  {['Overview', 'Curriculum', 'Details', 'Instructor', 'FAQs'].map((label) => (
                     <TabsTrigger
                        key={label}
                        value={label.toLowerCase()}
                        className="rounded-full px-4 py-2 text-sm text-[#d5deea] data-[state=active]:bg-white data-[state=active]:text-[#10243f] data-[state=active]:shadow-none"
                     >
                        {label}
                     </TabsTrigger>
                  ))}
               </TabsList>

               <TabsContent value="overview" className="mt-0 rounded-b-2xl border border-t-0 border-[#e7e1d6] bg-[#f7f6f3] p-6 md:p-8">
                  <div className="space-y-4 text-[15px] leading-relaxed text-[#3a4654]">
                     {landing.overview.map((paragraph) => (
                        <p key={paragraph}>{paragraph}</p>
                     ))}
                  </div>
               </TabsContent>

               <TabsContent value="curriculum" className="mt-0 rounded-b-2xl border border-t-0 border-[#e7e1d6] bg-[#f7f6f3] p-6 md:p-8">
                  {course && sections.length > 0 ? (
                     <Curriculum course={course} />
                  ) : (
                     <p className="text-[#3a4654]">The course outline will appear here when Drywall Estimating is published.</p>
                  )}
               </TabsContent>

               <TabsContent value="details" className="mt-0 rounded-b-2xl border border-t-0 border-[#e7e1d6] bg-[#f7f6f3] p-6 md:p-8">
                  {course && hasDetails ? (
                     <Details course={course} />
                  ) : (
                     <p className="text-[#3a4654]">Requirements and outcomes will appear here when they are added to the course.</p>
                  )}
               </TabsContent>

               <TabsContent value="instructor" className="mt-0 rounded-b-2xl border border-t-0 border-[#e7e1d6] bg-[#f7f6f3] p-6 md:p-8">
                  {course?.instructor ? (
                     <Instructor course={course} />
                  ) : (
                     <p className="text-[#3a4654]">Instructor details will appear here when the course is published.</p>
                  )}
               </TabsContent>

               <TabsContent value="faqs" className="mt-0 rounded-b-2xl border border-t-0 border-[#e7e1d6] bg-[#f7f6f3] p-6 md:p-8">
                  {faqs.length > 0 ? <Faqs faqs={faqs} /> : <p className="text-[#3a4654]">No frequently asked questions yet.</p>}
               </TabsContent>
            </Tabs>
         </section>
      </div>
   );
};

DrywallEstimating.layout = (page: ReactNode) => <LandingLayout children={page} customizable={false} />;

export default DrywallEstimating;
