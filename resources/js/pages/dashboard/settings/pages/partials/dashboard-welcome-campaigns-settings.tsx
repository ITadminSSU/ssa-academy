import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import Switch from '@/components/switch';
import BunnyVideoUploaderInput from '@/components/bunny-video-uploader-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { toDateTimeLocalValue } from '@/lib/course-launch';
import { SharedData } from '@/types/global';
import { useForm, usePage, router } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

export interface DashboardWelcomeCampaignRow {
   id: number;
   title: string;
   enabled: boolean;
   priority: number;
   weight: number;
   show_frequency: 'until_dismissed' | 'every_home_visit';
   starts_at?: string | null;
   ends_at?: string | null;
   headline?: string | null;
   body?: string | null;
   cta_label?: string | null;
   cta_url?: string | null;
   poster_url?: string | null;
   video_type?: 'none' | 'file' | 'embed';
   video_url?: string | null;
   video_url_resolved?: string | null;
   autoplay_muted?: boolean;
   is_live_now?: boolean;
}

interface Props {
   campaigns: DashboardWelcomeCampaignRow[];
   appTimezone?: string;
}

const emptyForm = {
   title: 'New campaign',
   enabled: false,
   priority: 10,
   weight: 100,
   show_frequency: 'until_dismissed' as const,
   starts_at: '',
   ends_at: '',
   headline: 'Welcome to SSU Academy!',
   body: 'Browse our courses and start building skills that move your career forward.',
   cta_label: 'Browse Courses',
   cta_url: '/dashboard/browse/all',
   poster_url: '',
   new_poster: null as File | null,
   clear_poster: false,
   video_type: 'none' as const,
   video_url: '',
   new_video: null as File | null,
   clear_video: false,
   autoplay_muted: true,
};

