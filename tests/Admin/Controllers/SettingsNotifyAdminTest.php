<?php

namespace Admin\Controllers;

use Okay\Admin\Controllers\SettingsNotifyAdmin;
use Okay\Core\Notify;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * testSMTP() перезаписував налаштування SMTP даними з POST, не перевіряючи
 * методу запиту. Звичайний GET стирав конфігурацію порожніми значеннями, і
 * CSRF-гард такий запит не бачить: Request::checkSession() пропускає все з
 * порожнім $_POST. Тобто налаштування пошти зносились по посиланню.
 */
class SettingsNotifyAdminTest extends TestCase
{
    public function testGetRequestChangesNothing(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->expects($this->never())->method('set');

        $notify = $this->createMock(Notify::class);
        $notify->expects($this->never())->method('SMTP');

        $controller = $this->controllerFor('GET', $settings, $captured);
        $controller->testSMTP($notify);

        $this->assertSame(['status' => false, 'message' => 'POST required'], json_decode($captured, true));
    }

    public function testPostRequestStillSavesAndTests(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->expects($this->atLeastOnce())->method('set');

        $notify = $this->createMock(Notify::class);
        $notify->expects($this->once())->method('SMTP')->willReturn(true);

        $controller = $this->controllerFor('POST', $settings, $captured);
        $controller->testSMTP($notify);

        $this->assertTrue(json_decode($captured, true)['status']);
    }

    private function controllerFor(string $method, Settings $settings, &$captured): SettingsNotifyAdmin
    {
        $captured = null;

        // Request має власний метод method(), тож білдер PHPUnit доводиться
        // брати через expects() - інакше виклик іде в мок, а не в налаштування.
        $request = $this->createMock(Request::class);
        $request->expects($this->any())->method('method')->willReturnCallback(
            static fn ($wanted) => strtoupper((string)$wanted) === $method
        );
        $request->expects($this->any())->method('post')->willReturn('');

        $response = $this->createMock(Response::class);
        $response->expects($this->any())->method('setContent')->willReturnCallback(
            function ($content) use (&$captured) {
                $captured = $content;
                return $this->createMock(Response::class);
            }
        );

        $controller = (new ReflectionClass(SettingsNotifyAdmin::class))->newInstanceWithoutConstructor();
        $values = [
            'request' => $request,
            'response' => $response,
            'settings' => $settings,
            'manager' => (object)['email' => 'admin@example.com'],
        ];
        foreach ($values as $name => $value) {
            (new ReflectionProperty(SettingsNotifyAdmin::class, $name))->setValue($controller, $value);
        }

        return $controller;
    }
}
