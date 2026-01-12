<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
   <meta charset="utf-8">
   <meta
      name="viewport"
      content="width=device-width, initial-scale=1"
   >
   <meta
      name="csrf-token"
      content="{{ csrf_token() }}"
   >

   {{-- Inline style to set the HTML background color based on our theme in app.css --}}
   <style>
      html {
         background-color: oklch(1 0 0);
      }

      html.dark {
         background-color: oklch(0.145 0 0);
      }

      /* Hide specific navbar menu items */
      a[href*="/our-team"],
      a[href*="/careers"],
      a[href*="/blogs"] {
         display: none !important;
      }
   </style>

   @if (app('system_settings'))
      <title inertia>
         {{ $metaTitle ?? app('system_settings')->fields['name'] }}
      </title>
      <meta
         name="description"
         content="{{ $metaDescription ?? app('system_settings')->fields['description'] }}"
      >
      <meta
         name="keywords"
         content="{{ $metaKeywords ?? app('system_settings')->fields['keywords'] }}"
      >
      <meta
         name="author"
         content="{{ app('system_settings')->fields['author'] }}"
      >

      {{-- Direct favicon injection for php artisan serve --}}
      <link
         rel="icon"
         href="{{ asset('assets/images/symbol-color.png') }}"
         type="image/png"
      >

      @if (!empty(app('system_settings')->fields['favicon']))
         <link
            rel="icon"
            href="{{ app('system_settings')->fields['favicon'] }}"
            type="image/png"
         >
      @elseif (!empty(app('system_settings')->fields['logo_light']))
         <link
            rel="icon"
            href="{{ app('system_settings')->fields['logo_light'] }}"
            type="image/png"
         >
      @else
         {{-- Fallback to symbol-color.png --}}
         <link
            rel="icon"
            href="{{ asset('assets/images/symbol-color.png') }}"
            type="image/png"
         >
      @endif

      <meta
         property="og:type"
         content="{{ $ogType ?? 'website' }}"
      >
      <meta
         property="og:url"
         content="{{ $ogUrl ?? env('APP_URL', config('app.url')) }}"
      >
      <meta
         property="og:title"
         content="{{ $ogTitle ?? (app('system_settings')->fields['title'] ?? app('system_settings')->fields['name']) }}"
      >
      <meta
         property="og:description"
         content="{{ $ogDescription ?? app('system_settings')->fields['description'] }}"
      >
      <meta
         property="og:site_name"
         content="{{ app('system_settings')->fields['name'] }}"
      >

      @if (!empty($ogImage))
         <meta
            property="og:image"
            content="{{ $ogImage }}"
         >
         <meta
            property="og:image:width"
            content="1200"
         >
         <meta
            property="og:image:height"
            content="630"
         >
         <meta
            property="og:image:alt"
            content="{{ $ogTitle ?? app('system_settings')->fields['name'] }}"
         >
      @elseif (!empty(app('system_settings')->fields['banner']))
         <meta
            property="og:image"
            content="{{ app('system_settings')->fields['banner'] }}"
         >
         <meta
            property="og:image:width"
            content="1000"
         >
         <meta
            property="og:image:height"
            content="600"
         >
         <meta
            property="og:image:alt"
            content="{{ app('system_settings')->fields['name'] }}"
         >
      @endif

      <meta
         name="twitter:card"
         content="{{ $twitterCard ?? 'summary_large_image' }}"
      >
      <meta
         name="twitter:title"
         content="{{ $twitterTitle ?? (app('system_settings')->fields['title'] ?? app('system_settings')->fields['name']) }}"
      >
      <meta
         name="twitter:description"
         content="{{ $twitterDescription ?? app('system_settings')->fields['description'] }}"
      >
      @if (!empty($twitterImage))
         <meta
            name="twitter:image"
            content="{{ $twitterImage }}"
         >
      @elseif (!empty(app('system_settings')->fields['banner']))
         <meta
            name="twitter:image"
            content="{{ app('system_settings')->fields['banner'] }}"
         >
      @endif
   @endif

   <link
      rel="preconnect"
      href="https://fonts.bunny.net"
   >
   <link
      href="https://fonts.bunny.net/css?family=inter:100,100i,200,200i,300,300i,400,400i,500,500i,600,600i,700,700i"
      rel="stylesheet"
   />

   @routes
   @viteReactRefresh
   @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
   @inertiaHead
