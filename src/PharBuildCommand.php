<?php

declare(strict_types=1);

namespace Imi\Phar;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PharBuildCommand extends Command
{
    protected static $defaultName = 'build';

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $container = implode('、', Constant::CONTAINER_SET);
        $this
            ->addArgument('container', InputArgument::OPTIONAL, "支持容器, {$container}")
            ->addOption('init', null, InputOption::VALUE_NONE, '初始化配置文件')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'phar 输出路径, 默认以配置文件为准')
            ->setDescription('构建 phar');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $baseDir = getcwd();
        $configFile = "{$baseDir}/" . Constant::CFG_FILE_NAME;
        $checkConfig = is_file($configFile);

        $output->writeln("Project dir : <info>{$baseDir}</info>");
        $output->writeln(sprintf('Check config: <info>%s</info>', $checkConfig ? '<info>success</info>' : '<comment>fail</comment>'));

        if ($input->getOption('init'))
        {
            if (file_exists($configFile))
            {
                /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('The configuration file already exists, whether to overwrite it? (y or n)', false);

                if (!$helper->ask($input, $output, $question))
                {
                    return Command::SUCCESS;
                }
            }
            $output->writeln("Write {$configFile} .");
            copy(__DIR__ . '/../config/imi-phar-cfg.php', $configFile);
            $output->writeln('Configuration file initialization completed.');

            return self::SUCCESS;
        }

        if (!$checkConfig)
        {
            $output->writeln('Config file does not exist, execute "vendor/bin/imi-phar build --init" initialize configuration file.');

            return self::INVALID;
        }

        $projectAutoload = $baseDir . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($projectAutoload))
        {
            require $projectAutoload;
        }

        try
        {
            $config = require $configFile;
        }
        catch (\Throwable $th)
        {
            $output->writeln('Config load fail: ' . $th);

            return self::FAILURE;
        }

        if (!\is_array($config))
        {
            $output->writeln('Config load fail: invalid config');

            return self::FAILURE;
        }

        $container = $input->getArgument('container');

        $pharOutput = $input->getOption('output') ?? $config['output'];

        if (empty($pharOutput))
        {
            $output->writeln('Output phar file value invalid');

            return self::FAILURE;
        }

        $config['output'] = $pharOutput;

        $phar = new PharService(
            $output,
            $baseDir,
            $config,
        );

        if (!$phar->checkConfiguration())
        {
            return self::INVALID;
        }

        if (!$phar->build($container))
        {
            return self::INVALID;
        }

        if (!$phar->outputResources())
        {
            return self::INVALID;
        }

        return self::SUCCESS;
    }
}
