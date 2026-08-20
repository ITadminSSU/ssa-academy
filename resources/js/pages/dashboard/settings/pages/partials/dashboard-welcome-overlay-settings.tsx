import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import Switch from '@/components/switch';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface DashboardWelcomeOverlayFields {
   enabled?: boolean;
   headline?: string;
   body?: string;
   cta_label?: string;
   cta_url?: string;
   poster_url?: string;
   video_type?: 'none' | 'file' | 'embed';
   video_url?: string;
   autoplay_muted?: boolean;
}

interface Props {
   overlay: Settings<DashboardWelcomeOverlayFields>;
}

const defaults = {
   enabled: false,
   headline: 'Welcome to SSU Academy!',
   body: 'Browse our courses and start building skills that move your career forward.',
   cta_label: 'Browse Courses',
   cta_url: '/dashboard/browse/all',
   poster_url: '',
   video_type: 'none' as const,
   video_url: '',
   autoplay_muted: true,
};

const DashboardWelcomeOverlaySettings = ({ overlay }: Props) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { settings, button, common } = translate;
   const fields = overlay?.fields || ({} as DashboardWelcomeOverlayFields);
   const [preview, setPreview] = useState<string | null>(fields.poster_url || null);

   const { data, setData, post, errors, processing } = useForm({
      enabled: Boolean(fields.enabled),
      headline: fields.headline ?? defaults.headline,
      body: fields.body ?? defaults.body,
      cta_label: fields.cta_label ?? defaults.cta_label,
      cta_url: fields.cta_url ?? defaults.cta_url,
      poster_url: fields.poster_url ?? defaults.poster_url,
      new_poster: null as File | null,
      clear_poster: false,
      video_type: (fields.video_type ?? defaults.video_type) as 'none' | 'file' | 'embed',
      video_url: fields.video_url ?? defaults.video_url,
      autoplay_muted: fields.autoplay_muted ?? defaults.autoplay_muted,
   });

   const handleSubmit = (e: FormEvent) => {
      e.preventDefault();
      post(route('settings.dashboard-welcome-overlay.update'), {
         forceFormData: true,
         preserveScroll: true,
      });
   };

   return (
      <Card>
         <form onSubmit={handleSubmit}>
            <div className="flex flex-col gap-4 border-b p-4 md:flex-row md:items-center md:justify-between">
               <div>
                  <h2 className="text-lg font-medium">
                     {settings.dashboard_welcome_overlay ?? 'Dashboard welcome overlay'}
                  </h2>
                  <p className="text-muted-foreground text-sm">
                     {settings.dashboard_welcome_overlay_description ??
                        'Show a translucent welcome message on the learner Home tab. Students who dismiss it will not see it again until you change the content.'}
                  </p>
               </div>

               <div className="flex items-center gap-2">
                  <Label htmlFor="dashboard-welcome-enabled">{data.enabled ? common.enabled : common.disabled}</Label>
                  <Switch
                     id="dashboard-welcome-enabled"
                     checked={data.enabled}
                     onCheckedChange={(checked) => setData('enabled', checked)}
                  />
               </div>
            </div>

            <div className="space-y-5 p-4">
               <div>
                  <Label>Headline</Label>
                  <Input
                     value={data.headline}
                     onChange={(e) => setData('headline', e.target.value)}
                     maxLength={160}
                     placeholder="Welcome to SSU Academy!"
                  />
                  <InputError message={errors.headline} />
               </div>

               <div>
                  <Label>Body text</Label>
                  <Textarea
                     rows={4}
                     value={data.body}
                     onChange={(e) => setData('body', e.target.value)}
                     maxLength={2000}
                     placeholder="Short announcement or welcome message"
                  />
                  <InputError message={errors.body} />
               </div>

               <div className="grid gap-4 md:grid-cols-2">
                  <div>
                     <Label>CTA label</Label>
                     <Input
                        value={data.cta_label}
                        onChange={(e) => setData('cta_label', e.target.value)}
                        maxLength={80}
                        placeholder="Browse Courses"
                     />
                     <InputError message={errors.cta_label} />
                  </div>
                  <div>
                     <Label>CTA link</Label>
                     <Input
                        value={data.cta_url}
                        onChange={(e) => setData('cta_url', e.target.value)}
                        maxLength={500}
                        placeholder="/dashboard/browse/all"
                     />
                     <InputError message={errors.cta_url} />
                  </div>
               </div>

               <div className="space-y-3 rounded-lg border p-4">
                  <div>
                     <Label>Opening video</Label>
                     <p className="text-muted-foreground mt-1 text-xs">
                        Browsers block autoplay with sound. Video starts muted; students tap Unmute for audio.
                     </p>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                     <div>
                        <Label>Video type</Label>
                        <Select
                           value={data.video_type}
                           onValueChange={(value: 'none' | 'file' | 'embed') => {
                              setData('video_type', value);
                              if (value === 'none') {
                                 setData('video_url', '');
                              }
                           }}
                        >
                           <SelectTrigger>
                              <SelectValue placeholder="None" />
                           </SelectTrigger>
                           <SelectContent>
                              <SelectItem value="none">None</SelectItem>
                              <SelectItem value="file">Direct file URL (MP4 / WebM)</SelectItem>
                              <SelectItem value="embed">Embed URL (Bunny / YouTube)</SelectItem>
                           </SelectContent>
                        </Select>
                        <InputError message={errors.video_type} />
                     </div>

                     <div className="flex items-end gap-2 pb-1">
                        <div className="flex items-center gap-2">
                           <Switch
                              id="autoplay-muted"
                              checked={data.autoplay_muted}
                              onCheckedChange={(checked) => setData('autoplay_muted', checked)}
                              disabled={data.video_type === 'none'}
                           />
                           <Label htmlFor="autoplay-muted">Muted autoplay</Label>
                        </div>
                     </div>
                  </div>

                  {data.video_type !== 'none' && (
                     <div>
                        <Label>{data.video_type === 'file' ? 'Video file URL' : 'Embed URL'}</Label>
                        <Input
                           value={data.video_url}
                           onChange={(e) => setData('video_url', e.target.value)}
                           maxLength={2000}
                           placeholder={
                              data.video_type === 'file'
                                 ? 'https://cdn.example.com/welcome.mp4'
                                 : 'https://player.mediadelivery.net/embed/LIBRARY/VIDEO_ID'
                           }
                        />
                        <p className="text-muted-foreground mt-1 text-xs">
                           {data.video_type === 'file'
                              ? 'Use a publicly reachable MP4 or WebM URL.'
                              : 'Paste a Bunny Stream embed URL, or a YouTube embed URL.'}
                        </p>
                        <InputError message={errors.video_url} />
                     </div>
                  )}
                  <InputError message={errors.autoplay_muted} />
               </div>

               <div className="space-y-3">
                  <Label>Poster image {data.video_type !== 'none' ? '(fallback / thumbnail)' : ''}</Label>
                  {preview ? (
                     <div className="overflow-hidden rounded-lg border">
                        <img src={preview} alt="Poster preview" className="max-h-56 w-full object-cover" />
                     </div>
                  ) : (
                     <p className="text-muted-foreground text-sm">No poster uploaded yet.</p>
                  )}
                  <Input
                     type="file"
                     accept="image/*"
                     onChange={(e) => {
                        const file = e.target.files?.[0] ?? null;
                        setData('new_poster', file);
                        setData('clear_poster', false);
                        setPreview(file ? URL.createObjectURL(file) : data.poster_url || null);
                     }}
                  />
                  <InputError message={errors.new_poster} />
                  {(preview || data.poster_url) && (
                     <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                           setData('new_poster', null);
                           setData('clear_poster', true);
                           setData('poster_url', '');
                           setPreview(null);
                        }}
                     >
                        Remove poster
                     </Button>
                  )}
               </div>

               <div className="flex justify-end">
                  <LoadingButton type="submit" loading={processing}>
                     {button.save_changes ?? 'Save changes'}
                  </LoadingButton>
               </div>
            </div>
         </form>
      </Card>
   );
};

export default DashboardWelcomeOverlaySettings;
