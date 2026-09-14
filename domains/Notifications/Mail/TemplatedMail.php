<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Renders a notification template body (plain text with light markdown) as HTML + text parts. */
final class TemplatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $mailSubject, public readonly string $body, public readonly string $templateKey, public readonly ?string $reference = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject, tags: [$this->templateKey], metadata: array_filter(['reference' => $this->reference]));
    }

    public function content(): Content
    {
        return new Content(htmlString: self::toHtml($this->body), text: 'mail.templated-text', with: ['body' => $this->body]);
    }

    /** Minimal, safe rendering: escape everything, then paragraphs, **bold**, [label](url) links and `- ` lists. */
    public static function toHtml(string $body): string
    {
        $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', fn ($m) => '<a href="'.$m[2].'">'.$m[1].'</a>', $escaped) ?? $escaped;
        $blocks = preg_split("/\n{2,}/", trim($escaped)) ?: [];
        $html = '';
        foreach ($blocks as $block) {
            $lines = explode("\n", $block);
            if (count($lines) > 0 && count(array_filter($lines, fn ($l) => str_starts_with(trim($l), '- '))) === count($lines)) {
                $html .= '<ul>'.implode('', array_map(fn ($l) => '<li>'.substr(trim($l), 2).'</li>', $lines)).'</ul>';
            } else {
                $html .= '<p>'.implode('<br>', $lines).'</p>';
            }
        }

        return '<!doctype html><html lang="cs"><body style="font-family:Archivo,Arial,sans-serif;font-size:15px;line-height:1.5;color:#111;max-width:640px;margin:0 auto;padding:24px">'
            .'<div style="font-weight:900;letter-spacing:.14em;font-size:15px;margin-bottom:24px">ONHOST</div>'.$html
            .'<hr style="border:0;border-top:2px solid #111;margin:32px 0 12px"><p style="font-size:12px;color:#555">ONhost · tento e-mail byl odeslán automaticky. Odpovězte na něj, pokud potřebujete pomoc — dorazí do podpory.</p></body></html>';
    }
}
