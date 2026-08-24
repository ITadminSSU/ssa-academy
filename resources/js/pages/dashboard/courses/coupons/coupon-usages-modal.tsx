import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useEffect, useState } from 'react';

interface Usage {
   id: number;
   user_name: string;
   user_email: string;
   course_title: string;
   amount: number;
   date: string;
}

interface Props {
   coupon: CourseCoupon;
   handler: React.ReactNode;
}

const CouponUsagesModal = ({ coupon, handler }: Props) => {
   const [open, setOpen] = useState(false);
   const [usages, setUsages] = useState<Usage[]>([]);
   const [usedCount, setUsedCount] = useState(coupon.used_count);
   const [loading, setLoading] = useState(false);

   useEffect(() => {
      if (!open) return;

      setLoading(true);
      fetch(route('course-coupons.usages', coupon.id), {
         headers: { Accept: 'application/json' },
         credentials: 'same-origin',
      })
         .then((res) => res.json())
         .then((data) => {
            setUsages(data.usages ?? []);
            setUsedCount(Number(data.total ?? data.usages?.length ?? 0));
         })
         .finally(() => setLoading(false));
   }, [open, coupon.id]);

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{handler}</DialogTrigger>

         <DialogContent className="max-w-3xl">
            <DialogHeader>
               <DialogTitle className="flex items-center gap-3">
                  <span>Coupon Usage:</span>
                  <code className="rounded bg-muted px-2 py-1 text-base">{coupon.code}</code>
                  <Badge variant="outline">{usedCount} used</Badge>
               </DialogTitle>
            </DialogHeader>

            <div className="max-h-[400px] overflow-y-auto">
               {loading ? (
                  <div className="flex items-center justify-center py-8 text-muted-foreground">Loading...</div>
               ) : usages.length === 0 ? (
                  <div className="flex items-center justify-center py-8 text-muted-foreground">No one has used this coupon yet.</div>
               ) : (
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>#</TableHead>
                           <TableHead>Name</TableHead>
                           <TableHead>Email</TableHead>
                           <TableHead>Course</TableHead>
                           <TableHead>Amount Paid</TableHead>
                           <TableHead>Date</TableHead>
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {usages.map((usage, index) => (
                           <TableRow key={usage.id}>
                              <TableCell>{index + 1}</TableCell>
                              <TableCell className="font-medium">{usage.user_name}</TableCell>
                              <TableCell className="text-muted-foreground">{usage.user_email}</TableCell>
                              <TableCell>{usage.course_title}</TableCell>
                              <TableCell>${Number(usage.amount).toFixed(2)}</TableCell>
                              <TableCell className="text-sm text-muted-foreground">{usage.date}</TableCell>
                           </TableRow>
                        ))}
                     </TableBody>
                  </Table>
               )}
            </div>
         </DialogContent>
      </Dialog>
   );
};

export default CouponUsagesModal;
