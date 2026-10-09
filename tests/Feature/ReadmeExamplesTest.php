<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Enums\DomainStatus;
use SpitsOnline\WeFact\Enums\InvoiceStatus;
use SpitsOnline\WeFact\Enums\ProductType;
use SpitsOnline\WeFact\Enums\QuoteStatus;
use SpitsOnline\WeFact\Enums\SubscriptionStatus;
use SpitsOnline\WeFact\Facades\WeFact;

// The README's examples, run against the fake, so a README that stops working fails the build.

beforeEach(function () {
    WeFact::fake()
        ->withDebtor(['Identifier' => 12, 'CompanyName' => 'Acme'])
        ->withProduct(['ProductCode' => 'HOSTING', 'PriceExcl' => '15'])
        ->withProduct(['ProductCode' => 'P001', 'PriceExcl' => '95'])
        ->withQuote(['Identifier' => 51, 'Debtor' => 12], [Line::create('Website')])
        ->withSubscription(['Debtor' => 12])
        ->withDomain(['Identifier' => 2, 'Debtor' => 12, 'Domain' => 'example', 'Tld' => 'com']);
});

it('runs the pitch', function () {
    $quote = WeFact::quotes()->create(
        debtor: 12,
        lines: [Line::create('Website redesign', priceExcl: 4500)],
    );

    expect($quote->accept(createInvoice: true)->status)->toBe(QuoteStatus::INVOICED);
});

it('runs the debtor examples', function () {
    $debtor = WeFact::debtors()->findByCode('DB10012');
    $changed = WeFact::debtors()->get(modifiedSince: CarbonImmutable::now()->subDay());
    $created = WeFact::debtors()->create(companyName: 'Acme', emailAddress: 'billing@acme.test');
    $updated = WeFact::debtor(12)->update(comment: 'Pays late');

    expect($debtor?->companyName)->toBe('Acme')
        ->and(WeFact::debtors()->get(search: 'Acme')->count())->toBe(2)
        ->and($changed->count())->toBe(2)
        ->and($created->code)->toBe('DB10013')
        ->and($updated->comment)->toBe('Pays late');
});

it('runs the quote examples', function () {
    $quote = WeFact::quotes()->create(
        debtor: 12,
        lines: [
            Line::create('Website redesign', priceExcl: 4500),
            Line::create(productCode: 'HOSTING', quantity: 12),
        ],
        referenceNumber: 'Website 2026',
    );

    expect($quote->amountExcl)->toBe(4680.0);

    $quote = WeFact::quote(51)->update(referenceNumber: 'Website 2026, v2', status: QuoteStatus::SENT);
    $quote->lines()->add(Line::create('Extra page', priceExcl: 350), Line::create('Hosting', priceExcl: 15));
    $quote->lines()->remove($quote->lines[0]);

    $open = WeFact::quotes()->get(status: [QuoteStatus::SENT, QuoteStatus::ACCEPTED], debtor: 12);

    expect(WeFact::quote(51)->get()->lines)->toHaveCount(2)
        ->and($open->pluck('id')->all())->toBe([51])
        ->and(WeFact::quote(51)->decline()->status)->toBe(QuoteStatus::DECLINED);

    WeFact::quote(51)->archive();
    expect(WeFact::quotes()->get()->pluck('id')->all())->not->toContain(51);
});

it('runs the invoice examples', function () {
    $line = Line::create('Programming', priceExcl: 95, quantity: 1.5);

    foreach ([1, 2] as $run) {
        $draft = WeFact::invoices()->get(status: InvoiceStatus::CONCEPT, debtor: 12)->first();

        if ($draft) {
            $draft->lines()->add($line);
        } else {
            WeFact::invoices()->create(debtor: 12, lines: [$line]);
        }
    }

    $invoice = WeFact::invoices()->findByCode('[concept]0001');

    expect($invoice?->lines)->toHaveCount(2)
        ->and($invoice?->amountOpen)->toBe(344.85);

    $invoice?->lines()->remove($invoice->lines[0]);
    WeFact::invoice(1)->delete();

    expect(WeFact::invoices()->find(1))->toBeNull();
});

it('runs the catalogue examples', function () {
    $general = WeFact::products()->get()->filter(fn ($product) => $product->type === ProductType::OTHER);
    $ended = WeFact::subscriptions()->get(status: SubscriptionStatus::TERMINATED, debtor: 12);
    $domains = WeFact::domains()->get(status: DomainStatus::ACTIVE);

    expect(WeFact::products()->findByCode('P001')?->priceExcl)->toBe(95.0)
        ->and($general)->toHaveCount(2)
        ->and(WeFact::subscriptions()->get()->first()?->debtorCode)->toBe('DB10012')
        ->and($ended)->toHaveCount(0)
        ->and($domains->first()?->name)->toBe('example.com')
        ->and(WeFact::domains()->find(2)?->debtorId)->toBe(12);
});
