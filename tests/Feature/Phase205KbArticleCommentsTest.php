<?php

use App\Models\KbArticle;
use App\Models\KbArticleComment;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view pending KB comments', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.kb-comments.index'))
        ->assertOk()
        ->assertViewHas('pending');
});

it('admin can approve a pending comment', function (): void {
    $article = KbArticle::create([
        'title'        => 'Test Article',
        'slug'         => 'test-article',
        'is_published' => true,
    ]);
    $comment = KbArticleComment::create([
        'kb_article_id' => $article->id,
        'user_id'       => adminUser()->id,
        'body'          => 'Useful article!',
        'is_approved'   => false,
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.kb-comments.approve', $comment))
        ->assertRedirect();

    expect($comment->fresh()->is_approved)->toBeTrue();
    expect($comment->fresh()->approved_at)->not()->toBeNull();
});

it('admin can delete a comment', function (): void {
    $article = KbArticle::create([
        'title'        => 'Delete Test',
        'slug'         => 'delete-test',
        'is_published' => true,
    ]);
    $comment = KbArticleComment::create([
        'kb_article_id' => $article->id,
        'user_id'       => adminUser()->id,
        'body'          => 'Spam comment',
        'is_approved'   => false,
    ]);

    $this->actingAs(adminUser())
        ->delete(route('admin.kb-comments.destroy', $comment))
        ->assertRedirect();

    expect(KbArticleComment::find($comment->id))->toBeNull();
});

it('customer can post a comment and it is pending by default', function (): void {
    $article = KbArticle::create([
        'title'        => 'Customer Comment',
        'slug'         => 'customer-comment',
        'is_published' => true,
    ]);
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.kb.comments.store', $article), [
            'body' => 'Great article, very helpful!',
        ])
        ->assertRedirect();

    $comment = KbArticleComment::where('kb_article_id', $article->id)->first();
    expect($comment)->not()->toBeNull();
    expect($comment->is_approved)->toBeFalse();
});

it('guest cannot post a KB article comment', function (): void {
    $article = KbArticle::create([
        'title'        => 'Guest Comment',
        'slug'         => 'guest-comment',
        'is_published' => true,
    ]);

    $this->post(route('panel.kb.comments.store', $article), [
        'body' => 'Some comment',
    ])
    ->assertRedirect();

    expect(KbArticleComment::count())->toBe(0);
});
