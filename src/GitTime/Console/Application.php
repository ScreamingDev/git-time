<?php

namespace GitTime\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);

        $this->fetchCommands();
    }

    protected function fetchCommands()
    {
        $finder = new Finder();
        $finder->in(__DIR__)->files()->name('*Command.php');

        foreach ($finder as $commandFile) {
            /* @var SplFileInfo $commandFile */

            $currentClass = strtr(
                $commandFile->getRealPath(),
                [
                    rtrim(GIT_TIME_SRC_PATH, DIRECTORY_SEPARATOR) => '',
                    '/'                                           => '\\',
                    $commandFile->getBasename()                   => $commandFile->getBasename('.php'),
                ]
            );

            if ( ! class_exists($currentClass)) {
                continue;
            }

            if (false !== strpos($currentClass, '\\Abstract')) {
                continue;
            }


            $name = str_replace(__NAMESPACE__.'\\', '', $currentClass);
            $name = preg_replace('/Command$/', '', $name);
            $name = str_replace('\\', ':', $name);
            $name = trim($name, ':');
            $name = strtolower($name);

            /* @var AbstractCommand $command */
            $command = new $currentClass($name);

            if (false == $command instanceof Command) {
                continue;
            }

            $reflectClass = new \ReflectionClass($currentClass);
            $comment      = $reflectClass->getDocComment();
            preg_match('/\s\*\s.*\./', $comment, $description);
            $description = preg_replace('/^\s\*\s/', '', current($description));

            $command->setDescription($description);

            preg_match('/(?<=\/\*\*).*(?=\n\s*\*\s@)/s', $comment, $help);      // get long description
            $help = preg_replace('/\s*\*\s*/s', "\n", current($help));          // remove comment-star
            $help = preg_replace('/([^\n])\n{1}([^\n])/s', '$1 $2', $help);     // remove single new-lines
            $help = str_replace("\n", "\n ", $help);                            // indent a bit

            $command->setHelp($help);

            $this->add($command);
        }
    }
}