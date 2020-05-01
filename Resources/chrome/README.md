## Kimai Timesheet Bridge

https://www.neontribe.co.uk, version 0.0.1

This extension allows time to be logged directly from github to a kimai server.  You will need an working install of kimai (https://www.kimai.org/) to connect to.

While this will work for any kimai server to get function add this Kimai plugin: https://github.com/neontribe/ChromeExtBundle

    curl http://localhost:8001/api/timesheets?tags=someproject-issues-123 -X GET -H "accept: application/json" -H "X-AUTH-USER: susan_super" -H "X-AUTH-TOKEN: kitten"

