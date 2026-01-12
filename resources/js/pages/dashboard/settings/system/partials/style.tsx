import CssEditor from '@/components/css-editor';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { SystemProps } from '..';

const Style = () => {
   const { props } = usePage<SharedData & SystemProps>();
   const { translate } = props;
   const { settings, button } = translate;
   const initialFields = props.system.fields as SystemFields;

   const { data, setData, post, errors, processing } = useForm({
      ...(props.system.fields as SystemFields),
      global_style: initialFields.global_style,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(route('settings.system.update', { id: props.system.id }), {
         showProgress: false,
         preserveScroll: true,
      });
   };

   return (
      <Card>
         <CardHeader className="p-4 sm:p-6">
            <CardTitle className="flex items-center gap-2">{settings.custom_global_style}</CardTitle>
            <CardDescription className="hidden sm:block">{settings.css_description}</CardDescription>
         </CardHeader>

         <Separator />

         <CardContent className="p-4 sm:p-6">
            <form onSubmit={handleSubmit} className="space-y-6">
               {/* CSS Editor */}
               <div>
                  {/* Fix focus area: set fixed height so editor fills the box; click-to-focus wrapper */}
                  <CssEditor value={data.global_style} setValue={(value) => setData('global_style', value)} />

                  <InputError message={errors.global_style} />
               </div>

               <div className="flex justify-end">
                  <LoadingButton loading={processing} type="submit">
                     {button.save_changes}
                  </LoadingButton>
               </div>
            </form>
         </CardContent>
      </Card>
   );
};

export default Style;
