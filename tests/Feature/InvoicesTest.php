<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SpitsOnline\WeFact\Data\Invoice;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Enums\InvoiceStatus;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\Facades\WeFact;

it('gets an invoice with its lines', function () {
    fakeApi(['invoice.show' => apiFixture('invoice')]);

    $invoice = WeFact::invoice(1)->get();

    expect($invoice)
        ->id->toBe(1)
        ->code->toBe('F0001')
        ->debtorId->toBe(2)
        ->status->toBe(InvoiceStatus::SENT)
        ->amountExcl->toBe(750.0)
        ->amountIncl->toBe(907.5)
        ->amountOpen->toBe(907.5)
        ->and($invoice->payBefore?->format('Y-m-d'))->toBe('2023-05-05')
        ->and($invoice->lines)->toHaveCount(2)
        ->and($invoice->lines[0])
        ->quantity->toBe(4.0)
        ->unit->toBe('hour')
        ->priceExcl->toBe(25.0)
        ->productCode->toBe('P001')
        ->amountExcl->toBe(100.0);
});

it('lists a debtor\'s concept invoices', function () {
    fakeApi(['invoice.list' => apiFixture('invoices')]);

    $invoices = WeFact::invoices()->get(status: InvoiceStatus::CONCEPT, debtor: 16)->all();

    expect($invoices[0])->toBeInstanceOf(Invoice::class)
        ->and($invoices[0]->lines)->toBeNull()
        ->and($invoices[0]->amountOpen)->toBe((float) apiFixture('invoices')['invoices'][0]['AmountOpen']);

    assertCalled('invoice.list', ['status' => '0', 'searchat' => 'Debtor', 'searchfor' => '16']);
});

it('reads what is open from wefact\'s amount outstanding', function () {
    $invoices = apiFixture('invoices');
    unset($invoices['invoices'][0]['AmountOpen']);
    $invoices['invoices'][0]['AmountOutstanding'] = '12.10';
    fakeApi(['invoice.list' => $invoices]);

    expect(WeFact::invoices()->get()->first()?->amountOpen)->toBe(12.1);
});

it('finds an invoice by code, or returns null', function () {
    fakeApi(['invoice.show' => [apiFixture('invoice'), failure('invoice.show', ['Ongeldig kenmerk'])]]);

    expect(WeFact::invoices()->findByCode('F0001')?->id)->toBe(1)
        ->and(WeFact::invoices()->find(404))->toBeNull();

    assertCalled('invoice.show', ['InvoiceCode' => 'F0001']);
});

it('creates a concept invoice', function () {
    fakeApi(['invoice.add' => success('invoice.add', ['invoice' => apiFixture('invoice')['invoice']])]);

    WeFact::invoices()->create(debtor: 16, lines: [
        Line::create('Programming', priceExcl: 95, quantity: 1.5, unit: 'hour'),
        Line::create('Travel', priceExcl: 0.23, quantity: 42, discountPercentage: 100),
    ]);

    assertCalled('invoice.add', ['Debtor' => '16', 'InvoiceLines' => [
        ['Number' => '1.5', 'NumberSuffix' => 'hour', 'Description' => 'Programming', 'PriceExcl' => '95'],
        ['Number' => '42', 'Description' => 'Travel', 'PriceExcl' => '0.23', 'DiscountPercentage' => '100'],
    ]]);
});

it('adds lines to an invoice', function () {
    fakeApi(['invoiceline.add' => success('invoiceline.add', ['invoice' => apiFixture('invoice')['invoice']])]);

    WeFact::invoice(16)->lines()->add(Line::create('Support', priceExcl: 60));

    assertCalled('invoiceline.add', ['Identifier' => '16', 'InvoiceLines' => [['Number' => '1', 'Description' => 'Support', 'PriceExcl' => '60']]]);
});

it('removes lines from an invoice by id', function () {
    fakeApi(['invoiceline.delete' => success('invoiceline.delete', ['invoice' => apiFixture('invoice')['invoice']])]);

    WeFact::invoice(16)->lines()->remove(196, 197);

    assertCalled('invoiceline.delete', ['Identifier' => '16', 'InvoiceLines' => [['Identifier' => '196'], ['Identifier' => '197']]]);
});

it('deletes an invoice', function () {
    fakeApi(['invoice.delete' => success('invoice.delete')]);

    WeFact::invoice(16)->delete();

    assertCalled('invoice.delete', ['Identifier' => '16']);
});

it('throws when the api refuses to delete an invoice', function () {
    fakeApi(['invoice.delete' => failure('invoice.delete', ['Factuur kan niet verwijderd worden'])]);

    WeFact::invoice(1)->delete();
})->throws(RequestFailed::class, 'HostFact refused `invoice.delete`: Factuur kan niet verwijderd worden');

it('lets a fetched invoice act on itself', function () {
    fakeApi([
        'invoice.show' => apiFixture('invoice'),
        'invoiceline.add' => success('invoiceline.add', ['invoice' => apiFixture('invoice')['invoice']]),
        'invoice.delete' => success('invoice.delete'),
    ]);

    $invoice = WeFact::invoices()->find(1);
    $invoice?->lines()->add(Line::create('Extra'));
    $invoice?->delete();

    assertCalled('invoiceline.add', ['Identifier' => '1']);
    assertCalled('invoice.delete', ['Identifier' => '1']);
    Http::assertSentCount(3);
});
