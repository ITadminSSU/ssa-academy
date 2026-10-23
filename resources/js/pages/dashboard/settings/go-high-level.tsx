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
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(route('settings.go-high-level.update', { id: goHighLevel.id }), {
         preserveScroll: true,
         onSuccess: () => setData('webhook_url', ''),
      });
   };

   const clearWebhook = () => {
      router.post(route('settings.go-high-level.clear', { id: goHighLevel.id }), {}, { preserveScroll: true });
   };

   return (
      <div className="md:px-3">
         <div className="mb-6">
            <h1 className="text-2xl font-bold">Go High Level</h1>
            <p className="text-muted-foreground">
               Send a learner&apos;s name, email, and enrolled courses to Go High Level when an enrollment becomes active.
            </p>
         </div>

         <Card className="p-4 sm:p-6">
            <form onSubmit={handleSubmit} className="space-y-6">
               <div className="flex items-center justify-between gap-4">
                  <div>
                     <Label htmlFor="ghl-enabled">Send enrollments to Go High Level</Label>
                     <p className="text-muted-foreground mt-1 text-sm">
                        Nothing is sent until this is on and a webhook URL is saved.
                     </p>
                  </div>
                  <Switch
                     id="ghl-enabled"
                     checked={data.enabled}
                     onCheckedChange={(checked) => setData('enabled', checked)}
                  />
               </div>

               <div>
                  <Label htmlFor="webhook_url">Webhook URL</Label>
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
                  <p className="text-muted-foreground mt-1 text-sm">Leave this blank to keep the saved URL.</p>
                  <InputError message={errors.webhook_url} />
               </div>

               <div className="flex items-center justify-end gap-3">
                  {goHighLevel.webhook_configured && (
                     <Button type="button" variant="outline" onClick={clearWebhook} disabled={processing}>
                        Remove webhook URL
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
