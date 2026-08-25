import LandingLayout from '@/layouts/landing-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent } from 'react';

type Warning = {
   id: number;
   link: string | null;
   public_note: string | null;
   confirmed_at: string | null;
   share_url: string | null;
};

type PaginatedWarnings = {
   data: Warning[];
   links: Array<{ url: string | null; label: string; active: boolean }>;
};

type PageProps = {
   warnings: PaginatedWarnings;
   filters: { q: string; sort: string };
   flashSuccess?: string | null;
   flash?: { success?: string };
};

function FraudTrainingTiplineMark({ className }: { className?: string }) {
   return (
      <div className={cn('flex items-center gap-4', className)}>
         <div className="relative flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-[#01123A] text-white shadow-sm sm:h-20 sm:w-20">
            <svg viewBox="0 0 64 64" className="h-10 w-10 sm:h-12 sm:w-12" aria-hidden>
               <rect x="14" y="10" width="28" height="36" rx="3" fill="none" stroke="currentColor" strokeWidth="2.5" />
               <path d="M20 18h16M20 24h16M20 30h10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
               <circle cx="42" cy="42" r="10" fill="#01123A" stroke="#F1F1F1" strokeWidth="2.5" />
               <circle cx="42" cy="42" r="5" fill="none" stroke="#F1F1F1" strokeWidth="2" />
               <path d="M49 49l6 6" stroke="#8C2A23" strokeWidth="3" strokeLinecap="round" />
            </svg>
         </div>
         <div className="min-w-0 text-left">
            <p className="text-[11px] font-semibold tracking-[0.18em] text-[#01123A] uppercase sm:text-xs">Fraud Training</p>
            <p className="font-display text-3xl leading-none font-bold tracking-tight text-[#8C2A23] sm:text-4xl">
               TIPLINE
               <span className="mt-1 block h-0.5 w-full bg-[#8C2A23]" />
            </p>
            <p className="mt-2 inline-flex rounded-full bg-[#01123A] px-3 py-1 text-[10px] font-semibold tracking-[0.12em] text-white uppercase sm:text-[11px]">
               We investigate for you
            </p>
         </div>
      </div>
   );
}

