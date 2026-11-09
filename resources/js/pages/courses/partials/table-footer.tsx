import { Button } from '@/components/ui/button';
import { getQueryParams } from '@/lib/route';
import { router, usePage } from '@inertiajs/react';

interface Props {
   className?: string;
   routeName: string;
   routeParams?: Record<string, string | number>;
   paginationInfo: Pagination<any>;
}

const TableFooter = ({ className, routeName, routeParams, paginationInfo }: Props) => {
   const { url, props } = usePage();
   const urlParams = getQueryParams(url);
   const { current_page, last_page, next_page_url, prev_page_url, total } = paginationInfo;
   const count = Number(total ?? 0);
   const pageProps = props as { category?: { id?: number }; categoryChild?: { id?: number } };

   const gotoRoute = (path: string) => {
      const pathParams = getQueryParams(path);

      router.get(
         route(routeName, {
            category: pageProps.category?.id,
            category_child: pageProps.categoryChild?.id,
            ...(routeParams || {}),
            ...urlParams,
            ...pathParams,
         }),
      );
   };

   return (
      <div className={className}>
         <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground text-sm">
               {count} {count === 1 ? 'course' : 'courses'}
            </p>
            {Number(last_page) > 1 ? (
               <div className="flex gap-2">
                  <Button
                     type="button"
                     variant="outline"
                     className="h-8 px-3 text-xs"
                     disabled={!prev_page_url || current_page <= 1}
                     onClick={() => prev_page_url && gotoRoute(prev_page_url)}
                  >
                     Previous
                  </Button>
                  <Button
                     type="button"
                     variant="outline"
                     className="h-8 px-3 text-xs"
                     disabled={!next_page_url}
                     onClick={() => next_page_url && gotoRoute(next_page_url)}
                  >
                     Next
                  </Button>
               </div>
            ) : null}
         </div>
      </div>
   );
};

export default TableFooter;
