import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StudentDashboardProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Download, ExternalLink, FileText } from 'lucide-react';

export const RESOURCE_TYPES: { key: string; label: string }[] = [
   { key: 'course_outline', label: 'Course Outlines' },
   { key: 'estimating_template', label: 'Estimating Templates' },
   { key: 'sample_project', label: 'Sample Projects' },
];

const ResourceCard = ({ resource }: { resource: LearnerResource }) => (
   <Card className="border">
      <CardContent className="space-y-3 p-4">
         <div className="flex items-start gap-3">
            <div className="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
               <FileText className="h-5 w-5" />
            </div>
            <div className="min-w-0">
               <p className="font-semibold">{resource.title}</p>
               {resource.description && <p className="text-muted-foreground mt-1 text-sm">{resource.description}</p>}
            </div>
         </div>
         <div className="flex flex-wrap gap-2">
            {resource.file && (
               <Button asChild size="sm" variant="outline">
                  <a href={resource.file} target="_blank" rel="noopener noreferrer" download={resource.file_name ?? true}>
                     <Download className="h-4 w-4" />
                     {resource.file_name ?? 'Download'}
                  </a>
               </Button>
            )}
            {resource.link && (
               <Button asChild size="sm" variant="outline">
                  <a href={resource.link} target="_blank" rel="noopener noreferrer">
                     <ExternalLink className="h-4 w-4" />
                     Open link
                  </a>
               </Button>
            )}
         </div>
      </CardContent>
   </Card>
);

const Resources = () => {
   const { resources = [] } = usePage<StudentDashboardProps>().props;

   const queryType = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('type') : null;
   const defaultType = RESOURCE_TYPES.find((type) => type.key === queryType)?.key ?? RESOURCE_TYPES[0].key;

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">Resources</h1>
            <p className="text-muted-foreground mt-1 text-sm">Download templates, outlines, and sample materials.</p>
         </div>

         <Tabs defaultValue={defaultType} className="w-full">
            <TabsList className="flex h-auto flex-wrap justify-start gap-2 bg-transparent p-0">
               {RESOURCE_TYPES.map((type) => (
                  <TabsTrigger
                     key={type.key}
                     value={type.key}
                     className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground rounded-lg border px-4 py-2"
                  >
                     {type.label}
                  </TabsTrigger>
               ))}
            </TabsList>

            {RESOURCE_TYPES.map((type) => {
               const items = resources.filter((resource) => resource.type === type.key);

               return (
                  <TabsContent key={type.key} value={type.key} className="mt-4">
                     {items.length > 0 ? (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                           {items.map((resource) => (
                              <ResourceCard key={resource.id} resource={resource} />
                           ))}
                        </div>
                     ) : (
                        <Card className="border">
                           <CardContent className="flex flex-col items-center justify-center gap-3 p-10 text-center">
                              <FileText className="text-muted-foreground h-10 w-10" />
                              <p className="text-muted-foreground text-sm">No {type.label.toLowerCase()} available yet.</p>
                           </CardContent>
                        </Card>
                     )}
                  </TabsContent>
               );
            })}
         </Tabs>
      </div>
   );
};

export default Resources;
