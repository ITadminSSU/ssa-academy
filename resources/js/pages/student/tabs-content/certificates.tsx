import DynamicCertificate from '@/components/dynamic-certificate';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StudentDashboardProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Award, Download } from 'lucide-react';

const Certificates = () => {
   const { certificates = [], certificateTemplate, examCertificateTemplate, auth } = usePage<StudentDashboardProps>().props;
   const studentName = auth.user?.name ?? '';

   const getTemplateForCertificate = (certificate: LearnerCertificate) => {
      return certificate.type === 'exam' ? examCertificateTemplate : certificateTemplate;
   };

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">Certificates</h1>
            <p className="text-muted-foreground mt-1 text-sm">All the certificates you've earned from courses and exams, ready to download.</p>
         </div>

         {certificates.length > 0 ? (
            <Card className="border">
               <CardContent className="p-0">
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>Type</TableHead>
                           <TableHead>Title</TableHead>
                           <TableHead>Completion Date</TableHead>
                           <TableHead>Reference</TableHead>
                           <TableHead className="text-right">Download</TableHead>
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {certificates.map((certificate) => {
                           const template = getTemplateForCertificate(certificate);

                           return (
                              <TableRow key={`${certificate.type}-${certificate.id}`}>
                                 <TableCell>
                                    <Badge variant={certificate.type === 'exam' ? 'secondary' : 'default'}>
                                       {certificate.type === 'exam' ? 'Exam' : 'Course'}
                                    </Badge>
                                 </TableCell>
                                 <TableCell className="font-medium">{certificate.title ?? '—'}</TableCell>
                                 <TableCell>{certificate.issued_at ?? '—'}</TableCell>
                                 <TableCell className="text-muted-foreground text-xs">
                                    {certificate.verification_reference ?? certificate.identifier ?? '—'}
                                 </TableCell>
                                 <TableCell className="text-right">
                                    {template ? (
                                       <Dialog>
                                          <DialogTrigger asChild>
                                             <Button size="sm" variant="outline">
                                                <Download className="h-4 w-4" />
                                                Download
                                             </Button>
                                          </DialogTrigger>
                                          <DialogContent className="max-w-4xl">
                                             <DialogHeader>
                                                <DialogTitle>{certificate.title}</DialogTitle>
                                             </DialogHeader>
                                             <DynamicCertificate
                                                template={template}
                                                courseName={certificate.title ?? ''}
                                                studentName={studentName}
                                                completionDate={certificate.issued_at ?? ''}
                                                verificationReference={certificate.verification_reference ?? certificate.identifier}
                                                certificateId={certificate.certificate_id}
                                                trainingHours={certificate.training_hours}
                                                instructorName={certificate.instructor_name}
                                             />
                                          </DialogContent>
                                       </Dialog>
                                    ) : (
                                       <span className="text-muted-foreground text-xs">Unavailable</span>
                                    )}
                                 </TableCell>
                              </TableRow>
                           );
                        })}
                     </TableBody>
                  </Table>
               </CardContent>
            </Card>
         ) : (
            <Card className="border">
               <CardContent className="flex flex-col items-center justify-center gap-3 p-10 text-center">
                  <Award className="text-muted-foreground h-10 w-10" />
                  <p className="text-muted-foreground text-sm">
                     You haven't earned any certificates yet. Complete a course or pass an exam to get started.
                  </p>
               </CardContent>
            </Card>
         )}
      </div>
   );
};

export default Certificates;
