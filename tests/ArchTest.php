<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();

arch('every file declares strict types')
    ->expect('SpitsOnline\WeFact')
    ->toUseStrictTypes();

arch('data objects are final and readonly')
    ->expect('SpitsOnline\WeFact\Data')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('exceptions extend the package base exception')
    ->expect('SpitsOnline\WeFact\Exceptions')
    ->classes()
    ->toExtend('SpitsOnline\WeFact\Exceptions\WeFactException')
    ->ignoring('SpitsOnline\WeFact\Exceptions\WeFactException');

arch('concerns are traits')->expect('SpitsOnline\WeFact\Concerns')->toBeTraits();
arch('enums are enums')->expect('SpitsOnline\WeFact\Enums')->toBeEnums();

arch('only the client sends requests')
    ->expect('Illuminate\Support\Facades\Http')
    ->toOnlyBeUsedIn('SpitsOnline\WeFact\WeFact');
