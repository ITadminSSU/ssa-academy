import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import DashboardLayout from '@/layouts/dashboard/layout';
import { cn } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Audit = {
   id: number;
   action: string;
   from_status: string | null;
   to_status: string | null;
   user_name?: string | null;
   created_at: string | null;
};

type Report = {
   id: number;
   reporter_name: string | null;
   reporter_email: string | null;
   link: string | null;
   normalized_link: string | null;
   details: string | null;
   screenshot: string | null;
   screenshot_name: string | null;
   status: string;
   status_label: string;
   public_note: string | null;
   is_published: boolean;
   confirmed_at: string | null;
   duplicate_of_id: number | null;
   reviewed_by_name?: string | null;
   reviewed_at: string | null;
   created_at: string | null;
   deleted_at: string | null;
   share_url: string | null;
   possible_duplicate: boolean;
   audits: Audit[];
};

type PaginatedReports = {
   data: Report[];
   links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Props = {
   reports: PaginatedReports;
   counts: Record<string, number>;
   filters: { status: string; q: string; archived: boolean };
   statuses: Array<{ value: string; label: string }>;
};

const statusTone = (status: string) => {
   switch (status) {
      case 'new':
         return 'bg-sky-50 text-sky-800 border-sky-200';
      case 'investigating':
         return 'bg-amber-50 text-amber-900 border-amber-200';
      case 'confirmed':
         return 'bg-red-50 text-red-900 border-red-200';
      case 'dismissed':
         return 'bg-slate-100 text-slate-700 border-slate-200';
      case 'duplicate':
         return 'bg-violet-50 text-violet-900 border-violet-200';
      default:
         return 'bg-muted text-muted-foreground border-border';
   }
};

function ReviewDialog({ report, statuses, onClose }: { report: Report; statuses: Props['statuses']; onClose: () => void }) {
   const { data, setData, put, processing, errors } = useForm({
      status: report.status,
      public_note: report.public_note ?? '',
      is_published: report.is_published,
      duplicate_of_id: report.duplicate_of_id ? String(report.duplicate_of_id) : '',
   });

   const submit = (e: FormEvent) => {
      e.preventDefault();
      put(route('scam-tipline.update', report.id), {
         onSuccess: onClose,
      });
   };

   return (
      <Dialog open onOpenChange={(open) => !open && onClose()}>
         <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
               <DialogTitle>Review tip #{report.id}</DialogTitle>
            </DialogHeader>

            <div className="space-y-3 rounded-lg border border-border/70 bg-muted/30 p-4 text-sm">
               <p>
                  <span className="font-medium">Reporter:</span> {report.reporter_name || '—'}
               </p>
               <p>
                  <span className="font-medium">Email:</span> {report.reporter_email || '—'}
               </p>
               <p className="break-all">
                  <span className="font-medium">Link:</span>{' '}
                  {report.link ? (
                     <a href={report.link} target="_blank" rel="noopener noreferrer" className="text-primary underline">
                        {report.link}
                     </a>
                  ) : (
                     '—'
                  )}
               </p>
               <p className="whitespace-pre-wrap">
                  <span className="font-medium">Details:</span> {report.details || '—'}
               </p>
               {report.screenshot && (
                  <p>
                     <span className="font-medium">Screenshot:</span>{' '}
                     <a href={report.screenshot} target="_blank" rel="noopener noreferrer" className="underline">
                        {report.screenshot_name || 'View image'}
                     </a>
                  </p>
               )}
               {report.possible_duplicate && (
                  <p className="text-amber-800">Possible duplicate of report #{report.duplicate_of_id}</p>
               )}
            </div>

            <form onSubmit={submit} className="space-y-4">
               <div>
                  <Label>Status</Label>
                  <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                     <SelectTrigger>
                        <SelectValue />
                     </SelectTrigger>
                     <SelectContent>
                        {statuses.map((status) => (
                           <SelectItem key={status.value} value={status.value}>
                              {status.label}
                           </SelectItem>
                        ))}
                     </SelectContent>
                  </Select>
                  <InputError message={errors.status} />
               </div>

               <div>
                  <Label>Public note (shown on Found Scams when confirmed)</Label>
                  <Textarea
                     rows={3}
                     value={data.public_note}
                     onChange={(e) => setData('public_note', e.target.value)}
                     placeholder="Short community warning note"
                  />
                  <InputError message={errors.public_note} />
               </div>

               <div className="flex items-center gap-2">
                  <Checkbox
                     id="is_published"
                     checked={data.is_published}
                     onCheckedChange={(checked) => setData('is_published', Boolean(checked))}
                  />
                  <Label htmlFor="is_published">Publish on public Found Scams list</Label>
               </div>

               {data.status === 'duplicate' && (
                  <div>
                     <Label>Duplicate of report ID</Label>
                     <Input
                        value={data.duplicate_of_id}
                        onChange={(e) => setData('duplicate_of_id', e.target.value)}
                        placeholder="e.g. 12"
                     />
                     <InputError message={errors.duplicate_of_id} />
                  </div>
               )}

               {report.audits?.length > 0 && (
                  <div>
                     <p className="mb-2 text-sm font-medium">Audit trail</p>
                     <ul className="max-h-40 space-y-2 overflow-y-auto text-xs text-muted-foreground">
                        {report.audits.map((audit) => (
                           <li key={audit.id} className="rounded border border-border/60 px-2 py-1.5">
                              <span className="font-medium text-foreground">{audit.action}</span>
                              {audit.from_status || audit.to_status ? ` · ${audit.from_status || '—'} → ${audit.to_status || '—'}` : ''}
                              {audit.user_name ? ` · ${audit.user_name}` : ''}
                           </li>
                        ))}
                     </ul>
                  </div>
               )}

               <DialogFooter>
                  <Button type="button" variant="outline" onClick={onClose}>
                     Cancel
                  </Button>
                  <Button type="submit" disabled={processing}>
                     Save
                  </Button>
               </DialogFooter>
            </form>
         </DialogContent>
      </Dialog>
   );
}

export default function ScamTiplineIndex({ reports, counts, filters, statuses }: Props) {
   const [selected, setSelected] = useState<Report | null>(null);

   const filterChips = useMemo(
      () => [
         { key: 'all', label: `All (${counts.all ?? 0})` },
         { key: 'new', label: `New (${counts.new ?? 0})` },
         { key: 'investigating', label: `Investigating (${counts.investigating ?? 0})` },
         { key: 'confirmed', label: `Confirmed (${counts.confirmed ?? 0})` },
         { key: 'dismissed', label: `Dismissed (${counts.dismissed ?? 0})` },
         { key: 'duplicate', label: `Duplicate (${counts.duplicate ?? 0})` },
      ],
      [counts],
   );

   const applyFilters = (next: Partial<Props['filters']>) => {
      const archived = next.archived ?? filters.archived;
      router.get(
         route('scam-tipline.index'),
         {
            status: next.status ?? filters.status,
            q: next.q ?? filters.q,
            archived: archived ? 1 : 0,
         },
         { preserveState: true, replace: true },
      );
   };

   return (
      <DashboardLayout headTitle="Fraud Training Tipline">
         <Head title="Fraud Training Tipline" />

         <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
               <h1 className="text-2xl font-semibold text-[#01123A]">Fraud Training Tipline</h1>
               <p className="mt-1 text-sm text-muted-foreground">
                  Review community tips, investigate, and publish confirmed warnings.
               </p>
            </div>
            <div className="flex flex-wrap gap-2">
               <Button asChild variant="outline">
                  <a href={route('fraud-training-tipline')} target="_blank" rel="noopener noreferrer">
                     View public page
                  </a>
               </Button>
               <Button asChild variant="brand">
                  <a href={route('scam-tipline.export', { status: filters.status, q: filters.q })}>
                     Export CSV
                  </a>
               </Button>
            </div>
         </div>

         <div className="mb-4 flex flex-wrap gap-2">
            {filterChips.map((chip) => (
               <button
                  key={chip.key}
                  type="button"
                  onClick={() => applyFilters({ status: chip.key, archived: false })}
                  className={cn(
                     'rounded-full border px-3 py-1 text-xs font-medium',
                     !filters.archived && filters.status === chip.key
                        ? 'border-[#01123A] bg-[#01123A] text-white'
                        : 'border-border bg-white text-[#01123A]',
                  )}
               >
                  {chip.label}
               </button>
            ))}
            <button
               type="button"
               onClick={() => applyFilters({ archived: true, status: 'all' })}
               className={cn(
                  'rounded-full border px-3 py-1 text-xs font-medium',
                  filters.archived ? 'border-[#01123A] bg-[#01123A] text-white' : 'border-border bg-white text-[#01123A]',
               )}
            >
               Archived ({counts.archived ?? 0})
            </button>
         </div>

         <form
            className="mb-4 flex gap-2"
            onSubmit={(e) => {
               e.preventDefault();
               const q = new FormData(e.currentTarget).get('q');
               applyFilters({ q: String(q || '') });
            }}
         >
            <Input name="q" defaultValue={filters.q} placeholder="Search name, email, link, details…" />
            <Button type="submit" variant="brand">
               Search
            </Button>
         </form>

         <div className="overflow-hidden rounded-xl border border-border/70 bg-white">
            <Table>
               <TableHeader>
                  <TableRow>
                     <TableHead>ID</TableHead>
                     <TableHead>Status</TableHead>
                     <TableHead>Reporter</TableHead>
                     <TableHead>Link</TableHead>
                     <TableHead>Submitted</TableHead>
                     <TableHead></TableHead>
                  </TableRow>
               </TableHeader>
               <TableBody>
                  {reports.data.length === 0 ? (
                     <TableRow>
                        <TableCell colSpan={6} className="py-10 text-center text-sm text-muted-foreground">
                           No reports in this view.
                        </TableCell>
                     </TableRow>
                  ) : (
                     reports.data.map((report) => (
                        <TableRow key={report.id}>
                           <TableCell className="font-medium">#{report.id}</TableCell>
                           <TableCell>
                              <span className={cn('inline-flex rounded-full border px-2 py-0.5 text-xs font-medium', statusTone(report.status))}>
                                 {report.status_label}
                              </span>
                              {report.is_published && <span className="mt-1 block text-[11px] text-emerald-700">Published</span>}
                              {report.possible_duplicate && <span className="mt-1 block text-[11px] text-amber-700">Possible duplicate</span>}
                           </TableCell>
                           <TableCell>
                              <div className="text-sm">{report.reporter_name || '—'}</div>
                              <div className="text-xs text-muted-foreground">{report.reporter_email || ''}</div>
                           </TableCell>
                           <TableCell className="max-w-[240px] truncate text-sm">{report.link || '—'}</TableCell>
                           <TableCell className="text-xs text-muted-foreground">
                              {report.created_at ? new Date(report.created_at).toLocaleString() : '—'}
                           </TableCell>
                           <TableCell className="space-x-2 text-right">
                              {!report.deleted_at ? (
                                 <>
                                    <Button size="sm" variant="outline" onClick={() => setSelected(report)}>
                                       Review
                                    </Button>
                                    <Button
                                       size="sm"
                                       variant="ghost"
                                       onClick={() => {
                                          if (confirm('Archive this report?')) {
                                             router.delete(route('scam-tipline.destroy', report.id));
                                          }
                                       }}
                                    >
                                       Archive
                                    </Button>
                                 </>
                              ) : (
                                 <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => router.post(route('scam-tipline.restore', report.id))}
                                 >
                                    Restore
                                 </Button>
                              )}
                              {report.share_url && (
                                 <Button asChild size="sm" variant="ghost">
                                    <a href={report.share_url} target="_blank" rel="noopener noreferrer">
                                       Share
                                    </a>
                                 </Button>
                              )}
                           </TableCell>
                        </TableRow>
                     ))
                  )}
               </TableBody>
            </Table>
         </div>

         {reports.links?.length > 3 && (
            <div className="mt-4 flex flex-wrap gap-2">
               {reports.links.map((link, index) =>
                  link.url ? (
                     <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        className={cn(
                           'rounded-md border px-3 py-1 text-sm',
                           link.active ? 'border-[#01123A] bg-[#01123A] text-white' : 'border-border bg-white',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                     />
                  ) : null,
               )}
            </div>
         )}

         {selected && <ReviewDialog report={selected} statuses={statuses} onClose={() => setSelected(null)} />}
      </DashboardLayout>
   );
}
