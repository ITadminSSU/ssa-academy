<?php

namespace App\Http\Controllers;

use App\Models\HelpCenterArticle;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HelpCenterController extends Controller
{
    public const CATEGORIES = ['getting_started', 'courses', 'exams', 'certificates', 'account', 'general'];

    public function __construct(protected MediaService $mediaService) {}

    public function index()
    {
        return Inertia::render('dashboard/help-center/index', [
            'articles' => HelpCenterArticle::with('author:id,name')
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateArticle($request);

        $article = HelpCenterArticle::create([
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'is_published' => $data['is_published'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        if ($request->hasFile('file')) {
            $this->attachFile($article, $request->file('file'), 'file', 'file_name');
        }

        if ($request->hasFile('video')) {
            $this->attachFile($article, $request->file('video'), 'video', 'video_name');
        }

        return redirect()->back()->with('success', 'Help article created');
    }

    public function update(Request $request, HelpCenterArticle $help_center_article)
    {
        $data = $this->validateArticle($request, true);

        $help_center_article->update([
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'is_published' => $data['is_published'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        if ($request->hasFile('file')) {
            $this->attachFile($help_center_article, $request->file('file'), 'file', 'file_name');
        }

        if ($request->hasFile('video')) {
            $this->attachFile($help_center_article, $request->file('video'), 'video', 'video_name');
        }

        return redirect()->back()->with('success', 'Help article updated');
    }

    public function destroy(HelpCenterArticle $help_center_article)
    {
        $help_center_article->delete();

        return redirect()->back()->with('success', 'Help article deleted');
    }

    private function validateArticle(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'category' => ['required', 'in:' . implode(',', self::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:2048'],
            'is_published' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'file' => ['nullable', 'file', 'max:51200'],
            'video' => ['nullable', 'file', 'max:512000', 'mimes:mp4,webm,ogg,mov,mkv,avi'],
        ]);
    }

    private function attachFile(HelpCenterArticle $article, $file, string $column, string $nameColumn): void
    {
        $url = $this->mediaService->addNewDeletePrev($article, $file, $column);

        $article->update([
            $column => $url,
            $nameColumn => $file->getClientOriginalName(),
        ]);
    }
}
