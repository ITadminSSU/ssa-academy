import { Button } from '@/components/ui/button';
import { Component, type ErrorInfo, type ReactNode } from 'react';

type ResetKey = string | number | boolean | null | undefined;

type ErrorBoundaryProps = {
   children: ReactNode;
   resetKeys?: ResetKey[];
   fallback?: ReactNode;
   title?: string;
   description?: string;
   actionLabel?: string;
   onReset?: () => void;
};

type ErrorBoundaryState = {
   hasError: boolean;
};

function resetKeysChanged(prev?: ResetKey[], next?: ResetKey[]) {
   if (prev === next) {
      return false;
   }

   if (!prev || !next || prev.length !== next.length) {
      return true;
   }

   return prev.some((value, index) => value !== next[index]);
}

export default class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
   state: ErrorBoundaryState = { hasError: false };

   static getDerivedStateFromError(): ErrorBoundaryState {
      return { hasError: true };
   }

   componentDidCatch(error: Error, info: ErrorInfo) {
      console.error('ErrorBoundary caught an error', error, info.componentStack);
   }

   componentDidUpdate(prevProps: ErrorBoundaryProps) {
      if (this.state.hasError && resetKeysChanged(prevProps.resetKeys, this.props.resetKeys)) {
         this.setState({ hasError: false });
      }
   }

   private handleReset = () => {
      this.setState({ hasError: false });
      this.props.onReset?.();
   };

   render() {
      if (!this.state.hasError) {
         return this.props.children;
      }

      if (this.props.fallback) {
         return this.props.fallback;
      }

      return (
         <div className="bg-muted flex min-h-[40vh] flex-col items-center justify-center gap-3 p-8 text-center">
            <p className="text-lg font-semibold">{this.props.title ?? 'Something went wrong'}</p>
            <p className="text-muted-foreground max-w-md text-sm">
               {this.props.description ?? 'Please try again. If this keeps happening, reload the page.'}
            </p>
            <div className="flex flex-wrap items-center justify-center gap-2">
               <Button type="button" onClick={this.handleReset}>
                  {this.props.actionLabel ?? 'Try again'}
               </Button>
               <Button type="button" variant="outline" onClick={() => window.location.reload()}>
                  Reload page
               </Button>
            </div>
         </div>
      );
   }
}
