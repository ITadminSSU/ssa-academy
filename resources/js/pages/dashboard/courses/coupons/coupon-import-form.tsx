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
import { Download } from 'lucide-react';
import { useState } from 'react';

interface Props {
   handler: React.ReactNode;
   courses: Course[];
}

const CouponImportForm = ({ handler, courses }: Props) => {
   const [open, setOpen] = useState(false);
   const [importMode, setImportMode] = useState<'simple' | 'csv'>('simple');

   const { data, setData, post, reset, processing, errors } = useForm({
      csv_content: '',
      import_mode: 'simple' as 'simple' | 'csv',
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
         setData('csv_content', text);

         const firstLine = text.split('\n')[0]?.toLowerCase() || '';
         if (firstLine.includes('code') && firstLine.includes('discount')) {
            setImportMode('csv');
            setData('import_mode', 'csv');
         }
      };
      reader.readAsText(file);
   };

   const handleModeChange = (mode: 'simple' | 'csv') => {
      setImportMode(mode);
      setData('import_mode', mode);
   };

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(route('course-coupons.import'), {
         preserveScroll: true,
         onSuccess: () => {
            reset();
            setImportMode('simple');
            setOpen(false);
         },
      });
   };

   const downloadTemplate = () => {
      const csv = `code,discount_type,discount,course_id,valid_from,valid_to,is_active
MARIA20,percentage,20,,2026-08-19,2026-12-31,true
JOHN15,fixed,15,,2026-08-19,2026-12-31,true
SARAH10,percentage,10,,2026-08-19,2026-12-31,true
EMPLOYEE50,percentage,50,,2026-08-19,2026-12-31,true`;

      const blob = new Blob([csv], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'coupon-import-template.csv';
      a.click();
      URL.revokeObjectURL(url);
   };

   const codeCount = (() => {
      if (!data.csv_content) return 0;
      const lines = data.csv_content.split(/\r?\n/).filter((l) => l.trim());
      if (importMode === 'csv' && lines.length > 1) return lines.length - 1;
      return new Set(
         data.csv_content
            .split(/[\r\n,]+/)
            .map((c) => c.trim())
            .filter(Boolean),
      ).size;
   })();

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{handler}</DialogTrigger>

         <DialogContent className="max-w-2xl">
            <DialogHeader>
               <DialogTitle>Import Coupon Codes</DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit}>
               <div className="space-y-4 py-4">
                  {/* Mode selector */}
                  <div className="flex gap-2">
                     <Button
                        type="button"
                        size="sm"
                        variant={importMode === 'simple' ? 'default' : 'outline'}
                        onClick={() => handleModeChange('simple')}
                     >
                        Simple (codes only)
                     </Button>
                     <Button
                        type="button"
                        size="sm"
                        variant={importMode === 'csv' ? 'default' : 'outline'}
                        onClick={() => handleModeChange('csv')}
                     >
                        CSV (multi-column)
                     </Button>
                     {importMode === 'csv' && (
                        <Button type="button" size="sm" variant="ghost" onClick={downloadTemplate} className="ml-auto">
                           <Download className="mr-1 h-3 w-3" />
                           Download Template
                        </Button>
                     )}
                  </div>

                  {importMode === 'csv' ? (
                     <>
                        <div className="rounded-md border border-dashed border-muted-foreground/30 bg-muted/30 p-3 text-xs text-muted-foreground">
                           <p className="mb-1 font-medium">CSV format — required columns: <code>code</code>, <code>discount_type</code>, <code>discount</code></p>
                           <p>Optional columns: <code>course_id</code>, <code>valid_from</code>, <code>valid_to</code>, <code>is_active</code></p>
                           <p className="mt-1">Leave <code>course_id</code> empty for global coupons. Dates in <code>YYYY-MM-DD</code> format.</p>
                        </div>

                        <div>
                           <Label htmlFor="csv_content">CSV Content *</Label>
                           <Textarea
                              id="csv_content"
                              value={data.csv_content}
                              onChange={(e) => setData('csv_content', e.target.value)}
                              placeholder={`code,discount_type,discount,course_id,valid_from,valid_to,is_active\nMARIA20,percentage,20,,2026-08-19,2026-12-31,true\nJOHN15,fixed,15,,2026-08-19,2026-12-31,true`}
                              rows={7}
                              className="font-mono text-xs"
                              required
                           />
                           {codeCount > 0 && <p className="mt-1 text-xs text-muted-foreground">{codeCount} row(s) detected</p>}
                           <InputError message={errors.csv_content} />
                        </div>

                        <div>
                           <Label className="mb-1 block text-xs text-muted-foreground">Or upload a CSV file</Label>
                           <Input type="file" accept=".csv,.txt" onChange={handleFileUpload} />
                        </div>
                     </>
                  ) : (
                     <>
                        <div>
                           <Label htmlFor="csv_content">Coupon Codes *</Label>
                           <Textarea
                              id="csv_content"
                              value={data.csv_content}
                              onChange={(e) => setData('csv_content', e.target.value.toUpperCase())}
                              placeholder={'Enter codes separated by commas or new lines:\nSUMMER2024\nWINTER2024\nSPRING2025'}
                              rows={5}
                              required
                           />
                           {codeCount > 0 && <p className="mt-1 text-xs text-muted-foreground">{codeCount} unique code(s) detected</p>}
                           <InputError message={errors.csv_content} />
                        </div>

                        <div>
                           <Label className="mb-1 block text-xs text-muted-foreground">Or upload a TXT/CSV file</Label>
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
                     </>
                  )}
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
