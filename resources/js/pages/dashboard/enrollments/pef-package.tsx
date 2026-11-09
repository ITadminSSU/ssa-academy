import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, useForm } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Props extends SharedData {
   pef: {
      enabled: boolean;
      access_duration: string;
      course_ids: string[];
   };
   courses: Array<{ id: number; title: string }>;
   durations: string[];
}

const PefPackage = ({ pef, courses, durations }: Props) => {
   const { data, setData, put, processing, errors } = useForm({
      pef_enabled: pef.enabled,
      pef_access_duration: pef.access_duration || '2 years',
      pef_course_ids: pef.course_ids,
   });

   const save = (event: React.FormEvent) => {
      event.preventDefault();
      put(route('enrollment-packages.pef.update'), { preserveScroll: true });
   };

   return (
      <>
         <Head title="PEF package" />
         <Card className="p-4 sm:p-6">
            <form onSubmit={save} className="space-y-6">
               <div>
                  <h1 className="text-2xl font-semibold">PEF package</h1>
                  <p className="text-muted-foreground mt-2 text-sm">
                     Tagging an external student as PEF enrolls them in every course selected here. Access lasts for the period below, counted from the day they are tagged. After that, finished lessons stay open and unfinished items lock.
                  </p>
               </div>

               <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                     <Label>PEF package</Label>
                     <Select value={data.pef_enabled ? '1' : '0'} onValueChange={(value) => setData('pef_enabled', value === '1')}>
                        <SelectTrigger>
                           <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                           <SelectItem value="1">On</SelectItem>
                           <SelectItem value="0">Off</SelectItem>
                        </SelectContent>
                     </Select>
                     <InputError message={errors.pef_enabled} />
                  </div>
                  <div>
                     <Label>Access period</Label>
                     <Combobox
                        data={durations.map((duration) => ({ label: duration, value: duration }))}
                        defaultValue={data.pef_access_duration}
                        placeholder="2 years"
                        onSelect={(selected) => setData('pef_access_duration', selected.value)}
                     />
                     <InputError message={errors.pef_access_duration} />
                  </div>
               </div>

               <div>
                  <Label>Package courses</Label>
                  {courses.length === 0 ? (
                     <p className="text-muted-foreground mt-2 text-xs">No approved courses yet. Publish the package courses as Approved, then return here to select them.</p>
                  ) : (
                     <div className="mt-3 max-h-64 space-y-2 overflow-y-auto rounded-md border p-3">
                        {courses.map((course) => {
                           const courseId = String(course.id);
                           const checked = data.pef_course_ids.includes(courseId);

                           return (
                              <label key={course.id} className="flex items-center gap-2 text-sm">
                                 <Checkbox
                                    checked={checked}
                                    onCheckedChange={(value) => {
                                       setData(
                                          'pef_course_ids',
                                          value === true
                                             ? [...data.pef_course_ids, courseId]
                                             : data.pef_course_ids.filter((id) => id !== courseId),
                                       );
                                    }}
                                 />
                                 <span>{course.title}</span>
                              </label>
                           );
                        })}
                     </div>
                  )}
                  <InputError message={errors.pef_course_ids} />
               </div>

               <LoadingButton loading={processing}>Save changes</LoadingButton>
            </form>
         </Card>
      </>
   );
};

PefPackage.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default PefPackage;
