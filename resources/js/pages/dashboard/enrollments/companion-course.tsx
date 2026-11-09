import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, useForm } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Props extends SharedData {
   companion: {
      enabled: boolean;
      course_id: string;
   };
   courses: Array<{ id: number; title: string }>;
}

const CompanionCourse = ({ companion, courses }: Props) => {
   const { data, setData, put, processing, errors } = useForm({
      companion_auto_enroll_enabled: companion.enabled,
      companion_course_id: companion.course_id,
   });

   const save = (event: React.FormEvent) => {
      event.preventDefault();
      put(route('enrollment-packages.companion.update'), {
         preserveScroll: true,
         transform: (payload) => ({
            ...payload,
            companion_course_id: payload.companion_course_id ? Number(payload.companion_course_id) : null,
         }),
      });
   };

   return (
      <>
         <Head title="Companion course" />
         <Card className="p-4 sm:p-6">
            <form onSubmit={save} className="space-y-6">
               <div>
                  <h1 className="text-2xl font-semibold">Companion course auto-enroll</h1>
                  <p className="text-muted-foreground mt-2 text-sm">
                     When a learner gets full access to any other course, they are also enrolled in this free course. Deposit-only seats wait until the balance is paid. Leave this Off until you pick a course.
                  </p>
               </div>

               <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                     <Label>Auto-enroll</Label>
                     <Select
                        value={data.companion_auto_enroll_enabled ? '1' : '0'}
                        onValueChange={(value) => setData('companion_auto_enroll_enabled', value === '1')}
                     >
                        <SelectTrigger>
                           <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                           <SelectItem value="1">On</SelectItem>
                           <SelectItem value="0">Off</SelectItem>
                        </SelectContent>
                     </Select>
                     <InputError message={errors.companion_auto_enroll_enabled} />
                  </div>
                  <div>
                     <Label>Companion course</Label>
                     <Combobox
                        data={courses.map((course) => ({ label: course.title, value: String(course.id) }))}
                        defaultValue={data.companion_course_id || ''}
                        placeholder="Select a free course"
                        onSelect={(selected) => setData('companion_course_id', selected.value)}
                     />
                     {courses.length === 0 ? (
                        <p className="text-muted-foreground mt-2 text-xs">
                           No approved courses yet. Publish the gift course as Approved, then return here to select it.
                        </p>
                     ) : null}
                     <InputError message={errors.companion_course_id} />
                  </div>
               </div>

               <LoadingButton loading={processing}>Save changes</LoadingButton>
            </form>
         </Card>
      </>
   );
};

CompanionCourse.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default CompanionCourse;
