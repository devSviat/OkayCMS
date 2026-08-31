<?php

namespace Okay\Core\Console\Commands\Release;

use Okay\Core\Console\Command;
use Okay\Core\Release\CoreMigrationException;
use Okay\Core\Release\CoreMigrator;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Другий шлях доставки core-міграцій — для інсталяцій, які оновлюються не
 * самооновлювачем, а деплоєм з гіта чи образом (як broken).
 *
 * Оновлювач бере міграції з пакета релізу; тут вони беруться з дерева, куди
 * приїхали разом із кодом. Трекер `ok_core_migrations` спільний, тож обидва
 * шляхи не застосують одну міграцію двічі — і на інсталяції, яка колись
 * переїде з деплою на самооновлення, нічого не зламається.
 */
#[AsCommand(name: 'core:migrate', description: 'Applies pending fork core migrations.')]
class CoreMigrateCommand extends Command
{
    protected function configure(): void
    {
        $this->setHelp(
            "Applies fork core migrations shipped in 1DB_changes/fork.\n"
            . 'Safe to run on every deploy: already applied migrations are skipped.'
        );
    }

    protected function handle(CoreMigrator $migrator): int
    {
        $migrationsDir = dirname(__DIR__, 5) . '/1DB_changes/fork';

        try {
            $applied = $migrator->apply($migrationsDir);
        } catch (CoreMigrationException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');

            // Що встигло застосуватись до падіння — інакше наступний запуск
            // мовчки продовжить із середини, і ніхто не знатиме, з якого місця.
            if ($e->appliedNames !== []) {
                $this->output->writeln('Застосовано до падіння: ' . implode(', ', $e->appliedNames));
            }

            return Command::FAILURE;
        }

        if ($applied === []) {
            $this->output->writeln('Немає нових core-міграцій.');

            return Command::SUCCESS;
        }

        foreach ($applied as $name) {
            $this->output->writeln('Застосовано: ' . $name);
        }

        return Command::SUCCESS;
    }
}
