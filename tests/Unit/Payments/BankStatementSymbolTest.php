<?php

declare(strict_types=1);

use Onhost\Domain\Payments\Statements\BankStatementFile;
use Onhost\Platform\Errors\DomainError;

/** @param list<string> $tx the children of each TxDtls; $entryExtra goes into the Ntry itself */
function bssCamt(array $tx, string $entryExtra = '', bool $withDetails = true): string
{
    $details = $withDetails ? '<NtryDtls>'.implode('', array_map(fn (string $t) => "<TxDtls>{$t}</TxDtls>", $tx)).'</NtryDtls>' : '';

    return '<?xml version="1.0" encoding="UTF-8"?><Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02"><BkToCstmrStmt><Stmt><Id>S1</Id>'
        .'<Acct><Id><IBAN>CZ6508000000002000145399</IBAN></Id></Acct><Ntry><Amt Ccy="CZK">100.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><Sts>BOOK</Sts>'
        .'<BookgDt><Dt>2026-09-12</Dt></BookgDt><AcctSvcrRef>R1</AcctSvcrRef>'.$entryExtra.$details.'</Ntry></Stmt></BkToCstmrStmt></Document>';
}

function bssVs(string $xml): ?string
{
    return BankStatementFile::parse($xml)['lines'][0]['variable_symbol'];
}

function bssUstrd(string $text): ?string
{
    return bssVs(bssCamt(['<RmtInf><Ustrd>'.htmlspecialchars($text, ENT_XML1).'</Ustrd></RmtInf>']));
}

function bssGpcRow(string $vs, string $message = ''): string
{
    $row = '075'.'0000002000145399'.'0000001234567890'.'0000000007001'.'000000150000'.'2'.str_pad($vs, 10, '0', STR_PAD_LEFT)
        .'0008000308'.'0000000000'.'120926'.str_pad('Jan Novak', 20).'0'.'0203'.'120926';
    $header = str_pad('074'.'0000002000145399'.'ONHOST S.R.O.       '.'110926', 128, '0');

    return $header."\r\n".$row."\r\n".($message !== '' ? '078'.str_pad($message, 125)."\r\n" : '');
}

it('reads a marked variable symbol from every free-text form Czech payers type', function (string $text, ?string $vs) {
    expect(bssUstrd($text))->toBe($vs);
})->with([
    'classic' => ['VS 123', '123'],
    'colon' => ['VS: 123', '123'],
    'colon tight' => ['VS:123', '123'],
    'lower case' => ['faktura vs 123 dik', '123'],
    'slash form' => ['VS/123', '123'],
    'CBA form' => ['/VS/123/SS/456/KS/0308', '123'],
    'CBA form zeros' => ['/VS/0000000123/SS/0/KS/0', '123'],
    'dot' => ['VS.123', '123'],
    'equals' => ['VS=123', '123'],
    'abbreviation' => ['v.s. 123', '123'],
    'abbreviation tight' => ['V.S.123', '123'],
    'var symbol' => ['var. symbol 123', '123'],
    'var symbol no dot' => ['var symbol 123', '123'],
    'full word' => ["variabiln\u{00ED} symbol 123", '123'],
    'full word no diacritics' => ['Variabilni symbol 123', '123'],
    'ten digits' => ['VS 1234567890', '1234567890'],
    'leading zeros stripped' => ['VS 0000000045', '45'],
    'after other symbols' => ['KS 0308 VS 777 SS 5', '777'],
    'eleven digits is not a symbol' => ['VS 12345678901', null],
    'glued to letters before' => ['AVS 123', null],
    'glued to letters after' => ['VS 123abc', null],
    'all zeros means none' => ['VS 0000000000', null],
    'bare number is not a symbol' => ['platba 123', null],
    'order number without a marker' => ['objednavka 2026001 castka 100', null],
    'marker without number' => ['VS neuvedeno', null],
    'two different symbols are ambiguous' => ['VS 111 a VS 222', null],
    'same symbol twice' => ['VS 111 a VS 0111', '111'],
]);

it('keeps the priority: structured reference, EndToEndId, free text, additional info', function () {
    $ref = '<RmtInf><Strd><CdtrRefInf><Ref>0000000111</Ref></CdtrRefInf></Strd><Ustrd>VS 999</Ustrd></RmtInf>';
    expect(bssVs(bssCamt(['<Refs><EndToEndId>/VS/222</EndToEndId></Refs>'.$ref])))->toBe('111');
    expect(bssVs(bssCamt(['<Refs><EndToEndId>/VS/222</EndToEndId></Refs><RmtInf><Ustrd>VS 999</Ustrd></RmtInf>'])))->toBe('222');
    expect(bssVs(bssCamt(['<RmtInf><Ustrd>VS 999</Ustrd></RmtInf><AddtlTxInf>VS 555</AddtlTxInf>'])))->toBe('999');
    expect(bssVs(bssCamt(['<AddtlTxInf>platba VS 555</AddtlTxInf>'], '<AddtlNtryInf>VS 888</AddtlNtryInf>')))->toBe('555');
    expect(bssVs(bssCamt(['<Refs/>'], '<AddtlNtryInf>VS 888</AddtlNtryInf>')))->toBe('888');
});

