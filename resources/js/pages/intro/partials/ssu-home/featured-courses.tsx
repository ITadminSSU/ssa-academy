import CourseCard1 from '@/components/cards/course-card-1';
import { Button } from '@/components/ui/button';
import { Carousel, type CarouselApi, CarouselContent, CarouselItem } from '@/components/ui/carousel';
import { getPageSection } from '@/lib/page';
import { cn } from '@/lib/utils';
import { IntroPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import Autoplay from 'embla-carousel-autoplay';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const FeaturedCourses = () => {
   const { props } = usePage<IntroPageProps>();
   const coursesSection = props.page?.sections ? getPageSection(props.page, 'top_courses') : undefined;
   const { topCourses } = props;
   const courses = topCourses ?? [];
   const [api, setApi] = useState<CarouselApi>();
   const [currentSlide, setCurrentSlide] = useState(0);
   const autoplay = useRef(Autoplay({ delay: 3000, stopOnInteraction: false, stopOnMouseEnter: true }));
   const heading = coursesSection?.title?.trim() || 'START LEARNING TODAY';
   const tagline =
      coursesSection?.description?.trim() ||
      'Explore assigned and open-enrollment courses curated for SMARTSOURCING USA teams and partners.';

   useEffect(() => {
      if (!api) {
         return;
      }

      const handleSelect = () => {
         setCurrentSlide(api.selectedScrollSnap());
      };

      api.on('select', handleSelect);

      return () => {
         api.off('select', handleSelect);
      };
   }, [api]);

   return (
      <section className="border-border/60 border-y bg-[color:var(--brand-grey)] py-20 dark:bg-muted/20">
         <div className="container space-y-10 px-4">
            <div className="mx-auto max-w-4xl space-y-3 text-center">
               <div className="flex items-center justify-center gap-4 md:gap-6">
                  <span className="bg-primary h-px w-10 shrink-0 sm:w-16 md:w-24" aria-hidden />
                  <h2 className="font-display text-primary text-2xl font-bold tracking-tight uppercase md:text-3xl">{heading}</h2>
                  <span className="bg-primary h-px w-10 shrink-0 sm:w-16 md:w-24" aria-hidden />
               </div>
               {tagline ? <p className="text-primary text-base md:text-lg">{tagline}</p> : null}
            </div>

            {courses.length > 0 ? (
               <div className="space-y-6">
                  <Carousel
                     setApi={setApi}
                     opts={{ align: 'start', loop: courses.length > 1, skipSnaps: false }}
                     plugins={courses.length > 1 ? [autoplay.current] : []}
                     className="relative"
                  >
                     <CarouselContent className="-ml-4 items-stretch">
                        {courses.map((course) => (
                           <CarouselItem key={course.id} className="flex basis-full pl-4 sm:basis-1/2 lg:basis-1/3">
                              <CourseCard1 course={course} className="h-full w-full" />
                           </CarouselItem>
                        ))}
                     </CarouselContent>
                  </Carousel>

                  {courses.length > 1 ? (
                     <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                           {courses.map(({ id }, index) => (
                              <button
                                 key={id}
                                 type="button"
                                 aria-label={`Go to slide ${index + 1}`}
                                 className={cn(
                                    'rounded-full transition-all duration-200',
                                    currentSlide === index ? 'bg-primary h-2 w-6' : 'bg-muted-foreground/30 h-2 w-2',
                                 )}
                                 onClick={() => api?.scrollTo(index)}
                              />
                           ))}
                        </div>

                        <div className="flex gap-2">
                           <Button
                              size="icon"
                              variant="outline"
                              className="rounded-full"
                              disabled={!api?.canScrollPrev()}
                              onClick={() => api?.scrollPrev()}
                           >
                              <ChevronLeft className="h-4 w-4" />
                           </Button>
                           <Button
                              size="icon"
                              variant="outline"
                              className="rounded-full"
                              disabled={!api?.canScrollNext()}
                              onClick={() => api?.scrollNext()}
                           >
                              <ChevronRight className="h-4 w-4" />
                           </Button>
                        </div>
                     </div>
                  ) : null}
               </div>
            ) : (
               <div className="ssu-surface-card flex flex-col items-center gap-4 p-10 text-center">
                  <p className="text-muted-foreground max-w-lg text-sm md:text-base">
                     New programs are on the way. Browse the catalog for upcoming courses or sign up to get notified when they
                     launch.
                  </p>
                  <Button asChild className="rounded-full">
                     <Link href={route('category.courses', { category: 'all' })}>Browse course catalog</Link>
                  </Button>
               </div>
            )}
         </div>
      </section>
   );
};

export default FeaturedCourses;
