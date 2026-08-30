<?php

namespace Okay\Core\Console\Commands\Release;

use Okay\Core\Config;
use Okay\Core\Console\Command;
use Okay\Core\Release\PackageBuilder;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'release:build-package', description: 'Builds a fork release package (zip + manifest + checksums).')]
class ReleaseBuildPackageCommand extends Command
{
    protected function configure(): void
    {
        $defaultRepoPath = dirname(__DIR__, 5);

        $this
            ->addOption('fork-version', null, InputOption::VALUE_REQUIRED, 'Fork version being released, e.g. 1.1.0')
            ->addOption('repo-path', null, InputOption::VALUE_REQUIRED, 'Repository root to package', $defaultRepoPath)
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Where to write the package', $defaultRepoPath . '/build/release')
            ->addOption('manifest', null, InputOption::VALUE_REQUIRED, 'Path to release-manifest.json', $defaultRepoPath . '/release-manifest.json')
            ->addOption('migrations', null, InputOption::VALUE_REQUIRED, 'Pending core migrations directory', $defaultRepoPath . '/release-migrations/pending')
            ->addOption('upstream-base', null, InputOption::VALUE_REQUIRED, 'Upstream OkayCMS version this release is based on (defaults to Config::$version at --repo-path)');
    }

    protected function handle(): int
    {
        $forkVersion = $this->input->getOption('fork-version');
        if (empty($forkVersion)) {
            $this->output->writeln('<error>--fork-version is required</error>');
            return Command::FAILURE;
        }

        $repoPath = $this->input->getOption('repo-path');
        $upstreamBase = $this->input->getOption('upstream-base');

        if (empty($upstreamBase)) {
            $config = new Config($repoPath . '/config/config.php', $repoPath . '/config/config.local.php');
            $upstreamBase = $config->version;
        }

        $builder = new PackageBuilder();

        $result = $builder->build(
            $repoPath,
            $this->input->getOption('manifest'),
            $forkVersion,
            $upstreamBase,
            $this->input->getOption('output-dir'),
            $this->input->getOption('migrations')
        );

        $this->output->writeln("Package built: {$result['zipPath']}");
        $this->output->writeln("Files: {$result['fileCount']}, migrations: {$result['migrationsCount']}");

        return Command::SUCCESS;
    }
}
