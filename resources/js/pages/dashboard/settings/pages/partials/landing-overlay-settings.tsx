import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import Switch from '@/components/switch';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Editor } from 'richtor';
import 'richtor/styles';

interface Props {
   home: Settings<PageFields>;
}

const defaults = {
   overlay_enabled: false,
   overlay_headline: 'WE HEARD YOU!',
   overlay_pains_title: 'USER PAINS:',
   overlay_pains: ['Add a pain point visitors will recognize.'],
   overlay_solution_title: 'OUR SOLUTION:',
   overlay_highlight: 'INTRODUCING SMARTSOURCING USA ACADEMY',
   overlay_solution_html:
      '<p>Describe what you are offering and why it is different. Visitors can close this overlay and continue to the site.</p>',
   overlay_cta_label: 'APPLY TO JOIN',
   overlay_cta_url: '/register',
   overlay_headline_size: 56,
   overlay_pains_title_size: 20,
   overlay_pains_size: 16,
   overlay_solution_title_size: 20,
   overlay_highlight_size: 19,
   overlay_solution_size: 16,
   overlay_panel_width: 1024,
};

const FontSizeInput = ({
   value,
   onChange,
   error,
}: {
   value: number;
   onChange: (value: number) => void;
   error?: string;
}) => (
   <div className="w-28 shrink-0">
      <Label>Size (px)</Label>
      <Input
         type="number"
         min={12}
         max={96}
         value={value}
         onChange={(e) => onChange(Number(e.target.value))}
      />
      <InputError message={error} />
   </div>
);

