import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { router, useForm, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

interface GoHighLevelSettings {
   id: number;
   enabled: boolean;
   webhook_configured: boolean;
   webhook_masked: string;
   registration_webhook_configured: boolean;
   registration_webhook_masked: string;
}

interface Props extends SharedData {
   goHighLevel: GoHighLevelSettings;
}

const GoHighLevel = ({ goHighLevel }: Props) => {
   const { props } = usePage<SharedData>();
   const { button } = props.translate;
   const { data, setData, post, errors, processing } = useForm({
      enabled: goHighLevel.enabled,
      webhook_url: '',
      registration_webhook_url: '',
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(route('settings.go-high-level.update', { id: goHighLevel.id }), {
         preserveScroll: true,
         onSuccess: () => {
            setData('webhook_url', '');
            setData('registration_webhook_url', '');
         },
      });
   };

   const clearWebhook = () => {
      router.post(route('settings.go-high-level.clear', { id: goHighLevel.id }), {}, { preserveScroll: true });
   };

   const clearRegistrationWebhook = () => {
      router.post(route('settings.go-high-level.clear-registration', { id: goHighLevel.id }), {}, { preserveScroll: true });
   };

   return (
      <div className="md:px-3">
         <div className="mb-6">
            <h1 className="text-2xl font-bold">Go High Level</h1>
            <p className="text-muted-foreground">
               The Roofing registration webhook sends a new account to Go High Level. The payment webhook is separate and
               unchanged.
            </p>
         </div>

         <Card className="p-4 sm:p-6">
            <form onSubmit={handleSubmit} className="space-y-6">
               <div className="flex items-center justify-between gap-4">
                  <div>
                     <Label htmlFor="ghl-enabled">Send to Go High Level</Label>
                     <p className="text-muted-foreground mt-1 text-sm">
                        Nothing is sent until this is on and that webhook URL is saved. Each URL is sent on its own.
                     </p>
                  </div>
                  <Switch
                     id="ghl-enabled"
                     checked={data.enabled}
                     onCheckedChange={(checked) => setData('enabled', checked)}
                  />
               </div>

               <div>
                  <Label htmlFor="webhook_url">Payment webhook URL</Label>
                  <Input
                     id="webhook_url"
                     name="webhook_url"
                     type="text"
                     autoComplete="off"
                     value={data.webhook_url}
                     onChange={(e) => setData('webhook_url', e.target.value)}
                     placeholder="Paste a new Go High Level inbound webhook URL"
                  />
                  {goHighLevel.webhook_configured && (
                     <p className="text-muted-foreground mt-1 text-sm">Saved URL: {goHighLevel.webhook_masked}</p>
                  )}
                  <p className="text-muted-foreground mt-1 text-sm">Leave this blank to keep the saved payment URL.</p>
                  <InputError message={errors.webhook_url} />
               </div>

               <div>
                  <Label htmlFor="registration_webhook_url">Roofing registration webhook URL</Label>
                  <Input
                     id="registration_webhook_url"
                     name="registration_webhook_url"
                     type="text"
                     autoComplete="off"
                     value={data.registration_webhook_url}
                     onChange={(e) => setData('registration_webhook_url', e.target.value)}
                     placeholder="Paste the Go High Level URL for new Roofing accounts"
                  />
                  {goHighLevel.registration_webhook_configured && (
                     <p className="text-muted-foreground mt-1 text-sm">Saved URL: {goHighLevel.registration_webhook_masked}</p>
                  )}
                  <p className="text-muted-foreground mt-1 text-sm">
                     New accounts from /register?source_course_id=7 are sent here. Course id 7 is Roofing Estimating. Leave this
                     blank to keep the saved URL. This does not replace the payment URL.
                  </p>
                  <InputError message={errors.registration_webhook_url} />
               </div>

               <div className="flex flex-wrap items-center justify-end gap-3">
                  {goHighLevel.webhook_configured && (
                     <Button type="button" variant="outline" onClick={clearWebhook} disabled={processing}>
                        Remove payment URL
                     </Button>
                  )}
                  {goHighLevel.registration_webhook_configured && (
                     <Button type="button" variant="outline" onClick={clearRegistrationWebhook} disabled={processing}>
                        Remove Roofing registration URL
                     </Button>
                  )}
                  <LoadingButton loading={processing}>{button.save_changes}</LoadingButton>
               </div>
            </form>
         </Card>
      </div>
   );
};

GoHighLevel.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default GoHighLevel;
