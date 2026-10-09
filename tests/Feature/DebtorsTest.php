<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use SpitsOnline\WeFact\Data\Debtor;
use SpitsOnline\WeFact\Exceptions\NotFound;
use SpitsOnline\WeFact\Facades\WeFact;
use SpitsOnline\WeFact\Resources\Debtors;

it('picks a debtor without sending a request', function () {
    Http::fake();

    WeFact::debtor(4);

    Http::assertNothingSent();
});

it('gets a debtor with every field', function () {
    fakeApi(['debtor.show' => apiFixture('debtor')]);

    $debtor = WeFact::debtor(4)->get();

    expect($debtor)
        ->id->toBe(4)
        ->code->toBe('DB10003')
        ->companyName->toBe('De Groot Stoffen')
        ->initials->toBe('Charlotte')
        ->surName->toBe('de Groot')
        ->emailAddress->toBe('info@example.com')
        ->phoneNumber->toBeNull()
        ->address->toBe('Voorbeeldstraat 1')
        ->zipCode->toBe('1234 AB')
        ->city->toBe('Rotterdam')
        ->country->toBe('NL')
        ->companyNumber->toBe('12345678')
        ->taxNumber->toBeNull()
        ->and($debtor->raw)->toHaveKey('LegalForm', 'ANDERS')
        ->and($debtor->modifiedAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($debtor->modifiedAt->timezoneName)->toBe('Europe/Amsterdam');

    assertCalled('debtor.show', ['Identifier' => '4']);
});

it('throws not found for a debtor that does not exist', function () {
    fakeApi(['debtor.show' => failure('debtor.show', ['Ongeldig kenmerk voor debiteur'])]);

    WeFact::debtor(999)->get();
})->throws(NotFound::class);

it('finds a debtor by id or code, or returns null', function () {
    fakeApi(['debtor.show' => [apiFixture('debtor'), apiFixture('debtor'), failure('debtor.show', ['Ongeldig kenmerk voor debiteur'])]]);

    expect(WeFact::debtors()->find(4)?->code)->toBe('DB10003')
        ->and(WeFact::debtors()->findByCode('DB10003')?->id)->toBe(4)
        ->and(WeFact::debtors()->find(999))->toBeNull();

    assertCalled('debtor.show', ['DebtorCode' => 'DB10003']);
});

it('lists every debtor lazily, a page at a time', function () {
    $page = fn (int $offset) => success('debtor.list', [
        'totalresults' => 3,
        'currentresults' => $offset === 0 ? 2 : 1,
        'offset' => $offset,
        'debtors' => $offset === 0 ? apiFixture('debtors')['debtors'] : [apiFixture('debtors')['debtors'][0]],
    ]);

    fakeApi(['debtor.list' => fn ($request) => $page((int) $request['offset'])]);

    // Two-row pages, so the second page is fetched.
    $debtors = new class(app(SpitsOnline\WeFact\WeFact::class)) extends Debtors
    {
        protected const int PAGE_SIZE = 2;
    };

    $list = $debtors->get();
    Http::assertNothingSent();

    expect($list->all())->toHaveCount(3)
        ->and($list->first())->toBeInstanceOf(Debtor::class);

    assertCalled('debtor.list', ['offset' => '0', 'limit' => '2']);
    assertCalled('debtor.list', ['offset' => '2', 'limit' => '2']);
});

it('stops paging on a short page', function () {
    fakeApi(['debtor.list' => apiFixture('debtors')]);

    expect(WeFact::debtors()->get()->all())->toHaveCount(2);

    Http::assertSentCount(1);
});

it('searches debtors and filters on when they changed', function () {
    fakeApi(['debtor.list' => success('debtor.list', ['totalresults' => 0])]);

    WeFact::debtors()->get(search: 'Groot', modifiedSince: CarbonImmutable::parse('2026-10-01 08:00:00', 'UTC'))->all();

    assertCalled('debtor.list', ['searchfor' => 'Groot', 'modified.from' => '2026-10-01 10:00:00']);
});

it('creates a debtor', function () {
    fakeApi(['debtor.add' => success('debtor.add', ['debtor' => apiFixture('debtor')['debtor']])]);

    $debtor = WeFact::debtors()->create(companyName: 'De Groot Stoffen', emailAddress: 'info@example.com', attributes: ['Sex' => 'f']);

    expect($debtor->code)->toBe('DB10003');
    assertCalled('debtor.add', ['CompanyName' => 'De Groot Stoffen', 'EmailAddress' => 'info@example.com', 'Sex' => 'f']);
});

it('updates only the fields you pass, and can clear one', function () {
    fakeApi(['debtor.edit' => success('debtor.edit', ['debtor' => apiFixture('debtor')['debtor']])]);

    WeFact::debtor(4)->update(comment: '', city: 'Delft');

    Http::assertSent(fn ($request) => $request['action'] === 'edit'
        && array_diff_key(wire($request), array_flip(['api_key', 'controller', 'action'])) === ['Identifier' => '4', 'City' => 'Delft', 'Comment' => '']);
});

it('lets a fetched debtor update itself', function () {
    fakeApi([
        'debtor.show' => apiFixture('debtor'),
        'debtor.edit' => success('debtor.edit', ['debtor' => apiFixture('debtor')['debtor']]),
    ]);

    WeFact::debtors()->find(4)?->update(comment: 'Pays late');

    assertCalled('debtor.edit', ['Identifier' => '4', 'Comment' => 'Pays late']);
});

it('leaves the client out when a debtor is serialized', function () {
    fakeApi(['debtor.show' => apiFixture('debtor')]);

    $serialized = serialize(WeFact::debtor(4)->get());

    expect($serialized)->not->toContain('secret-key')
        ->and(unserialize($serialized))->toBeInstanceOf(Debtor::class);
});
