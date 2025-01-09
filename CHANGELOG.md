# ChangeLog
## 2.0.5 (2025-01-09)
- Fix change user

## 2.0.4 (2025-01-06)
- Fix delete bookmark

## 2.0.3 (2025-01-02)
- Added Export/Import Dialog
- Added Dutch translation
- Added Chinese translation
- Fix sync import
- Removed hmarks

## 2.0.2 (2024-10-16)
- Added JSON debug function
- Added german GUI translation
- Fix empty import data

## 2.0.1 (2024-10-15)
- Removed unused db column
- Fixed issue with remove bookmark
- Add support for backup/restore client options

## 2.0.0 (2024-09-25)
- Added API and API description
- Added sort field for bookmarks
- Added ntfy support
- Removed Pushbullet support
- Fix some issues

## 1.9.1 (2024-04-28)
- Fix #80 for Extension
- Fix #81 for Extension
- Fix initial SQL
- Add function to remove questionmark from urlbar
- Added .htaccess file for Apache FastCGI workaround

## 1.9.0 (2023-11-24)
- Fix response
- Fix contextmenu Edge sidebar

## 1.8.9 (2023-11-18)
- Fix response
- Fix variable initialization
- Fix CSS
- Fix wording
- Fix messaging
- Added PWA / Service Worker
- Added Share Target
- Added indexedDB
- Added offline functions
- Added send later functions
- Removed background sync for privacy reasons

## 1.8.8 (2023-07-20)
- Added mouse middle click
- Fixed MySQL
- Fixed empty titles

## 1.8.7 (2023-07-03)
- Fixed right click Firefox Android
- Fixed dataset.url
- Fixed bookmark edit
- Added background to dialogs/menus

## 1.8.6 (2023-06-28)
- Open folders in tabs

## 1.8.5 (2022-12-12)
- Remove mark on esc
- Fixed login error

## 1.8.4 (2022-12-10)
- Fixed bookmark sort order
- Fixed bookmark move
- Fixed pushed sites
- Fixed index on move
- Fixed move multiple bookmarks

## 1.8.3 (2022-11-07)
- Updated foreign keys
- Reworked pages
- Added view

## 1.8.2 (2022-11-03)
- Added Foreign Key for bookmarks
- Added sync for tabs
- Fix for undisplayed bookmarks
- Updated SQL update

## 1.8.0 (2022-10-29)
- Added Foreign Key for bookmarks
- Reworked Move bookmarks
- Reworked Delete bookmarks

## 1.7.2 (2022-10-25)
- Added trigger for delete bookmark
- Added empty search field
- Added Token exchange
- Fixed Keyboard shortcuts

## 1.7.1 (2022-08-11)
- Fixed move bookmark
- Response first login

## 1.7.0 (2022-05-06)
- Reworked actions
- Reworked XHR requests
- Removed unused functions
- Added delete multiple bookmarks (Ctrl + Click > Del)
- Fix Re-Index folder
- Fix Rename client
- Other Small Fixes

## 1.6.7 (2022-03-19)
- Fixed delete bookmarks
- Fixed search
- Added colorize of logfile

## 1.6.6 (2022-03-08)
- Added token based login

## 1.6.5 (2022-02-24)
- Streamlined client info
- Fix encoding coming from browser
- Fix updateclient
- Minor Fixes

## 1.6.4 (2022-02-13)
- Send lastseen with pre-request

## 1.6.3 (2022-01-25)
- Add Logfile reload
- Add JSON header
- Add startup pre-request (in case other client made export)
- Fix client sort order
- Fix constants

## 1.6.2 (2021-11-24)
- Rework generate Chromium ID
- Fixed processing timestamp
- Set lastseen date only for startup sync
- Changed some requests

## 1.6.1 (2021-10-23)
- Fix notification not opening in new tab
- Fix adding bookmark
- Fix moving bookmark

## 1.6.0 (2021-07-29)
- Added option to stay logged in
- Added Load on demand for clients
- Added Load on demand for notifications
- Fix not showing moved bookmarks
- Fix rewrites
- Fix encoding some titles
- Fix empty notification list
- Fix mobile rename
- Fix delete notifications
- Removed unused session vars
- Moved assets

## 1.5.2 (2021-05-10)
- Write clientlist to disk, for debugging
- Rebuilding clientlist serverside
- Relocating log and debug files
- Fixed encoding issue

## 1.5.1 (2021-03-31)
- Fix for listing clients
- Fix for bookmark add response
- Fix encoding site title
- Fix get personal options

## 1.5.0 (2021-03-24)
- Added support for MySQL
- Re-Added back optional manual password

## 1.4.2 (2021-03-22)
- Changed feedback to client, when cant be registered
- Filter IP address for logfile

## 1.4.1 (2021-03-20)
- Fixed bug for user creation
- Fixed bug user delete
- Fixed login bug
- Changed Export behavior

