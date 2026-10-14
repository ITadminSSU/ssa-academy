import { useEffect, useRef } from 'react';

const GhlEmbed = ({ html }: { html: string }) => {
   const hostRef = useRef<HTMLDivElement>(null);

   useEffect(() => {
      const host = hostRef.current;

      if (!host) {
         return;
      }

      const template = document.createElement('template');
      template.innerHTML = html.trim();
      const scripts = [...template.content.querySelectorAll('script')];
      scripts.forEach((script) => script.remove());
      host.replaceChildren(template.content);

      const added: HTMLScriptElement[] = [];

      scripts.forEach((oldScript) => {
         const src = oldScript.getAttribute('src');

         if (!src || !src.startsWith('https://link.msgsndr.com/')) {
            return;
         }

         if (document.querySelector(`script[src="${src}"]`)) {
            return;
         }

         const script = document.createElement('script');
         script.src = src;
         script.async = true;
         document.body.appendChild(script);
         added.push(script);
      });

      return () => {
         host.replaceChildren();
         added.forEach((script) => script.remove());
      };
   }, [html]);

   return <div ref={hostRef} className="drywall-ghl min-h-[569px] w-full" />;
};

export default GhlEmbed;
