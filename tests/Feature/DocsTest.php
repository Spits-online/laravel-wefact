<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SpitsOnline\WeFact\Data\Line;
use SpitsOnline\WeFact\Facades\WeFact;

/*
 * Runs every PHP example in README.md and UPGRADE.md as it is written, so an
 * example that stops working fails the build. Each example runs against a fake
 * seeded with the records the examples use: debtor 12, quote 51, sent invoice 18
 * (`F2026-0001`), concept invoice 19, products `P001` and `HOSTING`, and domain 2.
 */

/**
 * @return Collection<string, array{string}>
 */
function docExamples(string $file): Collection
{
    preg_match_all('/```php\n(.*?)```/s', (string) file_get_contents(__DIR__."/../../{$file}"), $blocks);

    return collect($blocks[1])
        // The config example is a file, not code to run; ConfigTest covers its keys.
        ->reject(fn (string $code) => Str::startsWith($code, '<?php'))
        // Upgrade examples show v1 next to v2; only v2 runs.
        ->map(fn (string $code) => Str::contains($code, '// v1') ? Str::of($code)->after('// v2')->after("\n")->value() : $code)
        ->mapWithKeys(fn (string $code, int $index) => ["{$file} #{$index}: ".Str::of($code)->trim()->before("\n")->limit(50) => [$code]]);
}

/**
 * The example as a closure: its `use` statements on top, and a Pest `it()` around
 * it unwrapped, so the test it shows runs here.
 */
function exampleClosure(string $code): Closure
{
    $lines = Str::of($code)->explode("\n");
    $imports = $lines->filter(fn (string $line) => Str::startsWith($line, 'use '))
        ->merge(['use SpitsOnline\WeFact\Data\Line;', 'use SpitsOnline\WeFact\Facades\WeFact;'])
        ->unique(fn (string $line) => Str::afterLast(Str::before($line, ';'), '\\'));
    $body = $lines->reject(fn (string $line) => Str::startsWith($line, 'use '))->implode("\n");

    if (preg_match("/^it\\('[^']*', function \\(\\) \\{\n(.*)\n\\}\\);\s*$/s", Str::trim($body), $test)) {
        $body = $test[1];
    }

    $file = sys_get_temp_dir().'/wefact-docs-'.md5($code).'.php';
    file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n{$imports->implode("\n")}\n\nreturn function () {\n    \$quoteId = 51;\n    \$debtorId = 12;\n\n{$body}\n};\n");

    return require $file;
}

beforeEach(function () {
    WeFact::fake()
        ->withDebtor(['Identifier' => 12, 'CompanyName' => 'Acme', 'Sex' => 'm'])
        ->withProduct(['ProductCode' => 'P001', 'PriceExcl' => '95'])
        ->withProduct(['ProductCode' => 'HOSTING', 'PriceExcl' => '15'])
        ->withQuote(['Identifier' => 51, 'Debtor' => 12], [Line::create('Website'), Line::create('Hosting')])
        ->withInvoice(['Identifier' => 18, 'Debtor' => 12, 'InvoiceCode' => 'F2026-0001', 'Status' => '2'], [Line::create('Website'), Line::create('Hosting')])
        ->withInvoice(['Identifier' => 19, 'Debtor' => 12])
        ->withSubscription(['Debtor' => 12])
        ->withDomain(['Identifier' => 2, 'Debtor' => 12, 'Domain' => 'example', 'Tld' => 'com']);
});

it('runs the example', function (string $code) {
    // `request()` reaches what the fake doesn't model, so it runs against a faked API.
    if (Str::contains($code, 'WeFact::request(')) {
        WeFact::clearResolvedInstances();
        app()->forgetInstance(SpitsOnline\WeFact\WeFact::class);
        fakeApi(['hosting.list' => success('hosting.list', ['hosting' => []])]);
    }

    exampleClosure($code)();

    expect(true)->toBeTrue();
})->with([...docExamples('README.md'), ...docExamples('UPGRADE.md')]);

it('documents only config keys the package has', function () {
    preg_match('/```php\n<\?php\n\nreturn (\[.*?\]);\n```/s', (string) file_get_contents(__DIR__.'/../../README.md'), $config);

    $override = eval("return {$config[1]};");

    expect(Arr::except($override, array_keys(require __DIR__.'/../../config/wefact.php')))->toBe([]);
});
