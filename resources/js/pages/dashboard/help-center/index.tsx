import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export const HELP_CATEGORIES: { key: string; label: string }[] = [
   { key: 'getting_started', label: 'Getting Started' },
   { key: 'courses', label: 'Courses' },
   { key: 'exams', label: 'Exams' },
   { key: 'certificates', label: 'Certificates' },
   { key: 'account', label: 'Account & Settings' },
   { key: 'general', label: 'General' },
];

const categoryLabel = (key: string) => HELP_CATEGORIES.find((c) => c.key === key)?.label ?? key;

interface HelpCenterArticle {
   id: number;
   category: string;
   title: string;
   slug: string;
   body: string | null;
   video_url: string | null;
   video: string | null;
   video_name: string | null;
   file: string | null;
   file_name: string | null;
   is_published: boolean;
   sort_order: number;
   author?: { id: number; name: string } | null;
   created_at?: string;
}

interface Props extends SharedData {
   articles: HelpCenterArticle[];
}

const ArticleForm = ({ article, onDone }: { article?: HelpCenterArticle; onDone: () => void }) => {
   const { data, setData, post, errors, processing } = useForm<{
      category: string;
      title: string;
      body: string;
      video_url: string;
      is_published: boolean;
      sort_order: number | string;
      file: File | null;
      video: File | null;
   }>({
      category: article?.category ?? HELP_CATEGORIES[0].key,
      title: article?.title ?? '',
      body: article?.body ?? '',
      video_url: article?.video_url ?? '',
      is_published: article?.is_published ?? true,
      sort_order: article?.sort_order ?? 0,
      file: null,
      video: null,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      const url = article ? route('help-center.update', article.id) : route('help-center.store');
      post(url, { forceFormData: true, onSuccess: onDone });
   };

   return (
      <form onSubmit={handleSubmit} className="space-y-4">
         <div>
            <Label>Category</Label>
            <Select value={data.category} onValueChange={(value) => setData('category', value)}>
               <SelectTrigger>
                  <SelectValue />
               </SelectTrigger>
               <SelectContent>
                  {HELP_CATEGORIES.map((c) => (
                     <SelectItem key={c.key} value={c.key}>
                        {c.label}
                     </SelectItem>
                  ))}
               </SelectContent>
            </Select>
            <InputError message={errors.category} />
         </div>

         <div>
            <Label>Title</Label>
            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
            <InputError message={errors.title} />
         </div>

         <div>
            <Label>Content / Tutorial Steps</Label>
            <Textarea rows={6} value={data.body} onChange={(e) => setData('body', e.target.value)} placeholder="Write the tutorial steps or FAQ answer..." />
            <InputError message={errors.body} />
         </div>

         <div>
            <Label>Video URL (optional)</Label>
            <Input value={data.video_url} onChange={(e) => setData('video_url', e.target.value)} placeholder="https://youtube.com/..." />
            <InputError message={errors.video_url} />
         </div>

         <div>
            <Label>Upload Video Tutorial (optional)</Label>
            <Input
               type="file"
               accept="video/mp4,video/webm,video/ogg,video/quicktime,.mkv,.avi"
               onChange={(e) => setData('video', e.target.files?.[0] ?? null)}
            />
            {article?.video_name && (
               <p className="text-muted-foreground mt-1 text-xs">Current: {article.video_name}</p>
            )}
            <p className="text-muted-foreground mt-1 text-xs">MP4, WebM, OGG, MOV up to 500MB.</p>
            <InputError message={errors.video} />
         </div>

         <div>
            <Label>Tutorial Document (optional)</Label>
            <Input type="file" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} />
            {article?.file_name && (
               <p className="text-muted-foreground mt-1 text-xs">Current: {article.file_name}</p>
            )}
            <InputError message={errors.file} />
         </div>

         <div className="flex items-center gap-2">
            <Checkbox
               id="hc-published"
               checked={data.is_published}
               onCheckedChange={(checked) => setData('is_published', checked === true)}
            />
            <Label htmlFor="hc-published" className="text-sm">Published (visible to students)</Label>
            <InputError message={errors.is_published} />
         </div>

         <div>
            <Label>Sort order</Label>
            <Input
               type="number"
               value={data.sort_order}
               onChange={(e) => setData('sort_order', e.target.value === '' ? '' : Number(e.target.value))}
            />
            <InputError message={errors.sort_order} />
         </div>

         <DialogFooter>
            <Button type="submit" disabled={processing}>
               {processing ? 'Saving...' : 'Save'}
            </Button>
         </DialogFooter>
      </form>
   );
};