export default function FraudTrainingTiplinePage() {
   const { warnings, filters, flashSuccess, flash } = usePage<PageProps>().props;
   const successMessage = flashSuccess || flash?.success;

   const { data, setData, post, processing, errors, reset } = useForm({
      reporter_name: '',
      reporter_email: '',
      link: '',
      details: '',
      screenshot: null as File | null,
      website: '',
   });

   const submit = (e: FormEvent) => {
      e.preventDefault();
      post(route('fraud-training-tipline.store'), {
         forceFormData: true,
         onSuccess: () => reset('reporter_name', 'reporter_email', 'link', 'details', 'screenshot', 'website'),
      });
   };

   const search = (e: FormEvent<HTMLFormElement>) => {
      e.preventDefault();
      const form = new FormData(e.currentTarget);
      router.get(
         route('fraud-training-tipline'),
         {
            q: String(form.get('q') || ''),
            sort: String(form.get('sort') || 'newest'),
         },
         { preserveState: true, replace: true },
      );
   };

   return (
      <LandingLayout>
         <Head title="Fraud Training Tipline" />

         <section className="relative overflow-hidden border-b border-border/60 bg-[linear-gradient(160deg,#F7F8FA_0%,#EEF1F6_45%,#F7F8FA_100%)]">
            <div className="pointer-events-none absolute inset-0 opacity-[0.35]" style={{ backgroundImage: 'radial-gradient(circle at 20% 20%, rgba(140,42,35,0.08), transparent 40%), radial-gradient(circle at 80% 0%, rgba(1,18,58,0.08), transparent 35%)' }} />
            <div className="container relative py-14 sm:py-20">
               <FraudTrainingTiplineMark />
               <h1 className="mt-8 max-w-2xl text-3xl font-semibold tracking-tight text-[#01123A] sm:text-4xl">
                  Report suspicious training websites
               </h1>
               <p className="mt-3 max-w-2xl text-base text-muted-foreground sm:text-lg">
                  Community tips help us spot fake training programs. Our team reviews every report. Confirmed warnings appear below so others can stay safe.
               </p>
            </div>
         </section>

         <section className="container grid gap-12 py-12 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-16 lg:py-16">
            <div id="leave-a-tip">
               <h2 className="text-2xl font-semibold text-[#01123A]">Leave a tip</h2>
               <p className="mt-2 text-sm text-muted-foreground">
                  Share what you can. Name, email, link, details, and screenshot are all optional — but the more you include, the faster we can investigate.
               </p>

               {successMessage && (
                  <div className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                     {successMessage}
                  </div>
               )}

               <form onSubmit={submit} className="mt-6 space-y-4" encType="multipart/form-data">
                  <div className="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                     <Label htmlFor="website">Website</Label>
                     <Input
                        id="website"
                        name="website"
                        tabIndex={-1}
                        autoComplete="off"
                        value={data.website}
                        onChange={(e) => setData('website', e.target.value)}
                     />
                  </div>

                  <div>
                     <Label htmlFor="reporter_name">Your name</Label>
                     <Input id="reporter_name" value={data.reporter_name} onChange={(e) => setData('reporter_name', e.target.value)} />
                     <InputError message={errors.reporter_name} />
                  </div>

                  <div>
                     <Label htmlFor="reporter_email">Email (optional)</Label>
                     <Input
                        id="reporter_email"
                        type="email"
                        value={data.reporter_email}
                        onChange={(e) => setData('reporter_email', e.target.value)}
                     />
                     <InputError message={errors.reporter_email} />
                  </div>

                  <div>
                     <Label htmlFor="link">Suspicious website link</Label>
                     <Input
                        id="link"
                        placeholder="https://..."
                        value={data.link}
                        onChange={(e) => setData('link', e.target.value)}
                     />
                     <InputError message={errors.link} />
                  </div>

                  <div>
                     <Label htmlFor="details">Details</Label>
                     <Textarea
                        id="details"
                        rows={5}
                        placeholder="What made this look unsafe or fake?"
                        value={data.details}
                        onChange={(e) => setData('details', e.target.value)}
                     />
                     <InputError message={errors.details} />
                  </div>

                  <div>
                     <Label htmlFor="screenshot">Screenshot (optional)</Label>
                     <Input
                        id="screenshot"
                        type="file"
                        accept="image/*"
                        onChange={(e) => setData('screenshot', e.target.files?.[0] ?? null)}
                     />
                     <InputError message={errors.screenshot} />
                  </div>

                  <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                     {processing ? 'Sending…' : 'Submit tip'}
                  </Button>
               </form>
            </div>

            <div>
               <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                  <div>
                     <h2 className="text-2xl font-semibold text-[#01123A]">Found scams</h2>
                     <p className="mt-2 text-sm text-muted-foreground">
                        Community warnings for sites we have confirmed as unsafe. This is not a legal judgment list — use it as a safety check.
                     </p>
                  </div>
               </div>

               <form onSubmit={search} className="mt-5 flex flex-col gap-3 sm:flex-row">
                  <div className="relative flex-1">
                     <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                     <Input name="q" defaultValue={filters.q} placeholder="Search warnings…" className="pl-9" />
                  </div>
                  <select
                     name="sort"
                     defaultValue={filters.sort}
                     className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                  >
                     <option value="newest">Newest first</option>
                     <option value="oldest">Oldest first</option>
                  </select>
                  <Button type="submit" variant="brand">
                     Search
                  </Button>
               </form>

               <div className="mt-6 space-y-4">
                  {warnings.data.length === 0 ? (
                     <div className="rounded-xl border border-dashed border-border/80 bg-white/60 px-5 py-10 text-center text-sm text-muted-foreground">
                        No confirmed warnings published yet.
                     </div>
                  ) : (
                     warnings.data.map((warning) => (
                        <article key={warning.id} className="rounded-xl border border-border/70 bg-white p-5 shadow-sm">
                           <p className="text-xs font-semibold tracking-[0.14em] text-[#8C2A23] uppercase">Community warning</p>
                           <a
                              href={warning.link || '#'}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="mt-2 block break-all text-base font-medium text-[#01123A] hover:underline"
                           >
                              {warning.link || 'Link unavailable'}
                           </a>
                           {warning.public_note && <p className="mt-2 text-sm text-muted-foreground">{warning.public_note}</p>}
                           <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                              {warning.confirmed_at && <span>Confirmed {warning.confirmed_at}</span>}
                              {warning.share_url && (
                                 <Link href={warning.share_url} className="font-medium text-[#01123A] hover:underline">
                                    Share link
                                 </Link>
                              )}
                           </div>
                        </article>
                     ))
                  )}
               </div>

               {warnings.links?.length > 3 && (
                  <div className="mt-6 flex flex-wrap gap-2">
                     {warnings.links.map((link, index) =>
                        link.url ? (
                           <Link
                              key={`${link.label}-${index}`}
                              href={link.url}
                              className={cn(
                                 'rounded-md border px-3 py-1 text-sm',
                                 link.active ? 'border-[#01123A] bg-[#01123A] text-white' : 'border-border bg-white text-[#01123A]',
                              )}
                              dangerouslySetInnerHTML={{ __html: link.label }}
                           />
                        ) : null,
                     )}
                  </div>
               )}
            </div>
         </section>
      </LandingLayout>
   );
}
