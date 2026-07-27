import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useLang } from '@/hooks/use-lang';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';

interface Props {
   route: string;
   className?: string;
}

const TableDataExport = (props: Props) => {
   const { className, route: exportRoute } = props;
   const { table } = useLang();
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url) as Record<string, string>;

   const dataExport = () => {
      window.location.href = route(exportRoute, urlParams);
   };

   return (
      <div className={`relative ml-3 ${className}`}>
         <DropdownMenu>
            <DropdownMenuTrigger>
               <Button size="icon" variant="secondary" className="group h-10 w-11 rounded-md border p-0 hover:border-primary">
                  <Download className="h-4 w-4 text-secondary-foreground group-hover:text-primary" />
               </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
               <ScrollArea className="max-h-[198px]">
                  <DropdownMenuItem onClick={dataExport} className="text-center">
                     {table.csv}
                  </DropdownMenuItem>
               </ScrollArea>
            </DropdownMenuContent>
         </DropdownMenu>
      </div>
   );
};

export default TableDataExport;
