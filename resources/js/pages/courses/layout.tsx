import LandingLayout from '@/layouts/landing-layout';
import { getQueryParams } from '@/lib/route';
import { router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { CoursesIndexProps } from './index';
import CourseCatalogToolbar from './partials/course-catalog-toolbar';
import CourseFilter from './partials/course-filter';

const Layout = ({ children }: { children: ReactNode }) => {
   const { url, props } = usePage<CoursesIndexProps>();
   const { category, categoryChild, translate } = props;
   const { frontend } = translate;
   const urlParams = getQueryParams(url);
   const viewType = urlParams['view'] ?? 'grid';

   const pageTitle = `${category || categoryChild ? category?.title || categoryChild?.title : frontend.all} ${frontend.courses}`;
   const pageDescription = category?.description || categoryChild?.description || null;

   return (
      <LandingLayout customizable={false}>
         <div className="ssu-page-shell">
            <div className="container space-y-5 py-8">
               <CourseCatalogToolbar
                  title={pageTitle}
                  description={pageDescription}
                  viewType={viewType}
                  onViewChange={(view) =>
                     router.get(
                        route('category.courses', {
                           category: category?.slug || 'all',
                           category_child: categoryChild?.slug,
                           ...urlParams,
                           view,
                        }),
                     )
                  }
                  onSearch={(value) => router.get(route('category.courses', { category: 'all', search: value }))}
                  gridLabel="Tile"
                  listLabel="List"
               />

               <CourseFilter />

               {children}
            </div>
         </div>
      </LandingLayout>
   );
};

export default Layout;
