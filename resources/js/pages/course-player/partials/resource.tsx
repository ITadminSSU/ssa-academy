import { Button } from '@/components/ui/button';
import { CoursePlayerProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';

const Resource = () => {
   const { props } = usePage<CoursePlayerProps>();
   const resources = (props.watching as SectionLesson).resources;

   const handleDownload = (resource: LessonResource, e: React.MouseEvent) => {
      e.preventDefault();
      window.open(route('resources.download', resource.id), '_blank');
   };

   const handleView = (resource: LessonResource, e: React.MouseEvent) => {
      e.preventDefault();
      window.open(route('resources.view', { resource: resource.id }), '_blank');
   };

   return (
      <div className="space-y-5">
         {resources.map((resource: LessonResource) =>
            resource.type === 'link' ? (
               <div key={resource.id} className="bg-muted rounded-md p-1.5">
                  <div className="flex items-center justify-between gap-2">
                     <div className="w-full px-1">
                        <a target="_blank" rel="noopener noreferrer" href={resource.resource} className="cursor-pointer text-sm hover:underline">
                           {resource.title.slice(0, 50) + (resource.title.length > 50 ? '...' : '')}
                        </a>
                     </div>

                     <Button asChild size="icon" variant="secondary" className="h-7 w-7">
                        <a target="_blank" rel="noopener noreferrer" href={resource.resource}>
                           <Eye className="h-3 w-3" />
                        </a>
                     </Button>
                  </div>
               </div>
            ) : resource.is_downloadable !== false ? (
               <div key={resource.id} className="bg-muted rounded-md p-1.5">
                  <div className="flex items-center justify-between gap-2">
                     <div className="w-full px-1">
                        <span className="cursor-pointer text-sm hover:underline" onClick={(e) => handleDownload(resource, e)}>
                           {resource.title.slice(0, 50) + (resource.title.length > 50 ? '...' : '')}
                        </span>
                     </div>

                     <Button size="icon" variant="secondary" className="h-7 w-7" onClick={(e) => handleDownload(resource, e)}>
                        <Download className="h-3 w-3" />
                     </Button>
                  </div>
               </div>
            ) : (
               <div key={resource.id} className="bg-muted rounded-md p-1.5">
                  <div className="flex items-center justify-between gap-2">
                     <div className="w-full px-1">
                        <span className="text-sm">{resource.title.slice(0, 50) + (resource.title.length > 50 ? '...' : '')}</span>
                     </div>

                     <Button size="icon" variant="secondary" className="h-7 w-7" onClick={(e) => handleView(resource, e)}>
                        <Eye className="h-3 w-3" />
                     </Button>
                  </div>
               </div>
            ),
         )}
      </div>
   );
};

export default Resource;
