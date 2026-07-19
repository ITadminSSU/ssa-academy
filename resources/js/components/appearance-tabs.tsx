import { HTMLAttributes } from 'react';

export default function AppearanceToggleTab({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
    // Dark mode is disabled; the theme toggle tabs are hidden to keep the app in light mode only.
    return <div className={className} {...props} style={{ display: 'none' }} />;
}
