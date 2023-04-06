<?php

declare(strict_types=1);

namespace Imi\Phar;

trait TPharHandler
{
    private bool $required = false;

    protected function requiresRestart(bool $default): bool
    {
        $this->required = (bool) \ini_get('phar.readonly');

        return $this->required || $default;
    }

    protected function restart(array $command): void
    {
        if ($this->required)
        {
            # Add required ini setting to tmpIni
            $content = file_get_contents($this->tmpIni);
            $content .= 'phar.readonly=0' . \PHP_EOL;
            file_put_contents($this->tmpIni, $content);
        }

        parent::restart($command);
    }
}
