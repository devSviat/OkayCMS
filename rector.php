<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// Лише синтаксичні правила, без зміни сигнатур, видимості й типів властивостей:
// модулі наслідують класи ядра і перевизначають їхні методи. Правила перелічено
// явно, а не набором (withPhpSets): новий реліз Rector доповнює набори, і так у
// CI мовчки з'явилось би правило, якого ніхто не перевіряв. Чому не інші
// правила — docs/testing.md, розділ «Rector».
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Okay',
        __DIR__ . '/backend',
    ])
    ->withSkip([
        // Сторонній файловий менеджер, не наш код.
        __DIR__ . '/backend/design',
        '*/compiled/*',
        '*/vendor/*',
    ])
    ->withRules([
        Rector\Php54\Rector\Array_\LongArrayToShortArrayRector::class,
        Rector\Php71\Rector\List_\ListToArrayDestructRector::class,
        Rector\Php80\Rector\NotIdentical\StrContainsRector::class,
        Rector\Php80\Rector\Identical\StrStartsWithRector::class,
        Rector\Php80\Rector\Identical\StrEndsWithRector::class,
        Rector\Php70\Rector\Ternary\TernaryToNullCoalescingRector::class,
        Rector\Php70\Rector\StmtsAwareInterface\IfIssetToCoalescingRector::class,
        Rector\Php74\Rector\Assign\NullCoalescingOperatorRector::class,
        Rector\Php74\Rector\If_\IfToNullCoalescingAssignRector::class,
        Rector\Php53\Rector\Ternary\TernaryToElvisRector::class,
        Rector\Php70\Rector\FuncCall\MultiDirnameRector::class,
        Rector\Php56\Rector\FuncCall\PowToExpRector::class,
        Rector\Php80\Rector\Catch_\RemoveUnusedVariableInCatchRector::class,
        Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector::class,
        Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector::class,
        Rector\CodingStyle\Rector\FuncCall\FunctionFirstClassCallableRector::class,
        Rector\Php55\Rector\String_\StringClassNameToClassConstantRector::class,
        Rector\Php73\Rector\FuncCall\ArrayKeyFirstLastRector::class,
        Rector\Php83\Rector\ClassConst\AddTypeToConstRector::class,
    ]);
