# git time

## Estimate the time taken on a project or feature

Check how long you took for that?

    git-time estimate src/my-feature/foo.php
    
    # or for a specific time range
    git-time estimate 13ea7dd..HEAD
    
    # or both
    git-time estimate 13ea7dd..HEAD  src/my-feature/foo.php


The result will be shown in a table:

    +---------+------------------+------------------------------------------+-------------+-------------+
    | Hash    | Date             | Message                                  | Duration    | Cumulated   |
    +---------+------------------+------------------------------------------+-------------+-------------+
    | 18b978f | 2016-05-23 21:51 | initial empty commit                     |          1m |          1m |
    | feced85 | 2016-05-24 07:52 | Estimate taken time with max cap         |         30m |         31m |
    | d2c50ce | 2016-05-24 08:20 | Fetch parent commits                     |         28m |         59m |
    | d21fd09 | 2016-05-24 08:38 | Calculate time related to parent commit. |         19m |      1h 18m |
    +---------+------------------+------------------------------------------+-------------+-------------+


## Why?

The biggest question with a simple answer:

- Estimate the **cost** of a feature.
- Another customer needs a solution you once wrote - estimate it's **value**.
- You need to **clock the day at work**, so you take a look what has taken how long.

Might be all. Did I miss something?


## How it works

Imagine your history with these commits:

    Initial commit              second                 third          fourth
                |                  |                     |               |
                |--- 10 minutes ---|------ ~7 days ------|-- 3 minutes --|
                |                  |                     |               |
    last week 12:00              12:10           today 10:20           10:23
    

Now `git-time` starts estimating the time you took for this project:

- The **initial commit** itself will count as **one minute**.
  You don't really need long for opening a GIT repo.
  If so change this time via `--no-parent--time` and set it to hours if you take that long ;)
- From **initial commit** to **second** it took you **10 minutes** for the next commit.
  That's okay, we sum that up to 11 Minutes now.
- But from **second** to **third** you fell asleep and continue to work one week (or day) later.
  This is a gap of days but `git-time` limits this down to **30 minutes** - the time a change might need.
  If you don't commit often or take longer, then change this limit via the `--max-invest` option.
- The last and **fourth** commit is easy.
  Right **3 minutes** after the previous one and this will make 44 minutes in total for this project.
  Pretty small one.

As confusing as this sounds - `git-time` will give you a cleaner nicer look at this:

    +---------+------------------+----------------------+-------------+-------------+
    | Hash    | Date             | Message              | Duration    | Cumulated   |
    +---------+------------------+----------------------+-------------+-------------+
    | 18b978f | yesterday 12:00  | initial empty commit |          1m |          1m |
    | feced85 | yesterday 12:10  | second               |         10m |         11m |
    | d2c50ce | today 10:20      | third                |         30m |         41m |
    | d21fd09 | today 15:23      | fourth               |          3m |         44m |
    +---------+------------------+----------------------+-------------+-------------+
