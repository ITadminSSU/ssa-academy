import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SharedData } from '@/types/global';
import { router, useForm, usePage } from '@inertiajs/react';
import QRCode from 'qrcode';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

type TwoFactorSetup = {
   secret: string;
   qr_url: string;
};

const TwoFactor = () => {
   const { props } = usePage<SharedData>();
   const { auth, flash, translate } = props;
   const { button, input, auth: authLang } = translate;

   const enabled = Boolean(auth.twoFactorEnabled);
   const setup = (flash.two_factor_setup as TwoFactorSetup | null) ?? null;
   const recoveryCodes = (flash.two_factor_recovery_codes as string[] | null) ?? null;

   const t = {
      title: button.two_factor_authentication || 'Two-Factor Auth',
      enable: button.enable_two_factor || 'Enable two-factor',
      disable: button.disable_two_factor || 'Disable two-factor',
      confirm: button.confirm_and_enable || 'Confirm and enable',
      regenerate: button.regenerate_recovery_codes || 'Regenerate recovery codes',
      code: input.two_factor_code || 'Authentication code',
      codePlaceholder: input.two_factor_code_placeholder || '6-digit code or recovery code',
      settingsDescription:
         authLang.two_factor_settings_description ||
         'Add an authenticator app for an extra layer of security on admin and trainer accounts.',
      optionalNote: authLang.two_factor_optional_note || 'Optional for now. We recommend enabling it.',
      enabled: authLang.two_factor_enabled || 'Two-factor authentication is enabled',
      disabled: authLang.two_factor_disabled || 'Two-factor authentication is not enabled',
      scanTitle: authLang.two_factor_scan_title || 'Scan this QR code',
      scanDescription:
         authLang.two_factor_scan_description ||
         'Use Google Authenticator, Microsoft Authenticator, or Authy to scan the code, then enter the 6-digit code to confirm.',
      manualSecret: authLang.two_factor_manual_secret || 'Or enter this secret manually:',
      recoveryTitle: authLang.two_factor_recovery_title || 'Save your recovery codes',
      recoverySave:
         authLang.two_factor_recovery_save ||
         'Store these codes somewhere safe. Each code can be used once if you lose access to your authenticator.',
      regenerateDescription:
         authLang.two_factor_regenerate_description ||
         'Confirm your password and a current authenticator or recovery code to generate a new set of recovery codes.',
      disableDescription:
         authLang.two_factor_disable_description ||
         'Confirm your password and a current authenticator or recovery code to disable two-factor authentication.',
   };

   const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
   const [showDisable, setShowDisable] = useState(false);
   const [showRegenerate, setShowRegenerate] = useState(false);

   const confirmForm = useForm({ code: '' });
   const disableForm = useForm({ password: '', code: '' });
   const regenerateForm = useForm({ password: '', code: '' });

   useEffect(() => {
      let cancelled = false;

      if (!setup?.qr_url) {
         setQrDataUrl(null);
         return;
      }

      QRCode.toDataURL(setup.qr_url, { width: 220, margin: 2 })
         .then((url) => {
            if (!cancelled) {
               setQrDataUrl(url);
            }
         })
         .catch(() => {
            if (!cancelled) {
               setQrDataUrl(null);
            }
         });

      return () => {
         cancelled = true;
      };
   }, [setup?.qr_url]);

   const statusLabel = useMemo(() => (enabled ? t.enabled : t.disabled), [enabled, t.disabled, t.enabled]);

   const startSetup = () => {
      router.post(route('two-factor.start'), {}, { preserveScroll: true });
   };

   const confirmSetup: FormEventHandler = (e) => {
      e.preventDefault();
      confirmForm.post(route('two-factor.confirm'), {
         preserveScroll: true,
         onSuccess: () => confirmForm.reset(),
      });
   };

   const disableTwoFactor: FormEventHandler = (e) => {
      e.preventDefault();
      disableForm.post(route('two-factor.disable'), {
         preserveScroll: true,
         onSuccess: () => {
            disableForm.reset();
            setShowDisable(false);
         },
      });
   };

   const regenerateCodes: FormEventHandler = (e) => {
      e.preventDefault();
      regenerateForm.post(route('two-factor.recovery-codes'), {
         preserveScroll: true,
         onSuccess: () => {
            regenerateForm.reset();
            setShowRegenerate(false);
         },
      });
   };

   return (
      <Card className="border-none">
         <div className="border-b-border border-b px-7 pt-7 pb-4">
            <p className="text18 font-bold">{t.title}</p>
            <p className="text-muted-foreground mt-1 text-sm">{t.settingsDescription}</p>
         </div>

         <div className="flex flex-col gap-6 px-7 py-8">
            <div className="flex flex-wrap items-center justify-between gap-3">
               <div>
                  <p className="font-medium">{statusLabel}</p>
                  <p className="text-muted-foreground text-sm">{t.optionalNote}</p>
               </div>

               {!enabled && !setup && (
                  <Button type="button" className="h-9" onClick={startSetup}>
                     {t.enable}
                  </Button>
               )}
            </div>

            {recoveryCodes && recoveryCodes.length > 0 && (
               <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4">
                  <p className="mb-2 font-semibold text-amber-950 dark:text-amber-50">{t.recoveryTitle}</p>
                  <p className="text-muted-foreground mb-3 text-sm">{t.recoverySave}</p>
                  <ul className="grid gap-2 font-mono text-sm sm:grid-cols-2">
                     {recoveryCodes.map((code) => (
                        <li key={code} className="rounded bg-background/80 px-3 py-2">
                           {code}
                        </li>
                     ))}
                  </ul>
               </div>
            )}

            {setup && !enabled && (
               <div className="space-y-5">
                  <div>
                     <p className="mb-2 font-medium">{t.scanTitle}</p>
                     <p className="text-muted-foreground mb-4 text-sm">{t.scanDescription}</p>
                     {qrDataUrl ? (
                        <img src={qrDataUrl} alt="Two-factor QR code" className="h-[220px] w-[220px] rounded-md border bg-white p-2" />
                     ) : (
                        <div className="bg-muted flex h-[220px] w-[220px] items-center justify-center rounded-md text-sm">
                           Loading QR…
                        </div>
                     )}
                     <p className="text-muted-foreground mt-3 text-sm">{t.manualSecret}</p>
                     <code className="mt-1 block break-all rounded bg-muted px-3 py-2 text-sm">{setup.secret}</code>
                  </div>

                  <form onSubmit={confirmSetup} className="space-y-4">
                     <div>
                        <Label htmlFor="confirm-code">{t.code}</Label>
                        <Input
                           id="confirm-code"
                           value={confirmForm.data.code}
                           placeholder={t.codePlaceholder}
                           onChange={(e) => confirmForm.setData('code', e.target.value)}
                           autoComplete="one-time-code"
                           required
                        />
                        <InputError message={confirmForm.errors.code} className="mt-2" />
                     </div>
                     <LoadingButton loading={confirmForm.processing} className="h-9">
                        {t.confirm}
                     </LoadingButton>
                  </form>
               </div>
            )}

            {enabled && (
               <div className="space-y-4">
                  <div className="flex flex-wrap gap-3">
                     <Button type="button" variant="outline" className="h-9" onClick={() => setShowRegenerate((v) => !v)}>
                        {t.regenerate}
                     </Button>
                     <Button type="button" variant="destructive" className="h-9" onClick={() => setShowDisable((v) => !v)}>
                        {t.disable}
                     </Button>
                  </div>

                  {showRegenerate && (
                     <form onSubmit={regenerateCodes} className="space-y-4 rounded-lg border p-4">
                        <p className="text-sm">{t.regenerateDescription}</p>
                        <div>
                           <Label>{input.current_password}</Label>
                           <Input
                              type="password"
                              value={regenerateForm.data.password}
                              onChange={(e) => regenerateForm.setData('password', e.target.value)}
                              required
                           />
                           <InputError message={regenerateForm.errors.password} className="mt-2" />
                        </div>
                        <div>
                           <Label>{t.code}</Label>
                           <Input
                              value={regenerateForm.data.code}
                              onChange={(e) => regenerateForm.setData('code', e.target.value)}
                              placeholder={t.codePlaceholder}
                              required
                           />
                           <InputError message={regenerateForm.errors.code} className="mt-2" />
                        </div>
                        <LoadingButton loading={regenerateForm.processing} className="h-9">
                           {t.regenerate}
                        </LoadingButton>
                     </form>
                  )}

                  {showDisable && (
                     <form onSubmit={disableTwoFactor} className="space-y-4 rounded-lg border border-destructive/30 p-4">
                        <p className="text-sm">{t.disableDescription}</p>
                        <div>
                           <Label>{input.current_password}</Label>
                           <Input
                              type="password"
                              value={disableForm.data.password}
                              onChange={(e) => disableForm.setData('password', e.target.value)}
                              required
                           />
                           <InputError message={disableForm.errors.password} className="mt-2" />
                        </div>
                        <div>
                           <Label>{t.code}</Label>
                           <Input
                              value={disableForm.data.code}
                              onChange={(e) => disableForm.setData('code', e.target.value)}
                              placeholder={t.codePlaceholder}
                              required
                           />
                           <InputError message={disableForm.errors.code} className="mt-2" />
                        </div>
                        <LoadingButton loading={disableForm.processing} className="h-9" variant="destructive">
                           {t.disable}
                        </LoadingButton>
                     </form>
                  )}
               </div>
            )}
         </div>
      </Card>
   );
};

export default TwoFactor;
