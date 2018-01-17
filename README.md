# git time

- How much does that **feature cost**? `git time src/feature/path` 
- What did I do **today**? `git time --me -v --since=yesterday`
- Did we **invoice** everything? `git time -v --since="end of last month"`

The `-v` gives you a nice table showing the time spend on each commit.

## Installation

Works best as global composer package:

    composer global require sourcerer-mike/git-time
    
    # Do you have your composer bin accessible? If not ...
    echo 'PATH=$PATH:~/.config/composer/vendor/bin' >> ~/.bashrc

or local installation per project (`composer require sourcerer-mike/git-time:dev-master`).
You might like to add ` ~/.config/composer/vendor/bin/` to your PATH variable in Bash ;)


## Estimate the time taken on a project or feature

Check how long you took for that?

    git-time estimate src/my-feature/foo.php
    
    # or for a specific time range
    git-time estimate 13ea7dd..HEAD
    
    # or both
    git-time estimate 13ea7dd..HEAD  src/my-feature/foo.php
    
    # for a particular time range (e.g. today) and a single author(maybe for you to clock at work)
    git-time estimate -v --since 00:00 --author "Mike Pretzlaw"


The result will be shown in a table:

    +---------+------------------+------------------------------------------+-------------+-------------+
    | Hash    | Date             | Message                                  | Duration    | Cumulated   |
    +---------+------------------+------------------------------------------+-------------+-------------+
    | 18b978f | 2016-05-23 21:51 | initial empty commit                     |          1m |          1m |
    | feced85 | 2016-05-24 07:52 | Estimate taken time with max cap         |         30m |         31m |
    | d2c50ce | 2016-05-24 08:20 | Fetch parent commits                     |         28m |         59m |
    | d21fd09 | 2016-05-24 08:38 | Calculate time related to parent commit. |         19m |      1h 18m |
    +---------+------------------+------------------------------------------+-------------+-------------+


Might be all. Did I miss something?

## How it works

Imagine you run `git time -v` on these commits:

    Initial commit              second                 third          fourth
                |                  |                     |               |
                |--- 10 minutes ---|------ ~7 days ------|-- 3 minutes --|
                |                  |                     |               |
    last week 12:00              12:10           today 10:20           10:23
                |                  |                     |               |
                |--- 10 minutes ---|---- 30 minutes -----|-- 3 minutes --|
                |                  |  limited to maximum |               |
     Total of 43 minutes

The limit can be changed using `git time --max=60` (minutes)
and there is even more about this:

- The **initial commit** itself will count as **one minute**.
  You don't really need long for opening a GIT repo.
  If so change this time via `--no-parent-time` and set it to hours if you take that long ;)
- From **initial commit** to **second** it took you **10 minutes**.
  That's okay, we add that and have 11 minutes in total now.
- But from **second** to **third** you fell asleep and continue to work one week (or day) later.
  This is a gap of days but `git-time` limits this down to **30 minutes**.
  If you don't like this limit then change it via the `--max` option.
- The last and **fourth** commit is easy.
  Just **3 minutes** after the previous one which makes it 44 minutes in total for this project.
  Pretty small one.

Providing the `-v` flag will give you a cleaner nicer look at this:

    +---------+------------------+----------------------+-------------+-------------+
    | Hash    | Date             | Message              | Duration    | Cumulated   |
    +---------+------------------+----------------------+-------------+-------------+
    | 18b978f | yesterday 12:00  | initial empty commit |          1m |          1m |
    | feced85 | yesterday 12:10  | second               |         10m |         11m |
    | d2c50ce | today 10:20      | third                |         30m |         41m |
    | d21fd09 | today 10:23      | fourth               |          3m |         44m |
    +---------+------------------+----------------------+-------------+-------------+
