<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Data\Invoice;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Data\LineItem;
use SpitsOnline\WeFact\Data\Quote;
use SpitsOnline\WeFact\Enums\Driver;
use SpitsOnline\WeFact\Enums\InvoiceStatus;
use SpitsOnline\WeFact\Enums\QuoteStatus;
use SpitsOnline\WeFact\Enums\SubscriptionStatus;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\Exceptions\UnsupportedFeature;
use SpitsOnline\WeFact\Facades\WeFact;
use SpitsOnline\WeFact\Testing\WeFactFake;

it('fakes the configured driver unless told otherwise', function () {
    expect(WeFact::fake())->toBeInstanceOf(WeFactFake::class)
        ->and(WeFact::driver())->toBe(Driver::HOSTFACT)
        ->and(WeFact::fake(Driver::WEFACT)->driver())->toBe(Driver::WEFACT);
});

it('serves seeded debtors and applies updates to them', function () {
    $fake = WeFact::fake()->withDebtor(['CompanyName' => 'Acme']);

    $debtor = WeFact::debtors()->findByCode('DB10001');
    $debtor?->update(comment: 'Pays late');

    expect($debtor?->companyName)->toBe('Acme')
        ->and(WeFact::debtor(1)->get()->comment)->toBe('Pays late')
        ->and(WeFact::debtors()->find(2))->toBeNull();

    $fake->assertDebtorUpdated(1, fn (array $changes) => $changes === ['Comment' => 'Pays late']);
    Http::assertNothingSent();
});

it('creates debtors', function () {
    $fake = WeFact::fake();

    $debtor = WeFact::debtors()->create(companyName: 'Acme');

    expect($debtor)->id->toBe(1)->code->toBe('DB10001');
    $fake->assertDebtorCreated(fn (Debtor $debtor) => $debtor->companyName === 'Acme');
});

it('runs a quote from concept to invoice', function () {
    $fake = WeFact::fake()
        ->withDebtor(['CompanyName' => 'Acme'])
        ->withProduct(['ProductCode' => 'P001', 'ProductName' => 'Hosting', 'PriceExcl' => '10']);

    $quote = WeFact::quotes()->create(debtor: 1, lines: [Line::create('Website', priceExcl: 1000)]);
    $quote->lines()->add(Line::create(productCode: 'P001', quantity: 12));
    $quote = $quote->accept(createInvoice: true);

    $invoice = WeFact::invoices()->get(status: InvoiceStatus::CONCEPT, debtor: 1)->first();

    expect($quote->status)->toBe(QuoteStatus::INVOICED)
        ->and(WeFact::quote($quote->id)->get()->lines)->toHaveCount(2)
        ->and(WeFact::quote($quote->id)->get()->amountExcl)->toBe(1120.0)
        ->and($invoice)->toBeInstanceOf(Invoice::class)
        ->and(WeFact::invoices()->find((int) $invoice?->id)?->lines[1]->description)->toBe('Hosting');

    $fake->assertQuoteCreated(fn (Quote $quote) => $quote->debtorId === 1);
    $fake->assertQuoteLinesAdded($quote->id, fn (array $lines) => $lines[0] instanceof LineItem && $lines[0]->priceExcl === 10.0);
    $fake->assertQuoteAccepted($quote->id);
    $fake->assertInvoiceCreated(fn (Invoice $invoice) => $invoice->amountExcl === 1120.0);
});

it('removes quote lines', function () {
    $fake = WeFact::fake()->withDebtor()->withQuote(['Debtor' => 1], [Line::create('One'), Line::create('Two')]);

    $quote = WeFact::quote(1)->get();
    $quote->lines()->remove($quote->lines[0]);

    expect(WeFact::quote(1)->get()->lines)->toHaveCount(1)
        ->and(fn () => $quote->lines()->remove($quote->lines[1]))->toThrow(RequestFailed::class, 'at least one line must remain');
    $fake->assertQuoteLinesRemoved(1, fn (array $ids) => $ids === [$quote->lines[0]->id]);
});

it('refuses what the api refuses', function () {
    WeFact::fake()->withDebtor()->withQuote(['Debtor' => 1, 'Status' => '8']);

    expect(fn () => WeFact::quote(1)->accept())->toThrow(RequestFailed::class, "can't be accepted")
        ->and(fn () => WeFact::quote(9)->get())->toThrow(NotFound::class)
        ->and(fn () => WeFact::quotes()->create(debtor: 5, lines: []))->toThrow(RequestFailed::class, 'Invalid identifier for debtor')
        ->and(fn () => WeFact::request('hosting', 'list'))->toThrow(RequestFailed::class, 'Invalid action');
});

