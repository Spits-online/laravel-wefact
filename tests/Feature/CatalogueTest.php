<?php

declare(strict_types=1);

use SpitsOnline\WeFact\Data\Domain;
use SpitsOnline\WeFact\Data\Product;
use SpitsOnline\WeFact\Data\Subscription;
use SpitsOnline\WeFact\Enums\DomainStatus;
use SpitsOnline\WeFact\Enums\Period;
use SpitsOnline\WeFact\Enums\ProductType;
use SpitsOnline\WeFact\Enums\SubscriptionStatus;
use SpitsOnline\WeFact\Facades\WeFact;

it('lists products', function () {
    fakeApi(['product.list' => apiFixture('products')]);

    $products = WeFact::products()->get(search: 'SEO')->all();

    expect($products)->toHaveCount(2)->and($products[0])->toBeInstanceOf(Product::class);
    assertCalled('product.list', ['searchfor' => 'SEO']);
});

it('finds a product by code', function () {
    fakeApi(['product.show' => apiFixture('product')]);

    expect(WeFact::products()->findByCode('P005'))
        ->id->toBe(5)
        ->code->toBe('P005')
        ->name->toBe('SEO Maandelijkse Onderhoud')
        ->type->toBe(ProductType::OTHER)
        ->priceExcl->toBe(500.0)
        ->period->toBe(Period::MONTH)
        ->unit->toBe('month')
        ->taxPercentage->toBe(21.0);

    assertCalled('product.show', ['ProductCode' => 'P005']);
});

it('returns null for a product that does not exist', function () {
    fakeApi(['product.show' => failure('product.show', ['Ongeldig kenmerk'])]);

    expect(WeFact::products()->find(404))->toBeNull();
});

it('lists the active subscriptions by default', function () {
    fakeApi(['subscription.list' => apiFixture('subscriptions')]);

    $subscription = WeFact::subscriptions()->get()->first();

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscription)
        ->id->toBe(3)
        ->debtorId->toBe(4)
        ->debtorCode->toBe('DB10003')
        ->productCode->toBe('P005')
        ->quantity->toBe(1.0)
        ->unit->toBe('month')
        ->priceExcl->toBe(500.0)
        ->period->toBe(Period::MONTH)
        ->status->toBe(SubscriptionStatus::ACTIVE)
        ->type->toBe(ProductType::OTHER)
        ->terminationDate->toBeNull()
        ->and($subscription?->nextDate?->format('Y-m-d'))->toBe('2026-10-26');

    assertCalled('subscription.list', ['status' => 'active']);
});

it('lists every subscription of a debtor', function () {
    fakeApi(['subscription.list' => apiFixture('subscriptions')]);

    WeFact::subscriptions()->get(status: null, debtor: 4)->all();

    assertCalled('subscription.list', ['status' => '', 'searchat' => 'Debtor', 'searchfor' => '4']);
});

it('lists domains with their full name', function () {
    fakeApi(['domain.list' => apiFixture('domains')]);

    $domains = WeFact::domains()->get(status: DomainStatus::ACTIVE)->all();

    expect($domains[0])->toBeInstanceOf(Domain::class)
        ->and($domains[0]->name)->toBe("{$domains[0]->sld}.{$domains[0]->tld}")
        ->and($domains[0]->autoRenew)->toBeNull();

    assertCalled('domain.list', ['status' => '4']);
});

it('finds a domain', function () {
    fakeApi(['domain.show' => apiFixture('domain')]);

    expect(WeFact::domains()->find(2))
        ->name->toBe('test13.com')
        ->debtorId->toBe(2)
        ->status->toBe(DomainStatus::WAITING)
        ->registrar->toBe('TestProvider')
        ->autoRenew->toBeTrue()
        ->registrationDate->toBeNull();
});
