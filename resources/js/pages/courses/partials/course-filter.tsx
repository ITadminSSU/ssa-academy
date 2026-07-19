import SearchInput from '@/components/search-input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { getQueryParams } from '@/lib/route';
import { Link, router, usePage } from '@inertiajs/react';
import { CoursesIndexProps } from '..';

interface CourseFilterProps {
   setOpen?: (open: boolean) => void;
   routeName?: 'category.courses' | 'student.category.courses';
   categorySlug?: string;
}

const CourseFilter = ({ setOpen, routeName = 'category.courses', categorySlug }: CourseFilterProps) => {
   const page = usePage<CoursesIndexProps>();
   const urlParams = getQueryParams(page.url);
   const { levels, prices, categories, category, categoryChild, translate } = page.props;
   const { frontend, common } = translate;

   const activeCategorySlug = categorySlug ?? category?.slug ?? 'all';

   const getQueryRoute = (newParams: Record<string, string>, slug: string, category_child?: string) => {
      const updatedParams = { ...urlParams };

      if ('search' in updatedParams) {
         delete updatedParams.search;
      }

      if (routeName === 'student.category.courses') {
         return route('student.category.courses', {
            category: slug,
            ...updatedParams,
            ...newParams,
         });
      }

      return route('category.courses', {
         category: slug,
         category_child,
         ...updatedParams,
         ...newParams,
      });
   };

   return (
      <div className="space-y-6">
         <SearchInput
            onChangeValue={(value) =>
               router.get(
                  routeName === 'student.category.courses'
                     ? route('student.category.courses', { category: activeCategorySlug, search: value })
                     : route('category.courses', { category: 'all', search: value }),
               )
            }
         />

         <div>
            <h3 className="ssu-catalog-filter__heading">{common.categories}</h3>
            <RadioGroup value={categoryChild ? categoryChild?.slug : category?.slug || activeCategorySlug || 'all'}>
               <Link className="flex items-center" href={getQueryRoute({}, 'all')}>
                  <RadioGroupItem className="cursor-pointer" id="category" value="all" />
                  <label htmlFor="category" className="cursor-pointer pl-2">
                     {frontend.all}
                  </label>
               </Link>

               {categories.map((item, ind) => {
                  const key = `category${ind}`;
                  if (item.slug === 'default') return null;

                  return (
                     <div key={key} className="capitalize">
                        <Link
                           className="flex items-center"
                           href={getQueryRoute({}, item.slug)}
                           onFinish={() => !urlParams.search && setOpen && setOpen(false)}
                        >
                           <RadioGroupItem className="cursor-pointer" id={key} value={item.slug} />
                           <label htmlFor={key} className="cursor-pointer pl-2">
                              {item.title}
                           </label>
                        </Link>

                        {routeName === 'category.courses' &&
                           item.category_children?.map((child, childInd) => {
                              const childKey = `category_child${childInd}`;
                              return (
                                 <Link
                                    key={childKey}
                                    className="mt-2 flex items-center pl-3"
                                    href={getQueryRoute({}, item.slug, child.slug)}
                                    onFinish={() => !urlParams.search && setOpen && setOpen(false)}
                                 >
                                    <RadioGroupItem className="cursor-pointer" id={childKey} value={child.slug} />
                                    <label htmlFor={childKey} className="cursor-pointer pl-2">
                                       {child.title}
                                    </label>
                                 </Link>
                              );
                           })}
                     </div>
                  );
               })}
            </RadioGroup>
         </div>

         <div>
            <h3 className="ssu-catalog-filter__heading">{common.price}</h3>
            <RadioGroup value={urlParams['price'] || 'all'}>
               <Link
                  className="flex items-center"
                  href={getQueryRoute({ price: 'all' }, category?.slug || activeCategorySlug, categoryChild?.slug)}
                  onFinish={() => !urlParams.search && setOpen && setOpen(false)}
               >
                  <RadioGroupItem className="cursor-pointer" id="price" value="all" />
                  <label htmlFor="price" className="cursor-pointer pl-2">
                     {frontend.all}
                  </label>
               </Link>

               {prices.map((price) => (
                  <Link
                     key={price}
                     className="flex items-center capitalize"
                     href={getQueryRoute({ price }, category?.slug || activeCategorySlug, categoryChild?.slug)}
                     onFinish={() => !urlParams.search && setOpen && setOpen(false)}
                  >
                     <RadioGroupItem className="cursor-pointer" value={price} id={price} />
                     <label htmlFor={price} className="cursor-pointer pl-2">
                        {price}
                     </label>
                  </Link>
               ))}
            </RadioGroup>
         </div>

         <div>
            <h3 className="ssu-catalog-filter__heading">{common.level}</h3>
            <RadioGroup value={urlParams['level'] || 'all'}>
               <Link
                  className="flex items-center"
                  href={getQueryRoute({ level: 'all' }, category?.slug || activeCategorySlug, categoryChild?.slug)}
                  onFinish={() => !urlParams.search && setOpen && setOpen(false)}
               >
                  <RadioGroupItem className="cursor-pointer" id="level" value="all" />
                  <label htmlFor="level" className="cursor-pointer pl-2">
                     {frontend.all}
                  </label>
               </Link>
               {levels.map((level) => (
                  <Link
                     key={level}
                     className="flex items-center capitalize"
                     href={getQueryRoute({ level }, category?.slug || activeCategorySlug, categoryChild?.slug)}
                     onFinish={() => !urlParams.search && setOpen && setOpen(false)}
                  >
                     <RadioGroupItem className="cursor-pointer" value={level} id={level} />
                     <label htmlFor={level} className="cursor-pointer pl-2">
                        {level}
                     </label>
                  </Link>
               ))}
            </RadioGroup>
         </div>
      </div>
   );
};

export default CourseFilter;
