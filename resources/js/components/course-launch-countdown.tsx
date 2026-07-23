import { useEffect, useState } from 'react';
import { getLaunchCountdownParts, type LaunchCountdownParts } from '@/lib/course-launch';
import { cn } from '@/lib/utils';

interface CourseLaunchCountdownProps {
   launchAt: string;
   className?: string;
   variant?: 'hero' | 'inline';
}

const CountdownUnit = ({ value, label, variant }: { value: number; label: string; variant: 'hero' | 'inline' }) => (
   <div
      className={cn(
         'flex flex-col items-center justify-center rounded-xl border text-center',
         variant === 'hero'
            ? 'min-w-[4.5rem] border-white/20 bg-white/10 px-3 py-2.5 backdrop-blur-sm'
            : 'min-w-[3.25rem] border-amber-200/80 bg-amber-50 px-2 py-1.5',
      )}
   >
      <span className={cn('font-display font-bold tabular-nums', variant === 'hero' ? 'text-2xl md:text-3xl' : 'text-lg')}>
         {String(value).padStart(2, '0')}
      </span>
      <span className={cn('text-[10px] font-semibold tracking-wide uppercase', variant === 'hero' ? 'text-white/70' : 'text-amber-800/80')}>
         {label}
      </span>
   </div>
);

const CourseLaunchCountdown = ({ launchAt, className, variant = 'hero' }: CourseLaunchCountdownProps) => {
   const [parts, setParts] = useState<LaunchCountdownParts | null>(() => getLaunchCountdownParts(launchAt));

   useEffect(() => {
      setParts(getLaunchCountdownParts(launchAt));

      const intervalId = window.setInterval(() => {
         setParts(getLaunchCountdownParts(launchAt));
      }, 1000);

      return () => window.clearInterval(intervalId);
   }, [launchAt]);

   if (!parts || parts.expired) {
      return null;
   }

   return (
      <div className={cn('flex flex-wrap items-center gap-2.5', className)}>
         <CountdownUnit value={parts.days} label="Days" variant={variant} />
         <CountdownUnit value={parts.hours} label="Hours" variant={variant} />
         <CountdownUnit value={parts.minutes} label="Mins" variant={variant} />
         <CountdownUnit value={parts.seconds} label="Secs" variant={variant} />
      </div>
   );
};

export default CourseLaunchCountdown;
