import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type BunnyStreamFormData = BunnyStreamFields & Record<string, string | boolean>;

interface Props extends SharedData {
   bunnyStream: Settings<BunnyStreamFormData>;
}

const copy = {
   title: 'Bunny Stream Settings',
   description: 'Configure Bunny Stream for lesson video hosting and playback',
   enabled: 'Enable Bunny Stream for lesson videos',
   libraryId: 'Stream Library ID',
   apiKey: 'Stream API Key',
   cdnHostname: 'CDN Hostname',
   tokenAuthKey: 'Token Authentication Key',
   libraryIdPlaceholder: '710026',
   apiKeyPlaceholder: 'Enter your Bunny Stream API key',
   cdnHostnamePlaceholder: 'vz-xxxxx.b-cdn.net',
   tokenAuthKeyPlaceholder: 'Enter your Bunny token authentication key',
   cdnHelp: 'Your Bunny Stream pull zone hostname (e.g. vz-xxxxx.b-cdn.net).',
   tokenHelp: 'Required for signed embed URLs so lesson videos are not publicly hotlinkable.',
};

const BunnyStreamSettings = ({ bunnyStream }: Props) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { settings, input, button } = translate;
   const { data, setData, post, errors, processing } = useForm<BunnyStreamFormData>({
      ...bunnyStream.fields,
      enabled: Boolean(bunnyStream.fields.enabled),
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(route('settings.bunny-stream.update', { id: bunnyStream.id }));
   };

   return (
      <div className="md:px-3">
         <div className="mb-6">
            <h1 className="text-foreground text-2xl font-bold">{settings.bunny_stream_settings || copy.title}</h1>
            <p className="text-muted-foreground">{settings.bunny_stream_settings_description || copy.description}</p>
         </div>

         <Card className="p-4 sm:p-6">
            <form onSubmit={handleSubmit} className="space-y-6">
               <div className="flex items-center gap-2">
                  <Checkbox
                     id="enabled"
                     checked={Boolean(data.enabled)}
                     onCheckedChange={(checked) => setData('enabled', checked === true)}
                  />
                  <Label htmlFor="enabled" className="text-foreground cursor-pointer font-normal">
                     {input.bunny_stream_enabled || copy.enabled}
                  </Label>
               </div>
               <InputError message={errors.enabled} />

               {data.enabled && (
                  <>
                     <div>
                        <Label className="text-foreground">{input.bunny_stream_library_id || copy.libraryId} *</Label>
                        <Input
                           name="library_id"
                           value={data.library_id || ''}
                           onChange={(e) => setData('library_id', e.target.value)}
                           placeholder={input.bunny_stream_library_id_placeholder || copy.libraryIdPlaceholder}
                        />
                        <InputError message={errors.library_id} />
                     </div>

                     <div>
                        <Label className="text-foreground">{input.bunny_stream_api_key || copy.apiKey} *</Label>
                        <Input
                           type="password"
                           name="api_key"
                           value={data.api_key || ''}
                           onChange={(e) => setData('api_key', e.target.value)}
                           placeholder={input.bunny_stream_api_key_placeholder || copy.apiKeyPlaceholder}
                        />
                        <InputError message={errors.api_key} />
                     </div>

                     <div>
                        <Label className="text-foreground">{input.bunny_stream_cdn_hostname || copy.cdnHostname}</Label>
                        <Input
                           name="cdn_hostname"
                           value={data.cdn_hostname || ''}
                           onChange={(e) => setData('cdn_hostname', e.target.value)}
                           placeholder={input.bunny_stream_cdn_hostname_placeholder || copy.cdnHostnamePlaceholder}
                        />
                        <p className="text-muted-foreground mt-1 text-sm">{copy.cdnHelp}</p>
                        <InputError message={errors.cdn_hostname} />
                     </div>

                     <div>
                        <Label className="text-foreground">{input.bunny_stream_token_auth_key || copy.tokenAuthKey}</Label>
                        <Input
                           type="password"
                           name="token_auth_key"
                           value={data.token_auth_key || ''}
                           onChange={(e) => setData('token_auth_key', e.target.value)}
                           placeholder={input.bunny_stream_token_auth_key_placeholder || copy.tokenAuthKeyPlaceholder}
                        />
                        <p className="text-muted-foreground mt-1 text-sm">{copy.tokenHelp}</p>
                        <InputError message={errors.token_auth_key} />
                     </div>
                  </>
               )}

               <LoadingButton loading={processing}>{button.save_changes}</LoadingButton>
            </form>
         </Card>
      </div>
   );
};

BunnyStreamSettings.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default BunnyStreamSettings;
