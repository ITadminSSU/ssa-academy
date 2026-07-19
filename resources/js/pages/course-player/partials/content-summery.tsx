import StudentFeedback from '@/components/student-feedback';
import Tabs from '@/components/tabs';
import { Separator } from '@/components/ui/separator';
import { TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CoursePlayerProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Renderer } from 'richtor';
import 'richtor/styles';
import ReviewForm from '../forms/review';
import Forum from './forum';
import Resource from './resource';

const ContentSummery = () => {
   const { props, url } = usePage<CoursePlayerProps>();
   const { translate, type } = props;
   const { button } = translate;
   const [isResource, setIsResource] = useState(false);

   const defaultTab = useMemo(() => {
      const panel = new URLSearchParams(url.split('?')[1] ?? '').get('panel');

      if (panel === 'forum' || panel === 'review' || panel === 'resource' || panel === 'summery') {
         return panel;
      }

      return 'summery';
   }, [url]);

   useEffect(() => {
      if (type === 'lesson') {
         const watching = props.watching as SectionLesson;

         if (watching?.resources?.length > 0) {
            setIsResource(true);
         }
      }
   }, [props.watching, type]);

   const tabs = [
      {
         value: 'summery',
         label: button.summery,
      },
      {
         value: 'resource',
         label: 'Resource',
      },
      {
         value: 'forum',
         label: button.forum,
      },
      {
         value: 'review',
         label: button.review,
      },
   ];

   return (
      <Tabs defaultValue={defaultTab} key={defaultTab} className="mx-auto grid w-full max-w-5xl rounded-md pt-1 pb-10">
         <div className="overflow-x-auto">
            <TabsList className="bg-transparent px-0 py-4">
               {tabs.map(({ label, value }) => {
                  if (value === 'resource' && !isResource) {
                     return null;
                  }

                  return (
                     <TabsTrigger
                        key={value}
                        value={value}
                        className="border-primary data-[state=active]:!bg-muted data-[state=active]:before:bg-primary relative flex cursor-pointer items-center justify-start gap-3 rounded-none bg-transparent px-8 py-3 text-start !shadow-none before:absolute before:right-0 before:bottom-0 before:left-0 before:h-1 before:rounded-t-xl data-[state=active]:before:content-['.']"
                     >
                        <span>{label}</span>
                     </TabsTrigger>
                  );
               })}
            </TabsList>
         </div>

         <Separator className="mt-[1px]" />

         <TabsContent value="summery" className="m-0 p-5">
            <Renderer value={props.watching.summary as any} />
         </TabsContent>
         {isResource && (
            <TabsContent value="resource" className="m-0 p-5">
               <Resource />
            </TabsContent>
         )}
         <TabsContent value="forum" className="m-0 p-5">
            <Forum />
         </TabsContent>
         <TabsContent value="review" className="m-0 space-y-6 p-5">
            <StudentFeedback totalReviews={props.totalReviews} />
            <ReviewForm />
         </TabsContent>
      </Tabs>
   );
};

export default ContentSummery;
