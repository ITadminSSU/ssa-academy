import LogoCropDialog from '@/components/logo-crop-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import { LOGO_PLACEMENT_CONFIG, type LogoPlacement, type LogoSizeConfig } from '@/lib/logo-placements';
import { cn } from '@/lib/utils';
import { Crop, Upload } from 'lucide-react';
import { ChangeEvent, useEffect, useMemo, useRef, useState } from 'react';

interface BrandLogoSlotProps {
   placement: LogoPlacement;
   currentUrl?: string | null;
   previewUrl?: string | null;
   size: LogoSizeConfig;
   error?: string;
   onFileSelect: (fieldName: string, file: File, previewUrl: string) => void;
   onSizeChange: (placement: LogoPlacement, size: LogoSizeConfig) => void;
}

const PlacementMockup = ({
   placement,
   src,
   size,
}: {
   placement: LogoPlacement;
   src?: string | null;
   size: LogoSizeConfig;
}) => {
   const imageStyle = {
      height: `${size.height}px`,
      maxWidth: `${size.maxWidth}px`,
   };

   if (placement === 'navbar') {
      return (
         <div className="border-border/60 overflow-hidden rounded-lg border bg-white">
            <div className="bg-accent h-1 w-full" />
            <div className="flex h-14 items-center gap-3 px-4">
               <div className="ssu-logo-frame ssu-logo-frame--nav" style={{ height: `${Math.min(size.height + 8, 56)}px` }}>
                  {src ? <img src={src} alt="" className="ssu-nav-logo block object-contain" style={imageStyle} /> : <div className="bg-muted h-8 w-20 rounded" />}
               </div>
               <div className="bg-muted hidden h-2 flex-1 rounded sm:block" />
               <div className="bg-muted h-8 w-16 rounded-full" />
            </div>
         </div>
      );
   }

   if (placement === 'footer') {
      return (
         <div className="border-border/60 rounded-lg border bg-[color:var(--brand-grey)] p-4">
            <div className="ssu-logo-frame ssu-logo-frame--footer inline-flex" style={{ minHeight: `${size.height}px` }}>
               {src ? <img src={src} alt="" className="ssu-footer-logo block object-contain" style={imageStyle} /> : <div className="bg-muted h-16 w-28 rounded" />}
            </div>
            <div className="mt-4 grid grid-cols-3 gap-2">
               <div className="bg-muted h-2 rounded" />
               <div className="bg-muted h-2 rounded" />
               <div className="bg-muted h-2 rounded" />
            </div>
         </div>
      );
   }

   if (placement === 'auth') {
      return (
         <div className="bg-primary overflow-hidden rounded-lg p-4 text-white">
            <div className="flex min-h-40 items-center justify-center">
               {src ? (
                  <img src={src} alt="" className="mx-auto block object-contain" style={imageStyle} />
               ) : (
                  <div className="bg-primary-foreground/20 h-24 w-40 rounded" />
               )}
            </div>
            <div className="mt-4 space-y-2">
               <div className="bg-primary-foreground/25 h-3 w-2/3 rounded" />
               <div className="bg-primary-foreground/15 h-2 w-full rounded" />
            </div>
         </div>
      );
   }

   if (placement === 'dashboard') {
      return (
         <div className="border-border/60 overflow-hidden rounded-lg border">
            <div className="bg-sidebar border-sidebar-border border-b px-4 py-5">
               {src ? (
                  <img src={src} alt="" className="mx-auto block object-contain" style={imageStyle} />
               ) : (
                  <div className="bg-sidebar-accent mx-auto h-16 w-32 rounded" />
               )}
            </div>
            <div className="bg-background space-y-2 p-3">
               <div className="bg-muted h-8 rounded" />
               <div className="bg-muted h-8 rounded" />
            </div>
         </div>
      );
   }

   return (
      <div className="border-border/60 rounded-lg border bg-white p-6 text-center shadow-sm">
         <p className="text-muted-foreground mb-4 text-xs tracking-wide uppercase">Certificate preview</p>
         {src ? (
            <img src={src} alt="" className="mx-auto block object-contain" style={imageStyle} />
         ) : (
            <div className="bg-muted mx-auto h-16 w-24 rounded" />
         )}
         <div className="border-primary/30 mt-6 border-t-2 pt-4">
            <div className="bg-muted mx-auto h-2 w-1/2 rounded" />
         </div>
      </div>
   );
};