const CampaignForm = ({
   campaign,
   appTimezone,
   onDone,
}: {
   campaign?: DashboardWelcomeCampaignRow;
   appTimezone?: string;
   onDone: () => void;
}) => {
   const { bunnyStream } = usePage<SharedData>().props;
   const bunnyEnabled = Boolean(bunnyStream?.enabled && bunnyStream?.library_id);
   const [bunnyUploadError, setBunnyUploadError] = useState('');

   const { data, setData, post, processing, errors, reset } = useForm({
      ...emptyForm,
      title: campaign?.title ?? emptyForm.title,
      enabled: Boolean(campaign?.enabled),
      priority: campaign?.priority ?? 10,
      weight: campaign?.weight ?? 100,
      show_frequency: (campaign?.show_frequency ?? 'until_dismissed') as 'until_dismissed' | 'every_home_visit',
      starts_at: toDateTimeLocalValue(campaign?.starts_at, appTimezone),
      ends_at: toDateTimeLocalValue(campaign?.ends_at, appTimezone),
      headline: campaign?.headline ?? emptyForm.headline,
      body: campaign?.body ?? emptyForm.body,
      cta_label: campaign?.cta_label ?? emptyForm.cta_label,
      cta_url: campaign?.cta_url ?? emptyForm.cta_url,
      poster_url: campaign?.poster_url ?? '',
      video_type: (campaign?.video_type ?? 'none') as 'none' | 'file' | 'embed',
      video_url: campaign?.video_url ?? '',
      autoplay_muted: campaign?.autoplay_muted ?? true,
   });

   const [posterPreview, setPosterPreview] = useState<string | null>(campaign?.poster_url || null);

   useEffect(() => {
      reset({
         ...emptyForm,
         title: campaign?.title ?? emptyForm.title,
         enabled: Boolean(campaign?.enabled),
         priority: campaign?.priority ?? 10,
         weight: campaign?.weight ?? 100,
         show_frequency: (campaign?.show_frequency ?? 'until_dismissed') as 'until_dismissed' | 'every_home_visit',
         starts_at: toDateTimeLocalValue(campaign?.starts_at, appTimezone),
         ends_at: toDateTimeLocalValue(campaign?.ends_at, appTimezone),
         headline: campaign?.headline ?? emptyForm.headline,
         body: campaign?.body ?? emptyForm.body,
         cta_label: campaign?.cta_label ?? emptyForm.cta_label,
         cta_url: campaign?.cta_url ?? emptyForm.cta_url,
         poster_url: campaign?.poster_url ?? '',
         video_type: (campaign?.video_type ?? 'none') as 'none' | 'file' | 'embed',
         video_url: campaign?.video_url ?? '',
         autoplay_muted: campaign?.autoplay_muted ?? true,
         new_poster: null,
         clear_poster: false,
         new_video: null,
         clear_video: false,
      });
      setPosterPreview(campaign?.poster_url || null);
   }, [campaign?.id]); // eslint-disable-line react-hooks/exhaustive-deps

   const submit = (e: FormEvent) => {
      e.preventDefault();
      const url = campaign
         ? route('settings.dashboard-welcome-campaigns.update', { campaign: campaign.id })
         : route('settings.dashboard-welcome-campaigns.store');

      post(url, {
         forceFormData: true,
         preserveScroll: true,
         onSuccess: () => onDone(),
      });
   };

   return (
      <form onSubmit={submit} className="max-h-[75vh] space-y-4 overflow-y-auto pr-1">
         <div className="grid gap-4 md:grid-cols-2">
            <div className="md:col-span-2">
               <Label>Campaign title (admin only)</Label>
               <Input value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={160} />
               <InputError message={errors.title} />
            </div>

            <div className="flex items-center gap-2">
               <Switch id="campaign-enabled" checked={data.enabled} onCheckedChange={(v) => setData('enabled', v)} />
               <Label htmlFor="campaign-enabled">Enabled</Label>
            </div>

            <div className="flex items-center gap-2">
               <Switch
                  id="campaign-autoplay"
                  checked={data.autoplay_muted}
                  onCheckedChange={(v) => setData('autoplay_muted', v)}
                  disabled={data.video_type === 'none'}
               />
               <Label htmlFor="campaign-autoplay">Muted autoplay</Label>
            </div>

            <div>
               <Label>Priority (higher wins)</Label>
               <Input
                  type="number"
                  min={0}
                  max={9999}
                  value={data.priority}
                  onChange={(e) => setData('priority', Number(e.target.value))}
               />
               <InputError message={errors.priority} />
            </div>

            <div>
               <Label>A/B weight</Label>
               <Input
                  type="number"
                  min={1}
                  max={10000}
                  value={data.weight}
                  onChange={(e) => setData('weight', Number(e.target.value))}
               />
               <p className="text-muted-foreground mt-1 text-xs">Among same priority, higher weight shows more often.</p>
               <InputError message={errors.weight} />
            </div>

            <div>
               <Label>Show frequency</Label>
               <Select
                  value={data.show_frequency}
                  onValueChange={(value: 'until_dismissed' | 'every_home_visit') => setData('show_frequency', value)}
               >
                  <SelectTrigger>
                     <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                     <SelectItem value="until_dismissed">Until dismissed (or content changes)</SelectItem>
                     <SelectItem value="every_home_visit">Every Home visit (while scheduled)</SelectItem>
                  </SelectContent>
               </Select>
               <InputError message={errors.show_frequency} />
            </div>

            <div className="grid grid-cols-2 gap-3 md:col-span-2">
               <div>
                  <Label>Starts at</Label>
                  <Input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                  <InputError message={errors.starts_at} />
               </div>
               <div>
                  <Label>Ends at</Label>
                  <Input type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                  <InputError message={errors.ends_at} />
               </div>
            </div>
         </div>

         <div>
            <Label>Headline</Label>
            <Input value={data.headline} onChange={(e) => setData('headline', e.target.value)} maxLength={160} />
            <InputError message={errors.headline} />
         </div>

         <div>
            <Label>Body</Label>
            <Textarea rows={3} value={data.body} onChange={(e) => setData('body', e.target.value)} maxLength={2000} />
            <InputError message={errors.body} />
         </div>

         <div className="grid gap-4 md:grid-cols-2">
            <div>
               <Label>CTA label</Label>
               <Input value={data.cta_label} onChange={(e) => setData('cta_label', e.target.value)} maxLength={80} />
               <InputError message={errors.cta_label} />
            </div>
            <div>
               <Label>CTA link</Label>
               <Input value={data.cta_url} onChange={(e) => setData('cta_url', e.target.value)} maxLength={500} />
               <InputError message={errors.cta_url} />
            </div>
         </div>

         <div className="space-y-3 rounded-lg border p-3">
            <Label>Video</Label>
            <Select
               value={data.video_type}
               onValueChange={(value: 'none' | 'file' | 'embed') => {
                  setData('video_type', value);
                  if (value === 'none') {
                     setData('video_url', '');
                     setData('new_video', null);
                     setData('clear_video', true);
                  } else {
                     setData('clear_video', false);
                  }
               }}
            >
               <SelectTrigger>
                  <SelectValue />
               </SelectTrigger>
               <SelectContent>
                  <SelectItem value="none">None</SelectItem>
                  <SelectItem value="file">{bunnyEnabled ? 'Upload to Bunny Stream' : 'Upload / direct file URL'}</SelectItem>
                  <SelectItem value="embed">Paste embed URL (Bunny / YouTube)</SelectItem>
               </SelectContent>
            </Select>
            <InputError message={errors.video_type} />

            {data.video_type === 'embed' && (
               <>
                  <Input
                     value={data.video_url}
                     onChange={(e) => setData('video_url', e.target.value)}
                     placeholder="https://player.mediadelivery.net/embed/LIBRARY_ID/VIDEO_ID"
                  />
                  <p className="text-muted-foreground text-xs">
                     Prefer Bunny: upload below (or in Bunny dashboard), then the embed URL is saved automatically.
                  </p>
                  <InputError message={errors.video_url} />
               </>
            )}

            {data.video_type === 'file' && bunnyEnabled && (
               <>
                  <BunnyVideoUploaderInput
                     onFileUploaded={(fileData) => {
                        const libraryId = bunnyStream?.library_id || '';
                        const embedUrl = `https://player.mediadelivery.net/embed/${libraryId}/${fileData.bunny_video_id}`;
                        setBunnyUploadError('');
                        setData('video_type', 'embed');
                        setData('video_url', embedUrl);
                        setData('new_video', null);
                        setData('clear_video', false);
                     }}
                     onError={(message) => {
                        setBunnyUploadError(message);
                     }}
                  />
                  {bunnyUploadError ? <InputError message={bunnyUploadError} /> : null}
                  {data.video_url && (
                     <p className="text-muted-foreground break-all text-xs">Saved embed: {data.video_url}</p>
                  )}
                  <Button
                     type="button"
                     variant="outline"
                     size="sm"
                     onClick={() => {
                        setData('new_video', null);
                        setData('clear_video', true);
                        setData('video_url', '');
                        setData('video_type', 'none');
                     }}
                  >
                     Remove video
                  </Button>
               </>
            )}

            {data.video_type === 'file' && !bunnyEnabled && (
               <>
                  <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                     Bunny Stream is not enabled. Enable it under Settings → Bunny Stream, or paste a public embed URL
                     instead. Direct file uploads use server storage and often return 403 on private disks.
                  </p>
                  <Input
                     value={data.video_url}
                     onChange={(e) => setData('video_url', e.target.value)}
                     placeholder="Optional direct MP4/WebM URL"
                  />
                  <InputError message={errors.video_url} />
                  <Input
                     type="file"
                     accept="video/mp4,video/webm,video/quicktime"
                     onChange={(e) => {
                        setData('new_video', e.target.files?.[0] ?? null);
                        setData('clear_video', false);
                     }}
                  />
                  <InputError message={errors.new_video} />
                  {(data.video_url || campaign?.video_url) && (
                     <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                           setData('new_video', null);
                           setData('clear_video', true);
                           setData('video_url', '');
                           setData('video_type', 'none');
                        }}
                     >
                        Remove video
                     </Button>
                  )}
               </>
            )}
         </div>

         <div className="space-y-3">
            <Label>Poster image</Label>
            {posterPreview ? (
               <img src={posterPreview} alt="" className="max-h-40 w-full rounded-lg border object-cover" />
            ) : null}
            <Input
               type="file"
               accept="image/*"
               onChange={(e) => {
                  const file = e.target.files?.[0] ?? null;
                  setData('new_poster', file);
                  setData('clear_poster', false);
                  setPosterPreview(file ? URL.createObjectURL(file) : data.poster_url || null);
               }}
            />
            <InputError message={errors.new_poster} />
            {(posterPreview || data.poster_url) && (
               <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                     setData('new_poster', null);
                     setData('clear_poster', true);
                     setData('poster_url', '');
                     setPosterPreview(null);
                  }}
               >
                  Remove poster
               </Button>
            )}
         </div>

         <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={onDone}>
               Cancel
            </Button>
            <LoadingButton type="submit" loading={processing}>
               {campaign ? 'Save campaign' : 'Create campaign'}
            </LoadingButton>
         </div>
      </form>
   );
};

