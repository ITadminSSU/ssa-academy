import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import useScreen from '@/hooks/use-screen';
import LandingLayout from '@/layouts/landing-layout';
import { getQueryParams } from '@/lib/route';
import { router, usePage } from '@inertiajs/react';
import { ListFilter } from 'lucide-react';
import { ReactNode, useState } from 'react';
import { CoursesIndexProps } from './index';
import CourseCatalogToolbar from './partials/course-catalog-toolbar';
import CourseFilter from './partials/course-filter';

const Layout = ({ children }: { children: ReactNode }) => {
   const { url, props } = usePage<CoursesIndexProps>();
   const { category, categoryChild, translate } = props;
   const { frontend } = translate;
   const [open, setOpen] = useState(false);
   const urlParams = getQueryParams(url);
   const viewType = urlParams['view'] ?? 'grid';
   const { screen } = useScreen();

   const getQueryRoute = (newParams: Record<string, string>, slug: string, category_child?: string) => {
      const updatedParams = { ...urlParams };

      if ('search' in updatedParams) {
         delete updatedParams.search;
      }

      return route('category.courses', {
         category: slug,
         category_child,
         ...updatedParams,
         ...newParams,
      });
   };

   const gridListHandler = (view: string) => {
      router.get(getQueryRoute({ view }, category?.slug || 'all', categoryChild?.slug));
   };

   const pageTitle = `${category || categoryChild ? category?.title || categoryChild?.title : frontend.all} ${frontend.courses}`;
   const pageDescription = category?.description || categoryChild?.description || null;

   return (
      <LandingLayout customizable={false}>
         <div className="ssu-page-shell">
            <div className="container space-y-6 py-8">
               <CourseCatalogToolbar
                  variant="hero"
                  title={pageTitle}
                  description={pageDescription}
                  viewType={viewType}
                  onViewChange={gridListHandler}
                  gridLabel={frontend.grid_view}
                  listLabel={frontend.list_view}
               />

               <div className="flex items-start gap-6">
                  {screen > 768 && (
                     <aside className="ssu-catalog-filter sticky top-24 w-64 shrink-0">
                        <CourseFilter />
                     </aside>
                  )}

                  <div className="min-w-0 flex-1">
                     <div className="mb-6 flex items-center gap-2 md:hidden">
                        <Sheet open={open} onOpenChange={setOpen}>
                           <SheetTrigger asChild>
                              <Button size="sm" variant="outline">
                                 <ListFilter className="mr-2 h-4 w-4" />
                                 Filters
                              </Button>
                           </SheetTrigger>

                           <SheetContent side="left" className="border-border w-[260px]">
                              <ScrollArea className="h-full pr-3">
                                 <CourseFilter setOpen={setOpen} />
                              </ScrollArea>
                           </SheetContent>
                        </Sheet>
                     </div>

                     {children}
                  </div>
               </div>
            </div>
         </div>
      </LandingLayout>
   );
};

export default Layout;
