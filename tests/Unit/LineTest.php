<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Exceptions\InvalidLine;

it('writes amounts without a thousands separator', function (float $amount, string $expected) {
    expect(Line::create('x', priceExcl: $amount)->toArray()['PriceExcl'])->toBe($expected);
})->with([
    [1250.5, '1250.5'],
    [1250.0, '1250'],
    [99.95, '99.95'],
    [0.0, '0'],
    [0.000125, '0.000125'],
]);

it('leaves out what is not set, so the api fills it in', function () {
    expect(Line::create(productCode: 'P001')->toArray())->toBe(['Number' => '1', 'ProductCode' => 'P001']);
});

it('writes every field and passes extra ones through', function () {
    $line = Line::create(
        description: 'Hosting',
        priceExcl: 10,
        quantity: 2.5,
        productCode: 'P001',
        unit: 'month',
        date: CarbonImmutable::parse('2026-10-09 12:00', 'Europe/Amsterdam'),
        discountPercentage: 10,
        taxPercentage: 21,
        attributes: ['Periodic' => 'm'],
    );

    expect($line->toArray())->toBe([
        'Date' => '2026-10-09',
        'Number' => '2.5',
        'NumberSuffix' => 'month',
        'ProductCode' => 'P001',
        'Description' => 'Hosting',
        'PriceExcl' => '10',
        'DiscountPercentage' => '10',
        'TaxPercentage' => '21',
        'Periodic' => 'm',
    ]);
});

it('needs a description or a product code', function (?string $description) {
    Line::create($description, priceExcl: 10);
})->with([null, ''])->throws(InvalidLine::class, 'A line needs a description or a product code.');
