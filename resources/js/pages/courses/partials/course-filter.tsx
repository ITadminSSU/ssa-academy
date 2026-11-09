import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getQueryParams } from '@/lib/route';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import { CoursesIndexProps } from '..';

interface CourseFilterProps {
   routeName?: 'category.courses' | 'student.category.courses';
   categorySlug?: string;
}

const CourseFilter = ({ routeName = 'category.courses', categorySlug }: CourseFilterProps) => {
   const page = usePage<CoursesIndexProps>();
   const urlParams = getQueryParams(page.url);
   const { levels, prices, categories, category, categoryChild, translate } = page.props;
   const { frontend, common } = translate;
   const activeCategorySlug = categorySlug ?? category?.slug ?? 'all';
   const showChildren = routeName === 'category.courses';

   const routeFor = (newParams: Record<string, string>, slug: string, category_child?: string, keepSearch = false) => {
      const updatedParams = { ...urlParams };

      if (!keepSearch && 'search' in updatedParams) {
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

   const chipClass = (selected: boolean) =>
      cn(
         'inline-flex items-center rounded-full border px-3 py-1 text-sm',
         selected
            ? 'border-primary bg-primary text-primary-foreground'
            : 'border-primary/40 text-primary hover:border-primary bg-[color:var(--brand-grey)]',
      );

   const priceValue = urlParams['price'] || 'all';
   const levelValue = urlParams['level'] || 'all';
   const currentSlug = category?.slug || activeCategorySlug;

   const chooseMenu = (key: 'price' | 'level', value: string) => {
      router.get(routeFor({ [key]: value }, currentSlug, categoryChild?.slug, true), {}, { preserveScroll: true });
   };

   return (
      <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
         <div className="flex min-w-0 flex-1 flex-wrap gap-2">
            <Link href={routeFor({}, 'all')} className={chipClass(!category && !categoryChild)}>
               {frontend.all}
            </Link>

            {categories.map((item) => {
               if (item.slug === 'default') return null;

               const parentSelected = !categoryChild && category?.slug === item.slug;

               return (
                  <span key={item.slug} className="contents">
                     <Link href={routeFor({}, item.slug)} className={cn(chipClass(parentSelected), 'capitalize')}>
                        {item.title}
                     </Link>
                     {showChildren
                        ? item.category_children?.map((child) => (
                             <Link
                                key={`${item.slug}-${child.slug}`}
                                href={routeFor({}, item.slug, child.slug)}
                                className={cn(chipClass(categoryChild?.slug === child.slug), 'capitalize')}
                             >
                                {child.title}
                             </Link>
                          ))
                        : null}
                  </span>
               );
            })}
         </div>

         <div className="flex shrink-0 flex-col gap-2 sm:flex-row">
            <Select value={priceValue} onValueChange={(value) => chooseMenu('price', value)}>
               <SelectTrigger className="border-primary/30 bg-card h-9 w-full capitalize sm:w-[150px]" aria-label={common.price}>
                  <SelectValue>
                     {common.price}: {priceValue === 'all' ? frontend.all : priceValue}
                  </SelectValue>
               </SelectTrigger>
               <SelectContent>
                  <SelectItem value="all">
                     {common.price}: {frontend.all}
                  </SelectItem>
                  {prices.map((price) => (
                     <SelectItem key={price} value={price} className="capitalize">
                        {price}
                     </SelectItem>
                  ))}
               </SelectContent>
            </Select>

            <Select value={levelValue} onValueChange={(value) => chooseMenu('level', value)}>
               <SelectTrigger className="border-primary/30 bg-card h-9 w-full capitalize sm:w-[160px]" aria-label={common.level}>
                  <SelectValue>
                     {common.level}: {levelValue === 'all' ? frontend.all : levelValue}
                  </SelectValue>
               </SelectTrigger>
               <SelectContent>
                  <SelectItem value="all">
                     {common.level}: {frontend.all}
                  </SelectItem>
                  {levels.map((level) => (
                     <SelectItem key={level} value={String(level)} className="capitalize">
                        {String(level)}
                     </SelectItem>
                  ))}
               </SelectContent>
            </Select>
         </div>
      </div>
   );
};

export default CourseFilter;
