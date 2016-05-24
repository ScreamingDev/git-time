<?php

namespace GitTime\Console;


use GitWrapper\GitWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EstimateCommand extends Command
{
    protected $baseDir = '';
    protected $cliCols;

    protected $commitKeys = ['parent', 'hash', 'date', 'subject'];
    protected $subjectLength;

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
            'Maximum time to add to a commit',
            1800
        );

        $this->addOption(
            'no-parent-time',
            null,
            InputOption::VALUE_OPTIONAL,
            'Time to assign to commits without a parent',
            1
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $topLevel = $this->getBaseDir();

        $defaultLogArguments = ['log', '--no-merges', '--reverse'];
        $logArguments        = $defaultLogArguments;
        $logArguments[]      = '--pretty=%p %h %cI %s';

        $parentLogArguments   = $defaultLogArguments;
        $parentLogArguments[] = '--pretty=%h %cI %s';
        $parentLogArguments[] = '-1';

        if ($input->getOption('commits')) {
            $logArguments[] = $input->getOption('commits');
        }

        if ($input->getArgument('path')) {
            foreach ($input->getArgument('path') as $fileName) {
                $logArguments[] = $fileName;
            }
        }

        $git = $this->getGit();
        $git->clearOutput();
        $git->run($logArguments);
        $log = explode(PHP_EOL, trim($git->getOutput()));

        $dawnOfTime = new \DateTime();
        $dawnOfTime->setTimestamp(0);

        $prevCommit = [
            'parent'    => '',
            'hash'      => '',
            'date'      => $dawnOfTime,
            'subject'   => '',
            'cumulated' => 0,
        ];

        $logParsed = [];
        $maxInvest = $input->getOption('max-invest');

        foreach ($log as $commit) {
            $currentCommit = $this->parseLogLine($commit);

            if ( ! preg_match('@^[a-f0-9]{3,}$@', $currentCommit['hash'])) {
                // commit has no parent and needs different parsing
                $currentCommit           = array_combine(array_slice($this->commitKeys, 1), explode(' ', $commit, 3));
                $currentCommit['parent'] = '';
            }

            $currentCommit['date']      = new \DateTime($currentCommit['date']);
            $currentCommit['cumulated'] = $prevCommit['cumulated'];

            // calculate invest by comparing against parent
            if ($prevCommit['hash'] != $currentCommit['parent']) {
                // commits are not lined up: find parent
                $git->clearOutput();
                $findParent   = $parentLogArguments;
                $findParent[] = $currentCommit['hash'].'~1';
                $git->run($findParent);

                $prevCommit              = array_combine(
                    array_slice($this->commitKeys, 1),
                    explode(' ', trim($git->getOutput()), 3)
                );
                $prevCommit['date']      = new \DateTime($prevCommit['date']);
                $prevCommit['cumulated'] = $currentCommit['cumulated'];
            }

            $currentCommit['invest'] = $input->getOption('no-parent-time');

            if ($currentCommit['parent']) {
                $currentCommit['invest'] = min(
                    $maxInvest,
                    $currentCommit['date']->getTimestamp() - $prevCommit['date']->getTimestamp()
                );
            }

            // round seconds to one minute
            if ($currentCommit['invest'] % 60) {
                $currentCommit['invest'] += 60 - ($currentCommit['invest'] % 60);
            }

            $currentCommit['cumulated'] = $prevCommit['cumulated'] + $currentCommit['invest'];

            $logParsed[] = $currentCommit;
            $prevCommit  = $currentCommit;
        }

        $table = new Table($output);

        $table->setHeaders(
            [
                'Hash',
                'Date',
                'Message',
                "Duration",
                "Cumulated",
            ]
        );

        foreach ($logParsed as $item) {
            $table->addRow(
                [
                    $item['hash'],
                    $item['date']->format('Y-m-d H:i'),
                    $this->limitMessage($item['subject']),
                    $this->makeTimeFormat($item['invest']),
                    $this->makeTimeFormat($item['cumulated']),
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

    /**
     * @param $commit
     *
     * @return mixed
     */
    protected function parseLogLine($commit)
    {
        return array_combine($this->commitKeys, explode(' ', trim($commit), 4));
    }

    /**
     * @param $item
     *
     * @return mixed
     */
    protected function limitMessage($message)
    {
        return wordwrap($message, $this->getSubjectLength());
    }

    /**
     * @return mixed
     */
    protected function getSubjectLength()
    {
        if ($this->subjectLength) {
            return $this->subjectLength;
        }

        $this->subjectLength = $this->getCliCols()
                               - 1 // right table border
                               - 10 // border + hash
                               - 19 // border + date time
                               - 3 // message border and padding
                               - 14 // border + duration
                               - 14 // border + cumulative
        ;

        return $this->subjectLength;
    }

    /**
     * @return mixed
     */
    protected function getCliCols()
    {
        if ($this->cliCols) {
            return $this->cliCols;
        }

        $cliCols = trim(exec('tput cols'));

        if ( ! $cliCols) {
            $cliCols = 80;
        }

        return $this->cliCols = $cliCols;
    }

    /**
     * @param $timeFormat
     * @param $timestamp
     *
     * @return mixed
     */
    protected function makeTimeFormat($timestamp)
    {
        $timeFormat = '';
        if ($timestamp >= 86400) {
            $timeFormat .= sprintf('%2dd ', gmdate('d', $timestamp) - 1);
        }

        if ($timestamp >= 3600) {
            $timeFormat .= sprintf('%2dh ', gmdate('H', $timestamp));
        }

        $timeFormat .= sprintf('%2dm', gmdate('i', $timestamp));

        $timeFormat = str_pad($timeFormat, 11, ' ', STR_PAD_LEFT);

        return $timeFormat;
    }
}