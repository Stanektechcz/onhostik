<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\TicketAttachmentController;
use App\Models\TicketAttachment;

test('ticket attachment controller exists', function (): void {
    expect(class_exists(TicketAttachmentController::class))->toBeTrue();
});

test('ticket attachment model exists', function (): void {
    expect(class_exists(TicketAttachment::class))->toBeTrue();
});

test('ticket attachment table exists', function (): void {
    expect(\Schema::hasTable('ticket_attachments'))->toBeTrue();
});

test('ticket attachment store route exists', function (): void {
    expect(Route::has('panel.support.attachments.store'))->toBeTrue();
});

test('ticket attachment destroy route exists', function (): void {
    expect(Route::has('panel.support.attachments.destroy'))->toBeTrue();
});
