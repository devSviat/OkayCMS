<?php

namespace Okay\Core\Console\Commands\Features;

use Okay\Admin\Helpers\BackendFeaturesValuesHelper;
use Okay\Core\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/** Без --apply лише показує план. Логіка — BackendFeaturesValuesHelper::repairCsvQuoteDamage(). */
#[AsCommand(name: 'features:repair-csv-quotes', description: 'Repair feature values damaged by CSV backslash escaping (dry run without --apply)')]
class FeaturesRepairCsvQuotesCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the changes');
    }

    protected function handle(BackendFeaturesValuesHelper $featuresValuesHelper): int
    {
        $apply = (bool)$this->input->getOption('apply');
        $report = $featuresValuesHelper->repairCsvQuoteDamage($apply);

        $merged = 0;
        $repairedInPlace = 0;
        foreach ($report as $group) {
            $merged += count($group->merged_ids);
            $repairedInPlace += $group->survivor_clean ? 0 : 1;
            $this->output->writeln(sprintf(
                'feature %d: "%s" <- #%d %s%s',
                $group->feature_id,
                $group->value,
                $group->survivor_id,
                $group->survivor_clean ? '(already clean)' : '(repaired)',
                $group->merged_ids !== [] ? ', merged #' . implode(', #', $group->merged_ids) : ''
            ));
        }

        $this->output->writeln(sprintf(
            '%s: %d groups, %d repaired in place, %d merged into another value.',
            $apply ? 'Done' : 'Dry run, nothing written (add --apply)',
            count($report),
            $repairedInPlace,
            $merged
        ));

        return Command::SUCCESS;
    }
}
