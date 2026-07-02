<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Serves Cuba-template admin pages that don't need dedicated controllers.
 */
class PageController extends Controller
{
    public function rolesPermission(): View
    {
        $roles = Role::with('permissions', 'users')->orderBy('name')->get();

        return view('admin.roles-permission', compact('roles'));
    }

    public function pricing(): View
    {
        $plans = \App\Domains\Products\Models\PricingPlan::query()
            ->with('product')
            ->orderBy('sort_order')
            ->get();

        return view('admin.pricing', compact('plans'));
    }

    public function reviews(): View
    {
        return view('admin.reviews');
    }

    public function mailbox(): View
    {
        $inboxCount = 0;

        return view('admin.mailbox', compact('inboxCount'));
    }

    public function kanban(): View
    {
        $tasks = \App\Models\AdminTask::with(['creator', 'assignee'])->orderBy('priority', 'desc')->get();

        return view('admin.kanban', [
            'pending'    => $tasks->where('status', 'pending')->values(),
            'inprogress' => $tasks->where('status', 'inprogress')->values(),
            'done'       => $tasks->where('status', 'done')->values(),
            'admins'     => User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get(),
        ]);
    }

    public function todo(): View
    {
        return view('admin.todo');
    }

    public function contacts(): View
    {
        $contacts = collect();

        if (class_exists(User::class)) {
            $contacts = User::with('customer')
                ->whereHas('customer')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($u) => (object)[
                    'name'     => $u->name,
                    'email'    => $u->email,
                    'customer' => $u->customer,
                ]);
        }

        return view('admin.contacts', compact('contacts'));
    }

    public function bookmarks(): View
    {
        return view('admin.bookmarks');
    }

    public function social(): View
    {
        $stats = [
            'customers' => \App\Domains\Customer\Models\Customer::count(),
            'orders'    => \App\Domains\Billing\Models\Order::count(),
            'tickets'   => 0,
            'servers'   => \App\Domains\Provisioning\Models\Server::count(),
        ];

        return view('admin.social', compact('stats'));
    }

    public function fileManager(): View
    {
        return view('admin.file-manager');
    }

    public function subscribers(): View
    {
        $subscribers = collect();

        return view('admin.subscribers', compact('subscribers'));
    }

    public function sitemap(): View
    {
        return view('admin.sitemap');
    }

    public function samplePage(): View
    {
        return view('admin.sample-page');
    }
}
