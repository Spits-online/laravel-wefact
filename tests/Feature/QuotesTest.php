<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Data\LineItem;
use SpitsOnline\WeFact\Data\Quote;
use SpitsOnline\WeFact\Enums\Period;
use SpitsOnline\WeFact\Enums\QuoteStatus;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\Facades\WeFact;
use SpitsOnline\WeFact\WeFact as WeFactClient;

/**
 * The quote fixture with another status, as accept and decline answer it.
 */
function quoteWithStatus(string $call, string $status): array
{
    $quote = apiFixture('quote')['pricequote'];
    $quote['Status'] = $status;

    return success($call, ['pricequote' => $quote]);
}

it('gets a quote with its lines', function () {
    fakeApi(['pricequote.show' => apiFixture('quote')]);

    $quote = WeFact::quote(51)->get();

    expect($quote)
        ->id->toBe(51)
        ->code->toBe('OF0013')
        ->debtorId->toBe(2)
        ->debtorCode->toBe('DB10001')
        ->companyName->toBe('Tandem')
        ->status->toBe(QuoteStatus::CONCEPT)
        ->referenceNumber->toBeNull()
        ->amountExcl->toBe(0.0)
        ->and($quote->date?->format('Y-m-d'))->toBe('2026-08-11')
        ->and($quote->expirationDate?->format('Y-m-d'))->toBe('2026-09-10')
        ->and($quote->lines)->toHaveCount(2)
        ->and($quote->lines[0])->toBeInstanceOf(LineItem::class)
        ->and($quote->lines[0])
        ->id->toBe(662)
        ->description->toBe('<p>Big one</p>')
        ->quantity->toBe(1.0)
        ->taxPercentage->toBe(21.0)
        ->period->toBeNull();

    assertCalled('pricequote.show', ['Identifier' => '51']);
});

it('lists quotes without lines, filtered by status and debtor', function () {
    fakeApi(['pricequote.list' => apiFixture('quotes')]);

    $quotes = WeFact::quotes()->get(status: [QuoteStatus::SENT, QuoteStatus::ACCEPTED], debtor: 2)->all();

    expect($quotes)->toHaveCount(2)
        ->and($quotes[0])->toBeInstanceOf(Quote::class)
        ->and($quotes[0]->lines)->toBeNull();

    assertCalled('pricequote.list', ['status' => '2|3', 'searchat' => 'Debtor', 'searchfor' => '2']);
});

it('finds a quote by code', function () {
    fakeApi(['pricequote.show' => apiFixture('quote')]);

    expect(WeFact::quotes()->findByCode('OF0013')?->id)->toBe(51);

    assertCalled('pricequote.show', ['PriceQuoteCode' => 'OF0013']);
});

it('creates a quote with lines in the format the api accepts', function () {
    fakeApi(['pricequote.add' => success('pricequote.add', ['pricequote' => apiFixture('quote')['pricequote']])]);

    $quote = WeFact::quotes()->create(
        debtor: 2,
        lines: [
            Line::create('Website', priceExcl: 1250.5, quantity: 2),
            Line::create(productCode: 'P005', date: CarbonImmutable::parse('2026-10-01 23:30', 'UTC')),
            Line::create('Terms and conditions'),
        ],
        referenceNumber: 'Website 2026',
    );

    expect($quote->code)->toBe('OF0013');

    assertCalled('pricequote.add', [
        'Debtor' => '2',
        'ReferenceNumber' => 'Website 2026',
        'PriceQuoteLines' => [
            ['Number' => '2', 'Description' => 'Website', 'PriceExcl' => '1250.5'],
            ['Date' => '2026-10-02', 'Number' => '1', 'ProductCode' => 'P005'],
            ['Number' => '1', 'Description' => 'Terms and conditions'],
        ],
    ]);
});

it('updates a quote', function () {
    fakeApi(['pricequote.edit' => success('pricequote.edit', ['pricequote' => apiFixture('quote')['pricequote']])]);

    $quote = WeFact::quote(51)->update(referenceNumber: 'v2', date: CarbonImmutable::parse('2026-10-09'), status: QuoteStatus::SENT);

    expect($quote)->toBeInstanceOf(Quote::class);
    assertCalled('pricequote.edit', ['Identifier' => '51', 'ReferenceNumber' => 'v2', 'Date' => '2026-10-09', 'Status' => '2']);
});

it('accepts a quote', function () {
    fakeApi(['pricequote.accept' => quoteWithStatus('pricequote.accept', '3')]);

    expect(WeFact::quote(51)->accept()->status)->toBe(QuoteStatus::ACCEPTED);

    Http::assertSent(fn ($request) => $request['action'] === 'accept' && ! isset($request['CreateInvoice']));
});

it('accepts a quote and turns it into an invoice', function () {
    fakeApi(['pricequote.accept' => quoteWithStatus('pricequote.accept', '4')]);

    expect(WeFact::quote(51)->accept(createInvoice: true)->status)->toBe(QuoteStatus::INVOICED);

    assertCalled('pricequote.accept', ['Identifier' => '51', 'CreateInvoice' => 'yes']);
});

