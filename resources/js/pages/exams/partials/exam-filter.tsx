import SearchInput from '@/components/search-input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { getQueryParams } from '@/lib/route';
import { Link, router, usePage } from '@inertiajs/react';
import { ExamsIndexProps } from '..';

interface ExamFilterProps {
   setOpen?: (open: boolean) => void;
}

const ExamFilter = ({ setOpen }: ExamFilterProps) => {
   const page = usePage<ExamsIndexProps>();
   const urlParams = getQueryParams(page.url);
   const { levels, prices, translate } = page.props;
   const { frontend } = translate;

   const getQueryRoute = (newParams: Record<string, string>) => {
      const updatedParams = { ...urlParams };

      if ('search' in updatedParams) {
         delete updatedParams.search;
      }

      return route('exams.browse', {
         ...updatedParams,
         ...newParams,
      });
   };

   return (
      <div className="space-y-6">
         <SearchInput onChangeValue={(value) => router.get(route('exams.browse', { search: value }))} />

         <div>
            <h3 className="mb-3 font-semibold">{translate.common.price}</h3>
            <RadioGroup value={urlParams['pricing_type'] || 'all'}>
               <Link className="flex items-center" href={getQueryRoute({ pricing_type: 'all' })}>
                  <RadioGroupItem className="cursor-pointer" id="price" value="all" />
                  <label htmlFor="price" className="cursor-pointer pl-2">
                     {frontend.all}
                  </label>
               </Link>

               {prices.map((price) => (
                  <Link
                     key={price}
                     className="flex items-center capitalize"
                     href={getQueryRoute({ pricing_type: price })}
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
            <h3 className="mb-3 font-semibold">{translate.common.level}</h3>
            <RadioGroup value={urlParams['level'] || 'all'}>
               <Link
                  className="flex items-center"
                  href={getQueryRoute({ level: 'all' })}
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
                     href={getQueryRoute({ level })}
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

export default ExamFilter;
