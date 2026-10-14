<?php

declare(strict_types=1);

namespace Onhost\Platform\Dns\Dnsbl;

use Closure;

/**
 * The smallest DNS client that answers one question: the A records of a name, from one named resolver, over UDP.
 * PHP's own `dns_get_record()` always asks the system resolver, which on a cloud VM is often a public one — exactly the
 * querier the blocklists refuse. Only A over UDP is needed (a blocklist answer is a handful of 127.x records, far below
 * 512 bytes); a truncated answer still carries the records that fitted.
 */
final class UdpDnsClient
{
    private const TYPE_A = 1;

    private const CLASS_IN = 1;

    /** @var Closure(string, string, int, float): ?string */
    private readonly Closure $transport;

    /** @param  (Closure(string $host, string $packet, int $port, float $timeout): ?string)|null  $transport  sends the packet, returns the answer or null (tests replace it) */
    public function __construct(private readonly string $host, private readonly int $port = 53, private readonly float $timeout = 2.0, ?Closure $transport = null)
    {
        $this->transport = $transport ?? self::udp(...);
    }

    public function a(string $name): DnsblAnswer
    {
        $id = random_int(0, 0xFFFF);
        $packet = self::query($id, $name);
        if ($packet === null) {
            return DnsblAnswer::failed('invalid name');
        }
        $answer = ($this->transport)($this->host, $packet, $this->port, $this->timeout);

        return $answer === null ? DnsblAnswer::failed('no answer from '.$this->host) : self::parse($answer, $id);
    }

    /** The question packet, or null when the name cannot be one (an empty label or a label over 63 bytes). */
    public static function query(int $id, string $name): ?string
    {
        $qname = '';
        foreach (explode('.', rtrim($name, '.')) as $label) {
            if ($label === '' || strlen($label) > 63) {
                return null;
            }
            $qname .= chr(strlen($label)).$label;
        }

        return pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0).$qname."\0".pack('nn', self::TYPE_A, self::CLASS_IN);
    }

    /** The A records of an answer to question `$id`. */
    public static function parse(string $data, int $id): DnsblAnswer
    {
        if (strlen($data) < 12) {
            return DnsblAnswer::failed('short answer');
        }
        $header = unpack('nid/nflags/nqd/nan', substr($data, 0, 8));
        if (! is_array($header) || $header['id'] !== $id || ($header['flags'] & 0x8000) === 0) {
            return DnsblAnswer::failed('not an answer to the question');
        }
        $rcode = $header['flags'] & 0x000F;
        if ($rcode === 3) {
            return DnsblAnswer::none(); // NXDOMAIN: not listed
        }
        if ($rcode !== 0) {
            return DnsblAnswer::failed('rcode '.$rcode);
        }
        $offset = 12;
        for ($i = 0; $i < $header['qd']; $i++) {
            $offset = self::skipName($data, $offset);
            if ($offset === null) {
                return DnsblAnswer::failed('malformed question');
            }
            $offset += 4;
        }
        $addresses = [];
        for ($i = 0; $i < $header['an']; $i++) {
            $offset = self::skipName($data, $offset);
            if ($offset === null || $offset + 10 > strlen($data)) {
                break; // truncated: keep what fitted
            }
            $rr = unpack('ntype/nclass/Nttl/nlength', substr($data, $offset, 10));
            $offset += 10;
            if (! is_array($rr) || $offset + $rr['length'] > strlen($data)) {
                break;
            }
            if ($rr['type'] === self::TYPE_A && $rr['class'] === self::CLASS_IN && $rr['length'] === 4) {
                $addresses[] = (string) inet_ntop(substr($data, $offset, 4));
            }
            $offset += $rr['length'];
        }

        return DnsblAnswer::records($addresses);
    }

    /** The offset after a (possibly compressed) name, null when it runs off the packet. */
    private static function skipName(string $data, int $offset): ?int
    {
        $length = strlen($data);
        while ($offset < $length) {
            $byte = ord($data[$offset]);
            if ($byte === 0) {
                return $offset + 1;
            }
            if (($byte & 0xC0) === 0xC0) {
                return $offset + 2 <= $length ? $offset + 2 : null;
            }
            $offset += 1 + $byte;
        }

        return null;
    }

    private static function udp(string $host, string $packet, int $port, float $timeout): ?string
    {
        $target = str_contains($host, ':') ? "udp://[{$host}]:{$port}" : "udp://{$host}:{$port}";
        $socket = @stream_socket_client($target, $errno, $error, $timeout);
        if ($socket === false) {
            return null;
        }
        try {
            stream_set_timeout($socket, (int) $timeout, (int) (($timeout - (int) $timeout) * 1_000_000));
            if (@fwrite($socket, $packet) !== strlen($packet)) {
                return null;
            }
            $answer = @fread($socket, 4096);
            $meta = stream_get_meta_data($socket);

            return $answer === false || $answer === '' || ($meta['timed_out'] ?? false) ? null : $answer;
        } finally {
            fclose($socket);
        }
    }
}
