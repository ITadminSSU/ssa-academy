import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, router, useForm } from '@inertiajs/react';
import { Download, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Props extends SharedData {
   projects: ProjectItem[];
   projectCategories: ProjectCategory[];
   submissions: ProjectSubmission[];
}

const formatDate = (value?: string | null) => {
   if (!value) return '—';
   const date = new Date(value);
   return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const ScoreDialog = ({ submission, onDone }: { submission: ProjectSubmission; onDone: () => void }) => {
   const { data, setData, post, processing, errors } = useForm<{ score: string; feedback: string }>({
      score: submission.score !== null && submission.score !== undefined ? String(submission.score) : '',
      feedback: submission.feedback ?? '',
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(route('project-submissions.score', submission.id), { preserveScroll: true, onSuccess: onDone });
   };

   return (
      <form onSubmit={handleSubmit} className="space-y-4">
         <div className="text-sm">
            <p className="font-medium">{submission.project?.title}</p>
            <p className="text-muted-foreground">
               {submission.user?.name} · Submitted {formatDate(submission.submitted_at)}
            </p>
         </div>

         {submission.file && (
            <Button asChild size="sm" variant="outline" type="button">
               <a href={submission.file} target="_blank" rel="noopener noreferrer" download={submission.file_name ?? true}>
                  <Download className="h-4 w-4" />
                  Download submission
               </a>
            </Button>
         )}

         <div>
            <Label>Score (0–100)</Label>
            <Input type="number" min={0} max={100} step="0.01" value={data.score} onChange={(e) => setData('score', e.target.value)} />
            <InputError message={errors.score} />
         </div>

         <div>
            <Label>Feedback / remarks</Label>
            <Textarea rows={4} value={data.feedback} onChange={(e) => setData('feedback', e.target.value)} />
            <InputError message={errors.feedback} />
         </div>

         <DialogFooter>
            <Button type="submit" disabled={processing}>
               {processing ? 'Saving...' : 'Save review'}
            </Button>
         </DialogFooter>
      </form>
   );
};

const ProjectForm = ({ project, categories, onDone }: { project?: ProjectItem; categories: ProjectCategory[]; onDone: () => void }) => {
   const { data, setData, post, errors, processing } = useForm<{
      title: string;
      description: string;
      project_category_id: string;
      is_completed: boolean;
      is_published: boolean;
      file: File | null;
   }>({
      title: project?.title ?? '',
      description: project?.description ?? '',
      project_category_id: project?.project_category_id ? String(project.project_category_id) : '',
      is_completed: project?.is_completed ?? false,
      is_published: project?.is_published ?? false,
      file: null,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      const url = project ? route('projects.update', project.id) : route('projects.store');
      post(url, { forceFormData: true, onSuccess: onDone });
   };

   return (
      <form onSubmit={handleSubmit} className="space-y-4">
         <div>
            <Label>Title</Label>
            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
            <InputError message={errors.title} />
         </div>

         <div>
            <Label>Description</Label>
            <Textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
            <InputError message={errors.description} />
         </div>

         <div>
            <Label>Category</Label>
            <Select value={data.project_category_id} onValueChange={(value) => setData('project_category_id', value)}>
               <SelectTrigger>
                  <SelectValue placeholder="Select a category" />
               </SelectTrigger>
               <SelectContent>
                  {categories.map((category) => (
                     <SelectItem key={category.id} value={String(category.id)}>
                        {category.title}
                     </SelectItem>
                  ))}
               </SelectContent>
            </Select>
            <InputError message={errors.project_category_id} />
         </div>

         <div>
            <Label>File {project?.file_name && <span className="text-muted-foreground text-xs">(current: {project.file_name})</span>}</Label>
            <Input type="file" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} />
            <InputError message={errors.file} />
         </div>

         <div className="flex items-center justify-between rounded-md border p-3">
            <div className="space-y-0.5">
               <Label>Publish to learners</Label>
               <p className="text-muted-foreground text-xs">When on, employees can see, download and practice this project.</p>
            </div>
            <Switch checked={data.is_published} onCheckedChange={(value) => setData('is_published', value)} />
         </div>

         <div className="flex items-center justify-between rounded-md border p-3">
            <div className="space-y-0.5">
               <Label>Show as completed sample</Label>
               <p className="text-muted-foreground text-xs">Lists this project under the learner "Completed Projects" tab as a reference.</p>
            </div>
            <Switch checked={data.is_completed} onCheckedChange={(value) => setData('is_completed', value)} />
         </div>

         <DialogFooter>
            <Button type="submit" disabled={processing}>
               {processing ? 'Saving...' : 'Save'}
            </Button>
         </DialogFooter>
      </form>
   );
};

const Index = ({ projects, projectCategories, submissions }: Props) => {
   const [projectDialog, setProjectDialog] = useState(false);
   const [editing, setEditing] = useState<ProjectItem | undefined>(undefined);
   const [scoring, setScoring] = useState<ProjectSubmission | undefined>(undefined);
   const categoryForm = useForm({ title: '' });

   const addCategory = (e: React.FormEvent) => {
      e.preventDefault();
      categoryForm.post(route('project-categories.store'), { onSuccess: () => categoryForm.reset('title') });
   };

   const openCreate = () => {
      setEditing(undefined);
      setProjectDialog(true);
   };

   const openEdit = (project: ProjectItem) => {
      setEditing(project);
      setProjectDialog(true);
   };

   return (
      <>
         <Head title="Project Library" />

         <div className="container mx-auto space-y-6 px-4 py-6">
            <div>
               <h1 className="text-2xl font-bold">Project Library</h1>
               <p className="text-muted-foreground mt-1 text-sm">Manage projects per category and review learner submissions.</p>
            </div>

            <Tabs defaultValue="projects" className="w-full">
               <TabsList>
                  <TabsTrigger value="projects">Projects</TabsTrigger>
                  <TabsTrigger value="submissions">Submissions{submissions.length > 0 && ` (${submissions.length})`}</TabsTrigger>
               </TabsList>

               <TabsContent value="projects" className="mt-4 space-y-6">
                  <div className="flex justify-end">
                     <Button onClick={openCreate}>New Project</Button>
                  </div>

                  <Card>
                     <CardHeader>
                        <CardTitle className="text-base">Categories</CardTitle>
                     </CardHeader>
                     <CardContent className="space-y-4">
                        <form onSubmit={addCategory} className="flex gap-2">
                           <Input
                              placeholder="New category title (e.g. Residential, Commercial)"
                              value={categoryForm.data.title}
                              onChange={(e) => categoryForm.setData('title', e.target.value)}
                           />
                           <Button type="submit" disabled={categoryForm.processing}>
                              Add
                           </Button>
                        </form>
                        <div className="flex flex-wrap gap-2">
                           {projectCategories.map((category) => (
                              <Badge key={category.id} variant="secondary" className="flex items-center gap-2">
                                 {category.title}
                                 {typeof category.projects_count === 'number' && (
                                    <span className="text-muted-foreground">· {category.projects_count}</span>
                                 )}
                                 <button
                                    type="button"
                                    onClick={() => router.delete(route('project-categories.destroy', category.id))}
                                    className="text-muted-foreground hover:text-destructive"
                                 >
                                    <Trash2 className="h-3 w-3" />
                                 </button>
                              </Badge>
                           ))}
                           {projectCategories.length === 0 && <p className="text-muted-foreground text-sm">No categories yet.</p>}
                        </div>
                     </CardContent>
                  </Card>

                  <Card>
                     <CardContent className="p-0">
                        <Table>
                           <TableHeader>
                              <TableRow>
                                 <TableHead>Title</TableHead>
                                 <TableHead>Category</TableHead>
                                 <TableHead>Status</TableHead>
                                 <TableHead>File</TableHead>
                                 <TableHead className="text-right">Actions</TableHead>
                              </TableRow>
                           </TableHeader>
                           <TableBody>
                              {projects.map((project) => (
                                 <TableRow key={project.id}>
                                    <TableCell className="font-medium">{project.title}</TableCell>
                                    <TableCell>{project.category?.title ?? '—'}</TableCell>
                                    <TableCell>{project.is_published ? <Badge className="bg-emerald-600">Published</Badge> : <Badge variant="outline">Draft</Badge>}</TableCell>
                                    <TableCell>
                                       {project.file ? (
                                          <a href={project.file} target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">
                                             {project.file_name ?? 'View'}
                                          </a>
                                       ) : (
                                          '—'
                                       )}
                                    </TableCell>
                                    <TableCell className="space-x-2 text-right">
                                       <Button
                                          size="sm"
                                          variant={project.is_published ? 'outline' : 'default'}
                                          onClick={() => router.post(route('projects.publish', project.id), {}, { preserveScroll: true })}
                                       >
                                          {project.is_published ? 'Unpublish' : 'Publish'}
                                       </Button>
                                       <Button size="sm" variant="outline" onClick={() => openEdit(project)}>
                                          Edit
                                       </Button>
                                       <Button size="sm" variant="destructive" onClick={() => router.delete(route('projects.destroy', project.id))}>
                                          Delete
                                       </Button>
                                    </TableCell>
                                 </TableRow>
                              ))}
                              {projects.length === 0 && (
                                 <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-8 text-center text-sm">
                                       No projects yet.
                                    </TableCell>
                                 </TableRow>
                              )}
                           </TableBody>
                        </Table>
                     </CardContent>
                  </Card>
               </TabsContent>

               <TabsContent value="submissions" className="mt-4">
                  <Card>
                     <CardContent className="p-0">
                        <Table>
                           <TableHeader>
                              <TableRow>
                                 <TableHead>Student</TableHead>
                                 <TableHead>Project</TableHead>
                                 <TableHead>Category</TableHead>
                                 <TableHead>Submitted</TableHead>
                                 <TableHead>File</TableHead>
                                 <TableHead>Score</TableHead>
                                 <TableHead className="text-right">Action</TableHead>
                              </TableRow>
                           </TableHeader>
                           <TableBody>
                              {submissions.map((submission) => {
                                 const scored = submission.score !== null && submission.score !== undefined;
                                 return (
                                    <TableRow key={submission.id}>
                                       <TableCell className="font-medium">{submission.user?.name ?? '—'}</TableCell>
                                       <TableCell>{submission.project?.title ?? '—'}</TableCell>
                                       <TableCell>{submission.project?.category?.title ?? '—'}</TableCell>
                                       <TableCell>{formatDate(submission.submitted_at)}</TableCell>
                                       <TableCell>
                                          {submission.file ? (
                                             <a href={submission.file} target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">
                                                {submission.file_name ?? 'View'}
                                             </a>
                                          ) : (
                                             '—'
                                          )}
                                       </TableCell>
                                       <TableCell>
                                          {scored ? <Badge className="bg-emerald-600">{submission.score}/100</Badge> : <Badge variant="outline">Pending</Badge>}
                                       </TableCell>
                                       <TableCell className="text-right">
                                          <Button size="sm" variant="outline" onClick={() => setScoring(submission)}>
                                             {scored ? 'Edit review' : 'Review'}
                                          </Button>
                                       </TableCell>
                                    </TableRow>
                                 );
                              })}
                              {submissions.length === 0 && (
                                 <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground py-8 text-center text-sm">
                                       No submissions yet.
                                    </TableCell>
                                 </TableRow>
                              )}
                           </TableBody>
                        </Table>
                     </CardContent>
                  </Card>
               </TabsContent>
            </Tabs>
         </div>

         <Dialog open={projectDialog} onOpenChange={setProjectDialog}>
            <DialogContent>
               <DialogHeader>
                  <DialogTitle>{editing ? 'Edit Project' : 'New Project'}</DialogTitle>
               </DialogHeader>
               <ProjectForm
                  key={editing?.id ?? 'new'}
                  project={editing}
                  categories={projectCategories}
                  onDone={() => setProjectDialog(false)}
               />
            </DialogContent>
         </Dialog>

         <Dialog open={!!scoring} onOpenChange={(open) => !open && setScoring(undefined)}>
            <DialogContent>
               <DialogHeader>
                  <DialogTitle>Review submission</DialogTitle>
               </DialogHeader>
               {scoring && <ScoreDialog key={scoring.id} submission={scoring} onDone={() => setScoring(undefined)} />}
            </DialogContent>
         </Dialog>
      </>
   );
};

Index.layout = (page: React.ReactNode) => <DashboardLayout children={page} />;

export default Index;
