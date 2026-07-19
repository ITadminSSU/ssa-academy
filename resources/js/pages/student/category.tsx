import CourseCard1 from '@/components/cards/course-card-1';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import TableFooter from '@/components/table/table-footer';
import useScreen from '@/hooks/use-screen';
import { getQueryParams } from '@/lib/route';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Head, router, usePage } from '@inertiajs/react';
import { GraduationCap, ListFilter } from 'lucide-react';
import { ReactNode, useState } from 'react';
import CourseCatalogToolbar from '../courses/partials/course-catalog-toolbar';
import CourseFilter from '../courses/partials/course-filter';
import Layout from './partials/layout';

interface Props extends SharedData {
   category?: CourseCategory | null;
   categorySlug: string;
   categoryChild?: CourseCategoryChild | null;
   courses: Pagination<Course>;
   wishlists: CourseWishlist[];
   categories: CourseCategory[];
   levels: string[];
   prices: string[];
}

const CategoryCourses = ({ category, categorySlug, categoryChild, courses, wishlists, translate }: Props) => {
   const { url } = usePage<Props>();
   const { frontend } = translate;
   const urlParams = getQueryParams(url);
   const viewType = (urlParams['view'] as 'grid' | 'list') ?? 'grid';
   const { screen } = useScreen();
   const [open, setOpen] = useState(false);

   const title = category ? `${category.title} ${frontend.courses}` : frontend.all_courses;
   const description =
      category?.description ||
      categoryChild?.description ||
      `Browse ${courses.total} course${courses.total === 1 ? '' : 's'} available to your team.`;

   const getQueryRoute = (newParams: Record<string, string>) => {
      const updatedParams = { ...urlParams };

      if ('search' in updatedParams) {
         delete updatedParams.search;
      }

      return route('student.category.courses', {
         category: categorySlug,
         ...updatedParams,
         ...newParams,
      });
   };

   return (
      <>
         <Head title={title} />

         <div className="space-y-6">
            <CourseCatalogToolbar
               variant="hero"
               title={title}
               description={description}
               viewType={viewType}
               onViewChange={(view) => router.get(getQueryRoute({ view }))}
               gridLabel={frontend.grid_view}
               listLabel={frontend.list_view}
            />

            <div className="flex items-start gap-6">
               {screen > 768 && (
                  <aside className="ssu-catalog-filter sticky top-24 w-64 shrink-0">
                     <CourseFilter routeName="student.category.courses" categorySlug={categorySlug} />
                  </aside>
               )}

               <div className="min-w-0 flex-1 space-y-6">
                  <div className="flex items-center gap-2 md:hidden">
                     <Sheet open={open} onOpenChange={setOpen}>
                        <SheetTrigger asChild>
                           <Button size="sm" variant="outline">
                              <ListFilter className="mr-2 h-4 w-4" />
                              Filters
                           </Button>
                        </SheetTrigger>

                        <SheetContent side="left" className="border-border w-[260px]">
                           <ScrollArea className="h-full pr-3">
                              <CourseFilter routeName="student.category.courses" categorySlug={categorySlug} setOpen={setOpen} />
                           </ScrollArea>
                        </SheetContent>
                     </Sheet>
                  </div>

                  {courses.data.length > 0 ? (
                     <>
                        <div
                           className={cn(
                              viewType === 'list' ? 'space-y-6' : 'grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3',
                           )}
                        >
                           {courses.data.map((course) => (
                              <CourseCard1 key={course.id} course={course} wishlists={wishlists} viewType={viewType} />
                           ))}
                        </div>

                        <TableFooter
                           className="ssu-surface-card p-5 sm:p-7"
                           routeName="student.category.courses"
                           paginationInfo={courses}
                           routeParams={{ category: categorySlug }}
                        />
                     </>
                  ) : (
                     <Card className="ssu-surface-card border">
                        <CardContent className="flex flex-col items-center justify-center gap-3 p-12 text-center">
                           <GraduationCap className="text-muted-foreground h-10 w-10" />
                           <p className="text-muted-foreground text-sm">No courses are available in this category yet.</p>
                        </CardContent>
                     </Card>
                  )}
               </div>
            </div>
         </div>
      </>
   );
};

CategoryCourses.layout = (page: ReactNode) => <Layout children={page} tab="" />;

export default CategoryCourses;
