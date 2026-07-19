import SectionEditor from '@/components/section-editor';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Pencil } from 'lucide-react';

interface Props {
   landingPage?: Page | null;
}

const editableSlugs = ['hero', 'pillars', 'top_courses', 'call_to_action'];

const SsuLandingSettings = ({ landingPage }: Props) => {
   if (!landingPage) {
      return (
         <Card className="p-6">
            <p className="text-muted-foreground text-sm">SSU landing page is not configured. Run database migrations to create it.</p>
         </Card>
      );
   }

   const sections = (landingPage.sections || [])
      .filter((section) => editableSlugs.includes(section.slug))
      .sort((a, b) => (a.sort || 0) - (b.sort || 0));

   return (
      <Card>
         <div className="flex flex-col gap-4 border-b p-4 md:flex-row md:items-center md:justify-between">
            <div>
               <h2 className="text-lg font-medium">SSU Academy Landing Page</h2>
               <p className="text-muted-foreground text-sm">
                  Edit hero copy, value pillars, featured courses, and the bottom call-to-action. Preview at{' '}
                  <a href={route('home')} target="_blank" rel="noopener noreferrer" className="text-primary underline">
                     the public home page
                  </a>
                  .
               </p>
            </div>

            <Button asChild variant="outline" size="sm">
               <a href={route('home')} target="_blank" rel="noopener noreferrer">
                  Preview landing
               </a>
            </Button>
         </div>

         <div className="divide-y">
            {sections.map((section) => (
               <div key={section.id} className="flex items-center justify-between gap-4 p-4">
                  <div>
                     <p className="font-medium">{section.name}</p>
                     <p className="text-muted-foreground text-sm capitalize">{section.slug.replace(/_/g, ' ')}</p>
                  </div>

                  <SectionEditor
                     section={section}
                     actionComponent={
                        <Button size="sm" variant="secondary">
                           <Pencil className="mr-2 h-4 w-4" />
                           Edit copy
                        </Button>
                     }
                  />
               </div>
            ))}
         </div>
      </Card>
   );
};

export default SsuLandingSettings;
