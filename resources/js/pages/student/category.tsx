import CourseCard1 from '@/components/cards/course-card-1';
import { Card, CardContent } from '@/components/ui/card';
import { getQueryParams } from '@/lib/route';
import { cn } from '@/lib/utils';
import TableFooter from '@/pages/courses/partials/table-footer';
import { SharedData } from '@/types/global';
import { Head, router, usePage } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { ReactNode } from 'react';
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

   const title = category ? `${category.title} ${frontend.courses}` : frontend.all_courses;
   const description = category?.description || categoryChild?.description || null;

   return (
      <>
         <Head title={title} />

         <div className="space-y-5">
            <CourseCatalogToolbar
               title={title}
               description={description}
               viewType={viewType}
               onViewChange={(view) =>
                  router.get(
                     route('student.category.courses', {
                        category: categorySlug,
                        ...urlParams,
                        view,
                     }),
                  )
               }
               onSearch={(value) => router.get(route('student.category.courses', { category: categorySlug, search: value }))}
               gridLabel="Tile"
               listLabel="List"
            />

            <CourseFilter routeName="student.category.courses" categorySlug={categorySlug} />

            {courses.data.length > 0 ? (
               <>
                  <div className={cn(viewType === 'list' ? 'space-y-3' : 'grid grid-cols-1 items-start gap-5 sm:grid-cols-2 xl:grid-cols-3')}>
                     {courses.data.map((course) => (
                        <CourseCard1 key={course.id} course={course} wishlists={wishlists} viewType={viewType} variant="catalog" />
                     ))}
                  </div>

                  <TableFooter
                     className="mt-2"
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
      </>
   );
};

CategoryCourses.layout = (page: ReactNode) => <Layout children={page} tab="" />;

export default CategoryCourses;
