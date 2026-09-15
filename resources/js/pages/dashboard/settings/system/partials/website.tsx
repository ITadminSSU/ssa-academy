import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import currencies from '@/data/currencies';
import { onHandleChange } from '@/lib/inertia';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { SystemProps } from '..';

interface MediaFields {
   new_favicon: null | File;
   new_banner: null | File;
}

const Website = () => {
   const { props } = usePage<SharedData & SystemProps>();
   const { translate, companionCourseOptions = [] } = props;
   const { input, settings } = translate;
   const systemFields = props.system.fields as SystemFields;

   const mediaFields: MediaFields = {
      new_favicon: null,
      new_banner: null,
   };

   const { data, setData, post, errors, processing } = useForm({
      ...systemFields,
      companion_auto_enroll_enabled: Boolean(systemFields.companion_auto_enroll_enabled),
      companion_course_id: systemFields.companion_course_id ? String(systemFields.companion_course_id) : '',
      direction: 'none',
      ...(mediaFields as MediaFields),
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(route('settings.system.update', { id: props.system.id }));
   };

   return (
      <Card className="p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-6">
            {/* Website Information */}
            <div className="border-b pb-6">
               <h2 className="mb-4 text-xl font-semibold">{settings.website_information}</h2>

               <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                     <Label>{input.website_name}</Label>
                     <Input
                        name="name"
                        value={data.name || ''}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder={input.website_name_placeholder}
                     />
                     <InputError message={errors.name} />
                  </div>

                  <div>
                     <Label>{input.website_title}</Label>
                     <Input
                        name="title"
                        value={data.title || ''}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder={input.website_title_placeholder}
                     />
                     <InputError message={errors.title} />
                  </div>

                  <div className="md:col-span-2">
                     <Label>{input.keywords}</Label>
                     <Input
                        name="keywords"
                        value={data.keywords || ''}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder={input.keywords_placeholder}
                     />
                     <InputError message={errors.keywords} />
                  </div>

                  <div className="md:col-span-2">
                     <Label>{input.description}</Label>
                     <Textarea
                        rows={4}
                        name="description"
                        value={data.description || ''}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder={input.description_placeholder}
                     />
                     <InputError message={errors.description} />
                  </div>

                  <div>
                     <Label>{input.author}</Label>
                     <Input
                        name="author"
                        value={data.author || ''}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder={input.author_name_placeholder}
                     />
                     <InputError message={errors.author} />
                  </div>

                  <div>
                     <Label>{input.slogan}</Label>
                     <Input name="slogan" value={data.slogan || ''} onChange={(e) => onHandleChange(e, setData)} placeholder={input.slogan} />
                     <InputError message={errors.slogan} />
                  </div>
               </div>
            </div>

            {/* Contact Information */}
            <div className="border-b pb-6">
               <h2 className="mb-4 text-xl font-semibold">Contact Information</h2>

               <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                     <Label>System Email *</Label>
                     <Input
                        type="email"
                        name="email"
                        value={data.email || ''}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder="Enter System Email"
                     />
                     <InputError message={errors.email} />
                  </div>

                  <div>
                     <Label>Phone</Label>
                     <Input name="phone" value={data.phone || ''} onChange={(e) => onHandleChange(e, setData)} placeholder="Enter Phone Number" />
                     <InputError message={errors.phone} />
                  </div>
               </div>
            </div>

            <div className="border-b pb-6">
               <h2 className="mb-2 text-xl font-semibold">
                  {settings.companion_course_auto_enroll || 'Companion course auto-enroll'}
               </h2>
               <p className="text-muted-foreground mb-6 text-sm">
                  {settings.companion_course_auto_enroll_help ||
                     'When a learner gets full access to any other course, they are also enrolled in this free course. Deposit-only seats wait until the balance is paid. Leave this Off until you pick a course.'}
               </p>

               <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                     <Label>{settings.companion_auto_enroll_enabled || 'Auto-enroll'}</Label>
                     <Select
                        value={data.companion_auto_enroll_enabled ? '1' : '0'}
                        onValueChange={(value) => setData('companion_auto_enroll_enabled', value === '1')}
                     >
                        <SelectTrigger>
                           <SelectValue placeholder={input.select_option || 'Select'} />
                        </SelectTrigger>
                        <SelectContent>
                           <SelectItem value="1">On</SelectItem>
                           <SelectItem value="0">Off</SelectItem>
                        </SelectContent>
                     </Select>
                     <InputError message={errors.companion_auto_enroll_enabled} />
                  </div>

                  <div>
                     <Label>{settings.companion_course || 'Companion course'}</Label>
                     <Combobox
                        data={companionCourseOptions.map((course) => ({
                           label: course.title,
                           value: String(course.id),
                        }))}
                        defaultValue={data.companion_course_id || ''}
                        placeholder={settings.companion_course_placeholder || 'Select a free course'}
                        onSelect={(selected) => setData('companion_course_id', selected.value)}
                     />
                     {companionCourseOptions.length === 0 ? (
                        <p className="text-muted-foreground mt-2 text-xs">
                           No approved courses yet. Publish the gift course as Approved, then return here to select it.
                        </p>
                     ) : null}
                     <InputError message={errors.companion_course_id} />
                  </div>
               </div>
            </div>

            {/* Media Settings */}
            <div className="border-b pb-6">
               <h2 className="mb-4 text-xl font-semibold">Media</h2>

               <p className="text-muted-foreground mb-6 text-sm">
                  Site logos (navbar, footer, login, dashboard, certificates) are managed statically in{' '}
                  <code className="bg-muted rounded px-1 py-0.5 text-xs">config/branding.php</code> and{' '}
                  <code className="bg-muted rounded px-1 py-0.5 text-xs">public/assets/branding/</code>.
               </p>

               <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                     <Label>Favicon</Label>
                     {data.favicon ? (
                        <div className="border-border/60 mb-3 inline-flex rounded-lg border bg-white p-3">
                           <img src={data.favicon} alt="Current favicon preview" className="h-12 w-12 object-contain" />
                        </div>
                     ) : null}
                     <Input
                        type="file"
                        name="new_favicon"
                        accept="image/*"
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder="Select Favicon"
                     />
                     <p className="text-muted-foreground mt-2 text-xs">Square SSA icon works best. Recommended size: 512x512 PNG.</p>
                     <InputError message={errors.new_favicon} />
                  </div>

                  <div>
                     <Label>Banner</Label>
                     <Input type="file" name="new_banner" accept="image/*" onChange={(e) => onHandleChange(e, setData)} placeholder="Select Banner" />
                     <InputError message={errors.new_banner} />
                  </div>
               </div>
            </div>

            <div>
               <h2 className="mb-4 text-xl font-semibold">Additional Settings</h2>

               <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                  <div>
                     <Label>{'Default Theme'}</Label>
                     <Select value={data.theme} onValueChange={(value) => setData('theme', value as Appearance)}>
                        <SelectTrigger>
                           <SelectValue placeholder={input.select_option} />
                        </SelectTrigger>
                        <SelectContent>
                           <SelectItem value="light">Light</SelectItem>
                           <SelectItem value="dark">Dark</SelectItem>
                           <SelectItem value="system">System</SelectItem>
                        </SelectContent>
                     </Select>
                     <InputError message={errors.theme} />
                  </div>

                  <div>
                     <Label>{'Language Selector'}</Label>
                     <Select value={data.language_selector ? '1' : '0'} onValueChange={(value) => setData('language_selector', value === '1')}>
                        <SelectTrigger>
                           <SelectValue placeholder={input.select_option} />
                        </SelectTrigger>
                        <SelectContent>
                           <SelectItem value="1">Show</SelectItem>
                           <SelectItem value="0">Hide</SelectItem>
                        </SelectContent>
                     </Select>
                     <InputError message={errors.language_selector} />
                  </div>

                  <div>
                     <Label>{`Course Selling Currency (${data.selling_currency})`}</Label>
                     <Combobox
                        data={currencies}
                        defaultValue={data.selling_currency || ''}
                        placeholder="Select a selling currency"
                        onSelect={(selected) => setData('selling_currency', selected.value)}
                     />
                     <InputError message={errors.selling_currency} />
                  </div>

                  <div>
                     <Label>{'Course Selling Tax (%)'}</Label>
                     <Input
                        name="selling_tax"
                        value={data.selling_tax || ''}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder="Enter Course Selling Tax Percentage"
                     />
                     <InputError message={errors.selling_tax} />
                  </div>

                  {props.system.sub_type === 'collaborative' && (
                     <div>
                        <Label>{'Instructor Revenue (%)'}</Label>
                        <Input
                           name="instructor_revenue"
                           value={data.instructor_revenue || ''}
                           onChange={(e) => onHandleChange(e, setData)}
                           placeholder="Enter Instructor Revenue Percentage"
                        />
                        <InputError message={errors.instructor_revenue} />
                     </div>
                  )}
               </div>
            </div>

            <div className="flex justify-end">
               <LoadingButton loading={processing}>Save Changes</LoadingButton>
            </div>
         </form>
      </Card>
   );
};

export default Website;
