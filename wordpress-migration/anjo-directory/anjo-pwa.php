<?php
/**
 * Plugin Name: 安城ナビ PWA
 * Description: 安城ナビをAndroid・iPhoneのホーム画面に追加できるようにします。
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $icon = 'https://anjo.website/wp-content/uploads/2026/09/cropped-90ae676c-546c-4e31-9893-0ed90ba3c218.png';

    if ($path === '/anjo.webmanifest') {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo wp_json_encode([
            'name' => '安城ナビ',
            'short_name' => '安城ナビ',
            'description' => '安城市のお店・企業を検索できるサイト',
            'start_url' => '/?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f4faf7',
            'theme_color' => '#008585',
            'icons' => [
                ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($path === '/anjo-sw.js') {
        nocache_headers();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        echo "const CACHE='anjo-v1';\n";
        echo "self.addEventListener('install',e=>{self.skipWaiting();e.waitUntil(caches.open(CACHE).then(c=>c.addAll(['/'])))});\n";
        echo "self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});\n";
        echo "self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;e.respondWith(fetch(e.request).then(r=>{const x=r.clone();caches.open(CACHE).then(c=>c.put(e.request,x));return r}).catch(()=>caches.match(e.request).then(r=>r||caches.match('/'))))});\n";
        exit;
    }
}, 0);

add_action('wp_head', function () {
    $icon = 'https://anjo.website/wp-content/uploads/2026/09/cropped-90ae676c-546c-4e31-9893-0ed90ba3c218.png';
    echo '<link rel="manifest" href="/anjo.webmanifest">' . "\n";
    echo '<meta name="theme-color" content="#008585">' . "\n";
    echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="安城ナビ">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url($icon) . '">' . "\n";
    echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/anjo-sw.js")})}</script>' . "\n";
});
