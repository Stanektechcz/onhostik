<?php

declare(strict_types=1);

use App\Domains\Communication\Models\SystemAnnouncement;

it('isActive returns false when scheduled_at is in future', function (): void {
    adminUser();
    $announcement = SystemAnnouncement::create([
        'title'        => 'Budoucí oznámení',
        'body'         => 'Toto bude viditelné brzy.',
        'type'         => 'info',
        'is_published' => true,
        'scheduled_at' => now()->addHour(),
        'created_by'   => \App\Models\User::first()->id,
    ]);

    expect($announcement->isActive())->toBeFalse();
});

it('isActive returns true when scheduled_at is in past', function (): void {
    adminUser();
    $announcement = SystemAnnouncement::create([
        'title'        => 'Minulé oznámení',
        'body'         => 'Toto je již aktivní.',
        'type'         => 'info',
        'is_published' => true,
        'scheduled_at' => now()->subHour(),
        'created_by'   => \App\Models\User::first()->id,
    ]);

    expect($announcement->isActive())->toBeTrue();
});

it('isActive returns true when scheduled_at is null', function (): void {
    adminUser();
    $announcement = SystemAnnouncement::create([
        'title'        => 'Bez plánu',
        'body'         => 'Okamžitě publikováno.',
        'type'         => 'info',
        'is_published' => true,
        'scheduled_at' => null,
        'created_by'   => \App\Models\User::first()->id,
    ]);

    expect($announcement->isActive())->toBeTrue();
});

it('publish-scheduled command publishes due announcements', function (): void {
    $admin = adminUser();
    $announcement = SystemAnnouncement::create([
        'title'        => 'Naplánované oznámení',
        'body'         => 'Čas přišel.',
        'type'         => 'info',
        'is_published' => false,
        'scheduled_at' => now()->subMinutes(5),
        'created_by'   => $admin->id,
    ]);

    $this->artisan('announcements:publish-scheduled')->assertSuccessful();

    expect($announcement->fresh()->is_published)->toBeTrue();
});

it('publish-scheduled command skips future announcements', function (): void {
    $admin = adminUser();
    $announcement = SystemAnnouncement::create([
        'title'        => 'Budoucí oznámení',
        'body'         => 'Ještě ne.',
        'type'         => 'info',
        'is_published' => false,
        'scheduled_at' => now()->addHour(),
        'created_by'   => $admin->id,
    ]);

    $this->artisan('announcements:publish-scheduled')->assertSuccessful();

    expect($announcement->fresh()->is_published)->toBeFalse();
});
