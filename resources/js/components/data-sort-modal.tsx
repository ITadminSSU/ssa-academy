import DraggableContainer from '@/components/draggable-container';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useState } from 'react';

interface Props {
   data: Array<{
      id: number | string;
      sort: number;
      [key: string]: any;
   }>;
   title: string;
   handler: React.ReactNode;
   renderContent: (item: any) => React.ReactNode;
   onOrderChange: (newOrder: any[], setOpen?: (open: boolean) => void) => void;
   translate?: any;
}

const orderKey = (list: Array<{ id: number | string; sort: number }>) =>
   [...list]
      .sort((a, b) => a.sort - b.sort)
      .map((item) => String(item.id))
      .join('|');

const DataSortModal = ({ title, data, handler, renderContent, onOrderChange, translate }: Props) => {
   const [open, setOpen] = useState(false);
   const [items, setItems] = useState(data);
   const saveLabel = translate?.button?.save || translate?.common?.save || 'Save';
   const cancelLabel = translate?.button?.cancel || translate?.common?.cancel || 'Cancel';
   const hasChanges = orderKey(items) !== orderKey(data);

   const handleOpenChange = (nextOpen: boolean) => {
      if (nextOpen) {
         setItems(data.map((item) => ({ ...item })));
      }

      setOpen(nextOpen);
   };

   const handleSave = () => {
      if (!hasChanges) {
         setOpen(false);
         return;
      }

      onOrderChange(items, setOpen);
      setOpen(false);
   };

   return (
      <Dialog open={open} onOpenChange={handleOpenChange}>
         <DialogTrigger asChild>{handler}</DialogTrigger>

         <DialogContent className="flex max-h-[90vh] flex-col gap-0 p-0">
            <ScrollArea className="min-h-0 flex-1 p-6">
               <DialogHeader className="mb-6">
                  <DialogTitle>{title}</DialogTitle>
                  {data.length > 0 && (
                     <p className="text-muted-foreground text-sm">Drag to reorder, then click Save. Closing without saving keeps the current order.</p>
                  )}
               </DialogHeader>

               {data.length > 0 ? (
                  <DraggableContainer
                     items={items}
                     onOrderChange={setItems}
                     containerClassName="space-y-2"
                     renderItem={(item) => renderContent(item)}
                  />
               ) : (
                  <div className="px-4 py-3 text-center">
                     <p>{translate?.frontend?.no_element_available || 'No element available'}</p>
                  </div>
               )}
            </ScrollArea>

            {data.length > 0 && (
               <DialogFooter className="border-border gap-2 border-t p-4 sm:justify-end">
                  <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                     {cancelLabel}
                  </Button>
                  <Button type="button" onClick={handleSave} disabled={!hasChanges}>
                     {saveLabel}
                  </Button>
               </DialogFooter>
            )}
         </DialogContent>
      </Dialog>
   );
};

export default DataSortModal;
