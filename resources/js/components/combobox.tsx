import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Data {
   id?: number | string;
   child_id?: number | string;
   label: string;
   value: string;
}

interface Props {
   data: Data[];
   placeholder: string;
   onSelect: (selected: Data) => void;
   defaultValue?: string;
   translate?: any;
   /** When false, clicking the selected item again will not clear the selection. */
   allowDeselect?: boolean;
}

const Combobox = ({ data, placeholder, onSelect, defaultValue, translate, allowDeselect = true }: Props) => {
   const [open, setOpen] = useState(false);
   const [value, setValue] = useState(defaultValue || '');
   const onSelectRef = useRef(onSelect);
   const hydratedDefaultRef = useRef<string | undefined>(undefined);

   useEffect(() => {
      onSelectRef.current = onSelect;
   }, [onSelect]);

   useEffect(() => {
      const nextDefault = defaultValue || '';
      if (!nextDefault || hydratedDefaultRef.current === nextDefault) {
         return;
      }

      const defaultItem = data.find((item) => String(item.value) === String(nextDefault));
      if (!defaultItem) {
         return;
      }

      hydratedDefaultRef.current = nextDefault;
      setValue(String(nextDefault));
   }, [defaultValue, data]);

   const handleSelect = (selected: Data) => {
      const selectedValue = String(selected.value);

      if (selectedValue === value) {
         if (!allowDeselect) {
            setOpen(false);
            return;
         }

         setValue('');
         onSelectRef.current({ ...selected, value: '' });
         setOpen(false);
         return;
      }

      setValue(selectedValue);
      onSelectRef.current(selected);
      setOpen(false);
   };

   return (
      <Popover open={open} onOpenChange={setOpen}>
         <PopoverTrigger className="w-full">
            <Button type="button" variant="outline" role="combobox" aria-expanded={open} className="w-full justify-between">
               {value ? data.find((item) => String(item.value) === String(value))?.label : placeholder}
               <ChevronsUpDown className="opacity-50" />
            </Button>
         </PopoverTrigger>
         <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0">
            <Command
               filter={(itemValue, search) => {
                  if (!search.trim()) {
                     return 1;
                  }

                  return itemValue.toLowerCase().includes(search.toLowerCase().trim()) ? 1 : 0;
               }}
            >
               <CommandInput
                  placeholder={translate?.input?.search_placeholder || 'Search element...'}
                  className="focus:border-none focus:ring-0 focus:outline-none"
               />
               <CommandList>
                  <CommandEmpty>{translate?.frontend?.no_element_found || 'No element found.'}</CommandEmpty>
                  <CommandGroup className="max-h-[300px] overflow-y-auto">
                     {data.map((item) => (
                        <CommandItem
                           key={String(item.value)}
                           value={`${item.label} ${item.value}`}
                           onSelect={() => handleSelect(item)}
                        >
                           {item.label}
                           <Check className={cn('ml-auto', String(value) === String(item.value) ? 'opacity-100' : 'opacity-0')} />
                        </CommandItem>
                     ))}
                  </CommandGroup>
               </CommandList>
            </Command>
         </PopoverContent>
      </Popover>
   );
};

export default Combobox;
