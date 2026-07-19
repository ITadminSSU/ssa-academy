import { useEffect } from 'react';

const BLOCKED_SHORTCUTS = new Set(['c', 's', 'u', 'p', 'a']);

function isShortcutEvent(event: KeyboardEvent): boolean {
   const key = event.key.toLowerCase();
   const meta = event.metaKey || event.ctrlKey;

   if (meta && BLOCKED_SHORTCUTS.has(key)) {
      return true;
   }

   if (event.ctrlKey && event.shiftKey && ['i', 'j', 'c', 'k', 'u'].includes(key)) {
      return true;
   }

   if (event.metaKey && event.altKey && ['i', 'c'].includes(key)) {
      return true;
   }

   return event.key === 'F12';
}

export function useContentProtection(enabled = true) {
   useEffect(() => {
      if (!enabled) {
         return;
      }

      const preventContextMenu = (event: MouseEvent) => {
         event.preventDefault();
      };

      const preventCopy = (event: ClipboardEvent) => {
         event.preventDefault();
      };

      const preventDragStart = (event: DragEvent) => {
         event.preventDefault();
      };

      const preventShortcuts = (event: KeyboardEvent) => {
         if (isShortcutEvent(event)) {
            event.preventDefault();
            event.stopPropagation();
         }
      };

      document.addEventListener('contextmenu', preventContextMenu);
      document.addEventListener('copy', preventCopy);
      document.addEventListener('cut', preventCopy);
      document.addEventListener('dragstart', preventDragStart);
      document.addEventListener('keydown', preventShortcuts, true);

      return () => {
         document.removeEventListener('contextmenu', preventContextMenu);
         document.removeEventListener('copy', preventCopy);
         document.removeEventListener('cut', preventCopy);
         document.removeEventListener('dragstart', preventDragStart);
         document.removeEventListener('keydown', preventShortcuts, true);
      };
   }, [enabled]);
}
