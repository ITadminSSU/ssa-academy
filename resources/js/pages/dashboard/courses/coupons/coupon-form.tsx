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
import { Shuffle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
   title: string;
   handler: React.ReactNode;
   coupon?: CourseCoupon;
   courses: Course[];
}

const addressText = (value: unknown): string => {
   if (typeof value === 'string') {
      return value.trim();
   }

   if (value && typeof value === 'object' && 'email' in value) {
      const email = (value as { email?: unknown }).email;
      return typeof email === 'string' ? email.trim() : '';
   }

   return '';
};

const assignedEmailsText = (coupon?: CourseCoupon) => {
   const seen = new Set<string>();
   const emails: string[] = [];
   const sources = [...(Array.isArray(coupon?.assigned_emails) ? coupon.assigned_emails : []), coupon?.assigned_email];

   for (const source of sources) {
      const email = addressText(source);
      const key = email.toLowerCase();

      if (!email || seen.has(key)) {
         continue;
      }

      seen.add(key);
      emails.push(email);
   }

   return emails.join('\n');
};

const CouponForm = ({ title, handler, coupon, courses }: Props) => {
   const [open, setOpen] = useState(false);
   const hydratedCouponId = useRef<number | null>(null);

   // Convert datetime from "2025-11-19 18:00:00" to "2025-11-19T18:00" for datetime-local input
   const formatDatetimeLocal = (datetime?: string) => {
      if (!datetime) return '';
      return datetime.replace(' ', 'T').substring(0, 16);
   };

   const { data, setData, post, put, reset, processing, errors, transform } = useForm({
      code: coupon?.code || '',
      course_id: coupon?.course_id || '',
      discount_type: coupon?.discount_type || 'percentage',
      discount: coupon?.discount || 0,
      valid_from: formatDatetimeLocal(coupon?.valid_from),
      valid_to: formatDatetimeLocal(coupon?.valid_to),
      usage_limit: coupon?.usage_limit ?? '',
      is_active: coupon?.is_active ?? true,
      show_on_catalog: coupon?.show_on_catalog ?? false,
      assigned_emails: assignedEmailsText(coupon),
   });

   const hasAssignedEmails = data.assigned_emails.trim() !== '';

   transform((form) => ({
      ...form,
      assigned_emails: form.assigned_emails,
      usage_limit: form.usage_limit === '' || form.usage_limit === null || form.usage_limit === undefined ? null : Number(form.usage_limit),
   }));

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      if (coupon) {
         put(route('course-coupons.update', coupon.id), {
            preserveScroll: true,
            onSuccess: () => {
               setOpen(false);
            },
         });
      } else {
         post(route('course-coupons.store'), {
            preserveScroll: true,
            onSuccess: () => {
               reset();
               setOpen(false);
            },
         });
      }
   };

   const generateCode = () => {
      const code = Math.random().toString(36).substring(2, 10).toUpperCase();
      setData('code', code);
   };

   useEffect(() => {
      if (!open) {
         hydratedCouponId.current = null;
         return;
      }

      if (!coupon || hydratedCouponId.current === coupon.id) {
         return;
      }

      hydratedCouponId.current = coupon.id;
      setData({
         course_id: coupon.course_id || '',
         code: coupon.code || '',
         discount_type: coupon.discount_type || 'percentage',
         discount: coupon.discount || 0,
         valid_from: formatDatetimeLocal(coupon.valid_from),
         valid_to: formatDatetimeLocal(coupon.valid_to),
         usage_limit: coupon.usage_limit ?? '',
         is_active: coupon.is_active ?? true,
         show_on_catalog: coupon.show_on_catalog ?? false,
         assigned_emails: assignedEmailsText(coupon),
      });
   }, [open, coupon]);

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{handler}</DialogTrigger>

         <DialogContent className="max-w-2xl">
            <DialogHeader>
               <DialogTitle>{title}</DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit}>
               <div className="space-y-4 py-4">
                  <div className="grid grid-cols-2 gap-4">
                     <div className="col-span-2">
                        <Label htmlFor="code">Coupon Code *</Label>
                        <div className="flex gap-2">
                           <Input
                              id="code"
                              name="code"
                              value={data.code}
                              onChange={(e) => setData('code', e.target.value.toUpperCase())}
                              placeholder="SUMMER2024"
                              required
                           />
                           <Button type="button" variant="outline" onClick={generateCode}>
                              <Shuffle className="h-4 w-4" />
                           </Button>
                        </div>
                        <InputError message={errors.code} />
                     </div>

                     <div>
                        <Label htmlFor="discount_type">Discount Type *</Label>
                        <Select name="discount_type" value={data.discount_type} onValueChange={(value) => setData('discount_type', value as any)}>
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
                        <Label htmlFor="discount">Discount Value *</Label>
                        <Input
                           id="discount"
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
                        <Label htmlFor="course_id">Select Course</Label>
                        <Select
                           name="course_id"
                           value={data.course_id?.toString() || 'global'}
                           onValueChange={(value) => {
                              const courseId = value === 'global' ? '' : parseInt(value);
                              setData('course_id', courseId);
                              if (!courseId) {
                                 setData('show_on_catalog', false);
                              }
                           }}
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

                     <div className="col-span-2">
                        <Label htmlFor="assigned_emails">Allowed emails</Label>
                        <Textarea
                           id="assigned_emails"
                           name="assigned_emails"
                           value={data.assigned_emails}
                           rows={5}
                           onChange={(e) => {
                              const value = e.target.value;
                              setData({
                                 ...data,
                                 assigned_emails: value,
                                 show_on_catalog: value.trim() === '' ? data.show_on_catalog : false,
                              });
                           }}
                           placeholder={'student@example.com\ncolleague@example.com'}
                        />
                        <p className="text-muted-foreground mt-1 text-xs">
                           Leave blank and any student can use this code. Enter one email per line, or separate emails with commas. Only a signed-in
                           account on the list can use it, and the code stays off the catalog. The account does not have to exist yet. Clear the list
                           to remove the restriction.
                        </p>
                        <InputError message={errors.assigned_emails} />
                     </div>

                     <div>
                        <Label htmlFor="valid_from">Valid From</Label>
                        <Input
                           id="valid_from"
                           name="valid_from"
                           type="datetime-local"
                           value={data.valid_from}
                           onChange={(e) => onHandleChange(e, setData)}
                        />
                        <InputError message={errors.valid_from} />
                     </div>

                     <div>
                        <Label htmlFor="valid_to">Valid To</Label>
                        <Input
                           id="valid_to"
                           name="valid_to"
                           type="datetime-local"
                           value={data.valid_to}
                           onChange={(e) => onHandleChange(e, setData)}
                        />
                        <InputError message={errors.valid_to} />
                     </div>

                     <div>
                        <Label htmlFor="usage_limit">Usage limit</Label>
                        <Input
                           id="usage_limit"
                           name="usage_limit"
                           type="number"
                           value={data.usage_limit === null || data.usage_limit === undefined ? '' : String(data.usage_limit)}
                           onChange={(e) => {
                              const value = e.target.value;
                              setData('usage_limit', value === '' ? '' : Number(value));
                           }}
                           min="1"
                           placeholder="Unlimited"
                        />
                        <p className="text-muted-foreground mt-1 text-xs">
                           Leave blank for unlimited total redemptions. Each student can use a code only once. Set 1 for a single checkout across all
                           students.
                        </p>
                        <InputError message={errors.usage_limit} />
                     </div>

                     <div className="flex items-center justify-between">
                        <Label htmlFor="is_active">Active</Label>
                        <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                     </div>

                     <div className="col-span-2 flex items-start justify-between gap-4 rounded-md border p-3">
                        <div>
                           <Label htmlFor="show_on_catalog">Fallback: show on catalog card</Label>
                           <p className="text-muted-foreground mt-1 text-xs">
                              Use Pricing → Show coupon price on catalog card instead. This switch is only a fallback if that display promo is off.
                              Does not change charged prices. Students still enter the code at checkout. Coupons never apply to the deposit. An
                              email-locked code stays off the catalog.
                           </p>
                        </div>
                        <Switch
                           id="show_on_catalog"
                           checked={Boolean(data.show_on_catalog) && Boolean(data.course_id) && !hasAssignedEmails}
                           disabled={!data.course_id || hasAssignedEmails}
                           onCheckedChange={(checked) => setData('show_on_catalog', checked)}
                        />
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
                     {coupon ? 'Update' : 'Create'}
                  </LoadingButton>
               </DialogFooter>
            </form>
         </DialogContent>
      </Dialog>
   );
};

export default CouponForm;
