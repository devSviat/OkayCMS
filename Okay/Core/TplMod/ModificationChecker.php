<?php


namespace Okay\Core\TplMod;


use Okay\Core\Modules\DTO\ModificationDTO;
use Okay\Core\Modules\DTO\TplChangeDTO;
use Okay\Core\TplMod\DTO\CheckResultDTO;
use Okay\Core\TplMod\Nodes\BaseNode;

/**
 * Рахує те, що робив би TplMod, не вставляючи нічого.
 *
 * Шлях шаблона розвʼязується так само, як у Design::applyTplModifiers(): значення
 * "file" з module.json порівнюється як суфікс шляху, тому листи (html/email/),
 * backend/design/html/components/ і власні шаблони модулів працюють без окремих випадків.
 */
class ModificationChecker
{
    private TplMod $tplMod;
    private Parser $parser;

    /** @var array<string, string[]> */
    private array $templatesByRoot = [];

    /** @var array<string, BaseNode> */
    private array $parsed = [];

    public function __construct(TplMod $tplMod, Parser $parser)
    {
        $this->tplMod = $tplMod;
        $this->parser = $parser;
    }

    /** @return string[] */
    public static function frontRoots(string $rootDir, string $theme): array
    {
        $rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return array_merge(
            [$rootDir . 'design' . DIRECTORY_SEPARATOR . $theme],
            (array)glob($rootDir . 'Okay/Modules/*/*/design/html')
        );
    }

    /** @return string[] */
    public static function backendRoots(string $rootDir): array
    {
        $rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return array_merge(
            [$rootDir . 'backend' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'html'],
            (array)glob($rootDir . 'Okay/Modules/*/*/Backend/design/html')
        );
    }

    /**
     * @param ModificationDTO[] $modifications
     * @param string[] $roots
     * @return CheckResultDTO[]
     */
    public function check(string $module, array $modifications, array $roots): array
    {
        $results = [];
        foreach ($modifications as $modification) {
            $candidates = $this->candidates($modification->getFile(), $roots);
            foreach ($modification->getChanges() as $change) {
                $results[] = $this->checkChange($module, $modification->getFile(), $change, $candidates);
            }
        }

        return $results;
    }

    /** @param string[] $candidates */
    private function checkChange(string $module, string $file, TplChangeDTO $change, array $candidates): CheckResultDTO
    {
        $anchor = $change->getFind() !== '' ? $change->getFind() : $change->getLike();

        if ($candidates === []) {
            return new CheckResultDTO($module, $file, $anchor, CheckStatus::FileMissing, [], 0);
        }

        $matchedFiles = [];
        $matchCount = 0;
        $resolvedCount = 0;

        foreach ($candidates as $path) {
            $matches = $this->tplMod->findMatches($this->parse($path), $change);
            if ($matches === []) {
                continue;
            }

            $matchedFiles[] = $path;
            $matchCount += count($matches);
            foreach ($matches as $matchedNode) {
                if ($this->tplMod->resolveTarget($matchedNode, $change) !== null) {
                    $resolvedCount++;
                }
            }
        }

        if ($matchCount === 0) {
            $status = CheckStatus::NoAnchor;
        } elseif ($resolvedCount === 0) {
            $status = CheckStatus::ChainBroken;
        } elseif ($matchCount > 1) {
            $status = CheckStatus::Multiple;
        } else {
            $status = CheckStatus::Ok;
        }

        return new CheckResultDTO($module, $file, $anchor, $status, $matchedFiles, $matchCount);
    }

    /**
     * @param string[] $roots
     * @return string[]
     */
    private function candidates(string $file, array $roots): array
    {
        $suffix = DIRECTORY_SEPARATOR . ltrim($file, '/' . DIRECTORY_SEPARATOR);

        $candidates = [];
        foreach ($roots as $root) {
            foreach ($this->templates($root) as $path) {
                if (str_ends_with($path, $suffix)) {
                    $candidates[] = $path;
                }
            }
        }

        return $candidates;
    }

    /** @return string[] */
    private function templates(string $root): array
    {
        if (isset($this->templatesByRoot[$root])) {
            return $this->templatesByRoot[$root];
        }

        $templates = [];
        if (is_dir($root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if ($item->isFile() && $item->getExtension() === 'tpl') {
                    $templates[] = $item->getPathname();
                }
            }
            sort($templates);
        }

        return $this->templatesByRoot[$root] = $templates;
    }

    private function parse(string $path): BaseNode
    {
        // findMatches()/resolveTarget() дерево не міняють, тож розбір кешується
        return $this->parsed[$path] ??= $this->parser->parse(file_get_contents($path));
    }
}
