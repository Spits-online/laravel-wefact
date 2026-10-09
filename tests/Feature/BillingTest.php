<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SpitsOnline\WeFact\Data\Invoice;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Facades\WeFact;

it('bills lines to the debtor\'s concept invoice', function () {
    fakeApi([
        'invoice.list' => apiFixture('invoices'),
        'invoiceline.add' => success('invoiceline.add', ['invoice' => apiFixture('invoice')['invoice']]),
    ]);

    $invoice = WeFact::debtor(2)->bill(Line::create('Programming', priceExcl: 95, quantity: 1.5));

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->lines)->toHaveCount(2);

    $concept = apiFixture('invoices')['invoices'][0]['Identifier'];
    assertCalled('invoice.list', ['status' => '0', 'searchat' => 'Debtor', 'searchfor' => '2']);
    assertCalled('invoiceline.add', ['Identifier' => $concept, 'InvoiceLines' => [['Number' => '1.5', 'Description' => 'Programming', 'PriceExcl' => '95']]]);
    Http::assertSentCount(2);
});

it('creates a concept invoice when the debtor has none', function () {
    fakeApi([
        'invoice.list' => success('invoice.list', ['totalresults' => 0]),
        'invoice.add' => success('invoice.add', ['invoice' => apiFixture('invoice')['invoice']]),
    ]);

    WeFact::debtor(2)->bill(Line::create('Support'), Line::create('Hosting'));

    assertCalled('invoice.add', ['Debtor' => '2', 'InvoiceLines' => [
        ['Number' => '1', 'Description' => 'Support'],
        ['Number' => '1', 'Description' => 'Hosting'],
    ]]);
    Http::assertSentCount(2);
});

it('lets a fetched debtor bill itself', function () {
    $fake = WeFact::fake()->withDebtor();

    $debtor = WeFact::debtor(1)->get();
    $first = $debtor->bill(Line::create('One'));
    $second = $debtor->bill(Line::create('Two'));

    expect($second->id)->toBe($first->id)
        ->and($second->lines)->toHaveCount(2);

    $fake->assertInvoiceCreated();
    $fake->assertInvoiceLinesAdded($first->id, fn (array $lines) => $lines[0]->description === 'Two');
});

it('replaces every quote line, adding the new lines before removing the old ones', function () {
    $quote = apiFixture('quote');
    fakeApi([
        'pricequote.show' => $quote,
        'pricequoteline.add' => success('pricequoteline.add', ['pricequote' => $quote['pricequote']]),
        'pricequoteline.delete' => success('pricequoteline.delete', ['pricequote' => $quote['pricequote']]),
    ]);

    WeFact::quote(51)->lines()->replace(Line::create('New'));

    $calls = Http::recorded()->map(fn ($pair) => "{$pair[0]['controller']}.{$pair[0]['action']}")->all();

    expect($calls)->toBe(['pricequote.show', 'pricequoteline.add', 'pricequoteline.delete']);
    assertCalled('pricequoteline.delete', ['PriceQuoteLines' => [['Identifier' => '662'], ['Identifier' => '663']]]);
});

it('replaces the lines of a quote or invoice in the fake', function () {
    WeFact::fake()
        ->withDebtor()
        ->withQuote(['Debtor' => 1], [Line::create('Old one'), Line::create('Old two')])
        ->withInvoice(['Debtor' => 1], [Line::create('Old')]);

    WeFact::quote(1)->lines()->replace(Line::create('New one'), Line::create('New two'));
    WeFact::invoice(1)->lines()->replace(Line::create('New'));

    expect(collect(WeFact::quote(1)->get()->lines)->pluck('description')->all())->toBe(['New one', 'New two'])
        ->and(collect(WeFact::invoice(1)->get()->lines)->pluck('description')->all())->toBe(['New']);
});

it('reads the billing address', function () {
    $debtor = apiFixture('debtor');
    $debtor['debtor']['InvoiceAddress'] = 'Postbus 12';
    fakeApi(['debtor.show' => $debtor]);

    expect(WeFact::debtor(4)->get()->invoiceAddress)->toBe('Postbus 12');
});
