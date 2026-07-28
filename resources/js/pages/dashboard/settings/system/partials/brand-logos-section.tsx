import BrandLogoSlot from '@/pages/dashboard/settings/system/partials/brand-logo-slot';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { onHandleChange } from '@/lib/inertia';
import { LOGO_PLACEMENT_CONFIG, LOGO_PLACEMENTS, mergeLogoSizes, type LogoPlacement, type LogoSizeConfig } from '@/lib/logo-placements';
import { useMemo } from 'react';

interface BrandLogosSectionProps {
   data: SystemFields & Record<string, unknown>;
   errors: Record<string, string>;
   previews: Partial<Record<LogoPlacement, string | null>>;
   setData: (key: string, value: unknown) => void;
   setPreview: (placement: LogoPlacement, url: string | null) => void;
}

const BrandLogosSection = ({ data, errors, previews, setData, setPreview }: BrandLogosSectionProps) => {
   const logoSizes = useMemo(() => mergeLogoSizes(data.logo_sizes), [data.logo_sizes]);

   const handleFileSelect = (fieldName: string, file: File, previewUrl: string) => {
      setData(fieldName, file);
      const placement = LOGO_PLACEMENTS.find((item) => `new_logo_${item}` === fieldName);
      if (placement) {
         setPreview(placement, previewUrl);
      }
   };

   const handleSizeChange = (placement: LogoPlacement, size: LogoSizeConfig) => {
      setData('logo_sizes', {
         ...logoSizes,
         [placement]: size,
      });
   };

   return (
      <div className="border-b pb-6">
         <h2 className="mb-2 text-xl font-semibold">Brand logos</h2>
         <p className="text-muted-foreground mb-6 text-sm">
            Upload a logo for each area of the site. Adjust size with the sliders and use crop to fine-tune before saving.
         </p>

         <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {LOGO_PLACEMENTS.map((placement) => (
               <BrandLogoSlot
                  key={placement}
                  placement={placement}
                  currentUrl={data[LOGO_PLACEMENT_CONFIG[placement].field] as string}
                  previewUrl={previews[placement]}
                  size={logoSizes[placement]}
                  error={errors[`new_logo_${placement}`]}
                  onFileSelect={handleFileSelect}
                  onSizeChange={handleSizeChange}
               />
            ))}
         </div>

         <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
               <Label>Favicon</Label>
               {data.favicon ? (
                  <div className="border-border/60 mb-3 inline-flex rounded-lg border bg-white p-3">
                     <img src={data.favicon} alt="Current favicon preview" className="h-12 w-12 object-contain" />
                  </div>
               ) : null}
               <Input type="file" name="new_favicon" accept="image/*" onChange={(e) => onHandleChange(e, setData)} placeholder="Select Favicon" />
               <p className="text-muted-foreground mt-2 text-xs">Square SSA icon works best. Recommended size: 512x512 PNG.</p>
               <InputError message={errors.new_favicon} />
            </div>

            <div>
               <Label>Banner</Label>
               <Input type="file" name="new_banner" accept="image/*" onChange={(e) => onHandleChange(e, setData)} placeholder="Select Banner" />
               <InputError message={errors.new_banner} />
            </div>
         </div>
      </div>
   );
};

export default BrandLogosSection;
