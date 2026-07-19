import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StudentDashboardProps } from '@/types/page';
import { useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronDown, Clock, Download, FolderOpen, MessageSquare, Upload } from 'lucide-react';
import { useState } from 'react';

const formatDate = (value?: string | null) => {
   if (!value) return '—';
   const date = new Date(value);
   return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const SubmitWorkDialog = ({ project, hasSubmission }: { project: ProjectItem; hasSubmission: boolean }) => {
   const [open, setOpen] = useState(false);
   const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(route('project-submissions.store', project.id), {
         forceFormData: true,
         preserveScroll: true,
         onSuccess: () => {
            reset('file');
            setOpen(false);
         },
      });
   };

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>
            <Button size="sm" variant={hasSubmission ? 'outline' : 'default'}>
               <Upload className="h-4 w-4" />
               {hasSubmission ? 'Re-submit' : 'Submit Work'}
            </Button>
         </DialogTrigger>
         <DialogContent>
            <DialogHeader>
               <DialogTitle>Submit your work — {project.title}</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleSubmit} className="space-y-4">
               <div>
                  <Label>Upload your completed file</Label>
                  <Input type="file" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} />
                  <InputError message={errors.file} />
                  {hasSubmission && (
                     <p className="text-muted-foreground mt-2 text-xs">Re-submitting will replace your previous file and reset any prior trainer review.</p>
                  )}
               </div>
               <DialogFooter>
                  <Button type="submit" disabled={processing || !data.file}>
                     {processing ? 'Submitting...' : 'Submit'}
                  </Button>
               </DialogFooter>
            </form>
         </DialogContent>
      </Dialog>
   );
};

const ProjectCard = ({ project, submission }: { project: ProjectItem; submission?: ProjectSubmission }) => {
   const scored = submission && submission.score !== null && submission.score !== undefined;

   return (
      <Card className="border">
         <CardContent className="space-y-3 p-4">
            <div className="flex items-start justify-between gap-2">
               <p className="font-semibold">{project.title}</p>
               {project.is_completed && <Badge className="bg-emerald-600">Sample</Badge>}
            </div>
            {project.description && <p className="text-muted-foreground text-sm">{project.description}</p>}

            <div className="flex flex-wrap gap-2">
               {project.file && (
                  <Button asChild size="sm" variant="outline">
                     <a href={project.file} target="_blank" rel="noopener noreferrer" download={project.file_name ?? true}>
                        <Download className="h-4 w-4" />
                        Download
                     </a>
                  </Button>
               )}
               <SubmitWorkDialog project={project} hasSubmission={!!submission} />
            </div>

            {submission && (
               <div className="border-t pt-2 text-xs">
                  {scored ? (
                     <span className="inline-flex items-center gap-1 font-medium text-emerald-600">
                        <CheckCircle2 className="h-3.5 w-3.5" /> Scored: {submission.score}/100
                     </span>
                  ) : (
                     <span className="text-muted-foreground inline-flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5" /> Submitted {formatDate(submission.submitted_at)} · awaiting review
                     </span>
                  )}
               </div>
            )}
         </CardContent>
      </Card>
   );
};

const EmptyState = ({ message }: { message: string }) => (
   <Card className="border">
      <CardContent className="flex flex-col items-center justify-center gap-3 p-10 text-center">
         <FolderOpen className="text-muted-foreground h-10 w-10" />
         <p className="text-muted-foreground text-sm">{message}</p>
      </CardContent>
   </Card>
);

