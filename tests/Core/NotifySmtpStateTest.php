<?php

namespace Core;

use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Notify;
use Okay\Core\Settings;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Helpers\NotifyHelper;
use Okay\Helpers\OrdersHelper;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * PHPMailer один на процес. Notify::SMTP() чистив між листами лише адресатів,
 * тож Reply-To (у листі адміну про відгук — адреса покупця), вкладення й
 * заголовки попереднього листа їхали в наступний.
 */
class NotifySmtpStateTest extends TestCase
{
    /** @var PHPMailer&object{sent: array} */
    private PHPMailer $mailer;

    protected function setUp(): void
    {
        // Відправка підмінена: тест дивиться, з яким станом лист пішов би.
        $this->mailer = new class extends PHPMailer {
            public array $sent = [];
            public bool $result = true;

            public function send()
            {
                $this->sent[] = [
                    'to' => array_column($this->getToAddresses(), 0),
                    'replyTo' => array_column($this->getReplyToAddresses(), 0),
                    'attachments' => count($this->getAttachments()),
                    'headers' => array_column($this->getCustomHeaders(), 0),
                ];

                return $this->result;
            }
        };
    }

    private function notify(): Notify
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(fn($name) => ['smtp_user' => 'shop@example.com', 'smtp_port' => 25][$name] ?? null);

        return new Notify(
            $settings,
            $this->createStub(Languages::class),
            $this->createStub(EntityFactory::class),
            $this->createStub(Design::class),
            $this->createStub(FrontTemplateConfig::class),
            $this->createStub(OrdersHelper::class),
            $this->createStub(BackendTranslations::class),
            $this->createStub(FrontTranslations::class),
            $this->mailer,
            $this->createStub(LoggerInterface::class),
            $this->createStub(NotifyHelper::class),
            ''
        );
    }

    public function testNextEmailDoesNotInheritThePreviousOnesState(): void
    {
        $notify = $this->notify();

        $notify->SMTP('admin@example.com', 'Новий відгук', '<p>1</p>', '', 'buyer@example.com');
        $this->mailer->addAttachment(__FILE__);
        $this->mailer->addCustomHeader('X-Leak', '1');
        $notify->SMTP('other@example.com', 'Замовлення', '<p>2</p>');

        $second = $this->mailer->sent[1];
        $this->assertSame(['other@example.com'], $second['to']);
        $this->assertSame(['shop@example.com'], $second['replyTo'], 'Reply-To покупця з попереднього листа');
        $this->assertSame(0, $second['attachments']);
        $this->assertNotContains('X-Leak', $second['headers']);
    }

    /** PHPMailer пише MIME-Version сам; власний заголовок давав дубль у кожному листі. */
    public function testNoOwnMimeVersionHeader(): void
    {
        $this->notify()->SMTP('a@example.com', 'Тема', '<p>x</p>');

        $this->assertNotContains('MIME-Version', array_map('trim', $this->mailer->sent[0]['headers']));
    }

    public function testFailedSendReturnsFalseAndClosesTheOutputBuffer(): void
    {
        $this->mailer->result = false;
        $level = ob_get_level();

        $result = $this->notify()->SMTP('a@example.com', 'Тема', '<p>x</p>');

        $this->assertFalse($result);
        $this->assertSame($level, ob_get_level());
    }
}