it('reads structured creditor references: bare, marked, several blocks, never an RF reference', function () {
    $strd = fn (string $ref) => "<Strd><CdtrRefInf><Ref>{$ref}</Ref></CdtrRefInf></Strd>";
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('2026001').'</RmtInf>'])))->toBe('2026001');
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('VS 77').'</RmtInf>'])))->toBe('77');
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('/VS/88/SS/1').'</RmtInf>'])))->toBe('88');
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('').$strd('99').'</RmtInf>'])))->toBe('99');
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('5').$strd('0005').'</RmtInf>'])))->toBe('5');
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('5').$strd('6').'</RmtInf>'])))->toBeNull();
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('RF18539007547034').'</RmtInf>'])))->toBeNull();
    expect(bssVs(bssCamt(['<RmtInf>'.$strd('RF18 5390 0754 7034').'<Ustrd>VS 31</Ustrd></RmtInf>'])))->toBe('31');
});

it('reads a marked symbol from Strd AddtlRmtInf', function () {
    expect(bssVs(bssCamt(['<RmtInf><Strd><AddtlRmtInf>VS 4242</AddtlRmtInf></Strd></RmtInf>'])))->toBe('4242');
});

it('does not take a plain-digit EndToEndId as a variable symbol', function () {
    expect(bssVs(bssCamt(['<Refs><EndToEndId>123456</EndToEndId></Refs>'])))->toBeNull();
    expect(bssVs(bssCamt(['<Refs><EndToEndId>NOTPROVIDED</EndToEndId></Refs>'])))->toBeNull();
    expect(bssVs(bssCamt(['<Refs><EndToEndId>VS:55</EndToEndId></Refs>'])))->toBe('55');
});

it('reads an entry with no transaction details from its own additional information', function () {
    expect(bssVs(bssCamt([], '<AddtlNtryInf>Prichozi platba, VS: 0042</AddtlNtryInf>', false)))->toBe('42');
    expect(bssVs(bssCamt([], '<AddtlNtryInf>Prichozi platba</AddtlNtryInf>', false)))->toBeNull();
});

it('does not spread a batch entry note over its transactions', function () {
    $two = bssCamt(['<Amt Ccy="CZK">40.00</Amt><RmtInf><Ustrd>VS 1</Ustrd></RmtInf>', '<Amt Ccy="CZK">60.00</Amt>'], '<AddtlNtryInf>VS 9</AddtlNtryInf>');
    expect(array_column(BankStatementFile::parse($two)['lines'], 'variable_symbol'))->toBe(['1', null]);
});

it('still refuses a DOCTYPE', function () {
    $xml = str_replace('<Document', '<!DOCTYPE d [<!ENTITY x "VS 1">]><Document', bssCamt(['<RmtInf><Ustrd>&x;</Ustrd></RmtInf>']));
    BankStatementFile::parse($xml);
})->throws(DomainError::class);

it('GPC: the fixed field wins, a marked symbol in the message fills an empty or zero field', function () {
    expect(BankStatementFile::parse(bssGpcRow('555', 'VS 999'))['lines'][0]['variable_symbol'])->toBe('555');
    expect(BankStatementFile::parse(bssGpcRow('', 'Platba faktura VS 0999'))['lines'][0]['variable_symbol'])->toBe('999');
    expect(BankStatementFile::parse(bssGpcRow('0', '/VS/321/SS/1'))['lines'][0]['variable_symbol'])->toBe('321');
    expect(BankStatementFile::parse(bssGpcRow('', 'Dobiti kreditu'))['lines'][0]['variable_symbol'])->toBeNull();
    expect(BankStatementFile::parse(bssGpcRow(''))['lines'][0]['variable_symbol'])->toBeNull();
    expect(BankStatementFile::parse(bssGpcRow('', 'VS 1 a VS 2'))['lines'][0]['variable_symbol'])->toBeNull();
});

it('GPC: the external id does not change when the symbol comes from the message', function () {
    $with = BankStatementFile::parse(bssGpcRow('', 'VS 5'))['lines'][0]['external_id'];
    $without = BankStatementFile::parse(bssGpcRow('', 'nic'))['lines'][0]['external_id'];
    expect($with)->toBe($without);
});

it('camt: a line without a bank reference keeps its external id wherever (or whether) a symbol is found', function () {
    $idOf = fn (string $details) => BankStatementFile::parse(str_replace('<AcctSvcrRef>R1</AcctSvcrRef>', '', bssCamt([$details])))['lines'][0];
    $text = $idOf('<RmtInf><Ustrd>platba za hosting</Ustrd></RmtInf>');
    $withEndToEnd = $idOf('<Refs><EndToEndId>/VS/42</EndToEndId></Refs><RmtInf><Ustrd>platba za hosting</Ustrd></RmtInf>');
    $withStrd = $idOf('<RmtInf><Strd><CdtrRefInf><Ref>77</Ref></CdtrRefInf></Strd><Ustrd>platba za hosting</Ustrd></RmtInf>');

    expect($text['variable_symbol'])->toBeNull()->and($withEndToEnd['variable_symbol'])->toBe('42')->and($withStrd['variable_symbol'])->toBe('77');
    expect($text['external_id'])->toStartWith('cs:camt:h:')->and($withEndToEnd['external_id'])->toBe($text['external_id'])->and($withStrd['external_id'])->toBe($text['external_id']);
});

it('camt: a line with a bank reference keeps the reference id', function () {
    expect(BankStatementFile::parse(bssCamt(['<RmtInf><Ustrd>VS 5</Ustrd></RmtInf>']))['lines'][0]['external_id'])->toBe('cs:camt:CZ6508000000002000145399:R1');
});
