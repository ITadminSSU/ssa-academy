import { RefObject, useEffect } from 'react';

function isDevToolsShortcut(event: KeyboardEvent): boolean {
   const key = event.key.toLowerCase();

   if (key === 'f12') {
      return true;
   }

   if (event.ctrlKey && event.shiftKey && ['i', 'j', 'c', 'k', 'u'].includes(key)) {
      return true;
   }

   if (event.metaKey && event.altKey && key === 'i') {
      return true;
   }

   if (event.metaKey && event.altKey && key === 'c') {
      return true;
   }

   if (key === 'f11' || (event.metaKey && event.altKey && key === 'u')) {
      return true;
   }

   return false;
}

function isBlockedMediaShortcut(event: KeyboardEvent): boolean {
   const key = event.key.toLowerCase();
   const meta = event.metaKey || event.ctrlKey;

   return meta && ['s', 'p', 'u'].includes(key);
}

export function useVideoPlayerGuards(containerRef: RefObject<HTMLElement | null>, enabled = true) {
   useEffect(() => {
      if (!enabled) {
         return;
      }

      const handleKeyDown = (event: KeyboardEvent) => {
         if (isDevToolsShortcut(event) || isBlockedMediaShortcut(event)) {
            event.preventDefault();
            event.stopPropagation();
         }
      };

      const handleContextMenu = (event: MouseEvent) => {
         event.preventDefault();
      };

      const handleDragStart = (event: DragEvent) => {
         event.preventDefault();
      };

      const container = containerRef.current;
      const targets: Array<EventTarget | null | undefined> = [container, document];

      targets.forEach((target) => {
         target?.addEventListener('keydown', handleKeyDown as EventListener, true);
         target?.addEventListener('contextmenu', handleContextMenu as EventListener, true);
         target?.addEventListener('dragstart', handleDragStart as EventListener, true);
      });

      return () => {
         targets.forEach((target) => {
            target?.removeEventListener('keydown', handleKeyDown as EventListener, true);
            target?.removeEventListener('contextmenu', handleContextMenu as EventListener, true);
            target?.removeEventListener('dragstart', handleDragStart as EventListener, true);
         });
      };
   }, [containerRef, enabled]);
}
