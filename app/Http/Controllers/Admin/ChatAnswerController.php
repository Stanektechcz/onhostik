<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Ai\Models\ChatAnswer;
use App\Domains\Ai\Services\AiChatbotService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin CRUD for curated chat answers — lets support extend the chatbot's
 * knowledge without a deploy. `hits` shows which answers actually get used.
 */
class ChatAnswerController extends Controller
{
    public function index(): View
    {
        return view('admin.chat-answers.index', [
            'answers'    => ChatAnswer::query()->orderByDesc('priority')->orderBy('category')->get(),
            'categories' => AiChatbotService::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ChatAnswer::create($this->validated($request));
        Cache::forget('chat:curated-answers');

        return back()->with('status', 'Odpověď byla přidána do znalostní báze chatu.');
    }

    public function update(Request $request, ChatAnswer $chatAnswer): RedirectResponse
    {
        $chatAnswer->update($this->validated($request));
        Cache::forget('chat:curated-answers');

        return back()->with('status', 'Odpověď byla aktualizována.');
    }

    public function destroy(ChatAnswer $chatAnswer): RedirectResponse
    {
        $chatAnswer->delete();
        Cache::forget('chat:curated-answers');

        return back()->with('status', 'Odpověď byla odstraněna.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $categories = array_column(AiChatbotService::CATEGORIES, 'key');

        $data = $request->validate([
            'category'   => ['required', 'string', 'in:' . implode(',', $categories)],
            'question'   => ['required', 'string', 'max:255'],
            'keywords'   => ['required', 'string', 'max:1000'],
            'answer'     => ['required', 'string', 'max:4000'],
            'priority'   => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active'  => ['boolean'],
            'link_label' => ['nullable', 'string', 'max:100'],
            'link_url'   => ['nullable', 'string', 'max:500'],
        ]);

        $links = [];

        if (! empty($data['link_label']) && ! empty($data['link_url'])) {
            $links[] = ['label' => $data['link_label'], 'url' => $data['link_url']];
        }

        return [
            'category'  => $data['category'],
            'question'  => $data['question'],
            'keywords'  => $data['keywords'],
            'answer'    => $data['answer'],
            'links'     => $links === [] ? null : $links,
            'priority'  => (int) ($data['priority'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
