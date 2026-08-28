import { Separator } from '@/components/ui/separator';
import { CourseDetailsProps } from '@/pages/courses/show';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';

const UsExperience = () => {
   const { translate, usExperiencePreview = [] } = usePage<CourseDetailsProps & SharedData>().props;
   const { button } = translate;

   return (
      <>
         <h6 className="mb-4 text-xl font-semibold">{button.build_your_us_experience ?? 'Build Your US Experience'}</h6>
         <Separator className="my-6" />
         <p className="text-muted-foreground mb-6 text-sm">
            Practice takeoff plans included with a monthly subscription. Enrolled students download drawings, fill the Excel BOQ, and
            unlock the next plan after a passing score.
         </p>
         {usExperiencePreview.length > 0 ? (
            <div className="space-y-6">
               {usExperiencePreview.map((group) => (
                  <div key={group.group_name}>
                     <p className="font-medium">{group.group_name}</p>
                     {group.group_description && <p className="text-muted-foreground mt-1 text-sm">{group.group_description}</p>}
                     <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                        {group.plans.map((plan, planIndex) => (
                           <li key={`${plan.title}-${planIndex}`}>{plan.title}</li>
                        ))}
                     </ul>
                  </div>
               ))}
               <p className="text-sm font-medium">Included with monthly subscription</p>
            </div>
         ) : (
            <p className="text-muted-foreground text-sm">Included with monthly subscription.</p>
         )}
      </>
   );
};

export default UsExperience;
