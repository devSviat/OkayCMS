<?php


namespace Okay\Core\TplMod\DTO;


use Okay\Core\TplMod\CheckStatus;

class CheckResultDTO
{
    /** @param string[] $matchedFiles */
    public function __construct(
        private string $module,
        private string $file,
        private string $anchor,
        private CheckStatus $status,
        private array $matchedFiles,
        private int $matchCount
    ) {
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getAnchor(): string
    {
        return $this->anchor;
    }

    public function getStatus(): CheckStatus
    {
        return $this->status;
    }

    /** @return string[] */
    public function getMatchedFiles(): array
    {
        return $this->matchedFiles;
    }

    public function getMatchCount(): int
    {
        return $this->matchCount;
    }
}
