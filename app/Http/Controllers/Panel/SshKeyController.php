<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\SshKey;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SshKeyController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        return view('panel.account.ssh-keys', [
            'keys' => $customer->sshKeys()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'public_key' => ['required', 'string', 'max:4096'],
        ]);

        $key = trim((string) $validated['public_key']);

        if (! preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521|ssh-dss)\s/', $key)) {
            return back()
                ->withInput()
                ->withErrors(['public_key' => 'Veřejný klíč musí začínat platným typem (ssh-rsa, ssh-ed25519, ecdsa-sha2-*).' ]);
        }

        if ($customer->sshKeys()->count() >= 20) {
            return back()->withErrors(['public_key' => 'Maximálně 20 SSH klíčů na zákazníka.']);
        }

        $customer->sshKeys()->create([
            'name'        => $validated['name'],
            'public_key'  => $key,
            'fingerprint' => SshKey::computeFingerprint($key),
        ]);

        activity('account')
            ->causedBy($request->user())
            ->withProperties(['key_name' => $validated['name']])
            ->log('account.ssh_key_added');

        return back()->with('status', 'SSH klíč přidán.');
    }

    public function destroy(Request $request, SshKey $sshKey): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $sshKey->customer_id !== $customer->id, 403);

        $sshKey->delete();

        activity('account')
            ->causedBy($request->user())
            ->withProperties(['key_name' => $sshKey->name])
            ->log('account.ssh_key_removed');

        return back()->with('status', 'SSH klíč odstraněn.');
    }
}
