import { Badge } from '@/components/ui/badge';
import { ssuBadgeTone } from '@/lib/ssu-theme';
import { cn } from '@/lib/utils';
import { CheckCircle, CheckSquare, FileText, Headphones, Link2, ListOrdered, Type, Upload } from 'lucide-react';

interface Props {
   type: ExamQuestionType;
   className?: string;
}

const questionTypeConfig: Record<
   ExamQuestionType,
   {
      label: string;
      icon: React.ComponentType<{ className?: string }>;
      toneIndex: number;
   }
> = {
   multiple_choice: { label: 'Multiple Choice', icon: CheckCircle, toneIndex: 0 },
   multiple_select: { label: 'Multiple Select', icon: CheckSquare, toneIndex: 1 },
   matching: { label: 'Matching', icon: Link2, toneIndex: 2 },
   fill_blank: { label: 'Fill Blank', icon: Type, toneIndex: 0 },
   ordering: { label: 'Ordering', icon: ListOrdered, toneIndex: 1 },
   short_answer: { label: 'Short Answer', icon: FileText, toneIndex: 2 },
   file_submission: { label: 'File Submission', icon: Upload, toneIndex: 1 },
   quantity_takeoff: { label: 'Quantity Take-Off', icon: FileText, toneIndex: 2 },
   listening: { label: 'Listening', icon: Headphones, toneIndex: 0 },
};

const QuestionTypeBadge = ({ type, className }: Props) => {
   const config = questionTypeConfig[type];
   const Icon = config.icon;

   return (
      <Badge variant="outline" className={cn('gap-1.5 border', ssuBadgeTone(config.toneIndex), className)}>
         <Icon className="h-3 w-3" />
         <span className="text-xs font-medium">{config.label}</span>
      </Badge>
   );
};

export default QuestionTypeBadge;