it('archives quotes the way each driver does', function () {
    $fake = WeFact::fake(Driver::WEFACT)->withDebtor()->withQuote(['Debtor' => 1])->withQuote(['Debtor' => 1, 'Status' => '3']);

    expect(fn () => WeFact::quote(1)->archive())->toThrow(RequestFailed::class, 'Only accepted, invoiced and declined quotes can be archived');

    WeFact::quote(2)->archive();

    expect(WeFact::quotes()->get()->pluck('id')->all())->toBe([1]);
    $fake->assertQuoteArchived(2);
});

it('deletes concept invoices only', function () {
    $fake = WeFact::fake()->withDebtor()->withInvoice(['Debtor' => 1])->withInvoice(['Debtor' => 1, 'Status' => '2']);

    WeFact::invoice(1)->delete();

    expect(fn () => WeFact::invoice(2)->delete())->toThrow(RequestFailed::class, 'Only concept invoices can be deleted')
        ->and(WeFact::invoices()->find(1))->toBeNull();
    $fake->assertInvoiceDeleted(1);
});

it('adds and removes invoice lines', function () {
    $fake = WeFact::fake()->withDebtor()->withInvoice(['Debtor' => 1]);

    WeFact::invoice(1)->lines()->add(Line::create('Support', priceExcl: 60, discountPercentage: 50), Line::create('Hosting', priceExcl: 10));
    [$line, $last] = WeFact::invoice(1)->get()->lines;
    WeFact::invoice(1)->lines()->remove($line);

    expect($line->amountExcl)->toBe(30.0)
        ->and($line->discountAmountExcl)->toBe(30.0)
        ->and(WeFact::invoice(1)->get()->lines)->toHaveCount(1)
        ->and(fn () => WeFact::invoice(1)->lines()->remove($last))->toThrow(RequestFailed::class, 'at least one line must remain');
    $fake->assertInvoiceLinesAdded(1, fn (array $lines) => $lines[0]->description === 'Support');
    $fake->assertInvoiceLinesRemoved(1, fn (array $ids) => $ids === [$line->id]);
});

it('filters lists like the api', function () {
    WeFact::fake()
        ->withDebtor()->withDebtor(['Identifier' => 12])
        ->withSubscription(['Debtor' => 1])
        ->withSubscription(['Debtor' => 12, 'Status' => 'terminated'])
        ->withDomain(['Debtor' => 1, 'Domain' => 'example', 'Tld' => 'nl']);

    expect(WeFact::subscriptions()->get()->pluck('debtorId')->all())->toBe([1])
        ->and(WeFact::subscriptions()->get(status: null)->count())->toBe(2)
        ->and(WeFact::subscriptions()->get(status: SubscriptionStatus::TERMINATED, debtor: 12)->count())->toBe(1)
        ->and(WeFact::subscriptions()->get(status: null, debtor: 2)->count())->toBe(0)
        ->and(WeFact::domains()->get(debtor: 1)->first()?->name)->toBe('example.nl')
        ->and(WeFact::debtors()->get(search: 'DB10012')->count())->toBe(1);
});

it('has no domains on the wefact driver', function () {
    WeFact::fake(Driver::WEFACT);

    WeFact::domains();
})->throws(UnsupportedFeature::class);

it('asserts nothing changed', function () {
    $fake = WeFact::fake()->withDebtor();

    WeFact::debtors()->get()->all();
    $fake->assertNothingChanged();

    WeFact::debtor(1)->update(city: 'Delft');
    expect(fn () => $fake->assertNothingChanged())->toThrow(AssertionFailedError::class, 'Expected no changes, but 1 were made.');
});

it('fails an assertion that does not match', function () {
    $fake = WeFact::fake()->withDebtor()->withQuote(['Debtor' => 1]);

    WeFact::quote(1)->decline();

    $fake->assertQuoteDeclined(1);
    expect(fn () => $fake->assertQuoteAccepted(1))->toThrow(AssertionFailedError::class, 'Quote 1 was not accepted.')
        ->and(fn () => $fake->assertQuoteDeclined(2))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertQuoteUpdated(1))->toThrow(AssertionFailedError::class);
});
