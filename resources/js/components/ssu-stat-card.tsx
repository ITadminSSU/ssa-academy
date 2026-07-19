import { cn } from '@/lib/utils';
import { ssuStatTone } from '@/lib/ssu-theme';
import { ReactNode } from 'react';

interface SsuStatCardProps {
   title: string;
   value: ReactNode;
   icon: ReactNode;
   toneIndex?: number;
   className?: string;
}

const SsuStatCard = ({ title, value, icon, toneIndex = 0, className }: SsuStatCardProps) => {
   const tone = ssuStatTone(toneIndex);

   return (
      <div className={cn('ssu-stat-card', className)}>
         <div className="flex items-center justify-between gap-4">
            <div className="min-w-0">
               <p className="text-muted-foreground text-sm font-medium">{title}</p>
               <h4 className="font-display mt-1 text-2xl font-semibold tracking-tight">{value}</h4>
            </div>
            <div className={cn('shrink-0 rounded-xl p-3', tone.bg, tone.text)}>{icon}</div>
         </div>
      </div>
   );
};

export default SsuStatCard;
