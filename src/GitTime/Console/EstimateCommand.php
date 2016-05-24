<?php

namespace GitTime\Console;


use GitWrapper\GitWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EstimateCommand extends Command
{
    protected $baseDir = '';

    protected function configure()
    {
        $this->addArgument(
            'path',
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Path or files to check.'
        );

        $this->addOption(
            'commits',
            null,
            InputOption::VALUE_OPTIONAL,
            'History to look in between like 070816..HEAD or 15feb89..060414 .'
        );

        $this->addOption(
            'max-invest',
            null,
            InputOption::VALUE_OPTIONAL,
            'Maximum time to add to a commit.',
            1800
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $topLevel = $this->getBaseDir();


        $logArguments = ['log', '--pretty=%h %cI %s'];

        if ($input->getOption('commits')) {
            $logArguments[] = $input->getOption('commits');
        }

        if ($input->getArgument('path')) {
            $logArguments[] = $input->getArgument('path');
        }

        $git = $this->getGit();
        $git->clearOutput();
        $git->run($logArguments);
        $log = explode(PHP_EOL, trim($git->getOutput()));

        $commitKeys = ['hash', 'date', 'subject'];

        $dawnOfTime = new \DateTime();
        $dawnOfTime->setTimestamp(0);

        $prevCommit = [
            'hash' => '',
            'date' => $dawnOfTime,
            'subject' => '',
            'comulated' => 0,
        ];

        $logParsed = [];
        foreach ($log as $commit) {
            $currentCommit = array_combine($commitKeys, explode(' ', $commit, 3));
            $currentCommit['date'] = new \DateTime($currentCommit['date']);

            $currentCommit['invest'] = $currentCommit['date']->getTimestamp() - $prevCommit['date']->getTimestamp();

            if ($currentCommit['invest'] > $input->getOption('max-invest')) {
                $currentCommit['invest'] = $input->getOption('max-invest');
            }

            $currentCommit['comulated'] = $prevCommit['comulated'] + $currentCommit['invest'];

            $logParsed[] = $currentCommit;
            $prevCommit = $currentCommit;
        }

        $table = new Table($output);

        $table->setHeaders(
            [
                'Hash',
                'Date',
                'Time taken',
                'Comulated',
            ]
        );

        foreach ($logParsed as $item) {
            $table->addRow(
                [
                    $item['hash'],
                    $item['date']->format('Y-m-d H:i'),
                    gmdate("H:i:s", $item['invest']),
                    gmdate('H:i:s', $item['comulated']),
                ]
            );
        }

        $table->render();
    }

    /**
     * @return string
     */
    protected function getBaseDir()
    {
        if ($this->baseDir) {
            return $this->baseDir;
        }

        $git = $this->getGit();

        $git->clearOutput();
        $git->run(['rev-parse', '--show-toplevel']);
        $topLevel = trim($git->getOutput());

        return $this->baseDir = $topLevel;
    }

    /**
     * @return \GitWrapper\GitWorkingCopy
     */
    protected function getGit()
    {
        $gitWrapper = new GitWrapper();
        $git        = $gitWrapper->workingCopy(getcwd());

        return $git;
    }
}