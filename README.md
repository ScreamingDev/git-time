# git time

## Estimate the time taken on a project or feature

Check how long you took for that:

    git-time estimate src/my-feature/foo.php
    
    # or for a specific time range
    git-time estimate 13ea7dd..HEAD
    
    # or both
    git-time estimate 13ea7dd..HEAD  src/my-feature/foo.php


The result will be shown in a table:

    +---------+------------------+----------------------------------------------+------------+-----------+
    | Hash    | Date             | Message                                      | Time taken | Summed up |
    +---------+------------------+----------------------------------------------+------------+-----------+
    | 18b978f | 2016-05-23 21:51 | initial empty commit                         | 00:30:00   | 00:30:00  |
    | feced85 | 2016-05-24 07:52 | Estimate taken time with max cap             | 00:30:00   | 01:00:00  |
    | d2c50ce | 2016-05-24 08:20 | Fetch parent commits                         | 00:27:28   | 01:27:28  |
    | d21fd09 | 2016-05-24 08:38 | Calculate time related to parent commit.     | 00:18:01   | 01:45:29  |
    | a7286a3 | 2016-05-24 08:47 | Correctly resolve parent                     | 00:09:21   | 01:54:50  |
    | 0af37c2 | 2016-05-24 08:48 | Suppress spaces in table                     | 00:00:32   | 01:55:22  |
    | 8e82704 | 2016-05-24 08:50 | Better check if current commit has no parent | 00:01:54   | 01:57:16  |
    +---------+------------------+----------------------------------------------+------------+-----------+

If a commit has no parent or the time can not be resolved, then it is shown as a 30 minute work as in the first lines.
Change this with the `--max-invest` option in seconds (e.g. 900 for 15 minutes). 