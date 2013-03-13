php-mybb-registration-email-check
=================================

TL;DR
--------
A simple plugin for <a href="http://www.mybboard.net/">MyBB</a> which hooks into the registration process and verifies that the provided email address is a real one.

Details
-------

I wrote this PHP snippet, called Registration eMail Check (RMC), back in 2008 when I was maintaining an own <a href="http://www.mybboard.net/">MyBB</a> based forum. The plugin first verifies whether the email has a valid format, then compares the email host with a number of hosts which were explicitly disabled by the administrator (the plugin comes with a - at that time - decent list of well-known one-time address providers), and finally tries to communicate with the corresponding mail server in order to verify whether the address actually exists.

I wrote the plugin for two reasons: First, because I wanted to prevent automated bot registration with non-existing email addresses. When I look through lists of users waiting for activation I find an ever growing amount of accounts which were obviously generated automatically, using non-existing email addresses. Second, because I don't want people to use anonymous one-time email addresses when registering in forums where a certain mutual trust is mandatory.

The original blog post is here: http://digitalbreed.com/2008/mybb-registration-email-check

