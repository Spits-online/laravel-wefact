# Upgrade guide

## From 1.x to 2.0

2.0 is a rewrite, and from today only 2.x gets bug and security fixes. Every v1
call has a v2 equivalent below; the calls are grouped the way apps use them.

### Checklist

- [ ] Swap the package: the Composer name has changed.

  ```bash
  composer remove spits-online/laravel-wefact-api
  composer require spits-online/laravel-wefact:^2.0
  ```

- [ ] Rename `Spits\WeFactApi` to `SpitsOnline\WeFact` in every `use` statement.
- [ ] Replace the env keys, and delete `config/wefact.php` unless you change a
  default (see [Config and env keys](#config-and-env-keys)).
- [ ] Replace every entity call (see below). Search the app for `Spits\WeFactApi`.
- [ ] Replace Guzzle `MockHandler` fakes in your tests with `WeFact::fake()` (see
  [Tests](#tests)).
- [ ] Remove the catches around API calls that only existed to survive
  swallowed errors, and catch the package exceptions instead (see
  [Exceptions](#exceptions)).

### The package and namespace are renamed

**Why:** the house rule drops `-api` suffixes, and a Composer name can only change
in a major. The old package is marked abandoned on Packagist in favour of this one.

| v1 | v2 |
|---|---|
| `spits-online/laravel-wefact-api` | `spits-online/laravel-wefact` |
| `Spits\WeFactApi\…` | `SpitsOnline\WeFact\…` |
| `Spits\WeFactApi\Exceptions\ApiException` | `SpitsOnline\WeFact\Exceptions\WeFactException` |

### Config and env keys

**Why:** v1 picked HostFact by swapping in a class (`wefact.type`) and passed
`wefact.client` straight to Guzzle. v2 has a `driver` key instead.

| v1 | v2 |
|---|---|
| `'type' => HostFact::class` | `WEFACT_DRIVER=hostfact` |
| `'type' => WeFact::class` | `WEFACT_DRIVER=wefact` (the default) |
| `WEFACT_BASE_URI` / `client.base_uri` | `WEFACT_URL` (optional for WeFact) |
| `WEFACT_API_KEY` / `key` | `WEFACT_KEY` |
| `client.timeout` (Guzzle option) | `WEFACT_TIMEOUT` (seconds, default 10) |

For an app on HostFact, the `.env` becomes:

```env
WEFACT_DRIVER=hostfact
WEFACT_URL=https://administratie.example.com/apiv2/api.php
WEFACT_KEY=your-api-key
```

v2 merges an app's `config/wefact.php` over its defaults key by key, so you no
longer need to publish it. Delete the published file unless you change a
default, and then keep only those keys.

### Entities are replaced by resources and data objects

**Why:** v1 entities fetched a record in their constructor, returned raw arrays
from `list()`, and failed on `isset()`, missing fields and `delete()`. v2 reads
like Eloquent: pick a record, then act on it. Lists return typed data objects.

`WeFact` below is the facade, `SpitsOnline\WeFact\Facades\WeFact`.

#### Debtors

```php
// v1
foreach ((new Debtor)->list() as $row) { $row['Identifier']; }
$debtor = new Debtor($debtorId);
$debtor->Comment = 'Pays late';
$debtor->save();

// v2
foreach (WeFact::debtors()->get() as $debtor) { $debtor->id; }
$debtor = WeFact::debtor($debtorId)->get();
WeFact::debtor($debtorId)->update(comment: 'Pays late');
```

| v1 | v2 |
|---|---|
| `(new Debtor)->list()` (at most 1,000) | `WeFact::debtors()->get()` (every page, lazily) |
| `new Debtor($id)` | `WeFact::debtor($id)->get()`, or `WeFact::debtors()->find($id)` for null when missing |
| `$debtor->DebtorCode`, `->CompanyName`, … | `$debtor->code`, `->companyName`, … (see `Debtor`) |
| `$debtor->InvoiceAddress` | `$debtor->invoiceAddress` |
| Fields v2 doesn't model, e.g. `LegalForm` | `$debtor->raw['LegalForm']` |
| `$debtor->Comment = …; $debtor->save()` | `$debtor->update(comment: …)` |
| `(new Debtor)->create([...])` | `WeFact::debtors()->create(companyName: …)` |

#### Quotes

```php
// v1
$quote = new Quote($quoteId);
$quote->addQuoteLine([$line]);
$quote->removeQuoteLine($previousLines);
$quote->accept();

// v2: adds the new lines, then removes the previous ones
$quote = WeFact::quote($quoteId);
$quote->lines()->replace(Line::create('Website', priceExcl: 1250));
$quote->accept();
```

| v1 | v2 |
|---|---|
| `(new Quote)->list()` | `WeFact::quotes()->get(status: …, debtor: …)` |
| `new Quote($id)` / `(new Quote)->find($id)` | `WeFact::quote($id)->get()` / `WeFact::quotes()->find($id)` |
| `$quote->PriceQuoteLines` (arrays) | `$quote->lines` (`LineItem`s; null on list results) |
| `(new Quote)->create(['DebtorCode' => …, 'PriceQuoteLines' => […]])` | `WeFact::quotes()->create(debtor: $id, lines: [Line::create(…)])` |
| `$quote->set([...]); $quote->save()` | `$quote->update(referenceNumber: …, date: …, status: …)` |
| `$quote->addQuoteLine([$line])` | `$quote->lines()->add(Line::create(…))` (one request for every line) |
| `$quote->removeQuoteLine([['Identifier' => 1]])` | `$quote->lines()->remove(1)` or `->remove($lineItem)` |
| Add the new lines, then remove the previous ones | `$quote->lines()->replace(...$lines)` |
| `$quote->accept()` / `->decline()` | `$quote->accept()` / `->decline()`, which return the updated `Quote` |
| `(int) $quote->Status` | `$quote->status` (`QuoteStatus`) |

Two behaviours to know:
- `accept()` now **throws** on a declined quote. The API answers success but
  leaves the quote declined, which v1 reported as accepted.
- The API keeps at least one line on a quote, so add the new lines before you
  remove the old ones. Removing nothing sends nothing; v1 sent an empty delete,
  which the API refused.

#### Invoices

```php
// v1
$drafts = (new Invoice)->list([
    'status' => '0',
    'searchat' => 'Debtor',
    'searchfor' => $debtorId,
]);
$invoice = (new Invoice)->find($drafts[0]['Identifier']);
$invoice->InvoiceLines = $lines;
$invoice->save();

// v2: on the debtor's concept invoice, or a new one when it has none
WeFact::debtor($debtorId)->bill(Line::create(
    description: 'Programming',
    priceExcl: 95,
    quantity: 1.5,
));
```

| v1 | v2 |
|---|---|
| `list(['status' => '0', 'searchat' => 'Debtor', 'searchfor' => $id])` | `WeFact::invoices()->get(status: InvoiceStatus::CONCEPT, debtor: $id)` |
| `(new Invoice)->create(['DebtorCode' => …, 'InvoiceLines' => […]])` | `WeFact::invoices()->create(debtor: $id, lines: [Line::create(…)])` |
| `$invoice->InvoiceLines = $lines; $invoice->save()` | `$invoice->lines()->add(...$lines)` |
| The find-a-concept-invoice-or-create-one dance | `WeFact::debtor($id)->bill(...$lines)` |
| `['DiscountPercentage' => 100]` on a line | `Line::create(…, discountPercentage: 100)` |
| `'PriceExcl' => number_format($x, 2, '.', ',')` | `Line::create(…, priceExcl: $x)`, a float |
| `'Date' => $carbon` | `Line::create(…, date: $carbon)` |

`Line` writes amounts the way the API accepts them. v1 took whatever array you
passed, and `number_format($x, 2, '.', ',')` turns 1250 into `1,250.00`, which the
API rejects. `debtor:` takes an `int`, so a missing debtor id can no longer turn
into an empty search that matches every debtor's invoices.

#### Products, subscriptions and domains

| v1 | v2 |
|---|---|
| `(new Product)->list()` | `WeFact::products()->get()` |
| `$row['ProductType'] === 'other'` | `$product->type === ProductType::OTHER` |
| `(new Subscription)->list(['limit' => 250, 'offset' => …])` and `->meta['totalresults']` | `WeFact::subscriptions()->get()`, which pages itself |
| `(new Domain)->list(['offset' => …])` | `WeFact::domains()->get()` (HostFact only) |
| `$row['Domain'].'.'.$row['Tld']` | `$domain->name` |
| `$row['Status'] == 4` | `$domain->status === DomainStatus::ACTIVE` |

`subscriptions()->get()` returns the active subscriptions, as v1 did. Pass
`status: null` for every subscription.

#### Removed entities

v2 drops the entities that weren't used: creditors, purchase invoices
(`CreditInvoice`), groups, hosting, SSL, VPS, tickets, services, domain contacts
and attachments. `WeFact::request()` reaches any API call:

```php
$answer = WeFact::request('hosting', 'list', ['status' => 4]);
```

### Exceptions

**Why:** v1 threw `ApiException` for API errors, but leaked Guzzle and JSON
exceptions for everything else. v2 throws only its own exceptions, all
extending `WeFactException`.

| Situation | v1 | v2 |
|---|---|---|
| The API refused the request | `ApiException` | `RequestFailed` (`->errors`, `->body`) |
| A `show` of something missing | `ApiException` | `NotFound`, or `find()` returns null |
| A wrong key or an IP address not on the whitelist | `ApiException` | `AccessDenied` |
| The API can't be reached or times out | `GuzzleException` | `ConnectionFailed` |
| An answer that isn't JSON | `JsonException` | `RequestFailed` |
| No URL or key configured | a failing request | `MissingConfiguration` |

`AccessDenied` is not a missing record: don't archive local data when you catch it.

### Tests

v1 apps faked the API with a Guzzle `MockHandler` in `wefact.client.handler`.
That config key is gone. Use the fake instead:

```php
$fake = WeFact::fake()
    ->withDebtor(['CompanyName' => 'Acme'])
    ->withQuote(['Debtor' => 1]);

// The code under test; usually an action of your app.
WeFact::quote(1)->accept();

$fake->assertQuoteAccepted(1);
```

Tests that need the exact HTTP request can use `Http::fake()`: v2 sends its
requests through Laravel's HTTP client.
