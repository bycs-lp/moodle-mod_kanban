# Kanban

This activity supports using kanban method for managing projects or learning processes inside a moodle course.
This plugin is not built for improving course layout or sth similar, there are already other plugins that are way better
for this purpose (e.g. mod_board).

## Features

Within a kanban activity there can be several types of boards:

* The course board, accessible to everyone who has access to the activity (unless it's disabled in favour of personal
  boards).
* Personal boards for each user (accessible only by the user or with one of the viewallboards / editallboards
  functions): Can be enabled in the activity settings
* Group boards: Can be enabled in the activity settings (visible or separate groups)
* Template boards: Anyone with the manageboards capability can copy an existing board as a template board. Any board
  created in the Kanban activity afterwards will be copied from this board (no user data is copied). Template boards are
  also subject to the backup/restore process even if no user data is included.

If you have permission to access boards other than the course board, you can choose to switch to a user/group or
template board from the board action menu. The board title always indicates which board you are currently using.

If there is no template, a new board will consist of the three classic columns "Todo", "Doing" and "Done". Boards can be
deleted. When they are accessed again, a new board is created from the template.

You can add columns and cards by hovering between two existing columns/cards and clicking on the plus sign. Change their
titles by clicking on them or selecting 'Edit details' from the action menu. Cards/columns can be moved by dragging and
dropping (not yet on touch devices) or by selecting 'Move' from the action menu.

If you want to avoid moving / renaming columns accidentally, you can lock them using the action menu. You can also lock
all columns on the board, which will also prevent you from adding new columns (as this may not be necessary after you
have set up your board).

Cards can be assigned to yourself (via the action menu) or to (multiple) others via "Edit details". Cards can have a
description and attachments that explain the card in more detail. You can set a due date for a card and an alternative
notification date. Due dates are also added to your calendar. You can always edit cards you have created.
Cards can be marked as closed (in which case changing the title is disabled) or reopened. You can open a discussion for
the card (small chat, text only).
Columns can be set to automatically close cards when moved there (the Done column does this by default), and to hide
closed cards (they are not deleted, but you can make them visible again by clicking the eye icon at the top of the
column).

Notifications can be sent in the following cases:

* You have been (un)assigned to a card
* A card you are assigned to has been moved, discussed, closed or reopened.
* A card you are assigned to (and which is not closed) is due.

You can enable history in the activity settings (if your server allows it): It makes changes to cards visible (e.g. when
it was added / moved / closed / ... and by whom).

You can also move a particular map to all maps (including the template) in the action menu. This will create a copy of
the card and place it in the first available column of the board. If there is already an old copy of the card, the
existing copy will be updated (the position, assignees and discussion will not be changed).

While working with the board, it always fetches changes from the server to allow concurrent access (there is no locking
mechanism, so race conditions may occur in some cases). Live update can be disabled on the server, and the pull interval
can be changed (default is 10 seconds).

The plugin supports automatic completion by a certain amount of created and / or completed cards.

## Requirements

The plugin requires at least Moodle 4.1. Support for outdated Moodle versions will be dropped automatically - you will
have to use a recent Moodle version, if you want to use the latest version of this plugin.

Javascript has to be enabled as the plugin uses the reactive components of Moodle. There is no replacement, if
Javascript is disabled.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/mod/kanban

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2026 ISB Bayern, Stefan Hanauska <stefan.hanauska@csg-in.de>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
