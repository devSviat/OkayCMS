<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class UpgradeNotesTest extends TestCase
{
    /**
     * @dataProvider requiredTopicProvider
     */
    public function testUpgradeNotesCoverEveryBreakingChange($topic)
    {
        $this->assertStringContainsString($topic, $this->notes());
    }

    public function requiredTopicProvider()
    {
        return [
            'frontend session'  => ['okay_sid'],
            'backend session'   => ['okay_admin_sid'],
            'storefront csrf'   => ['customer_csrf_token'],
            'csrf cookie'       => ['okay_csrf'],
            'admin csrf field'  => ['session_id'],
            'svg'               => ['SVG'],
            'remote upload'     => ['url_upload'],
            'cookies'           => ['HttpOnly'],
            'headers'           => ['X-Frame-Options'],
            'recovery'          => ['password_remind'],
        ];
    }

    /**
     * Пропущені дефекти мають лишатися видимими, а не зникнути тихо.
     *
     * @dataProvider knownOpenProvider
     */
    public function testKnownOpenDefectsAreDocumented($topic)
    {
        $this->assertStringContainsString($topic, $this->notes());
    }

    public function knownOpenProvider()
    {
        return [
            'wayforpay'  => ['WayForPay'],
            'rozetkapay' => ['RozetkaPay'],
        ];
    }

    public function testNotesAreLinkedFromClaudeMd()
    {
        $claude = file_get_contents(dirname(__DIR__, 2) . '/CLAUDE.md');
        $this->assertIsString($claude);

        $this->assertStringContainsString('UPGRADE-security.md', $claude);
    }

    private function notes()
    {
        $notes = file_get_contents(dirname(__DIR__, 2) . '/docs/UPGRADE-security.md');
        $this->assertIsString($notes, 'docs/UPGRADE-security.md is missing');

        return $notes;
    }
}
