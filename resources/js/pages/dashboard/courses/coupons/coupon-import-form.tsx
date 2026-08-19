import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { onHandleChange } from '@/lib/inertia';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
   handler: React.ReactNode;
   courses: Course[];
}

const CouponImportForm = ({ handler, courses }: Props) => {
   const [open, setOpen] = useState(false);

   const { data, setData, post, reset, processing, errors } = useForm({
      codes: '',
      course_id: '' as string | number,
      discount_type: 'percentage',
      discount: 0,
      valid_from: '',
      valid_to: '',
      is_active: true,
   });

   const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (event) => {
         const text = event.target?.result as string;
         setData('codes', text);
      };
      reader.readAsText(file);
   };

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(route('course-coupons.import'), {
         preserveScroll: true,
         onSuccess: () => {
            reset();
            setOpen(false);
         },
      });
   };

   const codeCount = data.codes
      ? new Set(
           data.codes
              .split(/[\r\n,]+/)
              .map((c) => c.trim())
              .filter(Boolean),
        ).size
      : 0;

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{handler}</DialogTrigger>

         <DialogContent className="max-w-2xl">
            <DialogHeader>
               <DialogTitle>Import Coupon Codes</DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit}>
               <div className="space-y-4 py-4">
                  <div>
                     <Label htmlFor="codes">Coupon Codes *</Label>
                     <Textarea
                        id="codes"
                        name="codes"
                        value={data.codes}
                        onChange={(e) => setData('codes', e.target.value.toUpperCase())}
                        placeholder={'Enter codes separated by commas or new lines:\nSUMMER2024\nWINTER2024\nSPRING2025'}
                        rows={5}
                        required
                     />
                     {codeCount > 0 && <p className="mt-1 text-xs text-muted-foreground">{codeCount} unique code(s) detected</p>}
                     <InputError message={errors.codes} />
                  </div>

                  <div>
                     <Label className="mb-1 block text-xs text-muted-foreground">Or upload a CSV/TXT file</Label>
                     <Input type="file" accept=".csv,.txt" onChange={handleFileUpload} />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                     <div>
                        <Label htmlFor="import_discount_type">Discount Type *</Label>
                        <Select name="discount_type" value={data.discount_type} onValueChange={(value) => setData('discount_type', value)}>
                           <SelectTrigger>
                              <SelectValue placeholder="Select type" />
                           </SelectTrigger>
                           <SelectContent>
                              <SelectItem value="percentage">Percentage (%)</SelectItem>
                              <SelectItem value="fixed">Fixed Amount ($)</SelectItem>
                           </SelectContent>
                        </Select>
                        <InputError message={errors.discount_type} />
                     </div>

                     <div>
                        <Label htmlFor="import_discount">Discount Value *</Label>
                        <Input
                           id="import_discount"
                           name="discount"
                           type="number"
                           value={data.discount}
                           onChange={(e) => onHandleChange(e, setData)}
                           min="0"
                           step="0.01"
                           required
                        />
                        <InputError message={errors.discount} />
                     </div>

                     <div className="col-span-2">
                        <Label htmlFor="import_course_id">Select Course</Label>
                        <Select
                           name="course_id"
                           value={data.course_id?.toString() || 'global'}
                           onValueChange={(value) => setData('course_id', value === 'global' ? '' : parseInt(value))}
                        >
                           <SelectTrigger>
                              <SelectValue placeholder="All Courses (global coupon)" />
                           </SelectTrigger>
                           <SelectContent>
                              <SelectItem value="global">All Courses (global coupon)</SelectItem>
                              {courses.map((course) => (
                                 <SelectItem key={course.id} value={course.id.toString()}>
                                    {course.title}
                                 </SelectItem>
                              ))}
                           </SelectContent>
                        </Select>
                        <InputError message={errors.course_id} />
                     </div>

                     <div>
                        <Label htmlFor="import_valid_from">Valid From</Label>
                        <Input
                           id="import_valid_from"
                           name="valid_from"
                           type="datetime-local"
                           value={data.valid_from}
                           onChange={(e) => onHandleChange(e, setData)}
                        />
                        <InputError message={errors.valid_from} />
                     </div>

                     <div>
                        <Label htmlFor="import_valid_to">Valid To</Label>
                        <Input
                           id="import_valid_to"
                           name="valid_to"
                           type="datetime-local"
                           value={data.valid_to}
                           onChange={(e) => onHandleChange(e, setData)}
                        />
                        <InputError message={errors.valid_to} />
                     </div>

                     <div className="flex items-center justify-between">
                        <Label htmlFor="import_is_active">Active</Label>
                        <Switch id="import_is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                     </div>
                  </div>
               </div>

               <DialogFooter className="gap-2">
                  <DialogClose asChild>
                     <Button type="button" variant="outline">
                        Cancel
                     </Button>
                  </DialogClose>
                  <LoadingButton loading={processing} disabled={processing}>
                     Import {codeCount > 0 ? `(${codeCount})` : ''}
                  </LoadingButton>
               </DialogFooter>
            </form>
         </DialogContent>
      </Dialog>
   );
};

export default CouponImportForm;
