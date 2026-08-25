import { cn } from '@/lib/utils';

type FraudTrainingTiplineMarkProps = {
   className?: string;
   variant?: 'hero' | 'footer';
};

export default function FraudTrainingTiplineMark({ className, variant = 'hero' }: FraudTrainingTiplineMarkProps) {
   const isFooter = variant === 'footer';

   return (
      <div className={cn('flex items-center', isFooter ? 'gap-2.5' : 'mx-auto w-fit justify-center gap-4', className)}>
         <div
            className={cn(
               'relative flex shrink-0 items-center justify-center rounded-2xl bg-[#01123A] text-white shadow-sm',
               isFooter ? 'h-12 w-12 rounded-xl' : 'h-16 w-16 sm:h-20 sm:w-20',
            )}
         >
            <svg viewBox="0 0 64 64" className={cn(isFooter ? 'h-7 w-7' : 'h-10 w-10 sm:h-12 sm:w-12')} aria-hidden>
               <rect x="14" y="10" width="28" height="36" rx="3" fill="none" stroke="currentColor" strokeWidth="2.5" />
               <path d="M20 18h16M20 24h16M20 30h10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
               <circle cx="42" cy="42" r="10" fill="#01123A" stroke="#F1F1F1" strokeWidth="2.5" />
               <circle cx="42" cy="42" r="5" fill="none" stroke="#F1F1F1" strokeWidth="2" />
               <path d="M49 49l6 6" stroke="#8C2A23" strokeWidth="3" strokeLinecap="round" />
            </svg>
         </div>
         <div className="flex min-w-0 flex-col items-center text-center">
            <div className="inline-block w-max max-w-full">
               <p
                  className={cn(
                     'whitespace-nowrap font-semibold tracking-[0.18em] text-[#01123A] uppercase',
                     isFooter ? 'text-[9px]' : 'text-[11px] sm:text-xs',
                  )}
               >
                  Fraud Training
               </p>
               <p
                  className={cn(
                     'font-display w-full leading-none font-bold text-[#8C2A23] uppercase',
                     isFooter ? 'text-[1.65rem] tracking-[0.2em]' : 'text-[2.1rem] tracking-[0.14em] sm:text-[2.65rem] sm:tracking-[0.16em]',
                  )}
               >
                  TIPLINE
               </p>
               <span className={cn('mt-0.5 block h-0.5 w-full bg-[#8C2A23]', !isFooter && 'mt-1')} />
            </div>
            <p
               className={cn(
                  'inline-flex rounded-full bg-[#01123A] font-semibold tracking-[0.12em] text-white uppercase',
                  isFooter ? 'mt-1.5 px-2 py-0.5 text-[8px]' : 'mt-2 px-3 py-1 text-[10px] sm:text-[11px]',
               )}
            >
               We investigate for you
            </p>
         </div>
      </div>
   );
}
