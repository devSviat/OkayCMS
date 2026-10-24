<?php

namespace Okay\Core\Console\Commands\Manager;

use Okay\Core\Console\Command;
use Okay\Core\Security\ManagerSessions;
use Okay\Core\Security\ManagerTwoFactor;
use Okay\Entities\ManagersEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Останній шлях у адмінку, коли телефон і коди відновлення загублено, або
 * коли хтось із паролем вичерпав ліміт кодів і закрив власнику вхід. Доступ до
 * консолі — це доступ до сервера, тож він і так за межею 2FA.
 */
#[AsCommand(name: 'manager:2fa-reset', description: 'Disable two-factor authentication of a manager and end all their sessions')]
class ManagerTwoFactorResetCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('login', InputArgument::REQUIRED, 'Manager login');
    }

    protected function handle(
        ManagersEntity $managersEntity,
        ManagerTwoFactor $twoFactor,
        ManagerSessions $managerSessions
    ): int {
        $login = (string)$this->input->getArgument('login');
        $manager = $login !== '' ? $managersEntity->get($login) : null;
        if (!is_object($manager) || empty($manager->id)) {
            $this->output->writeln("<error>Manager \"{$login}\" not found.</error>");

            return Command::FAILURE;
        }

        $managerId = (int)$manager->id;
        if (!$twoFactor->disable($managerId, null, 'cli')) {
            $this->output->writeln("Two-factor authentication of \"{$manager->login}\" is not enabled, nothing was changed.");

            return Command::FAILURE;
        }

        $revoked = $managerSessions->revokeAll($managerId, null, 'cli');
        $this->output->writeln("Two-factor authentication of \"{$manager->login}\" is disabled, sessions ended: {$revoked}.");
        $this->output->writeln('The manager signs in with the password and can enable 2FA again in the profile.');

        return Command::SUCCESS;
    }
}
