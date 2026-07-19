import { cn } from '@/lib/utils';
import { GraduationCap } from 'lucide-react';

interface Props {
   title?: string;
   className?: string;
   iconClassName?: string;
   showTitle?: boolean;
}

/**
 * Branded fallback used wherever a course has no real banner/thumbnail yet.
 * Keeps the SmartSourcing Academy navy identity instead of a generic grey box.
 */
const CourseBannerPlaceholder = ({ title, className, iconClassName, showTitle = true }: Props) => (
   <div
      className={cn(
         'relative flex items-center justify-center overflow-hidden bg-gradient-to-br from-[oklch(0.22_0.04_255)] via-[oklch(0.26_0.06_258)] to-[oklch(0.34_0.09_255)]',
         className,
      )}
   >
      <div
         className="absolute inset-0 opacity-[0.12]"
         style={{
            backgroundImage: 'radial-gradient(circle at 1px 1px, white 1px, transparent 0)',
            backgroundSize: '18px 18px',
         }}
      />
      <div className="relative flex flex-col items-center gap-2 px-4 text-center text-white">
         <GraduationCap className={cn('h-10 w-10 opacity-90', iconClassName)} />
         {showTitle && title && <span className="font-display line-clamp-2 text-sm font-semibold opacity-90">{title}</span>}
      </div>
   </div>
);

export default CourseBannerPlaceholder;
