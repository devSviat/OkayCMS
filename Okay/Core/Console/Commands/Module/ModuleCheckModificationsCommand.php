<?php

namespace Okay\Core\Console\Commands\Module;

use Okay\Core\Console\Command;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Core\TplMod\CheckStatus;
use Okay\Core\TplMod\DTO\CheckResultDTO;
use Okay\Core\TplMod\ModificationChecker;
use Okay\Entities\ModulesEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'module:check-modifications',
    description: 'Checks that every modifications anchor declared in module.json still matches a template node.'
)]
class ModuleCheckModificationsCommand extends Command
{
    private const ANCHOR_EXCERPT_LENGTH = 60;

    protected function configure(): void
    {
        $this->setHelp(
            'A modifications anchor that no longer matches makes the module insert nothing, '
            . 'without an exception and without a log entry. This command reports such anchors.'
        );

        $this->addOption('all', null, InputOption::VALUE_NONE, 'Check disabled modules too.');
        $this->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme to check front modifications against.');
    }

    protected function handle(
        EntityFactory $entityFactory,
        Module $module,
        ModificationChecker $checker,
        FrontTemplateConfig $frontTemplateConfig
    ): int {
        $rootDir = dirname(__DIR__, 5);
        $theme = $this->input->getOption('theme') ?: $frontTemplateConfig->getTheme();

        if (!is_dir($rootDir . '/design/' . $theme)) {
            $this->output->writeln("<error>Theme '{$theme}' not found in design/</error>");

            return Command::FAILURE;
        }

        /** @var ModulesEntity $modulesEntity */
        $modulesEntity = $entityFactory->get(ModulesEntity::class);
        $filter = $this->input->getOption('all') ? [] : ['enabled' => 1];

        $results = [];
        $missingModules = [];
        foreach ($modulesEntity->find($filter) as $moduleRow) {
            $name = $moduleRow->vendor . '/' . $moduleRow->module_name;

            if ($module->moduleDirectoryNotExists($moduleRow->vendor, $moduleRow->module_name)) {
                $missingModules[] = $name;
                continue;
            }

            $params = $module->getModuleParams($moduleRow->vendor, $moduleRow->module_name);

            $results = array_merge(
                $results,
                $checker->check($name, $params->getFrontModifications(), ModificationChecker::frontRoots($rootDir, $theme)),
                $checker->check($name, $params->getBackendModifications(), ModificationChecker::backendRoots($rootDir))
            );
        }

        return $this->report($results, $missingModules, $theme);
    }

    /**
     * @param CheckResultDTO[] $results
     * @param string[] $missingModules
     */
    private function report(array $results, array $missingModules, string $theme): int
    {
        $verbose = $this->output->isVerbose();
        $failures = 0;

        $table = new Table($this->output);
        $table->setHeaders(['Module', 'File', 'Anchor', 'Status']);

        foreach ($results as $result) {
            if ($result->isFailure()) {
                $failures++;
            } elseif (!$verbose) {
                continue;
            }

            $table->addRow([
                $result->getModule(),
                $result->getFile(),
                $this->excerpt($result->getAnchor()),
                $this->formatStatus($result),
            ]);
        }

        foreach ($missingModules as $name) {
            $table->addRow([$name, '-', '-', '<error>MODULE MISSING</error>']);
        }

        $table->render();

        $this->output->writeln(sprintf(
            'Theme: %s. Checked %d anchors, %d failed, %d modules enabled but absent from the code.',
            $theme,
            count($results),
            $failures,
            count($missingModules)
        ));

        if (!$verbose) {
            $this->output->writeln('<comment>Run with -v to see healthy anchors and matched files.</comment>');
        }

        return $failures === 0 && $missingModules === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function formatStatus(CheckResultDTO $result): string
    {
        return match ($result->getStatus()) {
            CheckStatus::Ok => '<info>OK</info>',
            CheckStatus::Multiple => sprintf(
                '<comment>MULTIPLE</comment> (%d nodes in %d files)',
                $result->getMatchCount(),
                count($result->getMatchedFiles())
            ),
            CheckStatus::NoAnchor => '<error>NO ANCHOR</error>',
            CheckStatus::ChainBroken => '<error>CHAIN BROKEN</error> (anchor found, closest/children did not)',
            CheckStatus::FileMissing => '<error>FILE MISSING</error>',
        };
    }

    private function excerpt(string $anchor): string
    {
        $anchor = trim(preg_replace('~\s+~', ' ', $anchor));

        return mb_strlen($anchor) > self::ANCHOR_EXCERPT_LENGTH
            ? mb_substr($anchor, 0, self::ANCHOR_EXCERPT_LENGTH - 1) . '…'
            : $anchor;
    }
}
