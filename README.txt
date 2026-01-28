//--------------------
// READ ME
//--------------------
BellaBook3.8 Copyright © Jem Turner 2004-2007,2008,2013

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.



Support is given at my leisure. If you need help, first check:
http://codegrrl.com/forums/index.php?showforum=39
..to make sure your problem isn't covered there. If it isn't, post there or 
feel free to contact me: jem@jemjabella.co.uk


//--------------------
// INSTRUCTIONS
//--------------------
1. Customise prefs.php - set your username, password and various preferences
2. Upload all of the files to a directory, upload the smilies directory if you want emoticons

3. Set permissions on all guestbook data files (entries.txt, tempentries.txt, spamwords.txt, iplist.txt, websites.txt) so they are readable and writable by the web server user only (e.g., chmod 640 or 600). Do not make them world-writable or world-readable.

If deploying with cPanel, you can automate this in your .cpanel.yml:

		post_deploy:
			- chmod 640 entries.txt tempentries.txt spamwords.txt iplist.txt websites.txt

That's it, you're ready to go!


//--------------------
// CHANGELOG
//--------------------

--------------------
PROJECT MODERNIZATION & ENHANCEMENTS (2026)
--------------------
This project is a heavily modernized and security-enhanced fork of the original BellaBook guestbook by Jem Turner.

Major changes and new features since the original:

- **Security:**
	- Hardened admin authentication (SHA256, secure cookies, CSRF protection)
	- Path traversal and input validation throughout
	- XSS prevention and output escaping
- **Newline Handling:**
	- Consistent storage of newlines as literal \n in entry files
	- Robust display logic: converts \n to <br> for HTML, supports legacy real newlines
- **Link Summarization:**
	- Automatic extraction and summarization of all URLs and plain dotted domains (e.g., mesh.net.nz) in entries
	- Summaries are cached and included in admin email reports, with privacy-compliant error handling
	- Admin interface to view and manage the website summary cache
- **Admin & Moderation:**
	- Improved entry approval, editing, and deletion
	- Plain text email report for easy collation and review
	- All admin actions protected by CSRF tokens
- **Privacy & Compliance:**
	- No raw or clickable links in email reports; all domains are censored (e.g., [mesh[dot]net[dot]nz])
	- Error messages in summaries never include actual URLs
- **Codebase Modernization:**
	- Refactored for maintainability and extensibility
	- Shared library for link extraction and summarization
	- Improved UTF-8 support and emoji handling
- **UI/UX:**
	- Modernized admin and public views
	- Optional debug output for message inspection
--------------------

New features/fixes in version 3.8

1. Better spam protection in sign.php taken from Jem's Mail Form
2. Improved titles to fix Google Webmaster Tools warnings
3. Changed buffer size to unbuffered to hopefully curb file truncation
4. All files now UTF-8 without BOM to improve foreign language support


New features/fixes in version 3.7

1. Correct a regex error in sign.php/admin.php
2. Slightly better bot checking (from NinjaLinks) in config.php
3. Release under GPL licensing
4. Corrected typo causing strlen to fail in sign.php
5. Email 'breakage' as per BellaBuffs to curb potential for spam - sign.php/config.php/admin.php/index.php


New features/fixes in version 3.6

1. Fixed spam words problem caused by errant new lines
2. Tighter, lightweight code in admin.php
3. More reliant on functions over files/invidiual lines of code
4. Mass approve feature
5. Fix datestamp bug (not picking up custom set date)


New features/fixes in version 3.5

1. Moved preferences to separate file for easier future upgrades
2. Added further known spam bots
3. Bring sign sanitisation inline
4. Hidden field spam protection attempt


New features/fixes in version 3.4b

1. Fixed the spam word protection which wasn't working
2. Optional disallow links for extra spam protection


New features/fixes in version 3.4

1. More compact/compressed code in sign.php
2. More rigorous checking of input/server-set variables in sign.php
3. Fixed the head navigation bug causing problems with certain versions of php
4. Changed layout to more basic, typical guestbook style
5. Line breaks allowed in entries
6. Fixed links in footer/readme.txt in relation to new location of script


New features/fixes in version 3.3

1. Removed version number from footer
2. Improved/compressed code in admin.php to cut down on bloat
3. Checks for invalid characters in spam words
4. More rigorous checking of data input in admin.php
5. Prev/Next added to entry pagination on index.php
6. Customisable 'Return to site' link added to footer.php
7. Emoticons included - turn them on or off
8. Captcha included - turn it on or off


New features/fixes in version 3.2

1. States whether email required, and whether or not it's displayed
2. Optional moderation feature
3. Improved spam filtering
4. Pagination as standard - in admin panel as well
5. Entry management combined in admin panel


New features/fixes in version 3.1

1. Bad word blocking
2. IP blocking
3. Edit/delete entry now inside control panel
4. Flood control
5. Optional e-mail address display
6. Optional required fields
7. Multiple built-in themes
8. New, more secure login scripting
9. Ability to stay logged in to control panel
10. "Space out" big words to retain layout shape


//--------------------
// CREDITS
//--------------------
Mucho thanks go to the following people for helping with BellaBook:

Amelie	- not-noticeably.net (comprehensive bug testing)
Katy	- cathode-ray-coma.co.uk (bug testing + pagination!)
Jim		- fewl.net (original testing + security advice)
Dieter	- worldofbelushi.de (security brainstorming)
Dave	- thebritishbeardclub.org (bug reporting, ideas in 3.8)


Smilies and icons from:
Mark 	- famfamfam.com (great stuff!)