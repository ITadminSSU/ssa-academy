import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

interface PageNumberMenuProps {
   currentPage: number;
   lastPage: number;
   onSelect: (page: number) => void;
   triggerClassName?: string;
   triggerVariant?: 'ghost' | 'secondary' | 'outline';
}

const revealCurrentPage = (node: HTMLDivElement | null) => {
   if (!node) {
      return;
   }

   const menu = node.parentElement;

   if (!menu) {
      return;
   }

   const top = node.offsetTop;
   const bottom = top + node.offsetHeight;

   if (top < menu.scrollTop) {
      menu.scrollTop = top;
   } else if (bottom > menu.scrollTop + menu.clientHeight) {
      menu.scrollTop = bottom - menu.clientHeight;
   }
};

const PageNumberMenu = ({ currentPage, lastPage, onSelect, triggerClassName, triggerVariant = 'ghost' }: PageNumberMenuProps) => {
   const pageCount = Math.max(1, Number(lastPage) || 1);
   const pages = Array.from({ length: pageCount }, (_, index) => index + 1);

   return (
      <DropdownMenu>
         <DropdownMenuTrigger asChild>
            <Button type="button" variant={triggerVariant} className={triggerClassName}>
               {currentPage}
            </Button>
         </DropdownMenuTrigger>
         <DropdownMenuContent
            align="end"
            collisionPadding={8}
            className="min-w-[60px]"
            style={{ maxHeight: '15rem', overflowY: 'auto', overscrollBehavior: 'contain' }}
            onWheel={(event) => event.stopPropagation()}
         >
            {pages.map((pageNumber) => (
               <DropdownMenuItem
                  key={pageNumber}
                  ref={pageNumber === currentPage ? revealCurrentPage : undefined}
                  onClick={() => onSelect(pageNumber)}
                  className={`justify-center py-1 ${currentPage === pageNumber ? 'bg-primary-50' : ''}`}
               >
                  {pageNumber}
               </DropdownMenuItem>
            ))}
         </DropdownMenuContent>
      </DropdownMenu>
   );
};

export default PageNumberMenu;
