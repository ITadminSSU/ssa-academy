import Tabs from '@/components/tabs';
import { TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { router } from '@inertiajs/react';
import { BookText, CircleDollarSign, FileSpreadsheet, FileText, FlaskConical, FolderInput, HelpCircle, ListTodo, Settings } from 'lucide-react';
import { nanoid } from 'nanoid';
import { ReactNode, useMemo } from 'react';
import ExamUpdateHeader from './partials/exam-update-header';
import Attempts from './partials/tabs-content/attempts';
import Basic from './partials/tabs-content/basic';
import Info from './partials/tabs-content/info';
import Media from './partials/tabs-content/media';
import Pricing from './partials/tabs-content/pricing';
import Questions from './partials/tabs-content/questions';
import QuantityTakeoff from './partials/tabs-content/quantity-takeoff';
import Resources from './partials/tabs-content/resources';
import SEO from './partials/tabs-content/seo';
import ExamSettings from './partials/tabs-content/settings';

export interface ExamUpdateProps extends SharedData {
   tab?: string;
   exam: Exam;
   attempt: ExamAttempt | null;
   attempts: Pagination<ExamAttempt>;
   instructors: Instructor[] | null;
   takeoffAnalytics?: Array<{
      key: string;
      item: string;
      unit: string;
      attempts: number;
      misses: number;
      miss_rate: number;
   }> | null;
}

const Update = (props: ExamUpdateProps) => {
   const { tab, exam } = props;

   const tabs = useMemo(() => {
      const baseTabs = exam.exam_mode === 'quantity_takeoff'
         ? [
              {
                 id: nanoid(),
                 name: 'Quantity Take-Off',
                 slug: 'quantity-takeoff',
                 Icon: FileSpreadsheet,
                 Component: QuantityTakeoff,
              },
           ]
         : [
              {
                 id: nanoid(),
                 name: 'Questions',
                 slug: 'questions',
                 Icon: HelpCircle,
                 Component: Questions,
              },
           ];

      return [
         ...baseTabs,
      {
         id: nanoid(),
         name: 'Resources',
         slug: 'resources',
         Icon: ListTodo,
         Component: Resources,
      },
      {
         id: nanoid(),
         name: 'Attempts',
         slug: 'attempts',
         Icon: ListTodo,
         Component: Attempts,
      },
      {
         id: nanoid(),
         name: 'Basic',
         slug: 'basic',
         Icon: Settings,
         Component: Basic,
      },
      {
         id: nanoid(),
         name: 'Pricing',
         slug: 'pricing',
         Icon: CircleDollarSign,
         Component: Pricing,
      },
      {
         id: nanoid(),
         name: 'Settings',
         slug: 'settings',
         Icon: BookText,
         Component: ExamSettings,
      },
      {
         id: nanoid(),
         name: 'Info',
         slug: 'info',
         Icon: FileText,
         Component: Info,
      },
      {
         id: nanoid(),
         name: 'Media',
         slug: 'media',
         Icon: FolderInput,
         Component: Media,
      },
      {
         id: nanoid(),
         name: 'SEO',
         slug: 'seo',
         Icon: FlaskConical,
         Component: SEO,
      },
      ];
   }, [exam.exam_mode]);

   return (
      <section className="space-y-8">
         <ExamUpdateHeader />

         <Tabs value={tab ?? tabs[0].slug} className="grid grid-rows-1 gap-5 md:grid-cols-4">
            <div className="col-span-full md:col-span-1">
               <TabsList className="horizontal-tabs-list space-y-1">
                  {tabs.map(({ id, name, slug, Icon }) => (
                     <TabsTrigger
                        key={id}
                        value={slug}
                        className="horizontal-tabs-trigger"
                        onClick={() =>
                           router.get(
                              route('exams.edit', {
                                 exam: exam.id,
                                 tab: slug,
                              }),
                           )
                        }
                     >
                        <Icon className="h-4 w-4" />
                        <span>{name}</span>
                     </TabsTrigger>
                  ))}
               </TabsList>
            </div>

            <div className="col-span-full md:col-span-3">
               {tabs.map(({ id, slug, Component }) => (
                  <TabsContent key={id} value={slug} className="m-0">
                     <Component />
                  </TabsContent>
               ))}
            </div>
         </Tabs>
      </section>
   );
};

Update.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Update;
