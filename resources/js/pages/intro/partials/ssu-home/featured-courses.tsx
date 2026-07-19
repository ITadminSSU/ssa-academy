import CourseCard1 from '@/components/cards/course-card-1';
import { Button } from '@/components/ui/button';
import { getPageSection } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';

const FeaturedCourses = () => {
   const { props } = usePage<IntroPageProps>();
   const coursesSection = getPageSection(props.page, 'top_courses');
   const { topCourses } = props;

   if (!topCourses?.length) {
      return null;
   }

   return (
      <section className="border-border/60 border-y bg-[oklch(0.97_0.01_255)] py-20 dark:bg-muted/20">
         <div className="container space-y-8 px-4">
            <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
               <div className="space-y-2">
                  {coursesSection?.sub_title && <p className="ssu-kicker">{coursesSection.sub_title}</p>}
                  {coursesSection?.title && <h2 className="font-display text-2xl font-bold md:text-3xl">{coursesSection.title}</h2>}
                  {coursesSection?.description && <p className="text-muted-foreground max-w-2xl text-sm md:text-base">{coursesSection.description}</p>}
               </div>

               <Button asChild variant="outline" className="rounded-full">
                  <Link href={route('category.courses', { category: 'all' })}>View all courses</Link>
               </Button>
            </div>

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
               {topCourses.slice(0, 6).map((course) => (
                  <CourseCard1 key={course.id} course={course} />
               ))}
            </div>
         </div>
      </section>
   );
};

export default FeaturedCourses;