const Index = ({ articles }: Props) => {
   const [dialog, setDialog] = useState(false);
   const [editing, setEditing] = useState<HelpCenterArticle | undefined>(undefined);

   const openCreate = () => {
      setEditing(undefined);
      setDialog(true);
   };

   const openEdit = (article: HelpCenterArticle) => {
      setEditing(article);
      setDialog(true);
   };

   return (
      <>
         <Head title="Help Center" />

         <div className="container mx-auto space-y-6 px-4 py-6">
            <div className="flex items-center justify-between">
               <div>
                  <h1 className="text-2xl font-bold">Help Center</h1>
                  <p className="text-muted-foreground mt-1 text-sm">
                     Upload tutorials and FAQ guides that students can read to learn how to use the platform.
                  </p>
               </div>
               <Button onClick={openCreate}>New Article</Button>
            </div>

            <Card>
               <CardContent className="p-0">
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>Title</TableHead>
                           <TableHead>Category</TableHead>
                           <TableHead>Status</TableHead>
                           <TableHead>Video</TableHead>
                           <TableHead>Document</TableHead>
                           <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {articles.map((article) => (
                           <TableRow key={article.id}>
                              <TableCell className="font-medium">{article.title}</TableCell>
                              <TableCell>{categoryLabel(article.category)}</TableCell>
                              <TableCell>
                                 {article.is_published ? (
                                    <span className="text-sm font-medium text-green-600">Published</span>
                                 ) : (
                                    <span className="text-muted-foreground text-sm">Draft</span>
                                 )}
                              </TableCell>
                              <TableCell>
                                 {article.video ? (
                                    <a href={article.video} target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">
                                       {article.video_name ?? 'Video'}
                                    </a>
                                 ) : article.video_url ? (
                                    <a href={article.video_url} target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">
                                       External link
                                    </a>
                                 ) : (
                                    '—'
                                 )}
                              </TableCell>
                              <TableCell>
                                 {article.file ? (
                                    <a href={article.file} target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">
                                       {article.file_name ?? 'File'}
                                    </a>
                                 ) : (
                                    '—'
                                 )}
                              </TableCell>
                              <TableCell className="space-x-2 text-right">
                                 <Button size="sm" variant="outline" onClick={() => openEdit(article)}>
                                    Edit
                                 </Button>
                                 <Button size="sm" variant="destructive" onClick={() => router.delete(route('help-center.destroy', article.id))}>
                                    Delete
                                 </Button>
                              </TableCell>
                           </TableRow>
                        ))}
                        {articles.length === 0 && (
                           <TableRow>
                              <TableCell colSpan={6} className="text-muted-foreground py-8 text-center text-sm">
                                 No help articles yet. Click “New Article” to add one.
                              </TableCell>
                           </TableRow>
                        )}
                     </TableBody>
                  </Table>
               </CardContent>
            </Card>
         </div>

         <Dialog open={dialog} onOpenChange={setDialog}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
               <DialogHeader>
                  <DialogTitle>{editing ? 'Edit Article' : 'New Article'}</DialogTitle>
               </DialogHeader>
               <ArticleForm key={editing?.id ?? 'new'} article={editing} onDone={() => setDialog(false)} />
            </DialogContent>
         </Dialog>
      </>
   );
};

Index.layout = (page: React.ReactNode) => <DashboardLayout children={page} />;

export default Index;
