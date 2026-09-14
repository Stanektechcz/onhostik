<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A shell on a hosting node, bound to one site's identity: aaPanel runs it through the panel API (`ExecShell`,
 * root, switched to the site user), ISPConfig through SSH as the site's jailed agent user. Everything the panels
 * do not expose through their APIs (restores, staging copies, git deploys, WP-CLI, exports) runs here — always with
 * a timeout, always with captured output, never interactive.
 */
interface NodeShell
{
    /**
     * @param  array{timeout?:int, cwd?:string, user?:string, env?:array<string,string>}  $options  timeout in seconds (max 900), working directory, user to switch to (aaPanel only), environment
     */
    public function run(string $command, array $options = []): ShellResult;

    /** Can commands run right now (credentials present, transport reachable)? */
    public function available(): bool;

    /** Human description for operations and diagnostics, never with secrets. */
    public function describe(): string;
}
