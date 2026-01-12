import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogTrigger } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import LoadingButton from './loading-button';

interface Props {
   title?: string;
   method: 'get' | 'delete' | 'post' | 'put' | 'patch';
   routePath: string;
   actionComponent: React.ReactNode;
   children?: React.ReactNode;
}

const WarningModal = (props: Props) => {
   const { title, method, routePath, actionComponent, children } = props;
   const [modal, setModal] = useState<boolean>(false);
   const [loading, setLoading] = useState<boolean>(false);

   const requestProcess = () => ({
      preserveScroll: true,
      onSuccess: () => {
         setModal(false);
      },
      onFinish: () => {
         setLoading(false);
      },
   });

   const deleteHandler = () => {
      setLoading(true);

      switch (method) {
         case 'get':
            router.get(routePath, {}, requestProcess());
            break;
         case 'post':
            router.post(routePath, {}, requestProcess());
            break;

         default:
            break;
      }
   };

   return (
      <Dialog open={modal} onOpenChange={!loading ? setModal : undefined}>
         <DialogTrigger asChild>{actionComponent}</DialogTrigger>

         <DialogContent className={cn('px-6 py-8 sm:max-w-[425px]', title && children ? 'space-y-4' : 'space-y-8')}>
            {title && <h6 className="text-destructive text-center text-xl">{title}</h6>}

            {children}

            <div className="mb-0 flex items-center justify-center gap-6">
               <DialogClose>
                  <Button className="text-destructive border-destructive border bg-transparent px-5 hover:bg-transparent">Cancel</Button>
               </DialogClose>

               <LoadingButton type="button" variant="secondary" onClick={deleteHandler} loading={loading} disabled={loading}>
                  Submit
               </LoadingButton>
            </div>
         </DialogContent>
      </Dialog>
   );
};

export default WarningModal;
