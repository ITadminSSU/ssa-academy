import { Separator } from '@/components/ui/separator';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const UsExperience = () => {
   const { translate } = usePage<SharedData>().props;
   const { button } = translate;

   return (
      <>
         <h6 className="mb-4 text-xl font-semibold">
            {button.build_your_us_experience ?? 'Build Your US Experience'}
         </h6>
         <Separator className="my-6" />
      </>
   );
};

export default UsExperience;
