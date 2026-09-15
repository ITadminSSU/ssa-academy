import { cn } from '@/lib/utils';
import { ButtonHTMLAttributes } from 'react';
import { toast } from 'sonner';

type LockedCurriculumButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
   reason: string;
};

const LockedCurriculumButton = ({ reason, className, children, ...props }: LockedCurriculumButtonProps) => (
   <button
      type="button"
      {...props}
      className={cn('w-full cursor-pointer text-left', className)}
      onClick={(event) => {
         event.preventDefault();
         toast.error(reason);
      }}
   >
      {children}
   </button>
);

export default LockedCurriculumButton;