const DashboardWelcomeCampaignsSettings = ({ campaigns, appTimezone }: Props) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { settings } = translate;
   const [open, setOpen] = useState(false);
   const [editing, setEditing] = useState<DashboardWelcomeCampaignRow | undefined>();

   return (
      <Card>
         <div className="flex flex-col gap-4 border-b p-4 md:flex-row md:items-center md:justify-between">
            <div>
               <h2 className="text-lg font-medium">{settings.dashboard_welcome_overlay ?? 'Dashboard welcome overlay'}</h2>
               <p className="text-muted-foreground text-sm">
                  {settings.dashboard_welcome_overlay_description ??
                     'Schedule welcome messages and ads on the learner Home tab. Use priority and weight for A/B tests.'}
               </p>
            </div>
            <Button
               type="button"
               onClick={() => {
                  setEditing(undefined);
                  setOpen(true);
               }}
            >
               New campaign
            </Button>
         </div>

         <Table>
            <TableHeader>
               <TableRow>
                  <TableHead>Title</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Schedule</TableHead>
                  <TableHead>Priority / Weight</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
               </TableRow>
            </TableHeader>
            <TableBody>
               {campaigns.map((campaign) => (
                  <TableRow key={campaign.id}>
                     <TableCell>
                        <div className="font-medium">{campaign.title}</div>
                        <div className="text-muted-foreground text-xs">{campaign.headline || '—'}</div>
                     </TableCell>
                     <TableCell>
                        {campaign.is_live_now ? (
                           <Badge className="bg-emerald-600">Live</Badge>
                        ) : campaign.enabled ? (
                           <Badge variant="outline">Scheduled / waiting</Badge>
                        ) : (
                           <Badge variant="outline">Off</Badge>
                        )}
                     </TableCell>
                     <TableCell className="text-muted-foreground text-xs">
                        {campaign.starts_at || campaign.ends_at
                           ? `${campaign.starts_at ? new Date(campaign.starts_at).toLocaleString() : 'Anytime'} → ${
                                campaign.ends_at ? new Date(campaign.ends_at).toLocaleString() : 'No end'
                             }`
                           : 'Always'}
                        <div>{campaign.show_frequency === 'every_home_visit' ? 'Every Home visit' : 'Until dismissed'}</div>
                     </TableCell>
                     <TableCell>
                        {campaign.priority} / {campaign.weight}
                     </TableCell>
                     <TableCell className="space-x-2 text-right">
                        <Button
                           size="sm"
                           variant="outline"
                           onClick={() => {
                              setEditing(campaign);
                              setOpen(true);
                           }}
                        >
                           Edit
                        </Button>
                        <Button
                           size="sm"
                           variant="destructive"
                           onClick={() => {
                              if (confirm(`Delete campaign “${campaign.title}”?`)) {
                                 router.delete(route('settings.dashboard-welcome-campaigns.destroy', { campaign: campaign.id }));
                              }
                           }}
                        >
                           Delete
                        </Button>
                     </TableCell>
                  </TableRow>
               ))}
               {campaigns.length === 0 && (
                  <TableRow>
                     <TableCell colSpan={5} className="text-muted-foreground py-8 text-center text-sm">
                        No campaigns yet. Create one to show a welcome overlay or ad.
                     </TableCell>
                  </TableRow>
               )}
            </TableBody>
         </Table>

         <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-w-2xl">
               <DialogHeader>
                  <DialogTitle>{editing ? 'Edit campaign' : 'New campaign'}</DialogTitle>
               </DialogHeader>
               <CampaignForm
                  key={editing?.id ?? 'new'}
                  campaign={editing}
                  appTimezone={appTimezone}
                  onDone={() => setOpen(false)}
               />
            </DialogContent>
         </Dialog>
      </Card>
   );
};

export default DashboardWelcomeCampaignsSettings;