const ProjectLibrary = () => {
   const { projects = [], projectCategories = [], projectSubmissions = [] } = usePage<StudentDashboardProps>().props;

   const queryView = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('view') : null;
   const defaultView = queryView === 'completed' || queryView === 'feedback' ? 'completed' : 'category';

   const submissionByProject = new Map<number, ProjectSubmission>();
   projectSubmissions.forEach((submission) => submissionByProject.set(submission.project_id, submission));

   const uncategorized = projects.filter((project) => !project.project_category_id);

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">Project Library</h1>
            <p className="text-muted-foreground mt-1 text-sm">Download projects, submit your completed work, and review trainer feedback.</p>
         </div>

         <Tabs defaultValue={defaultView} className="w-full">
            <TabsList className="flex h-auto flex-wrap justify-start gap-2 bg-transparent p-0">
               <TabsTrigger value="category" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground rounded-lg border px-4 py-2">
                  Category
               </TabsTrigger>
               <TabsTrigger value="completed" className="data-[state=active]:bg-primary data-[state=active]:text-primary-foreground rounded-lg border px-4 py-2">
                  Completed Projects
               </TabsTrigger>
            </TabsList>

            <TabsContent value="category" className="mt-4 space-y-3">
               {projects.length === 0 && <EmptyState message="No projects are available yet." />}

               {projectCategories.map((category) => {
                  const items = projects.filter((project) => project.project_category_id === category.id);
                  if (items.length === 0) return null;

                  return (
                     <Collapsible key={category.id} defaultOpen className="rounded-lg border">
                        <CollapsibleTrigger className="group flex w-full items-center justify-between gap-2 p-4 text-left">
                           <div className="flex items-center gap-3">
                              <FolderOpen className="text-primary h-5 w-5" />
                              <span className="font-semibold">{category.title}</span>
                              <Badge variant="secondary">{items.length} project{items.length === 1 ? '' : 's'}</Badge>
                           </div>
                           <ChevronDown className="text-muted-foreground h-4 w-4 transition-transform group-data-[state=open]:rotate-180" />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="border-t p-4">
                           <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                              {items.map((project) => (
                                 <ProjectCard key={project.id} project={project} submission={submissionByProject.get(project.id)} />
                              ))}
                           </div>
                        </CollapsibleContent>
                     </Collapsible>
                  );
               })}

               {uncategorized.length > 0 && (
                  <Collapsible defaultOpen className="rounded-lg border">
                     <CollapsibleTrigger className="group flex w-full items-center justify-between gap-2 p-4 text-left">
                        <div className="flex items-center gap-3">
                           <FolderOpen className="text-primary h-5 w-5" />
                           <span className="font-semibold">Other</span>
                           <Badge variant="secondary">{uncategorized.length} project{uncategorized.length === 1 ? '' : 's'}</Badge>
                        </div>
                        <ChevronDown className="text-muted-foreground h-4 w-4 transition-transform group-data-[state=open]:rotate-180" />
                     </CollapsibleTrigger>
                     <CollapsibleContent className="border-t p-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                           {uncategorized.map((project) => (
                              <ProjectCard key={project.id} project={project} submission={submissionByProject.get(project.id)} />
                           ))}
                        </div>
                     </CollapsibleContent>
                  </Collapsible>
               )}
            </TabsContent>

            <TabsContent value="completed" className="mt-4 space-y-4">
               {projectSubmissions.length === 0 && <EmptyState message="You haven't submitted any work yet. Download a project, complete it, then submit it for review." />}

               {projectSubmissions.map((submission) => {
                  const scored = submission.score !== null && submission.score !== undefined;
                  return (
                     <Card key={submission.id} className="border">
                        <CardContent className="space-y-3 p-4">
                           <div className="flex flex-wrap items-start justify-between gap-2">
                              <div>
                                 <p className="font-semibold">{submission.project?.title ?? 'Project'}</p>
                                 {submission.project?.category?.title && (
                                    <p className="text-muted-foreground text-xs">{submission.project.category.title}</p>
                                 )}
                              </div>
                              {scored ? (
                                 <Badge className="bg-emerald-600">Score: {submission.score}/100</Badge>
                              ) : (
                                 <Badge variant="outline">Awaiting review</Badge>
                              )}
                           </div>

                           <div className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                              <span>Submitted: {formatDate(submission.submitted_at)}</span>
                              {scored && <span>Reviewed: {formatDate(submission.scored_at)}</span>}
                              {submission.scorer?.name && <span>By: {submission.scorer.name}</span>}
                           </div>

                           {submission.file && (
                              <Button asChild size="sm" variant="outline">
                                 <a href={submission.file} target="_blank" rel="noopener noreferrer" download={submission.file_name ?? true}>
                                    <Download className="h-4 w-4" />
                                    My submission
                                 </a>
                              </Button>
                           )}

                           {submission.feedback ? (
                              <div className="bg-muted/40 rounded-md border p-3">
                                 <p className="mb-1 flex items-center gap-1 text-xs font-semibold">
                                    <MessageSquare className="h-3.5 w-3.5" /> Feedback from Trainer
                                 </p>
                                 <p className="text-sm whitespace-pre-wrap">{submission.feedback}</p>
                              </div>
                           ) : (
                              scored && <p className="text-muted-foreground text-xs">No written feedback was left.</p>
                           )}
                        </CardContent>
                     </Card>
                  );
               })}
            </TabsContent>
         </Tabs>
      </div>
   );
};

export default ProjectLibrary;
