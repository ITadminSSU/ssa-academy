import SsuStatCard from '@/components/ssu-stat-card';
import { cn } from '@/lib/utils';
import { ssuStatTone } from '@/lib/ssu-theme';
import { LucideIcon, TrendingDown, TrendingUp } from 'lucide-react';

interface Props {
   icon: LucideIcon;
   label: string;
   value: string | number;
   trend?: {
      value: number;
      isPositive: boolean;
   };
   className?: string;
   toneIndex?: number;
}

const ExamStatsCard = ({ icon: Icon, label, value, trend, className, toneIndex = 0 }: Props) => {
   const trendTone = trend?.isPositive ? ssuStatTone(1) : ssuStatTone(2);

   return (
      <div className={cn('relative', className)}>
         <SsuStatCard title={label} value={value} toneIndex={toneIndex} icon={<Icon className="h-6 w-6" />} />
         {trend && (
            <div
               className={cn(
                  'absolute top-4 right-4 flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium',
                  trendTone.bg,
                  trendTone.text,
               )}
            >
               {trend.isPositive ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
               {Math.abs(trend.value)}%
            </div>
         )}
      </div>
   );
};

export default ExamStatsCard;
