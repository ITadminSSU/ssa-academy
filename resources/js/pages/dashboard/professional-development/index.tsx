import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, useForm } from '@inertiajs/react';
import { Editor } from 'richtor';
import 'richtor/styles';

interface Props extends SharedData {
   guides: ProfessionalDevelopmentGuide[];
}

const GuideForm = ({ guide }: { guide: ProfessionalDevelopmentGuide }) => {
   const { data, setData, put, errors, processing } = useForm({
      title: guide.title,
      content: guide.content ?? '',
      is_published: guide.is_published,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      put(route('professional-development.update', guide.id));
   };

   return (
      <Card className="p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-4">
            <div>
               <Label>Title</Label>
               <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
               <InputError message={errors.title} />
            </div>

            <div>
               <Label>Content</Label>
               <Editor
                  ssr={true}
                  output="html"
                  contentMinHeight={256}
                  contentMaxHeight={640}
                  initialContent={data.content}
                  onContentChange={(value) => setData('content', value as string)}
               />
               <InputError message={errors.content} />
            </div>

            <div className="flex items-center gap-3">
               <Switch checked={data.is_published} onCheckedChange={(value) => setData('is_published', value)} />
               <Label>Published</Label>
            </div>

            <Button type="submit" disabled={processing}>
               {processing ? 'Saving...' : 'Save changes'}
            </Button>
         </form>
      </Card>
   );
};

const Index = ({ guides }: Props) => {
   return (
      <>
         <Head title="Professional Development" />

         <div className="container mx-auto space-y-6 px-4 py-6">
            <div>
               <h1 className="text-2xl font-bold">Professional Development</h1>
               <p className="text-muted-foreground mt-1 text-sm">Manage the career guides shown to learners.</p>
            </div>

            {guides.length > 0 && (
               <Tabs defaultValue={guides[0].key} className="w-full">
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
                        <GuideForm guide={guide} />
                     </TabsContent>
                  ))}
               </Tabs>
            )}
         </div>
      </>
   );
};

Index.layout = (page: React.ReactNode) => <DashboardLayout children={page} />;

export default Index;
