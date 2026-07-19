import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import courseLanguages from '@/data/course-languages';
import { courseAudienceFieldLabel, courseAudienceOptionLabel } from '@/lib/course-audience-labels';
import DashboardLayout from '@/layouts/dashboard/layout';
import { onHandleChange } from '@/lib/inertia';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useMemo } from 'react';
import { Editor } from 'richtor';
import 'richtor/styles';
import { CourseUpdateProps } from '../update';

const Basic = () => {
   const { props } = usePage<CourseUpdateProps>();
   const { auth, system, tab, labels, audiences, categories, course, instructors, instructorExams = [], translate } = props;
   const { input, button, common } = translate;

   const { data, setData, post, errors, processing } = useForm({
      tab: tab,
      title: course.title,
      short_description: course.short_description,
      description: course.description,
      status: course.status,
      level: course.level,
      language: course.language,
      instructor_id: course.instructor_id,
      course_category_id: course.course_category_id,
      course_category_child_id: course.course_category_child_id,
      audience: course.audience || 'public',
      final_exam_id: course.final_exam_id ?? '',
      training_hours: course.training_hours ?? '',
   });

   // Handle form submission
   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(route('courses.update', { id: course.id }));
   };

   const transformedCategories = useMemo(() => {
      return categories.flatMap((category) => {
         // Parent categories
         const categoryItem = {
            id: category.id,
            label: category.title,
            value: category.title,
            child_id: '',
         };

         // Child categories
         const childItems =
            category.category_children?.map((child) => ({
               id: child.course_category_id,
               label: `--${child.title}`,
               value: child.title,
               child_id: child.id,
            })) || [];

         return [categoryItem, ...childItems]; // Combine parent + children
      });
   }, [categories]);

   const transformedInstructors = instructors?.map((instructor) => ({
      label: instructor.user.name,
      value: instructor.id as string,
   }));

   const selectedFinalExamId = data.final_exam_id || course.final_exam_id;
   const selectedFinalExamTitle =
      instructorExams.find((exam) => exam.id === Number(selectedFinalExamId))?.title ?? course.final_exam?.title;

   // Resolve the currently-selected category/child from the LIVE form values
   // (not the original course values) so the Combobox reflects edits immediately.
   let selectedCategory: any;
   categories.forEach((category) => {
      if (data.course_category_child_id) {
         const child = category.category_children?.find((c) => c.id === data.course_category_child_id);
         if (child) {
            selectedCategory = child;
         }
      } else if (category.id === data.course_category_id) {
         selectedCategory = category;
      }
   });

   return (
      <Card className="container p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-4">
            <div>
               <Label>{input.title} *</Label>
               <Input name="title" value={data.title} onChange={(e) => onHandleChange(e, setData)} placeholder={input.title_placeholder} />
               <InputError message={errors.title} />
            </div>

            <div>
               <Label>{input.short_description}</Label>
               <Textarea
                  rows={5}
                  name="short_description"
                  value={data.short_description}
                  onChange={(e) => onHandleChange(e, setData)}
                  placeholder={input.short_description_placeholder}
               />
               <InputError message={errors.short_description} />
            </div>

            <div>
               <Label>{input.description}</Label>
               <Editor
                  ssr={true}
                  output="html"
                  placeholder={{
                     paragraph: input.description_placeholder,
                     imageCaption: input.description_placeholder,
                  }}
                  contentMinHeight={256}
                  contentMaxHeight={640}
                  initialContent={data.description}
                  onContentChange={(value) =>
                     setData((prev) => ({
                        ...prev,
                        description: value as string,
                     }))
                  }
               />
               <InputError message={errors.description} />
            </div>

            {auth.user.role === 'admin' && system.sub_type === 'collaborative' && (
               <div>
                  <Label>{input.course_instructor} *</Label>
                  <Combobox
                     defaultValue={data.instructor_id as string}
                     data={transformedInstructors || []}
                     placeholder={input.instructor_placeholder}
                     onSelect={(selected) => setData('instructor_id', selected.value)}
                  />
                  <InputError message={errors.instructor_id} />
               </div>
            )}

            <div className="grid gap-6 md:grid-cols-2">
               <div>
                  <Label>{input.category} *</Label>
                  <Combobox
                     data={transformedCategories}
                     placeholder={input.category_placeholder}
                     defaultValue={selectedCategory?.title || ''}
                     onSelect={(selected) => {
                        setData('course_category_id', selected.id as number);
                        setData('course_category_child_id', selected.child_id as number);
                     }}
                  />
                  <InputError message={errors.course_category_id} />
               </div>

               <div>
                  <Label>{input.course_level} *</Label>
                  <Select value={data.level} onValueChange={(value) => setData('level', value)}>
                     <SelectTrigger>
                        <SelectValue placeholder={input.course_level_placeholder} />
                     </SelectTrigger>
                     <SelectContent>
                        {labels.map((label) => (
                           <SelectItem key={label} value={label} className="capitalize">
                              {label}
                           </SelectItem>
                        ))}
                     </SelectContent>
                  </Select>
                  <InputError message={errors.level} />
               </div>

               <div>
                  <Label>{input.course_language} *</Label>
                  <Combobox
                     defaultValue={data.language}
                     data={courseLanguages}
                     placeholder={input.course_language_placeholder}
                     onSelect={(selected) => setData('language', selected.value)}
                  />
                  <InputError message={errors.language} />
               </div>

               <div className="md:col-span-2">
                  <Label>Final exam (optional)</Label>
                  <Select
                     value={data.final_exam_id ? data.final_exam_id.toString() : 'none'}
                     onValueChange={(value) => setData('final_exam_id', value === 'none' ? '' : parseInt(value))}
                  >
                     <SelectTrigger>
                        <SelectValue placeholder="Select a final exam" />
                     </SelectTrigger>
                     <SelectContent>
                        <SelectItem value="none">No final exam</SelectItem>
                        {instructorExams.map((exam) => (
                           <SelectItem key={exam.id} value={exam.id.toString()}>
                              {exam.title}
                           </SelectItem>
                        ))}
                     </SelectContent>
                  </Select>
                  <p className="text-muted-foreground mt-1 text-xs">
                     When the learner completes this course, they can take this exam from their course card.
                  </p>
                  {selectedFinalExamId ? (
                     <div className="mt-3 flex flex-wrap items-center gap-3">
                        <Button asChild variant="outline" size="sm">
                           <Link href={route('exams.edit', { exam: selectedFinalExamId, tab: 'attempts' })}>
                              View final exam attempts
                           </Link>
                        </Button>
                        {selectedFinalExamTitle ? (
                           <span className="text-muted-foreground text-xs">{selectedFinalExamTitle}</span>
                        ) : null}
                     </div>
                  ) : null}
                  <InputError message={errors.final_exam_id} />
               </div>

               <div className="md:col-span-2">
                  <Label>Training Hours (optional)</Label>
                  <Input
                     value={(data.training_hours as string) ?? ''}
                     onChange={(e) => setData('training_hours', e.target.value)}
                     placeholder="e.g. 40 Hours"
                  />
                  <p className="text-muted-foreground mt-1 text-xs">Shown on the completion certificate. Free text, e.g. "40 Hours".</p>
                  {errors.training_hours && <InputError message={errors.training_hours} />}
               </div>

               <div className="md:col-span-2">
                  <Label>{courseAudienceFieldLabel(input)} *</Label>
                  <RadioGroup
                     value={data.audience as string}
                     className="flex flex-col gap-2 pt-2 pb-1"
                     onValueChange={(value) => setData('audience', value)}
                  >
                     {audiences.map((audience) => (
                        <div key={audience} className="flex items-center space-x-2">
                           <RadioGroupItem value={audience} id={`edit-audience-${audience}`} />
                           <Label htmlFor={`edit-audience-${audience}`} className="font-normal">
                              {courseAudienceOptionLabel(input, audience)}
                           </Label>
                        </div>
                     ))}
                  </RadioGroup>
                  <InputError message={errors.audience} />
               </div>
            </div>

            <div className="mt-8">
               <LoadingButton loading={processing}>{button.save_changes}</LoadingButton>
            </div>
         </form>
      </Card>
   );
};

Basic.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Basic;
