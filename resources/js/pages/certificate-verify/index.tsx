import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import DashboardLayout from '@/layouts/dashboard/layout';
import { router } from '@inertiajs/react';
import { BadgeCheck, CalendarDays, GraduationCap, ShieldAlert, ShieldCheck, User } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

interface VerificationResult {
   valid: boolean;
   reference: string;
   type?: 'course' | 'exam';
   recipient_name?: string | null;
   title?: string | null;
   course_title?: string | null;
   issued_at?: string | null;
   score?: number | null;
}

interface CertificateVerifyProps {
   reference?: string | null;
   result?: VerificationResult | null;
}

const CertificateVerify = ({ reference, result }: CertificateVerifyProps) => {
   const [value, setValue] = useState(reference ?? '');

   const handleSubmit = (event: FormEvent) => {
      event.preventDefault();

      const trimmed = value.trim();

      if (!trimmed) {
         return;
      }

      router.get(route('certificate.verify', { reference: trimmed }), {}, { preserveScroll: true });
   };

   return (
      <div className="mx-auto w-full max-w-2xl">
         <Card>
            <CardHeader>
               <CardTitle className="flex items-center gap-2">
                  <ShieldCheck className="h-5 w-5" />
                  Verify a Certificate
               </CardTitle>
               <p className="text-muted-foreground text-sm">
                  Enter the verification reference printed on a certificate —{' '}
                  <span className="font-mono">SSU-VRN-XXXXXXXXXXXX</span> for courses or{' '}
                  <span className="font-mono">SSU-EXR-XXXXXXXXXXXX</span> for exams — to confirm it is authentic and see who it was issued to.
               </p>
            </CardHeader>

            <CardContent className="space-y-6">
               <form onSubmit={handleSubmit} className="flex flex-col gap-3 sm:flex-row">
                  <Input
                     value={value}
                     onChange={(event) => setValue(event.target.value)}
                     placeholder="SSU-VRN-... or SSU-EXR-..."
                     className="h-11 flex-1 font-mono"
                     autoFocus
                  />
                  <Button type="submit" className="h-11 px-6">
                     <ShieldCheck className="mr-2 h-4 w-4" />
                     Verify
                  </Button>
               </form>

               {result &&
                  (result.valid ? (
                     <div className="overflow-hidden rounded-xl border border-green-200">
                        <div className="flex items-center gap-3 border-b border-green-100 bg-green-50 px-6 py-4">
                           <BadgeCheck className="h-6 w-6 text-green-600" />
                           <div>
                              <p className="font-semibold text-green-800">Authentic certificate</p>
                              <p className="text-xs text-green-700">This certificate is genuine and on record.</p>
                           </div>
                        </div>

                        <dl className="divide-y divide-slate-100">
                           <ResultRow
                              icon={<ShieldCheck className="h-4 w-4" />}
                              label="Type"
                              value={result.type === 'exam' ? 'Exam certificate' : 'Course certificate'}
                           />
                           <ResultRow icon={<User className="h-4 w-4" />} label="Issued to" value={result.recipient_name} />
                           <ResultRow
                              icon={<GraduationCap className="h-4 w-4" />}
                              label={result.type === 'exam' ? 'Exam' : 'Course'}
                              value={result.title ?? result.course_title}
                           />
                           <ResultRow icon={<CalendarDays className="h-4 w-4" />} label="Issued on" value={result.issued_at} />
                           {result.type === 'exam' && result.score != null && (
                              <ResultRow icon={<BadgeCheck className="h-4 w-4" />} label="Score" value={`${result.score}%`} />
                           )}
                           <ResultRow icon={<ShieldCheck className="h-4 w-4" />} label="Reference" value={result.reference} mono />
                        </dl>

                        <p className="border-t border-slate-100 px-6 py-3 text-xs text-muted-foreground">
                           The name shown above is the only valid holder of this certificate. If the person presenting it does not match this
                           name, the certificate does not belong to them.
                        </p>
                     </div>
                  ) : (
                     <div className="rounded-xl border border-red-200">
                        <div className="flex items-center gap-3 border-b border-red-100 bg-red-50 px-6 py-4">
                           <ShieldAlert className="h-6 w-6 text-red-600" />
                           <div>
                              <p className="font-semibold text-red-800">No certificate found</p>
                              <p className="text-xs text-red-700">We could not find a certificate with this reference.</p>
                           </div>
                        </div>
                        <div className="px-6 py-4 text-sm text-muted-foreground">
                           Please double-check the reference <span className="font-mono text-foreground">{result.reference}</span> and try again.
                           References look like <span className="font-mono">SSU-VRN-XXXXXXXXXXXX</span> (courses) or{' '}
                           <span className="font-mono">SSU-EXR-XXXXXXXXXXXX</span> (exams).
                        </div>
                     </div>
                  ))}
            </CardContent>
         </Card>
      </div>
   );
};

const ResultRow = ({ icon, label, value, mono = false }: { icon: ReactNode; label: string; value?: string | null; mono?: boolean }) => (
   <div className="flex items-start gap-3 px-6 py-4">
      <span className="mt-0.5 text-muted-foreground/70">{icon}</span>
      <div className="min-w-0">
         <dt className="text-muted-foreground text-xs tracking-wide uppercase">{label}</dt>
         <dd className={`mt-0.5 break-words text-foreground ${mono ? 'font-mono text-sm' : 'font-medium'}`}>{value || '—'}</dd>
      </div>
   </div>
);

CertificateVerify.layout = (page: ReactNode) => <DashboardLayout headTitle="Verify Certificate" children={page} />;

export default CertificateVerify;
