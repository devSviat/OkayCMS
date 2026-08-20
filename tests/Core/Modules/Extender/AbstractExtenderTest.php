<?php


namespace Core\Modules\Extender;


use Okay\Core\Modules\Extender\AbstractExtender;
use Okay\Core\Modules\Extender\ExtensionInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AbstractExtenderTest extends TestCase
{
    /**
     * @param $path
     * @param $expectedResult
     */
    #[DataProvider('deprecatedMethodsDataProvider')]
    public function testLoadDeprecatedMethods($config, $expectedResult)
    {
        /** @var AbstractExtender $abstractExtender */
        $abstractExtender = new class extends AbstractExtender {};

        $reflector = new \ReflectionClass($abstractExtender);
        $property = $reflector->getProperty('deprecatedMethods');

        $abstractExtender->setDeprecated($config);

        $this->assertEquals($property->getValue($abstractExtender), $expectedResult);
        $property->setValue($abstractExtender, []);
    }

    /**
     * @param $deprecated
     */
    #[DataProvider('newExtensionsDataProvider')]
    public function testNewExtension($deprecated, $expectedResult)
    {
        $abstractExtenderBuilder = $this
            ->getMockBuilder(AbstractExtender::class)
            ->onlyMethods(['checkAndCorrectDeprecatedMethod', 'validateExtension']);

        $abstractExtender = $abstractExtenderBuilder->getMock();
        if ($deprecated) {
            $abstractExtender
                ->expects($this->once())
                ->method('checkAndCorrectDeprecatedMethod')
                ->willReturn(['Okay\TestClass3', 'testMethod3']);
        } else {
            $abstractExtender
                ->expects($this->once())
                ->method('checkAndCorrectDeprecatedMethod')
                ->willReturn(false);
        }

        $abstractExtender
            ->expects($this->once())
            ->method('validateExtension');

        $reflector = new \ReflectionClass($abstractExtender);
        $property = $reflector->getProperty('triggers');

        $abstractExtender->newExtension(
            'Okay\ClassTest1',
            'testMethod1',
            'Okay\ClassTest2',
            'testMethod2');

        $this->assertEquals($property->getValue(), $expectedResult);
        $property->setValue(null, []);
    }

    /**
     * Бутстрап модулів проходить на кожному запиті. Якщо процес переживає
     * запит, повторна реєстрація подвоювала б ланцюг - і ланцюгове розширення
     * застосувало б знижку стільки разів, скільки запитів обробив процес.
     */
    public function testRegisteringTheSameExtensionTwiceKeepsOneEntry(): void
    {
        $extender = $this->getMockBuilder(AbstractExtender::class)
            ->onlyMethods(['checkAndCorrectDeprecatedMethod', 'validateExtension'])
            ->getMock();
        $extender->expects($this->exactly(3))
            ->method('checkAndCorrectDeprecatedMethod')
            ->willReturn(false);
        $extender->expects($this->exactly(3))->method('validateExtension');

        $property = (new \ReflectionClass($extender))->getProperty('triggers');
        $property->setValue(null, []);

        for ($i = 0; $i < 3; $i++) {
            $extender->newExtension('Okay\ClassTest1', 'testMethod1', 'Okay\ClassTest2', 'testMethod2');
        }

        $triggers = $property->getValue();
        $property->setValue(null, []);

        $this->assertCount(1, $triggers['Okay\ClassTest1::testMethod1']);
    }

    public function testCompileTrigger()
    {
        /** @var AbstractExtender $abstractExtender */
        $abstractExtender = new class extends AbstractExtender {};

        $reflector = new \ReflectionClass($abstractExtender);
        $method = $reflector->getMethod('compileTrigger');

        $actualResult = $method->invoke($abstractExtender, 'Okay\TestClass', 'testMethod');

        $this->assertEquals('Okay\TestClass::testMethod', $actualResult);
    }

    /**
     * @param $trigger
     * @param $expectedResult
     */
    #[DataProvider('correctDeprecatedMethodsDataProvider')]
    public function testCheckAndCorrectDeprecatedMethod($trigger, $error, $expectedResult)
    {
        /** @var AbstractExtender $abstractExtender */
        $abstractExtender = new class extends AbstractExtender {};

        $reflector = new \ReflectionClass($abstractExtender);
        $property = $reflector->getProperty('deprecatedMethods');
        $property->setValue($abstractExtender, [
            'Okay\TestClass1::testMethod1' => [
                ['Okay\TestClass1', 'testMethod1'],
                ['Okay\TestClass2', 'testMethod2']
            ],
            'Okay\TestClass3::testMethod3' => [
                ['Okay\TestClass3', 'testMethod3'],
                false
            ]
        ]);

        $method = $reflector->getMethod('checkAndCorrectDeprecatedMethod');

        // expectWarning() and expectDeprecation() were removed in PHPUnit 10, and
        // they also turned the raised error into an exception - so the assertion
        // on the return value below never ran for the two data sets that expect
        // one. Collecting the error by hand keeps the method returning, which
        // lets both halves be checked.
        $raised = [];
        set_error_handler(static function (int $errno) use (&$raised): bool {
            $raised[] = $errno;
            return true;
        }, E_USER_WARNING | E_USER_DEPRECATED);

        try {
            $actualResult = $method->invoke($abstractExtender, $trigger);
        } finally {
            restore_error_handler();
        }

        $this->assertSame($error === false ? [] : [$error], $raised);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @param $classExpandable
     * @param $classExtender
     * @param $exception
     * @param $exceptionMessage
     */
    #[DataProvider('extensionsValidateDataProvider')]
    public function testValidateExtension($classExpandable, $classExtender, $exceptionMessage)
    {
        /** @var AbstractExtender $abstractExtender */
        $abstractExtender = new class extends AbstractExtender {};

        $reflector = new \ReflectionClass($abstractExtender);
        $method = $reflector->getMethod('validateExtension');

        $actualResult = null;
        try {
            $method->invoke($abstractExtender, $classExpandable, 'methodExpandable', $classExtender, 'methodExtender');
        } catch (\Exception $e) {
            $actualResult = $e->getMessage();
        };

        $this->assertEquals($actualResult, $exceptionMessage);
    }

    /**
     * @param $trigger
     */
    #[DataProvider('triggersDataProvider')]
    public function testExtensionLog($trigger, $expectedResult)
    {
        // extensionLog() статичний, а PHPUnit 10+ не викликає статичні методи на моках —
        // та мок тут і не потрібен, звертаємось до класу напряму.
        $reflector = new \ReflectionClass(AbstractExtender::class);
        $property = $reflector->getProperty('triggers');
        $property->setValue(null, [
            'Okay\TestClass::testMethod' => ['test']
        ]);

        $actualResult = AbstractExtender::extensionLog($trigger);

        $this->assertEquals($actualResult, $expectedResult);
    }

    public static function deprecatedMethodsDataProvider()
    {
        return [
            'Not empty config' => [
                [ // Конфиг
                    [
                        ['Okay\TestClass1', 'testMethod1'],
                        ['Okay\TestClass2', 'testMethod2']
                    ],
                    [
                        ['Okay\TestClass3', 'testMethod3'],
                        false
                    ]
                ],
                [ // Ожидаемый результат
                    'Okay\TestClass1::testMethod1' => [
                        ['Okay\TestClass1', 'testMethod1'],
                        ['Okay\TestClass2', 'testMethod2']
                    ],
                    'Okay\TestClass3::testMethod3' => [
                        ['Okay\TestClass3', 'testMethod3'],
                        false
                    ]
                ]
            ],
            'Empty config' => [
                [],
                []
            ]
        ];
    }

    public static function newExtensionsDataProvider()
    {
        return [
            'With deprecated method' => [
                true,
                [
                    'Okay\TestClass3::testMethod3' => [
                        'Okay\ClassTest2::testMethod2' => (object) [
                            'class' => 'Okay\ClassTest2',
                            'method' => 'testMethod2'
                        ]
                    ]
                ]
            ],
            'Withou deprecated method' => [
                false,
                [
                    'Okay\ClassTest1::testMethod1' => [
                        'Okay\ClassTest2::testMethod2' => (object) [
                            'class' => 'Okay\ClassTest2',
                            'method' => 'testMethod2'
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function extensionsValidateDataProvider()
    {
        return [
            'Wrong expandable method' => [
                new class {
                    public function methodExpandableWrong() {}
                    public function __toString()
                    {
                        return 'Okay\ClassExpandable';
                    }
                },
                new class implements ExtensionInterface {
                    public function methodExtender() {}
                    public function __toString()
                    {
                        return 'Okay\ClassExtender';
                    }
                },
                'Expandable "Okay\ClassExpandable::methodExpandable()" is not a method', // Сообщение exception
            ],
            'Extender method has not callable structure' => [
                new class {
                    public function methodExpandable() {}
                    public function __toString()
                    {
                        return 'Okay\ClassExpandable';
                    }
                },
                null,
                "Method ::methodExtender is not callable",
            ],
            'ClassExtender without ExtensionInterface' => [
                new class {
                    public function methodExpandable() {}
                    public function __toString()
                    {
                        return 'Okay\ClassExpandable';
                    }
                },
                new class {
                    public function methodExtender() {}
                    public function __toString()
                    {
                        return 'Okay\ClassExtender';
                    }
                },
                "Class Okay\ClassExtender::class must implements " . ExtensionInterface::class . " interface",
            ],
            'Without errors' => [
                new class {
                    public function methodExpandable() {}
                    public function __toString()
                    {
                        return 'Okay\ClassExpandable';
                    }
                },
                new class implements ExtensionInterface {
                    public function methodExtender() {}
                    public function __toString()
                    {
                        return 'Okay\ClassExtender';
                    }
                },
                null,
            ]
        ];
    }

    public static function triggersDataProvider()
    {
        return [
            'Correct string trigger' => [
                [ // Триггер
                   'Okay\TestClass',
                    'testMethod'
                ],
                ['test'] // Ожидаемый результат
            ],
            'Correct array trigger' => [
                'Okay\TestClass::testMethod',
                ['test']
            ],
            'Wrong string trigger' => [
                'Okay\TestClass::testMethodWrong',
                []
            ],
        ];
    }

    public static function correctDeprecatedMethodsDataProvider()
    {
        return [
            'Deprecated method with replace' => [
                'Okay\TestClass1::testMethod1',
                E_USER_DEPRECATED,
                ['Okay\TestClass2', 'testMethod2']
            ],
            'Deprecated method without replace' => [
                'Okay\TestClass3::testMethod3',
                E_USER_WARNING,
                false
            ],
            'Not deprecated method' => [
                'Okay\TestClass4::testMethod4',
                false,
                false

            ]
        ];
    }
}