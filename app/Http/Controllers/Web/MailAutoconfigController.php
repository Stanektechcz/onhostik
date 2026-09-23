<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Onhost\Domain\Services\Mail\MailSettings;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * Automatic configuration of mail clients. A customer types their address and their password; Thunderbird fetches
 * `autoconfig.<domain>/mail/config-v1.1.xml` and Outlook posts to `autodiscover.<domain>/autodiscover/autodiscover.xml`,
 * and the client sets itself up. Without it every new mailbox is a support conversation about ports.
 *
 * Both answers are given **only for a domain the platform actually hosts mail for** — the records are public and
 * anybody can ask, so an unknown name gets a plain 404 rather than an invitation to point a client at our servers.
 */
final class MailAutoconfigController extends Controller
{
    /** Mozilla autoconfig (Thunderbird and everything that copied it). */
    public function mozilla(Request $request): Response
    {
        $domain = self::domainOf((string) $request->query('emailaddress', ''), $request);
        if ($domain === null) {
            return response('', 404);
        }
        $settings = MailSettings::of($domain['service']);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<clientConfig version="1.1">'
            .'<emailProvider id="'.e($domain['name']).'">'
            .'<domain>'.e($domain['name']).'</domain>'
            .'<displayName>'.e((string) config('onhost.brand', 'ONhost')).'</displayName>'
            .'<displayShortName>'.e((string) config('onhost.brand', 'ONhost')).'</displayShortName>'
            .self::server('incomingServer', 'imap', $settings['imap'])
            .self::server('incomingServer', 'pop3', $settings['pop3'])
            .self::server('outgoingServer', 'smtp', $settings['smtp'])
            .'</emailProvider>'
            .'</clientConfig>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Microsoft autodiscover: the request body carries the address, the answer is the same servers. */
    public function microsoft(Request $request): Response
    {
        $body = (string) $request->getContent();
        $address = preg_match('~<EMailAddress>\s*([^<\s]+)\s*</EMailAddress>~i', $body, $m) ? $m[1] : (string) $request->query('emailaddress', '');
        $domain = self::domainOf($address, $request);
        if ($domain === null) {
            return response('', 404);
        }
        $settings = MailSettings::of($domain['service']);
        $xml = '<?xml version="1.0" encoding="utf-8"?>'."\n"
            .'<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">'
            .'<Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a">'
            .'<Account><AccountType>email</AccountType><Action>settings</Action>'
            .self::outlookProtocol('IMAP', $settings['imap'], 'on')
            .self::outlookProtocol('SMTP', $settings['smtp'], 'TLS')
            .'</Account></Response></Autodiscover>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * The domain of the address, if the platform hosts mail for it — and the service that does.
     *
     * @return array{name:string, service:Service}|null
     */
    private static function domainOf(string $address, Request $request): ?array
    {
        $name = mb_strtolower(trim(str_contains($address, '@') ? (string) mb_substr($address, (int) mb_strpos($address, '@') + 1) : $address));
        if ($name === '' && $request->getHost() !== '') {
            $name = mb_strtolower(preg_replace('/^(autoconfig|autodiscover)\./', '', $request->getHost()) ?? ''); // asked without an address
        }
        if ($name === '' || ! preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/', $name)) {
            return null;
        }
        $row = MailDomain::query()->where('domain', $name)->where('state', 'active')->first();
        $service = $row === null ? null : Service::query()->whereKey($row->service_id)->whereNotIn('state', [ServiceStateMachine::TERMINATED])->first();

        return $service === null ? null : ['name' => $name, 'service' => $service];
    }

    /** @param array<string,mixed> $server */
    private static function server(string $element, string $type, array $server): string
    {
        return '<'.$element.' type="'.$type.'">'
            .'<hostname>'.e((string) $server['host']).'</hostname>'
            .'<port>'.(int) $server['port'].'</port>'
            .'<socketType>'.($server['security'] === 'STARTTLS' ? 'STARTTLS' : 'SSL').'</socketType>'
            .'<authentication>password-cleartext</authentication>'
            .'<username>%EMAILADDRESS%</username>'
            .'</'.$element.'>';
    }

    /** @param array<string,mixed> $server */
    private static function outlookProtocol(string $type, array $server, string $ssl): string
    {
        return '<Protocol><Type>'.$type.'</Type>'
            .'<Server>'.e((string) $server['host']).'</Server>'
            .'<Port>'.(int) $server['port'].'</Port>'
            .'<LoginName>%EMAILADDRESS%</LoginName>'
            .'<SSL>'.$ssl.'</SSL>'
            .'<SPA>off</SPA><AuthRequired>on</AuthRequired>'
            .'</Protocol>';
    }
}