const BrandLogoSlot = ({ placement, currentUrl, previewUrl, size, error, onFileSelect, onSizeChange }: BrandLogoSlotProps) => {
   const config = LOGO_PLACEMENT_CONFIG[placement];
   const inputRef = useRef<HTMLInputElement>(null);
   const [cropOpen, setCropOpen] = useState(false);
   const [cropSource, setCropSource] = useState<string | null>(null);
   const [pendingFileName, setPendingFileName] = useState('logo.png');
   const displayUrl = previewUrl || currentUrl || null;

   useEffect(() => {
      return () => {
         if (previewUrl?.startsWith('blob:')) {
            URL.revokeObjectURL(previewUrl);
         }
      };
   }, [previewUrl]);

   const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
      const file = event.target.files?.[0];
      if (!file) {
         return;
      }

      const objectUrl = URL.createObjectURL(file);
      setPendingFileName(file.name);
      setCropSource(objectUrl);
      setCropOpen(true);
      event.target.value = '';
   };

   const limits = useMemo(() => config.sizeLimits, [config.sizeLimits]);

   return (
      <div className={cn('border-border/60 space-y-4 rounded-xl border p-4')}>
         <div>
            <Label className="text-base font-semibold">{config.label}</Label>
            <p className="text-muted-foreground mt-1 text-sm">{config.description}</p>
         </div>

         <PlacementMockup placement={placement} src={displayUrl} size={size} />

         <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => inputRef.current?.click()}>
               <Upload className="mr-2 h-4 w-4" />
               Upload image
            </Button>
            {displayUrl ? (
               <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                     setCropSource(displayUrl);
                     setCropOpen(true);
                  }}
               >
                  <Crop className="mr-2 h-4 w-4" />
                  Crop &amp; adjust
               </Button>
            ) : null}
         </div>

         <input
            ref={inputRef}
            type="file"
            accept="image/*"
            className="sr-only"
            onChange={handleFileChange}
         />

         <div className="space-y-4">
            <div className="space-y-2">
               <div className="flex items-center justify-between text-sm">
                  <span>Height</span>
                  <span className="text-muted-foreground">{size.height}px</span>
               </div>
               <Slider
                  min={limits.height.min}
                  max={limits.height.max}
                  step={1}
                  value={[size.height]}
                  onValueChange={(values) => onSizeChange(placement, { ...size, height: values[0] })}
               />
            </div>

            <div className="space-y-2">
               <div className="flex items-center justify-between text-sm">
                  <span>Max width</span>
                  <span className="text-muted-foreground">{size.maxWidth}px</span>
               </div>
               <Slider
                  min={limits.maxWidth.min}
                  max={limits.maxWidth.max}
                  step={4}
                  value={[size.maxWidth]}
                  onValueChange={(values) => onSizeChange(placement, { ...size, maxWidth: values[0] })}
               />
            </div>
         </div>

         <p className="text-muted-foreground text-xs">{config.recommended}</p>
         {error ? <p className="text-destructive text-sm">{error}</p> : null}

         <LogoCropDialog
            open={cropOpen}
            imageSrc={cropSource}
            fileName={pendingFileName}
            placement={placement}
            onOpenChange={setCropOpen}
            onConfirm={(file, url) => onFileSelect(config.uploadField, file, url)}
         />
      </div>
   );
};

export default BrandLogoSlot;