## 1.4.0 (2021-03-17)
- Fixed #61
- Removed manual enter password
- Added password reset function
- Added e-mail field for user
- Changed login behavior
- Updated DB

## 1.3.7 (2021-03-05)
- Added drag&drop for bookmarks move
- Added function for Extension
- Fixed notifications
- Fixed import bookmarks
- Fixed delete notifications
- Replaced alerts with notifications

## 1.3.6 (2021-02-25)
- Added duplicates checker
- Fix contextmenu when manually adding bookmark

## 1.3.5 (2021-02-21)
- Fix for startup sync

## 1.3.4 (2021-02-11)
- Fix for js error

## 1.3.3 (2021-01-15)
- Removed hidden forms
- Cleanup functions
- Check database update only on new version
- Fix for import from Chromium

## 1.3.2 (2021-01-11)
- Added login/logout form

## 1.3.1 (2021-01-06)
- Added index/trigger/foreign key to DB
- Added DB init/update script
- Fix Export date
- Fix Client name
- Fix CSS
- Fix not opening duplicate foldernames
- Fix login/logout
- Reworked logout

## 1.3.0 (2021-01-02)
- Regenerate index at bookmark removal
- Add context menu for non-system-folders
- Removed unused session variable
- Changed Client Updates
- Changed Import to transaction
- Changed SQL handling
- Changed POST requests
- Reworked Actions
- Small fixes

## 1.2.17 (2020-12-12)
- Fix XHR response
- Changed tesfile creation

## 1.2.16 (2020-12-12)
- Fix for folder selection

## 1.2.15 (2020-12-09)
- Changes in GUI
- Changes in include files
- Fix for import bookmark with quote
- Fix for encoding

## 1.2.14 (2020-12-05)
- Reworked menu
- Reworked Pushbullet
- Reworked CSS
- Fixed adding new bookmark
- Fixed folder variable
- Added client id as hint

## 1.2.13 (2020-11-30)
- Fixed adding new folder
- Fixed adding new folder from browser

## 1.2.12 (2020-10-19)
- Add target info to notifications
- Fix for removing notifications
- Add function to save json file for corrupt bookmark export
- Fix when user has no client registered
- Fix for initialising DB
- Add initial user/password to config file
- Fix error in config file
- Fix for creating database/directory
- Added search by url
- Add creation of folders
- Changed usage of srcElement
- Fixed export error with malformed JSON
- Fixed display of lastlogin

## 1.2.11 (2020-09-09)
- Detect browser at server level

## 1.2.10 (2020-09-06)
- Fix removing clients
- Fix for css notifications table
- Fix first client registration
- Fix for editing client bookmarks

## 1.2.9 (2020-09-04)
- Fixed a small export issue
- Set lastseen for client only on import, export, startup sync
- Fixed html encoding when exporting bookmarks
- Fix removing clients

## 1.2.8 (2020-08-30)
- Fixed adding single bookmark

## 1.2.7 (2020-08-17)
- Rework editing of clients
- Rework editing of notifications
- Do not return current client
- Fixed bug in client update
- Optimized Styles

## 1.2.6 (2020-08-16)
- Fix renaming of clients
- Fix client menu

## 1.2.5 (2020-08-14)
- Notifications can now send to dedicated clients
- Small fixes

## 1.2.4 (2020-08-06)
- re-added search feature

## 1.2.3 (2020-07-30)
- Admin level required to show logfile
- Removed all jQuery dependencies
- Reworked mobile view

## 1.2.2 (2020-07-24)
- Validate URL's before add them
- Fix for pushed URL's

## 1.2.1 (2020-07-17)
- Fix for pushed URL's

## 1.2.0 (2020-07-10)
- Added support for pushed links

## 1.1.1 (2020-02-24)
- Added bookmarklet to add current site as bookmark

## 1.1.0 (2020-02-22)
- Added HTML export to export bookmarks in HTML format for import in other browsers
- Updated jquery library
- removed PWA functions

## 1.0.0 (2019-01-13)
- Added experimental support for Chrome Extension
- Minify some files
- Fixed some small bug for initial sync

## 0.9.15 (2018-12-23)
- Added PWA mode for mobile browsers
- Moved configuration to separate file

## 0.9.14 (2018-12-03)
- added search function

## 0.9.13 (2018-12-03)
- fixed dialog hiding
- fixed unique id issue
- added function to select device name

## 0.9.12 (2018-12-01)
- added edit function
- added move function
- cleaned css

## 0.9.11 (2018-11-27)
- better mobile browser detection
- added user management
- removed import for file

## 0.9.10 (2018-11-25)
- check for already existing bookmarks added

## 0.9.9 (2018-11-24)
- added plugin import function
- added plugin export function

## 0.9.8 (2018-11-20)
- changed lastlogin recording

## 0.9.7 (2018-11-19)
- added mobil theme
- added mobile scripting

## 0.9.6 (2018-11-18)
- added startup sync for firefox
- changed login process

## 0.9.5 (2018-10-03)
- changed WebGUI to AJAX calls