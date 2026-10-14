{{-- The shared input dialog (window.OnhostDialog, the replacement of window.prompt) for the staff Blade pages. `dialogOnly` makes the
     bridge define the dialog and the wording helpers and stop: it does not touch the shell's session or storage. A page under a
     nonce-based CSP (the service console) passes its nonce. --}}
<script @if (! empty($nonce)) nonce="{{ $nonce }}" @endif>window.ONHOST = Object.assign(window.ONHOST || {}, { dialogOnly: true });</script>
<script @if (! empty($nonce)) nonce="{{ $nonce }}" @endif src="/surfaces/api/onhost-session-bridge.js?v={{ @filemtime(base_path('apps/surfaces/api/onhost-session-bridge.js')) ?: 0 }}"></script>
