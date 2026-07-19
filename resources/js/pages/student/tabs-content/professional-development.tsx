import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StudentDashboardProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Briefcase } from 'lucide-react';

const ProfessionalDevelopment = () => {
   const { guides = [] } = usePage<StudentDashboardProps>().props;

   const queryGuide = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('guide') : null;
   const defaultGuide = guides.find((guide) => guide.key === queryGuide)?.key ?? guides[0]?.key;

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">Professional Development</h1>
            <p className="text-muted-foreground mt-1 text-sm">Guides to help you grow your career and win more work.</p>
         </div>

         {guides.length > 0 ? (
            <Tabs defaultValue={defaultGuide} className="w-full">
               <TabsList className="flex h-auto flex-wrap justify-start gap-2 bg-transparent p-0">
                  {guides.map((guide) => (
                     <TabsTrigger
                        key={guide.key}
                        value={guide.key}
                        className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground rounded-lg border px-4 py-2"
                     >
                        {guide.title}
                     </TabsTrigger>
                  ))}
               </TabsList>

               {guides.map((guide) => (
                  <TabsContent key={guide.key} value={guide.key} className="mt-4">
                     <Card className="border">
                        <CardContent className="p-6">
                           <h2 className="mb-4 text-lg font-semibold">{guide.title}</h2>
                           {guide.content ? (
                              <div
                                 className="prose prose-sm dark:prose-invert max-w-none"
                                 dangerouslySetInnerHTML={{ __html: guide.content }}
                              />
                           ) : (
                              <p className="text-muted-foreground text-sm">No content available yet.</p>
                           )}
                        </CardContent>
                     </Card>
                  </TabsContent>
               ))}
            </Tabs>
         ) : (
            <Card className="border">
               <CardContent className="flex flex-col items-center justify-center gap-3 p-10 text-center">
                  <Briefcase className="text-muted-foreground h-10 w-10" />
                  <p className="text-muted-foreground text-sm">No guides are available right now.</p>
               </CardContent>
            </Card>
         )}
      </div>
   );
};

export default ProfessionalDevelopment;
