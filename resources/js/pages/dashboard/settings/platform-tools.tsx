import WarningModal from '@/components/warning-modal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, usePage } from '@inertiajs/react';
import { Eraser, FolderSymlink, Wrench } from 'lucide-react';
import { ReactNode } from 'react';

const PlatformTools = () => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { settings } = translate;

   return (
      <div className="md:px-3">
         <Head title={settings.platform_tools} />

         <div className="border-border/60 bg-card mb-6 rounded-2xl border p-6 shadow-sm">
            <p className="text-primary mb-2 text-xs font-semibold tracking-[0.18em] uppercase">SSU Academy</p>
            <h1 className="text-2xl font-semibold tracking-tight">{settings.platform_tools}</h1>
            <p className="text-muted-foreground mt-2 text-sm">{settings.platform_tools_description}</p>
         </div>

         <Card className="border-border/60 shadow-sm">
            <CardHeader className="p-6">
               <CardTitle className="flex items-center gap-2 text-xl">
                  <Wrench className="text-primary h-5 w-5" />
                  {settings.platform_tools_operations}
               </CardTitle>
               <CardDescription>{settings.platform_tools_operations_description}</CardDescription>
            </CardHeader>

            <CardContent className="space-y-6 p-6 pt-0">
               <div className="grid gap-4 md:grid-cols-2">
                  <Card className="border-border/60 bg-muted/30">
                     <CardHeader className="p-4">
                        <CardTitle className="text-base">{settings.platform_tools_clear_cache}</CardTitle>
                        <CardDescription className="text-sm">{settings.platform_tools_clear_cache_description}</CardDescription>
                     </CardHeader>
                     <CardContent className="p-4 pt-0">
                        <WarningModal
                           method="post"
                           routePath={route('settings.platform-tools.clear-cache')}
                           title={settings.platform_tools_clear_cache_confirm}
                           actionComponent={
                              <Button type="button" className="w-full sm:w-auto">
                                 <Eraser className="h-4 w-4" />
                                 <span>{settings.platform_tools_clear_cache}</span>
                              </Button>
                           }
                        />
                     </CardContent>
                  </Card>

                  <Card className="border-border/60 bg-muted/30">
                     <CardHeader className="p-4">
                        <CardTitle className="text-base">{settings.platform_tools_storage_link}</CardTitle>
                        <CardDescription className="text-sm">{settings.platform_tools_storage_link_description}</CardDescription>
                     </CardHeader>
                     <CardContent className="p-4 pt-0">
                        <WarningModal
                           method="post"
                           routePath={route('settings.platform-tools.storage-link')}
                           title={settings.platform_tools_storage_link_confirm}
                           actionComponent={
                              <Button type="button" variant="secondary" className="w-full sm:w-auto">
                                 <FolderSymlink className="h-4 w-4" />
                                 <span>{settings.platform_tools_storage_link}</span>
                              </Button>
                           }
                        />
                     </CardContent>
                  </Card>
               </div>

               <p className="text-muted-foreground text-sm">{settings.platform_tools_note}</p>
            </CardContent>
         </Card>
      </div>
   );
};

PlatformTools.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default PlatformTools;
