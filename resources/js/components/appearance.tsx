import { HTMLAttributes } from 'react';

export default function Appearance({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
   // Dark mode is disabled; the theme toggle is hidden to keep the app in light mode only.
   return <div className={className} {...props} style={{ display: 'none' }} />;
}
