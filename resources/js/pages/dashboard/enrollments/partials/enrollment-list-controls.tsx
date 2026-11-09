import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';

export interface EnrollmentSortState {
   column: string;
   direction: 'asc' | 'desc';
}

export interface EnrollmentSelection {
   matchingIds: number[];
   selectedIds: number[];
   onToggleAll: (checked: boolean) => void;
   onToggle: (id: number) => void;
}

export interface EnrollmentListControls {
   sort?: EnrollmentSortState | null;
   onSort?: (column: string) => void;
   selection?: EnrollmentSelection;
}

export const EnrollmentSortHeader = ({
   label,
   column,
   sort,
   onSort,
}: {
   label: string;
   column: string;
   sort?: EnrollmentSortState | null;
   onSort?: (column: string) => void;
}) => {
   if (!onSort) {
      return <span>{label}</span>;
   }

   const active = sort?.column === column;
   const Icon = !active ? ArrowUpDown : sort?.direction === 'asc' ? ArrowUp : ArrowDown;

   return (
      <button type="button" className={cn('inline-flex items-center gap-1', active && 'text-primary')} onClick={() => onSort(column)}>
         {label}
         <Icon className="h-3.5 w-3.5" />
      </button>
   );
};

export const EnrollmentSelectHeader = ({ selection }: { selection: EnrollmentSelection }) => {
   const selected = new Set(selection.selectedIds);
   const all = selection.matchingIds.length > 0 && selection.matchingIds.every((id) => selected.has(id));
   const some = selection.matchingIds.some((id) => selected.has(id));

   return (
      <Checkbox
         checked={all ? true : some ? 'indeterminate' : false}
         onCheckedChange={(value) => selection.onToggleAll(value === true)}
         disabled={selection.matchingIds.length === 0}
         aria-label="Select all matching enrollments"
      />
   );
};

export const EnrollmentSelectCell = ({ selection, id }: { selection: EnrollmentSelection; id: number }) => (
   <Checkbox checked={selection.selectedIds.includes(id)} onCheckedChange={() => selection.onToggle(id)} aria-label={`Select enrollment ${id}`} />
);
