import { Badge } from '@/components/ui/badge';

export interface StudentLabelPill {
   id: number;
   name: string;
   color: string;
}

export const textOn = (hex: string) => {
   const normalized = hex.replace('#', '');

   if (normalized.length !== 6) {
      return '#ffffff';
   }

   const red = Number.parseInt(normalized.slice(0, 2), 16);
   const green = Number.parseInt(normalized.slice(2, 4), 16);
   const blue = Number.parseInt(normalized.slice(4, 6), 16);
   const brightness = (red * 299 + green * 587 + blue * 114) / 1000;

   return brightness >= 160 ? '#1e293b' : '#ffffff';
};

const StudentTagPills = ({
   user,
}: {
   user: {
      role?: string;
      pef_tagged?: boolean;
      student_labels?: StudentLabelPill[];
   };
}) => {
   if (user.role && user.role !== 'student') {
      return <span className="text-muted-foreground text-sm">—</span>;
   }

   const labels = user.student_labels ?? [];

   if (!user.pef_tagged && labels.length === 0) {
      return null;
   }

   return (
      <div className="flex max-w-[16rem] flex-wrap gap-1">
         {user.pef_tagged ? <Badge>PEF</Badge> : null}
         {labels.map((label) => (
            <Badge
               key={label.id}
               className="border-transparent"
               style={{ backgroundColor: label.color, color: textOn(label.color) }}
            >
               {label.name}
            </Badge>
         ))}
      </div>
   );
};

export default StudentTagPills;
