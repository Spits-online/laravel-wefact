<!--
  Keep this README in sync with the code. Every change to the public API, config,
  routes, channels, exceptions or the fake updates this file in the same commit:
  every feature has a working example here, and nothing is shown that doesn't exist.
-->

<div align="left">
  <a href="https://github.com/Spits-online/laravel-wefact">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Spits-online/laravel-wefact/main/art/banner-dark.png">
      <img alt="Laravel WeFact by Spits" src="https://raw.githubusercontent.com/Spits-online/laravel-wefact/main/art/banner-light.png">
    </picture>
  </a>

<h1>Work with WeFact and HostFact in Laravel</h1>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spits-online/laravel-wefact.svg?style=flat-square)](https://packagist.org/packages/spits-online/laravel-wefact)
[![Tests](https://img.shields.io/github/actions/workflow/status/Spits-online/laravel-wefact/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Spits-online/laravel-wefact/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/Spits-online/laravel-wefact/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/Spits-online/laravel-wefact/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/spits-online/laravel-wefact.svg?style=flat-square)](https://packagist.org/packages/spits-online/laravel-wefact)

</div>

[WeFact](https://www.wefact.nl) is online invoicing software; [HostFact](https://www.hostfact.nl)
is its self-hosted sibling for hosting companies. Both speak the same API, and this
package talks to either one through a single driver setting. Calls read like
Eloquent, answers come back as typed objects, lists page themselves, and
`WeFact::fake()` lets you test your app without the API.

```php
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Facades\WeFact;

$quote = WeFact::quotes()->create(
    debtor: 12,
    lines: [Line::create('Website redesign', priceExcl: 4500)],
);

$quote->accept(createInvoice: true);

WeFact::debtor(12)->bill(
    Line::create('Support', priceExcl: 95, quantity: 1.5),
);
```

## Requirements

- PHP 8.3 or higher
- Laravel 12 or 13
- A WeFact account, or a HostFact installation, with the API enabled

## Installation

```bash
composer require spits-online/laravel-wefact
```

Add the driver and your API key to `.env`:

```env
# wefact (the default) or hostfact
WEFACT_DRIVER=hostfact
# HostFact only: the apiv2/api.php of your installation
WEFACT_URL=https://administratie.example.com/apiv2/api.php
WEFACT_KEY=your-api-key

# Optional
WEFACT_TIMEOUT=10
WEFACT_TIMEZONE=Europe/Amsterdam
```

Where to find each value:

- **`WEFACT_KEY`:** the "beveiligingscode" under Instellingen → API in WeFact,
  or Instellingen → HostFact voorkeuren → API in HostFact.
- **The IP whitelist** is on the same page. Add the IP address of every server
  that calls the API, or each request is refused with `AccessDenied`.
- **`WEFACT_URL`:** WeFact's API lives at `https://api.mijnwefact.nl/v2/`, which is
  used when you leave it out. For HostFact it is required: the `apiv2/api.php`
  under your HostFact address.
- **`WEFACT_TIMEZONE`:** the timezone the API's server runs in. The API sends
  dates without an offset; they are read, and dates you send are written, in this
  timezone. It defaults to the app's timezone.

There's no config file to publish. To change a default, create
`config/wefact.php` with **only the keys you change**. It merges over the
package's [defaults](config/wefact.php) key by key:

```php
<?php

return [
    'timeout' => 20,
];
```

## Usage

Every call starts from the `WeFact` facade, `SpitsOnline\WeFact\Facades\WeFact`.
Picking one record, such as `WeFact::quote(51)`, sends no request; each method
you call on it sends one. Records you fetched can act on themselves, so
`$quote->accept()` works the same as `WeFact::quote($quote->id)->accept()`.

### Finding debtors

```php
$debtor = WeFact::debtors()->findByCode('DB10001');
$debtor = WeFact::debtors()->find(12);

$debtor?->companyName;
$debtor?->emailAddress;
$debtor?->invoiceAddress;
```

`find()` and `findByCode()` return null when the debtor doesn't exist.
`WeFact::debtor(12)->get()` throws `NotFound` instead.

To walk every debtor, use `get()`. It returns a `LazyCollection` that fetches
the next page of 1,000 only when you get there:

```php
use Carbon\CarbonImmutable;

$changed = WeFact::debtors()->get(
    modifiedSince: CarbonImmutable::now()->subDay(),
);

foreach ($changed as $debtor) {
    // …
}

$debtors = WeFact::debtors()->get(search: 'Acme');
```

A debtor from `get()` holds the API's short version: the id, code, names, email
address and modification date. `find()` returns every field. Fields the package
doesn't model are in `$debtor->raw`, in the API's own keys:

```php
$debtor->raw['InvoiceAddress'];
```

### Creating and changing debtors

```php
$debtor = WeFact::debtors()->create(
    companyName: 'Acme',
    emailAddress: 'billing@acme.test',
);

$debtor = WeFact::debtor(12)->update(comment: 'Pays late');
```

`update()` changes only the arguments you pass; pass `''` to clear a field. Both
methods take any other field the API documents through `attributes`, in its own
keys: `attributes: ['Sex' => 'f']`.

### Building lines

Quote and invoice lines are built with `Line::create()`:

```php
use SpitsOnline\WeFact\Data\Line;

Line::create('Programming', priceExcl: 95, quantity: 1.5, unit: 'hour');

// The product's description and price, from the API.
Line::create(productCode: 'P001', quantity: 12);

// A line that is shown but not charged.
Line::create('Travel', priceExcl: 0.23, quantity: 42, discountPercentage: 100);

// A text line.
Line::create('Prices exclude VAT.');
```

A line needs a description or a product code. Leave out the price to use the
product's price, the date to use today, and the tax percentage to use the API's
default rate. Amounts are floats; the package writes them the way the API takes
them (`1250.5`, never `1,250.50`).

### Creating quotes

```php
$quote = WeFact::quotes()->create(
    debtor: 12,
    lines: [
        Line::create('Website redesign', priceExcl: 4500),
        Line::create(productCode: 'HOSTING', quantity: 12),
    ],
    referenceNumber: 'Website 2026',
);

$quote->code;      // OF0014
$quote->status;    // QuoteStatus::CONCEPT
$quote->amountExcl;
```

`debtor` takes the debtor's id, or a `Debtor` you fetched. `create()` also takes
a `date` (today by default), a `description` and `attributes`.

### Reading quotes and their lines

```php
use SpitsOnline\WeFact\Enums\QuoteStatus;

$quote = WeFact::quote(51)->get();

foreach ($quote->lines as $line) {
    $line->description;
    $line->quantity;
    $line->priceExcl;
    $line->amountExcl;
}

$open = WeFact::quotes()->get(
    status: [QuoteStatus::SENT, QuoteStatus::ACCEPTED],
    debtor: 12,
);
```

Quotes from `get()` have no lines: `$quote->lines` is null there, so fetch the
quote with `get()` or `find()` to read them. `findByCode('OF0014')` finds a quote
by its code. Archived quotes don't appear in lists.

### Changing quotes and their lines

```php
$quote = WeFact::quote(51)->update(
    referenceNumber: 'Website 2026, v2',
    status: QuoteStatus::SENT,
);

$quote->lines()->add(
    Line::create('Extra page', priceExcl: 350),
    Line::create('Hosting', priceExcl: 15),
);

$quote->lines()->remove($quote->lines[0]);
```

`add()` and `remove()` send one request for all their lines. `remove()` takes
the `LineItem`s from `$quote->lines`, or their ids. The API keeps at least one
line on a quote, so `remove()` can't take them all.

To swap every line for new ones, use `replace()`. It adds the new lines before
it removes the old ones, so a refused line never leaves the quote half-edited,
and it takes at least one line:

```php
WeFact::quote(51)->lines()->replace(
    Line::create('Website redesign', priceExcl: 4500),
    Line::create('Hosting', priceExcl: 15),
);
```

### Accepting, declining and archiving quotes

```php
$quote = WeFact::quote(51)->accept();

// Also turns the quote into a concept invoice.
$quote = WeFact::quote(51)->accept(createInvoice: true);

$quote = WeFact::quote(51)->decline();

WeFact::quote(51)->archive();
```

`accept()` throws `RequestFailed` when the quote can't be accepted, such as a
declined quote: the API answers success there, but leaves the quote declined. An
accepted quote can still be declined. After `accept(createInvoice: true)` the
quote's status is `QuoteStatus::INVOICED`; `$quote->status?->isAccepted()` is
true for both. An archived quote no longer appears in `WeFact::quotes()->get()`;
on WeFact, only accepted, invoiced and declined quotes can be archived.

### Billing work to a debtor

`bill()` puts lines on the debtor's concept invoice, to be sent later: its
newest concept invoice when it has one, or a new one. It returns that invoice.

```php
$invoice = WeFact::debtor(12)->bill(
    Line::create('Programming', priceExcl: 95, quantity: 1.5),
    Line::create('Travel', priceExcl: 0.23, quantity: 42),
);
```

A concept invoice can also come from accepting a quote with `createInvoice`, so
the lines may land on that one.

### Creating invoices

```php
use SpitsOnline\WeFact\Enums\InvoiceStatus;

$invoice = WeFact::invoices()->create(
    debtor: 12,
    lines: [Line::create('Website redesign', priceExcl: 4500)],
);

$concepts = WeFact::invoices()->get(
    status: InvoiceStatus::CONCEPT,
    debtor: 12,
);
```

Filtering on `debtor` matches the debtor's id exactly. `create()` makes a concept
invoice, and takes the same `referenceNumber`, `date`, `description` and
`attributes` as quotes.

### Reading, changing and deleting invoices

```php
$invoice = WeFact::invoice(18)->get();
$invoice = WeFact::invoices()->findByCode('F2026-0001');

$invoice->status;     // InvoiceStatus::SENT
$invoice->amountIncl;
$invoice->amountOpen;
$invoice->payBefore;

$invoice->lines()->remove($invoice->lines[0]);

WeFact::invoice(18)->delete();
```

Only concept invoices can be deleted. Invoice lines work like quote lines,
`replace()` included: the API keeps at least one line on an invoice. Invoices
from `get()` have no lines.

### Products

```php
use SpitsOnline\WeFact\Enums\ProductType;

$product = WeFact::products()->findByCode('P001');

$product?->name;
$product?->priceExcl;
$product?->period;   // Period::MONTH, or null for a one-off price

$general = WeFact::products()->get()
    ->filter(fn ($product) => $product->type === ProductType::OTHER);
```

`products()->find($id)` finds a product by id, and `get(search: 'SEO')` searches
the code, name and key phrase.

### Subscriptions

```php
use SpitsOnline\WeFact\Enums\SubscriptionStatus;

foreach (WeFact::subscriptions()->get() as $subscription) {
    $subscription->debtorCode;
    $subscription->productCode;
    $subscription->priceExcl;
    $subscription->nextDate;
}

$ended = WeFact::subscriptions()->get(
    status: SubscriptionStatus::TERMINATED,
    debtor: 12,
);
```

Like the API, `get()` returns the active subscriptions; `status: null` returns
them all. On HostFact this includes the subscriptions of domains and hosting
accounts; `$subscription->type` says which.

### Domains

Only HostFact manages domains. On the `wefact` driver, `domains()` throws
`UnsupportedFeature`.

```php
use SpitsOnline\WeFact\Enums\DomainStatus;

$domains = WeFact::domains()->get(status: DomainStatus::ACTIVE);

foreach ($domains as $domain) {
    $domain->name;   // example.com
    $domain->debtorId;
    $domain->expirationDate;
}

$domain = WeFact::domains()->find(2);
```

`get()` also filters on `debtor`. Only `find()` sends `$domain->autoRenew`.

### Anything else the API offers

`WeFact::request()` sends any call the API documents, with the API's own keys,
and returns its answer as an array. It throws the same exceptions as every
other call.

```php
$answer = WeFact::request('hosting', 'list', ['status' => 4]);
```

### Switching from HostFact to WeFact

Every call above works the same on both drivers, except domains. Switch by
changing `WEFACT_DRIVER`, `WEFACT_URL` and `WEFACT_KEY`. The package takes care
of the differences between the two APIs, such as how a quote is archived and
which field holds what is still to be paid. `WeFact::driver()` returns the
driver in use, as a `SpitsOnline\WeFact\Enums\Driver`.

## Error handling

Every exception extends `SpitsOnline\WeFact\Exceptions\WeFactException`, so
one `catch` covers them all:

| Exception | When |
|---|---|
| `AccessDenied` | The API refused the key or this server's IP address, or WeFact blocked the IP address for going over its rate limits. |
| `NotFound` | The record doesn't exist. `find()` and `findByCode()` return null instead. |
| `RequestFailed` | The API refused the request. `$e->errors` holds its messages and `$e->body` the whole answer. |
| `ConnectionFailed` | The API couldn't be reached, or didn't answer within `WEFACT_TIMEOUT` seconds. |
| `MissingConfiguration` | `WEFACT_URL`, `WEFACT_KEY` or `WEFACT_DRIVER` isn't set right. |
| `UnsupportedFeature` | The driver doesn't have this, such as domains on WeFact. |
| `InvalidLine` | A `Line` has neither a description nor a product code. |

`NotFound` and `AccessDenied` extend `RequestFailed`.

```php
use SpitsOnline\WeFact\Exceptions\AccessDenied;
use SpitsOnline\WeFact\Exceptions\RequestFailed;
use SpitsOnline\WeFact\Exceptions\WeFactException;

try {
    WeFact::quote(51)->accept();
} catch (AccessDenied $e) {
    // A setup problem, not a missing quote.
    report($e);
} catch (RequestFailed $e) {
    // The API's own error messages, e.g. why it refused.
    $e->errors;
} catch (WeFactException $e) {
    // The API couldn't be reached.
}
```

The API answers errors with HTTP 200, so the package reads them from the
answer itself. WeFact limits each IP address to 200 calls a minute and 3,600 an
hour, and blocks an IP address that goes over; you have to contact WeFact to be
unblocked.

## Testing your app

`WeFact::fake()` swaps the client for an in-memory WeFact (or HostFact, when
that's your driver). Seed it in the API's own keys, then assert on what changed:

```php
use SpitsOnline\WeFact\Facades\WeFact;

it('bills the hours to the draft invoice', function () {
    $fake = WeFact::fake()
        ->withDebtor(['CompanyName' => 'Acme'])
        ->withInvoice(['Debtor' => 1]);

    // … run the code under test …

    $fake->assertInvoiceLinesAdded(
        1,
        fn (array $lines) => $lines[0]->quantity === 1.5,
    );
});
```

The fake answers the same requests the real client sends, so it behaves like
the API: lists filter and page, a missing record throws `NotFound`, a declined
quote can't be accepted, only concept invoices can be deleted, a quote or invoice
keeps at least one line, and `accept(createInvoice: true)` creates a concept
invoice. Ids start at 1, debtor codes at `DB10001`.

Seed records with `withDebtor()`, `withProduct()`, `withQuote()`,
`withInvoice()`, `withSubscription()` and `withDomain()`. Quotes and invoices take
their lines as a second argument:

```php
$fake = WeFact::fake()
    ->withDebtor()
    ->withProduct(['ProductCode' => 'P001', 'PriceExcl' => '95'])
    ->withQuote(['Debtor' => 1], [Line::create('Website')]);
```

The assertions, each with an optional callback that must return true:

| Assertion | The callback receives |
|---|---|
| `assertDebtorCreated(?callback)` | the `Debtor` |
| `assertDebtorUpdated($id, ?callback)` | the changed fields, e.g. `['Comment' => 'Pays late']` |
| `assertQuoteCreated(?callback)` | the `Quote` |
| `assertQuoteUpdated($id, ?callback)` | the changed fields |
| `assertQuoteAccepted($id)` | |
| `assertQuoteDeclined($id)` | |
| `assertQuoteArchived($id)` | |
| `assertQuoteLinesAdded($id, ?callback)` | the added `LineItem`s |
| `assertQuoteLinesRemoved($id, ?callback)` | the removed line ids |
| `assertInvoiceCreated(?callback)` | the `Invoice` |
| `assertInvoiceDeleted($id)` | |
| `assertInvoiceLinesAdded($id, ?callback)` | the added `LineItem`s |
| `assertInvoiceLinesRemoved($id, ?callback)` | the removed line ids |
| `assertNothingChanged()` | |

To fake the other driver, pass it: `WeFact::fake(Driver::WEFACT)`.

The package sends its requests through Laravel's HTTP client, so `Http::fake()`
works too when a test needs the exact request.

## Testing

```bash
composer test
```

`composer check` runs Pint, PHPStan and the tests, like CI.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Upgrading

Please see [UPGRADE](UPGRADE.md) for how to upgrade from 1.x.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [SpitsOnline](https://spits.online)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