</head>

<body class="font-sans antialiased">
   @inertia

   {{-- Inject Global Style AFTER app styles so it wins in the cascade --}}
   @php($globalStyle = app('system_settings')->fields['global_style'] ?? '')
   @if ($globalStyle)
      <style
         data-global-style
         type="text/css"
      >
         {!! $globalStyle !!}
      </style>
   @endif

   {{-- Hide navbar menu items: Our Team, Blogs, Careers --}}
   <script>
      (function() {
         const itemsToHide = ['Our Team', 'Blogs', 'Careers', 'Exams'];
         
         function hideNavbarItems() {
            // Find all links in navbars/headers
            const navLinks = document.querySelectorAll('header a, nav a, div[class*="navbar"] a, div[class*="container"] a');
            
            navLinks.forEach(function(link) {
               const linkText = link.textContent.trim();
               const href = link.getAttribute('href') || '';
               
               // Hide if text matches or href contains the paths
               if (itemsToHide.includes(linkText) || 
                   href.includes('/our-team') || 
                   href.includes('/careers') || 
                   href.includes('/blogs')) {
                  link.style.display = 'none';
               }
            });
         }
         
         // Run on load and mutations
         if (document.body) {
            hideNavbarItems();
            const observer = new MutationObserver(hideNavbarItems);
            observer.observe(document.body, {
               childList: true,
               subtree: true
            });
         }
         
         // Also run after delays to catch dynamically loaded content
         setTimeout(hideNavbarItems, 100);
         setTimeout(hideNavbarItems, 500);
         setTimeout(hideNavbarItems, 1000);
      })();
   </script>

   {{-- Redirect "About Us" to smartsourcingusa.com/#about --}}
   <script>
      (function() {
         const ABOUT_URL = 'https://smartsourcingusa.com/#about';
         
         // Intercept clicks on "About Us" links using capture phase (before Inertia)
         document.addEventListener('click', function(e) {
            let target = e.target;
            let link = null;
            
            // Find the link element
            if (target.tagName === 'A') {
               link = target;
            } else {
               link = target.closest('a');
            }
            
            if (!link) return;
            
            // Check if this is an "About Us" link
            const linkText = link.textContent.trim();
            const href = link.getAttribute('href') || '';
            const isAboutUs = linkText === 'About Us' || 
                             linkText === 'About' ||
                             href.includes('/about-us') ||
                             href.includes('/about');
            
            if (isAboutUs) {
               e.preventDefault();
               e.stopImmediatePropagation();
               e.stopPropagation();
               
               window.location.href = ABOUT_URL;
               return false;
            }
         }, true); // Capture phase - runs before Inertia
         
         // Also update href attributes as backup
         function updateAboutUsLinks() {
            const navLinks = document.querySelectorAll('header a, nav a, div[class*="navbar"] a, div[class*="container"] a');
            navLinks.forEach(function(link) {
               const linkText = link.textContent.trim();
               const href = link.getAttribute('href') || '';
               
               if (linkText === 'About Us' || 
                   linkText === 'About' ||
                   href.includes('/about-us') ||
                   href.includes('/about')) {
                  link.href = ABOUT_URL;
                  link.target = '_blank';
                  link.rel = 'noopener noreferrer';
               }
            });
         }
         
         // Run on load and mutations
         if (document.body) {
            updateAboutUsLinks();
            const observer = new MutationObserver(updateAboutUsLinks);
            observer.observe(document.body, {
               childList: true,
               subtree: true
            });
         }
         
         // Also run after delays
         setTimeout(updateAboutUsLinks, 100);
         setTimeout(updateAboutUsLinks, 500);
         setTimeout(updateAboutUsLinks, 1000);
      })();
   </script>
</body>

</html>