const LandingOverlaySettings = ({ home }: Props) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { settings, button, common } = translate;
   const fields = home?.fields || ({} as PageFields);

   const { data, setData, put, errors, processing } = useForm({
      overlay_enabled: Boolean(fields.overlay_enabled),
      overlay_headline: fields.overlay_headline ?? defaults.overlay_headline,
      overlay_pains_title: fields.overlay_pains_title ?? defaults.overlay_pains_title,
      overlay_pains: fields.overlay_pains?.length ? fields.overlay_pains : defaults.overlay_pains,
      overlay_solution_title: fields.overlay_solution_title ?? defaults.overlay_solution_title,
      overlay_highlight: fields.overlay_highlight ?? defaults.overlay_highlight,
      overlay_solution_html: fields.overlay_solution_html ?? defaults.overlay_solution_html,
      overlay_cta_label: fields.overlay_cta_label ?? defaults.overlay_cta_label,
      overlay_cta_url: fields.overlay_cta_url ?? defaults.overlay_cta_url,
      overlay_headline_size: Number(fields.overlay_headline_size ?? defaults.overlay_headline_size),
      overlay_pains_title_size: Number(fields.overlay_pains_title_size ?? defaults.overlay_pains_title_size),
      overlay_pains_size: Number(fields.overlay_pains_size ?? defaults.overlay_pains_size),
      overlay_solution_title_size: Number(fields.overlay_solution_title_size ?? defaults.overlay_solution_title_size),
      overlay_highlight_size: Number(fields.overlay_highlight_size ?? defaults.overlay_highlight_size),
      overlay_solution_size: Number(fields.overlay_solution_size ?? defaults.overlay_solution_size),
      overlay_panel_width: Number(fields.overlay_panel_width ?? defaults.overlay_panel_width),
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      put(route('settings.landing-overlay.update'), {
         preserveScroll: true,
      });
   };

   const updatePain = (index: number, value: string) => {
      setData(
         'overlay_pains',
         data.overlay_pains.map((pain, i) => (i === index ? value : pain)),
      );
   };

   const addPain = () => {
      if (data.overlay_pains.length >= 12) {
         return;
      }

      setData('overlay_pains', [...data.overlay_pains, '']);
   };

   const removePain = (index: number) => {
      setData(
         'overlay_pains',
         data.overlay_pains.filter((_, i) => i !== index),
      );
   };

   return (
      <Card>
         <form onSubmit={handleSubmit}>
            <div className="flex flex-col gap-4 border-b p-4 md:flex-row md:items-center md:justify-between">
               <div>
                  <h2 className="text-lg font-medium">{settings.landing_overlay ?? 'Landing overlay'}</h2>
                  <p className="text-muted-foreground text-sm">
                     {settings.landing_overlay_description ??
                        'Show a dismissible full-screen message on the public home page. Visitors who close it will not see it again until you change the content.'}
                  </p>
               </div>

               <div className="flex items-center gap-4">
                  <div className="flex items-center gap-2">
                     <Label htmlFor="overlay-enabled">{data.overlay_enabled ? common.enabled : common.disabled}</Label>
                     <Switch
                        id="overlay-enabled"
                        checked={data.overlay_enabled}
                        onCheckedChange={(checked) => setData('overlay_enabled', checked)}
                     />
                  </div>

                  <Button asChild variant="outline" size="sm" type="button">
                     <a href={route('home', { preview_overlay: 1 })} target="_blank" rel="noopener noreferrer">
                        {settings.preview_overlay ?? 'Preview overlay'}
                     </a>
                  </Button>
               </div>
            </div>

            <div className="space-y-5 p-4">
               <div className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                  <div>
                     <Label>{settings.overlay_panel_width ?? 'Panel width'}</Label>
                     <Input
                        type="number"
                        min={640}
                        max={1280}
                        step={16}
                        value={data.overlay_panel_width}
                        onChange={(e) => setData('overlay_panel_width', Number(e.target.value))}
                     />
                     <p className="text-muted-foreground mt-1 text-xs">
                        {settings.overlay_panel_width_hint ?? 'Maximum panel width in pixels (640–1280). Default is 1024.'}
                     </p>
                     <InputError message={errors.overlay_panel_width} />
                  </div>
               </div>

               <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                  <div className="flex-1">
                     <Label>{settings.overlay_headline ?? 'Headline'}</Label>
                     <Input
                        value={data.overlay_headline}
                        onChange={(e) => setData('overlay_headline', e.target.value)}
                        placeholder="WE HEARD YOU!"
                     />
                     <InputError message={errors.overlay_headline} />
                  </div>
                  <FontSizeInput
                     value={data.overlay_headline_size}
                     onChange={(value) => setData('overlay_headline_size', value)}
                     error={errors.overlay_headline_size}
                  />
               </div>

               <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                  <div className="flex-1">
                     <Label>{settings.overlay_pains_title ?? 'Pain points title'}</Label>
                     <Input
                        value={data.overlay_pains_title}
                        onChange={(e) => setData('overlay_pains_title', e.target.value)}
                        placeholder="USER PAINS:"
                     />
                     <InputError message={errors.overlay_pains_title} />
                  </div>
                  <FontSizeInput
                     value={data.overlay_pains_title_size}
                     onChange={(value) => setData('overlay_pains_title_size', value)}
                     error={errors.overlay_pains_title_size}
                  />
               </div>

               <div className="space-y-3">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                     <div className="flex-1">
                        <Label>{settings.overlay_pains ?? 'Pain points'}</Label>
                     </div>
                     <FontSizeInput
                        value={data.overlay_pains_size}
                        onChange={(value) => setData('overlay_pains_size', value)}
                        error={errors.overlay_pains_size}
                     />
                  </div>
                  {data.overlay_pains.map((pain, index) => (
                     <div key={index} className="flex items-start gap-2">
                        <Input value={pain} onChange={(e) => updatePain(index, e.target.value)} placeholder="Add a pain point" />
                        <Button type="button" size="icon" variant="ghost" onClick={() => removePain(index)} aria-label="Remove pain point">
                           <Trash2 className="h-4 w-4" />
                        </Button>
                     </div>
                  ))}
                  <InputError message={errors.overlay_pains} />
                  {data.overlay_pains.length < 12 && (
                     <Button type="button" variant="outline" size="sm" onClick={addPain}>
                        <Plus className="mr-2 h-4 w-4" />
                        {settings.add_pain_point ?? 'Add pain point'}
                     </Button>
                  )}
               </div>

               <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                  <div className="flex-1">
                     <Label>{settings.overlay_solution_title ?? 'Solution title'}</Label>
                     <Input
                        value={data.overlay_solution_title}
                        onChange={(e) => setData('overlay_solution_title', e.target.value)}
                        placeholder="OUR SOLUTION:"
                     />
                     <InputError message={errors.overlay_solution_title} />
                  </div>
                  <FontSizeInput
                     value={data.overlay_solution_title_size}
                     onChange={(value) => setData('overlay_solution_title_size', value)}
                     error={errors.overlay_solution_title_size}
                  />
               </div>

               <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                  <div className="flex-1">
                     <Label>{settings.overlay_highlight ?? 'Highlight line'}</Label>
                     <Input
                        value={data.overlay_highlight}
                        onChange={(e) => setData('overlay_highlight', e.target.value)}
                        placeholder="INTRODUCING SMARTSOURCING USA ACADEMY"
                     />
                     <p className="text-muted-foreground mt-1 text-xs">
                        {settings.overlay_highlight_hint ?? 'Shown on a solid light rectangle, like INTRODUCING SMARTSOURCING USA ACADEMY.'}
                     </p>
                     <InputError message={errors.overlay_highlight} />
                  </div>
                  <FontSizeInput
                     value={data.overlay_highlight_size}
                     onChange={(value) => setData('overlay_highlight_size', value)}
                     error={errors.overlay_highlight_size}
                  />
               </div>

               <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                  <div className="flex-1">
                     <Label>{settings.overlay_solution ?? 'Solution message'}</Label>
                     <Editor
                        ssr={true}
                        output="html"
                        placeholder={{
                           paragraph: 'Describe the solution visitors should see.',
                        }}
                        contentMinHeight={180}
                        contentMaxHeight={420}
                        initialContent={data.overlay_solution_html}
                        onContentChange={(value) => setData('overlay_solution_html', value as string)}
                     />
                     <InputError message={errors.overlay_solution_html} />
                  </div>
                  <FontSizeInput
                     value={data.overlay_solution_size}
                     onChange={(value) => setData('overlay_solution_size', value)}
                     error={errors.overlay_solution_size}
                  />
               </div>

               <div className="grid gap-4 md:grid-cols-2">
                  <div>
                     <Label>{settings.overlay_cta_label ?? 'Button label'}</Label>
                     <Input
                        value={data.overlay_cta_label}
                        onChange={(e) => setData('overlay_cta_label', e.target.value)}
                        placeholder="APPLY TO JOIN"
                     />
                     <InputError message={errors.overlay_cta_label} />
                  </div>
                  <div>
                     <Label>{settings.overlay_cta_url ?? 'Button link'}</Label>
                     <Input
                        value={data.overlay_cta_url}
                        onChange={(e) => setData('overlay_cta_url', e.target.value)}
                        placeholder="/register or https://..."
                     />
                     <p className="text-muted-foreground mt-1 text-xs">
                        {settings.overlay_cta_url_hint ?? 'Use a site path like /register, or a full http(s) URL.'}
                     </p>
                     <InputError message={errors.overlay_cta_url} />
                  </div>
               </div>

               <LoadingButton loading={processing}>{button.save_changes}</LoadingButton>
            </div>
         </form>
      </Card>
   );
};

export default LandingOverlaySettings;
