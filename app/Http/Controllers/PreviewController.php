<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\Page;
use App\Models\Folder;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function index(Request $request, Website $website): Response
    {
        $page = $website->pages()
            ->where('path', '/')
            ->where(function ($q) {
                $q->where('filename', 'index.html')->orWhereNull('filename');
            })
            ->first();

        $content = $page?->content ?? '<h1>Homepage not found</h1>';
        $html = $this->processUrls($content, $website->id);
        if (!$this->shouldHidePreviewBar($request)) {
            $html = $this->addPreviewBanner($html, $website, $page);
        }

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Frame-Options', 'DENY');
    }

    public function folder(Request $request, Folder $folder, ?string $slug = null): Response
    {
        $website = $folder->website;
        $pages = $folder->pages()->get();

        $pagesData = $pages->map(function ($page) use ($website) {
            $data = json_decode($page->content_json ?? '{}', true) ?: [];
            $gallery = $data['gallery'] ?? [];
            return [
                'title' => $data['title'] ?? $page->title ?? 'Untitled',
                'description' => $data['about1'] ?? '',
                'image' => $gallery[0] ?? '',
                'location_text' => $data['location_text'] ?? $data['location'] ?? '',
                'url' => '/preview/' . $website->id . $page->path,
                'amenities' => $data['amenities'] ?? [],
            ];
        })->toArray();

        $templatePath = public_path('templates/listing-1/index.html');
        $html = file_exists($templatePath) ? file_get_contents($templatePath) : '<h1>Template not found</h1>';

        $folderName = $folder->name ?? 'Category';
        $folderDesc = $folder->description ?? 'Browse all properties in this category';

        $html = str_replace('{{TITLE}}', e($folderName), $html);
        $html = str_replace('{{DESCRIPTION}}', e($folderDesc), $html);
        $html = str_replace('{{OG_IMAGE}}', $pagesData[0]['image'] ?? '', $html);
        $html = str_replace('{{OG_URL}}', url()->current(), $html);

        $dataScript = '<script type="application/json" id="page-data">' . json_encode(['pages' => $pagesData]) . '</script>';
        $html = str_replace('{{PAGE_DATA_SCRIPT}}', $dataScript, $html);

        if (!$this->shouldHidePreviewBar($request)) {
            $html = $this->addFolderPreviewBanner($html, $website, $folder);
        }

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Frame-Options', 'DENY');
    }

    public function page(Request $request, Website $website, string $path): Response
    {
        $normalized = '/' . ltrim($path, '/');
        $page = $website->pages()->where('path', $normalized)->orderByDesc('id')->first();

        $content = $page?->content ?? '<h1>Page not found</h1>';
        $html = $this->processUrls($content, $website->id);
        if (!$this->shouldHidePreviewBar($request)) {
            $html = $this->addPreviewBanner($html, $website, $page);
        }

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Frame-Options', 'DENY');
    }

    private function processUrls(string $html, int $websiteId): string
    {
        $withBase = $this->injectIntoHead($html, '<base href="/preview/' . $websiteId . '/">');

        $rewritten = preg_replace(
            '/(href|src)="\/(?!(?:websites|vps|login|settings|api|monitoring|profile|templates)\b)/i',
            '$1="/preview/' . $websiteId . '/',
            $withBase
        );

        $robots = '<meta name="robots" content="noindex, nofollow">';
        $withRobots = $this->injectIntoHead($rewritten, $robots);

        return $withRobots;
    }

    private function addFolderPreviewBanner(string $html, Website $website, Folder $folder): string
    {
        $status = $website->status ?? 'draft';
        $statusColor = match ($status) {
            'deployed' => '#16a34a',
            'deploying' => '#2563eb',
            'suspended' => '#f97316',
            'error' => '#dc2626',
            default => '#6b7280',
        };

        $folderEditorUrl = '/websites/' . $website->id . '/folders/' . $folder->id;

        $bar = '<div id="__preview_bar__" style="position:fixed;top:0;left:0;right:0;z-index:2147483647;background:linear-gradient(90deg,#0ea5e9,#3b82f6);color:#fff;padding:10px 16px;display:flex;align-items:center;gap:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;box-shadow:0 2px 8px rgba(0,0,0,0.15)">' .
            '<strong style="margin-right:8px">' . e($folder->name) . '</strong>' .
            '<span style="display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;background:#fff;color:' . $statusColor . ';font-weight:600;font-size:12px">' . ucfirst($status) . '</span>' .
            '<span style="flex:1"></span>' .
            '<a href="' . e($folderEditorUrl) . '" style="text-decoration:none;color:#fff;border:1px solid rgba(255,255,255,0.6);padding:6px 10px;border-radius:6px">Edit Folder</a>' .
            '<button type="button" onclick="__hide_preview_bar()" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,0.6);padding:6px 10px;border-radius:6px">Hide</button>' .
            '<a href="/websites/' . $website->id . '/folders" style="text-decoration:none;color:#fff;border:1px solid rgba(255,255,255,0.6);padding:6px 10px;border-radius:6px">Back</a>' .
            '</div>' .
            '<script>function __hide_preview_bar(){try{localStorage.setItem("previewBarHidden","1");var b=document.getElementById("__preview_bar__");if(b){b.remove();}document.documentElement.style.scrollPaddingTop="0px";var s=document.getElementById("__preview_show__");if(!s){s=document.createElement("button");s.id="__preview_show__";s.textContent="Show";s.style.position="fixed";s.style.top="8px";s.style.right="8px";s.style.zIndex="2147483647";s.style.background="#111827";s.style.color="#fff";s.style.border="1px solid rgba(255,255,255,0.6)";s.style.padding="6px 10px";s.style.borderRadius="6px";s.onclick=function(){__show_preview_bar();};document.body.appendChild(s);}}catch(e){}}function __show_preview_bar(){try{localStorage.removeItem("previewBarHidden");var s=document.getElementById("__preview_show__");if(s){s.remove();}window.location.reload();}catch(e){}}(function(){try{if(localStorage.getItem("previewBarHidden")==="1"){var b=document.getElementById("__preview_bar__");if(b){b.remove();}document.documentElement.style.scrollPaddingTop="0px";var s=document.getElementById("__preview_show__");if(!s){s=document.createElement("button");s.id="__preview_show__";s.textContent="Show";s.style.position="fixed";s.style.top="8px";s.style.right="8px";s.style.zIndex="2147483647";s.style.background="#111827";s.style.color="#fff";s.style.border="1px solid rgba(255,255,255,0.6)";s.style.padding="6px 10px";s.style.borderRadius="6px";s.onclick=function(){__show_preview_bar();};document.body.appendChild(s);}}}catch(e){}})();</script>';

        $shim = '<style>html{scroll-padding-top:56px}</style>';

        return $this->injectIntoBodyTop($html, $bar . $shim);
    }

    private function addPreviewBanner(string $html, Website $website, ?Page $page): string
    {
        $status = $website->status ?? 'draft';
        $statusColor = match ($status) {
            'deployed' => '#16a34a',
            'deploying' => '#2563eb',
            'suspended' => '#f97316',
            'error' => '#dc2626',
            default => '#6b7280',
        };

        $pageEditorUrl = $page ? '/websites/' . $website->id . '/pages/' . $page->id : '/websites/' . $website->id . '/pages';

        $bar = '<div id="__preview_bar__" style="position:fixed;top:0;left:0;right:0;z-index:2147483647;background:linear-gradient(90deg,#0ea5e9,#3b82f6);color:#fff;padding:10px 16px;display:flex;align-items:center;gap:12px;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;box-shadow:0 2px 8px rgba(0,0,0,0.15)">' .
            '<strong style="margin-right:8px">' . e($website->domain) . '</strong>' .
            '<span style="display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;background:#fff;color:' . $statusColor . ';font-weight:600;font-size:12px">' . ucfirst($status) . '</span>' .
            '<span style="flex:1"></span>' .
            '<a href="' . e($pageEditorUrl) . '" style="text-decoration:none;color:#fff;border:1px solid rgba(255,255,255,0.6);padding:6px 10px;border-radius:6px">Edit</a>' .
            '<button type="button" onclick="__deploy()" style="background:#16a34a;color:#fff;border:none;padding:6px 10px;border-radius:6px">Deploy</button>' .
            '<button type="button" onclick="__hide_preview_bar()" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,0.6);padding:6px 10px;border-radius:6px">Hide</button>' .
            '<a href="/websites/' . $website->id . '/pages" style="text-decoration:none;color:#fff;border:1px solid rgba(255,255,255,0.6);padding:6px 10px;border-radius:6px">Back</a>' .
            '</div>' .
            '<script>function __deploy(){var t=localStorage.getItem("adminToken");if(!t){alert("Missing admin token. Please login.");return}fetch("/api/websites/' . $website->id . '/deploy",{method:"POST",headers:{"X-Admin-Token":t}}).then(async r=>{if(!r.ok){const j=await r.json().catch(()=>({error:"Deploy failed"}));alert(j.error||"Deploy failed")}else{alert("Deployment started")}}).catch(()=>alert("Network error"));}function __hide_preview_bar(){try{localStorage.setItem("previewBarHidden","1");var b=document.getElementById("__preview_bar__");if(b){b.remove();}document.documentElement.style.scrollPaddingTop="0px";var s=document.getElementById("__preview_show__");if(!s){s=document.createElement("button");s.id="__preview_show__";s.textContent="Show";s.style.position="fixed";s.style.top="8px";s.style.right="8px";s.style.zIndex="2147483647";s.style.background="#111827";s.style.color="#fff";s.style.border="1px solid rgba(255,255,255,0.6)";s.style.padding="6px 10px";s.style.borderRadius="6px";s.onclick=function(){__show_preview_bar();};document.body.appendChild(s);}}catch(e){}}function __show_preview_bar(){try{localStorage.removeItem("previewBarHidden");var s=document.getElementById("__preview_show__");if(s){s.remove();}window.location.reload();}catch(e){}}(function(){try{if(localStorage.getItem("previewBarHidden")==="1"){var b=document.getElementById("__preview_bar__");if(b){b.remove();}document.documentElement.style.scrollPaddingTop="0px";var s=document.getElementById("__preview_show__");if(!s){s=document.createElement("button");s.id="__preview_show__";s.textContent="Show";s.style.position="fixed";s.style.top="8px";s.style.right="8px";s.style.zIndex="2147483647";s.style.background="#111827";s.style.color="#fff";s.style.border="1px solid rgba(255,255,255,0.6)";s.style.padding="6px 10px";s.style.borderRadius="6px";s.onclick=function(){__show_preview_bar();};document.body.appendChild(s);}}}catch(e){}})();</script>';

        $shim = '<style>html{scroll-padding-top:56px}</style>';

        $withBar = $this->injectIntoBodyTop($html, $bar . $shim);
        return $withBar;
    }

    private function shouldHidePreviewBar(Request $request): bool
    {
        return $request->boolean('hide_preview_bar')
            || $request->boolean('hide_banner')
            || ($request->headers->get('X-Hide-Preview-Bar') === '1');
    }

    private function injectIntoHead(string $html, string $injection): string
    {
        if (stripos($html, '<head') !== false) {
            return preg_replace('/<head[^>]*>/i', '$0' . $injection, $html, 1);
        }
        return '<head>' . $injection . '</head>' . $html;
    }

    private function injectIntoBodyTop(string $html, string $injection): string
    {
        if (stripos($html, '<body') !== false) {
            return preg_replace('/<body[^>]*>/i', '$0' . $injection, $html, 1);
        }
        return $injection . $html;
    }
}