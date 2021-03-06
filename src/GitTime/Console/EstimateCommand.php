<?php

namespace GitTime\Console;


use GitWrapper\GitWrapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
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
            'Path or files to check'
        );

        $this->addOption(
            'author',
            null,
            InputOption::VALUE_OPTIONAL,
            'Name of an author to sum up only his time'
        );

        $this->addOption(
            'commits',
            null,
            InputOption::VALUE_OPTIONAL,
            'History to look in between like 070816..HEAD or 15feb89..060414'
        );

        $this->addOption(
            'max-invest',
            null,
            InputOption::VALUE_OPTIONAL,
            'Maximum time to add to a commit',
            1800
        );

        $this->addOption(
            'max',
            null,
            InputOption::VALUE_OPTIONAL,
            'Maximum time to add to a commit',
            1800
        );

	    $this->addOption(
		    'me',
		    null,
		    InputOption::VALUE_NONE,
		    'Show only your commits'
	    );

        $this->addOption(
            'no-parent-time',
            null,
            InputOption::VALUE_OPTIONAL,
            'Time to assign to commits without a parent commit',
            1
        );

        $this->addOption(
            'progress',
            null,
            InputOption::VALUE_NONE,
            'Show progressbar while working'
        );

        $this->addOption(
            'since',
            null,
            InputOption::VALUE_OPTIONAL,
            'Time range to work with (e.g. "yesterday", "00:00" o\'clock or "2016-05-25")'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $topLevel = $this->getBaseDir();
        $git = $this->getGit();

        $defaultLogArguments = ['log', '--no-merges', '--reverse'];
        $logArguments        = $defaultLogArguments;
        $logArguments[]      = '--pretty=%p %h %cI %s';

        if ($input->getOption('me')) {
        	$git->run(['config', 'user.name']);
            $logArguments[]      = '--author';
            $logArguments[]      = trim($git->getOutput());
        }

        $parentLogArguments   = $defaultLogArguments;
        $parentLogArguments[] = '--pretty=%h %cI %s';
        $parentLogArguments[] = '-1';

        if ($input->getOption('author')) {
            $logArguments[] = '--author='.$input->getOption('author');
        }

        if ($input->getOption('since')) {
            $logArguments[] = '--since='. escapeshellarg($input->getOption('since'));
        }

        if ($input->getOption('commits')) {
            $logArguments[] = $input->getOption('commits');
        }

        if ($input->getArgument('path')) {
            foreach ($input->getArgument('path') as $fileName) {
                $logArguments[] = $fileName;
            }
        }

        $git->clearOutput();
        $git->run($logArguments);

        $log = explode(PHP_EOL, trim($git->getOutput()));
        $log = array_filter($log); // no empty lines

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

        $maxInvest = $input->getOption('max');
        if ($input->getParameterOption('--max-invest')) {
	        $maxInvest = $input->getOption('max-invest');

            $output->writeln(
                '<error>Option --max-invest is deprecated. Please use --max instead.</error>'
            );
        }

        // remove all empty lines, otherwise the parser will crash
        $log = array_filter($log);

        if ( ! $log) {
            $output->writeln('No commit found.');
            exit;
        }

        $totalTime = 0;

        if ($input->getOption('progress')) {
            $progressBar = new ProgressBar($output, count($log)+1);
        }

        foreach ($log as $commit) {
            if ($input->getOption('progress')) {
                $progressBar->advance();
            }
            $currentCommit = $this->parseLogLine($commit);

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
                $currentCommit['invest'] += 60 - ( $currentCommit['invest'] % 60 );
            }

            $currentCommit['cumulated'] = $prevCommit['cumulated'] + $currentCommit['invest'];
            $totalTime = $currentCommit['cumulated'];

            $logParsed[] = $currentCommit;
            $prevCommit  = $currentCommit;
        }

        if ($input->getOption('progress')) {
            $progressBar->finish();
            $progressBar->clear();
        }

        if ($output->isVerbose()) {
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

        $output->writeln('Total time: ' . $this->makeTimeFormat($totalTime));
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
        $tmp = explode(' ', trim($commit), 3);

        if ( ! preg_match('@^[a-f0-9]{3,}$@', $tmp[1])) {
            // commit has no parent and needs different parsing
            $currentCommit           = array_combine(array_slice($this->commitKeys, 1), explode(' ', $commit, 3));
            $currentCommit['parent'] = '';

            return $currentCommit;
        }

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
