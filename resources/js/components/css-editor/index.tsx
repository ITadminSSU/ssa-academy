import { autocompletion, closeBrackets, closeBracketsKeymap, completionKeymap } from '@codemirror/autocomplete';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { css } from '@codemirror/lang-css';
import { bracketMatching } from '@codemirror/language';
import { highlightSelectionMatches } from '@codemirror/search';
import { EditorState } from '@codemirror/state';
import { EditorView, highlightActiveLine, highlightActiveLineGutter, keymap, lineNumbers } from '@codemirror/view';
import { useEffect, useRef } from 'react';
import { theme } from './theme';

const CssEditor = ({ value, setValue }: { value: string; setValue: (value: string) => void }) => {
   // CodeMirror editor for CSS content
   const editorRef = useRef<HTMLDivElement>(null);
   const viewRef = useRef<EditorView | null>(null);

   useEffect(() => {
      if (!editorRef.current) return;

      const startDoc = (value as string) || '';

      const state = EditorState.create({
         doc: startDoc,
         extensions: [
            lineNumbers(),
            highlightActiveLine(),
            highlightActiveLineGutter(),
            css(),
            theme,
            history(),
            bracketMatching(),
            closeBrackets(),
            autocompletion(),
            highlightSelectionMatches(),
            keymap.of([...defaultKeymap, ...historyKeymap, ...completionKeymap, ...closeBracketsKeymap]),
            EditorView.updateListener.of((v) => {
               if (v.docChanged) {
                  const value = v.state.doc.toString();
                  setValue(value);
               }
            }),
            EditorView.lineWrapping,
         ],
      });

      const view = new EditorView({ state, parent: editorRef.current });
      viewRef.current = view;

      return () => view.destroy();
   }, [editorRef]);

   return (
      <div
         ref={editorRef}
         className="h-[420px] w-full rounded-md border"
         onMouseDown={(e) => {
            // If click is not handled by the editor (e.g., on padding/edges), ensure focus
            // We allow CodeMirror internal handling when the target is inside .cm-editor
            const target = e.target as HTMLElement;
            if (!target.closest('.cm-editor')) {
               e.preventDefault();
               viewRef.current?.focus();
            }
         }}
      />
   );
};

export default CssEditor;
