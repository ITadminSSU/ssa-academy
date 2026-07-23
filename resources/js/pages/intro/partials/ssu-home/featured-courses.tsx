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
   const coursesSection = getPageSection(props.page, 'top_courses');
   const { topCourses } = props;
   const courses = topCourses ?? [];
   const [api, setApi] = useState<CarouselApi>();
   const [currentSlide, setCurrentSlide] = useState(0);
   const autoplay = useRef(Autoplay({ delay: 3500, stopOnInteraction: false }));

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
      <section className="border-border/60 border-y bg-[oklch(0.97_0.01_255)] py-20 dark:bg-muted/20">
         <div className="container space-y-8 px-4">
            <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
               <div className="space-y-2">
                  <p className="ssu-kicker">{coursesSection?.sub_title || 'Start learning today'}</p>
                  <h2 className="font-display text-2xl font-bold md:text-3xl">{coursesSection?.title || 'Featured Programs'}</h2>
                  <p className="text-muted-foreground max-w-2xl text-sm md:text-base">
                     {coursesSection?.description ||
                        'Explore assigned and open-enrollment courses curated for Smart Sourcing Academy teams and partners.'}
                  </p>
               </div>

               <Button asChild variant="outline" className="rounded-full">
                  <Link href={route('category.courses', { category: 'all' })}>View all courses</Link>
               </Button>
            </div>

            {courses.length > 0 ? (
               <div className="space-y-6">
                  <Carousel
                     setApi={setApi}
                     opts={{ align: 'start', loop: courses.length > 1 }}
                     plugins={[autoplay.current]}
                     className="relative"
                  >
                     <CarouselContent className="-ml-4">
                        {courses.map((course) => (
                           <CarouselItem key={course.id} className="basis-full pl-4 sm:basis-1/2 lg:basis-1/3">
                              <CourseCard1 course={course} />
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
