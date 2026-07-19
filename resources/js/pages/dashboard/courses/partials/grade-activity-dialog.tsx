import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { format } from 'date-fns';
import { Calendar, CheckCircle, Clock, Download, ExternalLink, FileText, Star } from 'lucide-react';
import { useState } from 'react';
import ActivityGradeForm from './forms/activity-grade-form';

interface Props {
   submission: LessonActivitySubmission;
}

const GradeActivityDialog = ({ submission }: Props) => {
   const [open, setOpen] = useState(false);
   const isGraded = ['graded', 'passed', 'approved'].includes(submission.status);
   const totalMarks = submission.lesson?.activity_total_mark || 100;

   const formatDate = (dateString: string) => format(new Date(dateString), 'MMMM dd, yyyy, hh:mm a');

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>
            <Button variant="secondary" size="sm" className="gap-2">
               <Star className="h-4 w-4" />
               <span>{isGraded ? 'Review' : 'Grade'}</span>
            </Button>
         </DialogTrigger>
         <DialogContent className="max-h-[90vh] max-w-4xl p-0">
            <ScrollArea className="max-h-[90vh]">
               <div className="p-6">
                  <DialogHeader className="mb-6">
                     <DialogTitle>{isGraded ? 'Review activity submission' : 'Grade activity submission'}</DialogTitle>
                  </DialogHeader>

                  <div className="space-y-6">
                     <div className="grid grid-cols-2 gap-4">
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                           <Calendar className="text-primary h-5 w-5" />
                           <div>
                              <p className="text-muted-foreground text-sm">Submitted at</p>
                              <p className="text-sm font-medium">{formatDate(submission.submitted_at)}</p>
                           </div>
                        </div>
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                           <FileText className="text-primary h-5 w-5" />
                           <div>
                              <p className="text-muted-foreground text-sm">Activity</p>
                              <p className="font-medium">{submission.lesson?.title || 'N/A'}</p>
                           </div>
                        </div>
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                           <CheckCircle className="text-primary h-5 w-5" />
                           <div>
                              <p className="text-muted-foreground text-sm">Total marks</p>
                              <p className="font-medium">{totalMarks}</p>
                           </div>
                        </div>
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                           <Clock className="text-primary h-5 w-5" />
                           <div>
                              <p className="text-muted-foreground text-sm">Attempt</p>
                              <Badge variant="outline">#{submission.attempt_number}</Badge>
                           </div>
                        </div>
                     </div>

                     <div className="space-y-4 rounded-lg border p-4">
                        <h3 className="font-semibold">Submission details</h3>
                        <div>
                           <p className="mb-2 text-sm font-medium">Submitted {submission.attachment_type === 'url' ? 'URL' : 'file'}:</p>
                           {submission.attachment_type === 'url' ? (
                              <a
                                 href={submission.attachment_path}
                                 target="_blank"
                                 rel="noopener noreferrer"
                                 className="text-primary flex items-center gap-2 hover:underline"
                              >
                                 <ExternalLink className="h-4 w-4" />
                                 {submission.attachment_path}
                              </a>
                           ) : (
                              <Button variant="outline" size="sm" className="gap-2" asChild>
                                 <a href={submission.attachment_path} download>
                                    <Download className="h-4 w-4" />
                                    Download submission
                                 </a>
                              </Button>
                           )}
                        </div>
                        {submission.comment && (
                           <div>
                              <p className="mb-2 text-sm font-medium">Learner notes:</p>
                              <p className="text-muted-foreground bg-muted rounded-lg p-3 text-sm">{submission.comment}</p>
                           </div>
                        )}
                     </div>

                     <ActivityGradeForm isGraded={isGraded} totalMarks={totalMarks} submission={submission} />
                  </div>
               </div>
            </ScrollArea>
         </DialogContent>
      </Dialog>
   );
};

export default GradeActivityDialog;
