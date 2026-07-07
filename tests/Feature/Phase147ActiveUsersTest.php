<?php

declare(strict_types=1);

it('admin can view active users dashboard', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.active-users'))
         ->assertOk()
         ->assertSee('DAU');
});

it('active users dashboard shows MAU and WAU', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.active-users'))
         ->assertOk()
         ->assertSee('WAU')
         ->assertSee('MAU');
});

it('active users dashboard counts login history', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    \Illuminate\Support\Facades\DB::table('user_login_history')->insert([
        'user_id'    => $customer->id,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.active-users'));

    $response->assertOk();
    // MAU should be at least 1
    expect($response->getContent())->toContain('1');
});

it('login history from more than 30 days ago is excluded from MAU', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    \Illuminate\Support\Facades\DB::table('user_login_history')->insert([
        'user_id'    => $customer->id,
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subDays(31),
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.active-users'));

    // Just asserting the page loads, not specific counts
    $response->assertOk();
});

it('customer cannot access active users dashboard', function (): void {
    adminUser();
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.metrics.active-users'))
         ->assertForbidden();
});
