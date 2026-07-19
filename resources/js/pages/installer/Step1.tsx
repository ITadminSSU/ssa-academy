import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Check, CircleX } from 'lucide-react';
import { ReactNode } from 'react';
import Layout from './Partials/Layout';
import StepNavigator from './Partials/StepNavigator';

interface Props {
   allValuesAreTrue: boolean;
   requirements: Record<string, boolean | string>;
}

const Step1 = (props: Props) => {
   const { allValuesAreTrue, requirements } = props;
   const symlinkEnabled = Boolean(requirements.symlink_enabled);

   const statusList = [
      {
         title: `PHP >= ${requirements.required_php_version}`,
         key: 'php_version',
         optional: false,
      },
      { title: 'OpenSSL PHP Extension', key: 'openssl_enabled', optional: false },
      { title: 'PDO PHP Extension', key: 'pdo_enabled', optional: false },
      { title: 'Mbstring PHP Extension', key: 'mbstring_enabled', optional: false },
      { title: 'Curl PHP Extension', key: 'curl_enabled', optional: false },
      { title: 'Tokenizer PHP Extension', key: 'tokenizer_enabled', optional: false },
      { title: 'XML PHP Extension', key: 'xml_enabled', optional: false },
      { title: 'CTYPE PHP Extension', key: 'ctype_enabled', optional: false },
      { title: 'Fileinfo PHP Extension', key: 'fileinfo_enabled', optional: false },
      { title: 'GD PHP Extension', key: 'gd_enabled', optional: false },
      { title: 'JSON PHP Extension', key: 'json_enabled', optional: false },
      { title: 'BCmath PHP Extension', key: 'bcmath_enabled', optional: false },
      { title: 'Symlink Function (optional on shared hosting)', key: 'symlink_enabled', optional: true },
   ];

   const renderStatus = (key: string, optional: boolean) => {
      const passed = Boolean(requirements[key]);

      if (passed) {
         return <Check className="text-green-500" />;
      }

      if (optional) {
         return <AlertTriangle className="text-amber-500" />;
      }

      return <CircleX className="text-red-500" />;
   };

   return (
      <>
         <StepNavigator step1="active" />

         {!allValuesAreTrue && <p className="bg-red-100 text-red-500">Your server doesn't meet the following requirements</p>}

         <div className="border border-border">
            {statusList.map(({ key, title, optional }) => (
               <div key={key} className="flex items-center justify-between px-6 py-4 text-muted-foreground odd:bg-muted">
                  {title}

                  {key === 'php_version' ? (
                     <div className="flex items-center">
                        <span className="mr-2">{requirements.current_php_version}</span>
                        {renderStatus(key, optional)}
                     </div>
                  ) : (
                     renderStatus(key, optional)
                  )}
               </div>
            ))}
         </div>

         {!symlinkEnabled && allValuesAreTrue && (
            <div className="mt-4 rounded-md border border-amber-500/30 bg-amber-500/10 p-4">
               <div className="text-sm text-amber-900 dark:text-amber-200">
                  <strong>Symlink is disabled</strong> on this host (common on Hostinger). You can still continue — the
                  installer sets <code className="text-xs">PUBLIC_STORAGE_PATH=app/public</code> and serves files from{' '}
                  <code className="text-xs">storage/app/public</code> via the <code className="text-xs">/storage/…</code> route.
               </div>
            </div>
         )}

         {!allValuesAreTrue && (
            <div className="mt-4 rounded-md border border-amber-500/30 bg-amber-500/10 p-4">
               <div className="flex">
                  <div className="ml-3">
                     <h3 className="text-sm font-medium text-amber-900 dark:text-amber-200">Important Notes</h3>
                     <div className="mt-2 text-sm text-amber-800 dark:text-amber-100">
                        <ul className="list-disc space-y-1 pl-5">
                           <li>
                              <strong>Symlink Function:</strong> Optional on shared hosting. When disabled, uploads still work via
                              PUBLIC_STORAGE_PATH.
                           </li>
                           <li>
                              <strong>PHP Extensions:</strong> These extensions are essential for Laravel to function properly
                           </li>
                           <li>Contact your hosting provider if any required extensions are not met</li>
                        </ul>
                     </div>
                  </div>
               </div>
            </div>
         )}

         {allValuesAreTrue && (
            <div className="mt-8 flex items-center justify-end">
               <Link href={route('install.show-step2')}>
                  <Button className="bg-orange-500 px-6 py-3 text-white uppercase hover:bg-orange-600/90">Next Step</Button>
               </Link>
            </div>
         )}
      </>
   );
};

Step1.layout = (page: ReactNode) => <Layout children={page} />;

export default Step1;
