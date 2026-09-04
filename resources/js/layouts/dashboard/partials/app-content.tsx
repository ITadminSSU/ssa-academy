import * as React from 'react';
import { cn } from '@/lib/utils';

interface AppContentProps extends React.ComponentProps<'main'> {
    variant?: 'header' | 'sidebar';
}

export function AppContent({ variant = 'header', children, className, ...props }: AppContentProps) {
    return (
        <main className={cn('mx-auto flex h-full w-full max-w-7xl flex-1 flex-col rounded-xl', className)} {...props}>
            {children}
        </main>
    );
}