it('throws when the api leaves a declined quote declined', function () {
    // HostFact answers success for this, but doesn't accept the quote.
    fakeApi(['pricequote.accept' => quoteWithStatus('pricequote.accept', '8')]);

    WeFact::quote(51)->accept();
})->throws(RequestFailed::class, "HostFact refused `pricequote.accept`: Quote OF0013 is declined and can't be accepted.");

it('declines a quote', function () {
    fakeApi(['pricequote.decline' => quoteWithStatus('pricequote.decline', '8')]);

    expect(WeFact::quote(51)->decline()->status)->toBe(QuoteStatus::DECLINED);
});

it('archives a quote through delete on hostfact', function () {
    fakeApi(['pricequote.delete' => success('pricequote.delete')]);

    WeFact::quote(51)->archive();

    assertCalled('pricequote.delete', ['Identifier' => '51']);
});

it('archives a quote through archive on wefact', function () {
    Http::fake([WEFACT => Http::response(success('pricequote.archive'))]);

    WeFactClient::fromConfig(['driver' => 'wefact', 'key' => 'k'])->quote(51)->archive();

    assertCalled('pricequote.archive', ['Identifier' => '51']);
});

it('adds and removes quote lines in one request each', function () {
    fakeApi([
        'pricequoteline.add' => success('pricequoteline.add', ['pricequote' => apiFixture('quote')['pricequote']]),
        'pricequoteline.delete' => success('pricequoteline.delete', ['pricequote' => apiFixture('quote')['pricequote']]),
        'pricequote.show' => apiFixture('quote'),
    ]);

    $quote = WeFact::quote(51)->get();
    $quote->lines()->add(Line::create('Hosting', priceExcl: 10), Line::create('Domain', priceExcl: 15));
    $quote->lines()->remove(...$quote->lines ?? []);

    assertCalled('pricequoteline.add', ['Identifier' => '51', 'PriceQuoteLines' => [
        ['Number' => '1', 'Description' => 'Hosting', 'PriceExcl' => '10'],
        ['Number' => '1', 'Description' => 'Domain', 'PriceExcl' => '15'],
    ]]);
    assertCalled('pricequoteline.delete', ['Identifier' => '51', 'PriceQuoteLines' => [['Identifier' => '662'], ['Identifier' => '663']]]);
});

it('sends nothing when adding or removing no lines', function () {
    Http::fake();

    WeFact::quote(51)->lines()->add();
    WeFact::quote(51)->lines()->remove();

    Http::assertNothingSent();
});

it('reads a periodic line', function () {
    $quote = apiFixture('quote');
    $quote['pricequote']['PriceQuoteLines'][0] = array_merge($quote['pricequote']['PriceQuoteLines'][0], [
        'Periodic' => 'j', 'Periods' => '2', 'StartPeriod' => '2027-01-01', 'EndPeriod' => '2029-01-01',
        'NoDiscountAmountExcl' => 200, 'DiscountAmountExcl' => -20, 'DiscountPercentage' => 10,
    ]);
    fakeApi(['pricequote.show' => $quote]);

    $line = WeFact::quote(51)->get()->lines[0];

    expect($line)
        ->period->toBe(Period::YEAR)
        ->periods->toBe(2)
        ->amountExcl->toBe(180.0)
        ->discountAmountExcl->toBe(20.0)
        ->discountPercentage->toBe(10.0)
        ->and($line->startDate?->format('Y-m-d'))->toBe('2027-01-01')
        ->and($line->endDate?->format('Y-m-d'))->toBe('2029-01-01');
});

it('reads a status it does not know as null', function () {
    fakeApi(['pricequote.show' => quoteWithStatus('pricequote.show', '7')]);

    $quote = WeFact::quote(51)->get();

    expect($quote->status)->toBeNull()
        ->and($quote->raw['Status'])->toBe('7');
});

it('lets a fetched quote act on itself', function () {
    fakeApi([
        'pricequote.show' => apiFixture('quote'),
        'pricequote.edit' => success('pricequote.edit', ['pricequote' => apiFixture('quote')['pricequote']]),
        'pricequote.decline' => quoteWithStatus('pricequote.decline', '8'),
        'pricequote.accept' => quoteWithStatus('pricequote.accept', '3'),
        'pricequote.delete' => success('pricequote.delete'),
    ]);

    $quote = WeFact::quotes()->find(51);
    $quote?->update(referenceNumber: 'x');
    $quote?->decline();
    $quote?->accept();
    $quote?->archive();

    foreach (['edit', 'decline', 'accept', 'delete'] as $action) {
        assertCalled("pricequote.{$action}", ['Identifier' => '51']);
    }
});

it('writes dates in the configured timezone', function () {
    Config::set('wefact.timezone', 'America/New_York');
    fakeApi(['pricequote.edit' => success('pricequote.edit', ['pricequote' => apiFixture('quote')['pricequote']])]);

    WeFact::quote(51)->update(date: CarbonImmutable::parse('2026-10-09 02:00', 'UTC'));

    assertCalled('pricequote.edit', ['Date' => '2026-10-08']);
});
