# Changelog

## 1.0.0  (v1.0, first stable release)
No new features over 0.20.0; this marks the plugin as ready for use. What v1.0 includes:
- Device records with ALD-A001 style IDs, status, owner, department, history notes and photos.
- Self-service intake form (public link) with a required screenshot; new submissions
  wait as Pending Review until approved, then their details lock.
- All Devices: search, status chips, Filters dropdown (Department, Brand, Size, Year,
  Chip, Memory). Pagination is optional (Settings), 10 per page when on.
- Square QR stickers: one Size (mm) box on the device page and on the full-width
  Print Stickers page; the device ID is drawn in the QR centre.
- Settings (left menu): General, Access & Roles (Admin / Manager / User),
  Export CSV (with status filter), Google Sheets live link, Demo data.
- Light / dark theme and full-screen toggle on every plugin page.
Upgrade note: install over the old version; the database updates itself.

## 0.20.0
- Settings page redesigned: a menu on the left (General, Access & Roles,
  Export (CSV), Google Sheets, Demo data) and one section at a time on the right,
  instead of five cards squeezed side by side. The menu shows Google Sheets On/Off
  and how many demo devices exist. On a narrow screen the menu becomes a row on top.
- Every action (save, Make Manager, Turn on, Add demo data...) returns to the same
  section, and the address (?tab=...) can be bookmarked.

## 0.19.0
- Settings page is full width; its cards sit side by side on wide screens.
- Settings > Google Sheets live link (OFF by default). Turn it on to get a
  formula like =IMPORTDATA("...secret link...") to paste in a Google Sheet; the
  sheet then shows the device list (same columns as the CSV, real devices only)
  and Google refreshes it by itself, roughly every hour. "Make a new link"
  replaces the secret key so an old link stops working; "Turn off" stops it.
  Anyone with the link can read the list, so share the sheet, not the link.
  Google cannot reach a LocalWP/localhost site; it works on the live site.
- Settings > Access & Roles: shows the three levels (Admin, Manager, User),
  who is in each, and what each can do. Pick any WordPress user and click
  "Make Manager" (or "Remove") — this gives or takes the
  manage_device_inventory permission for that person only; no WordPress role
  is changed. Admins are WordPress Administrators.

## 0.18.0
- Print Stickers page is now full width (like All Devices); the device list
  can be taller (up to half the window).
- Stickers are square: one "Size" (mm) box replaces Width and Height. Each
  sticker is just the QR code, with the device ID in its centre, sized exactly
  in mm. The "Label text" option is removed. "Per row" stays.
- A line under the options says whether the row fits on A4 (190 mm), and how
  many fit per row if it does not.
- Size and Per row are remembered. The size is shared with the device page.
- Device page: "Print QR" also has a single "Size" (mm) box instead of Width
  and Height, and prints a size × size mm square.

## 0.17.0
- Settings > Export devices (CSV): a "Download CSV" button for the device list
  (Device ID, Brand, Device Name, Screen Size, Model Year, Chip, Memory, Serial,
  Owner, Owner Email, Department, Status, Details Locked, Photo URL, Added, Last
  Updated). Opens in Excel / Google Sheets, Bangla text included.
- Status filter: tick one or more statuses to export only those (the count for
  each is shown); leave all unticked to export everything.
- The file name carries the date, e.g. devices-2026-10-06.csv
  (devices-2026-10-06-in-repair.csv when one status is picked).
- Demo devices (DEMO-...) are never exported. Admins only.
- Cells that start with = + - @ get a leading ' so Excel can't run them as formulas.

## 0.16.0
- Settings: new "All Devices list" option, "Split the list into pages (10 devices
  per page)". It is OFF by default, so every device shows on one page. Tick it
  and Save to get pages of 10 with Previous / numbers / Next.
- The window-height based rows-per-page (5 to 9) from 0.15.0 is removed; when
  pagination is on it is always 10.
- Changing page scrolls back to the top of the list.

## 0.15.0
- All Devices: a Filters dropdown just before the search box. Tick checkboxes
  under Department, Brand, Size, Year, Chip and Memory; the chosen filters show
  as removable chips under the toolbar (with Clear all). Inside one filter the
  ticked values are "any of" (Apple or Dell); between filters they combine ("and"),
  and they also combine with the status chips and the search. Values are tidied:
  16GB = 16 GB, 14 inch = 14-inch, and Nov 2023 / Jan 2023 count as the year 2023.
- The search box is a little narrower to make room.
- Pagination with Previous / numbers / Next and a "Showing 1-8 of 14 devices"
  line. The number of rows per page follows the window height (5 to 9; 8 on a
  1078x864 window, 9 on a tall one, 8 on a phone), so the page numbers stay on
  screen. The list is a little more compact (smaller row and card padding).

## 0.14.2
- Settings > Demo data: "Add demo data" fills the plugin with 14 made-up devices
  (all six statuses, owners, departments, history notes, two with photos) so you
  can see it with content; "Remove demo data" deletes exactly those and their
  history. They use the IDs DEMO-001 to DEMO-014, so the real ALD-A001 numbers
  are untouched. (To drop this feature later, delete includes/class-demo.php and
  its three lines in the main file and Settings.)

## 0.14.1
- The plugin now has the AuthLab "a" logo as its icon in the WordPress left menu
  (instead of the laptop icon). It is a white SVG, so it shows on the dark menu;
  WordPress recolours it to match the admin colour scheme. A black version is
  included for light backgrounds. Files: assets/img/authlab-logo-white.svg and
  assets/img/authlab-logo.svg (replace them to change the logo).

## 0.14.0
- The intake screenshot is now REQUIRED. The form cannot be sent without it, and
  the Attach button says "(required)".
- Only PNG, JPG (.jpg / .jpeg) and SVG files are accepted, at most 2 MB. A wrong
  type or a too-big file is refused straight away with a red message. The server
  enforces the same rules (a file must really be the type its name says), so
  they cannot be skipped by sending the form directly. Uploads are stored under
  a new random name.
- SVG files are cleaned on the server before saving, using a strict allow-list:
  scripts, event handlers, links, animation, <style>, foreign content and
  anything that loads from outside the file are removed; an SVG that cannot be
  cleaned is refused. (If the server has no DOM/XML support, SVG is refused and
  people are asked to use PNG or JPG.)
- Notes and device updates keep their old photo rules (jpg, png, gif, webp).

## 0.13.2
- Add Device page: the small picture card now reads "This is the window we mean:
  Apple menu -> About This Mac" (same wording as the public form page).

## 0.13.1
- Public form page (the direct link): a Light / Dark switch above the example
  picture (above the form on small screens). With nothing chosen it follows the
  visitor's system setting, as before; a choice is remembered in that browser.
- The whole public page is drawn 15% bigger (type, spacing, form and picture).
  Phones keep their normal size. The page needs about 790 px of window height to
  show without scrolling.

## 0.13.0
- "Add Device" is now ONE page: the old separate Add Device form is gone, and the
  former "Intake Form Link" page is renamed Add Device (same place in the menu).
  It shows "AuthLab Device Inventory" and "Add Device" at the top, the form on the
  left and the direct link + a small preview of the example picture on the right.
  The "+ Add device" button on All Devices opens it. Old Intake Form Link
  bookmarks are redirected here.
- The example picture is much smaller (admin page: fits without scrolling;
  public page: about 200 px wide). The public form has more breathing room.
- Public form heading and tab title: "AuthLab Device Intake Form".
- The serial number in the example picture is now a made-up value, blurred (the
  real serial is no longer anywhere in the picture file), and the Serial number
  placeholder is a made-up sample too.
- Plugin admin pages no longer show the WordPress footer line, so pages don't
  scroll for no reason.

## 0.12.1
- The example window picture is now a small built-in drawing of the About This
  Mac window (about-this-mac-example.svg, under 3 KB, sharp at any size).
- To show your own real screenshot instead, save it in the plugin's assets/img
  folder as about-this-mac-example.png (or .webp / .jpg). It is used automatically
  in place of the drawing, on the form page and on the admin page.

## 0.12.0
- The direct-link form page now shows a real "About This Mac" window beside the
  form (under it on small screens), with the caption "This is the window we
  mean: Apple menu -> About This Mac", so people can see exactly which window
  to copy from. The same picture is shown on the admin "Device Intake Form" page,
  under the link card.
- The picture is a small (about 20 KB) image file inside the plugin:
  assets/img/about-this-mac-example.svg. Replace that file to change it.

## 0.11.4
- Removed the grey "Mac window" box around the device fields (its background,
  border, shadow and the three coloured dots). The form is now one flat, aligned
  table: a thin divider line, the one-line "Apple menu -> About This Mac" hint
  lined up with the boxes, then Brand ... Serial number. A little shorter too.

## 0.11.3
- Two short notes on the form: at the top of the Mac box (on its title bar, next
  to the three dots) "Open the Apple menu (top-left corner) -> About This Mac,
  then copy what you see", and under "Attach screenshot" "Please attach a
  screenshot of your About This Mac window". Still fits one laptop screen.

## 0.11.2
- The page, the form heading and the browser tab are now called "Device Intake
  Form".
- Model-year example is "Nov 2020" (the format About This Mac shows), and the box
  no longer asks phones for a numbers-only keyboard so month names can be typed.

## 0.11.1
- "Intake Form Link" page: the form is now on the left and the direct-link card
  (Copy link, Open, "Other ways to use it") on the right, and the form is a
  little roomier.
- Form: name, email and department are on three lines. Every label now sits in
  one column and every box in the next, in both the personal block and the
  Mac-style panel, so it reads like a table. The device row is now "Brand",
  "Device name", "Size and year" (16-inch , 2023 side by side, together as wide
  as the other boxes), then Chip, Memory, Serial number.
- Example text (placeholders) is much fainter and no longer bold, so it can't be
  mistaken for something already typed. Unselected dropdowns are muted too.

## 0.11.0
- The intake form now has its own address: share the direct link and people can
  fill it in without logging in. No WordPress page or shortcode needed
  (/device-intake/, or ?adi_intake=1 if the site uses plain permalinks). The page
  is not indexed by search engines and follows the visitor's light/dark setting.
  The [device_intake_form] shortcode still works.
- "Intake Form Link" admin page now shows the live form, with the direct link
  and a Copy link button (and Open) above it. Full width.
- Form redesigned to mirror macOS "About This Mac" so it can be filled in by
  reading the window: Name, Email, Department on one line; then a Mac-style
  panel with Brand + Device name (example: MacBook Pro), Screen size , Model year
  on one line, then Chip, Memory, Serial number with labels beside their boxes;
  then the optional screenshot and Submit on the last line. No instructions or
  reference picture. Fits on one laptop screen without scrolling.
- "Add another device" keeps your name, email and department.
- Spam trap: a hidden field that people never see; bots that fill it are
  silently ignored.

## 0.10.0
- New "Device Status" section at the top of the left column: current owner,
  department and status (moved out of the details), with its own Edit and three
  quick actions: Hand over (asks who and which department), Send for repair, and
  Return to stock (clears owner and department). Each can carry an optional note
  that is saved in Device History. Nothing is sent until you confirm.
- "Device details" (brand, device name, screen size, model year, chip, memory,
  serial number) now sits at the top right, above the QR, and is permanent
  once a submission is reviewed (approved), like the device ID. A submission
  waiting for review can still be corrected with Edit; approving locks it. Devices
  added by an admin are locked as soon as they are saved. The lock is enforced on
  the server, not just hidden in the page.
  Upgrading: every device that was already approved is locked automatically.
- "History" is now called "Device History".
- Notes written by a person now have Edit and Delete. An admin can change any
  note; anyone else only their own. Edit works in place (and can remove the
  photo); Delete asks first. Edited notes show "edited". Automatic entries
  (changes, system messages) can never be edited or deleted. Deleting a note
  removes its text and photo for good and leaves a one-line trace ("A note by X
  (date) was deleted") with who deleted it.
- Updates now only change the fields they send, so a request that changes the
  status can no longer blank the other fields.
- Admin "Add device": warns that the screenshot details are permanent once saved.

## 0.9.0
- Every change to a device is now recorded in its History automatically,
  with who made it and the date and time: e.g. "Status: In Use → Need repair",
  "Owner: Shovon → Provath". Several changes saved together become one entry.
  Saving without changing anything adds nothing. Approving a submission,
  adding a device and the intake submission are logged too. (Before this
  version, edits were NOT recorded.)
- History timestamps now follow the WordPress timezone setting (Settings →
  General). Set it to your city so the times read correctly.
- History redesigned: one box to write in, with a paperclip icon inside it to
  attach a photo (or just paste a screenshot). A note can be text, a photo or
  both. Each entry shows its text with a small thumbnail; click the thumbnail
  to view the photo in a lightbox (Esc to close, arrow keys / arrows to move
  between photos). Ctrl/Cmd+Enter sends. Same on the public QR page.
- Edit on the device page is now inline: the values in the details table turn
  into small fields in place, with Save / Cancel at the top. The details card
  moves to the top of the right column while editing so the whole editor fits
  on screen. Enter saves, Esc cancels.
- Print QR now has Width and Height (mm). The QR prints as the largest square
  that fits, centred in that area. Both sizes are remembered.

## 0.8.0
- Device detail page redesigned so it needs very little scrolling:
  - Left (70%): History, the note box, photo attach and all earlier notes.
    A long history scrolls inside its own box instead of lengthening the page.
  - Right (30%): the QR code, with a size box (mm) and a "Print QR" button
    that prints only that one QR at exactly that size (the size is
    remembered); below it the device details with the Edit button. The
    verification photo is now a small thumbnail row in the details.
  - The page title is now the device ID with its status next to it.
  - Edit opens in place of the details card instead of below the page.
  - Uses the full width of the screen (no longer a narrow column).
  - On phones and narrow windows the columns stack, QR and details first.
- A submission waiting for review shows its "Approve" banner across the top.

## 0.7.0
- QR codes now carry the device ID in the middle (e.g. "ALD-A001"), on the
  device page, the public device view and the printable sticker sheet. On
  small stickers the ID is split over two lines ("ALD-" / "A001") so it stays
  readable. The sticker still shows the ID in large type next to the QR too.
- The label is drawn into the QR image itself, so it prints correctly even
  when the browser's "background graphics" option is off.
- QR codes use error-correction level Q and the label covers under ~9% of the
  code. Tested with two independent decoders (ZBar and OpenCV) at sticker and
  screen sizes: scans as reliably as a plain QR.
- QR codes now have a proper white border (quiet zone), which makes them
  easier to scan on dark backgrounds.

## 0.6.1
- The full-screen control is now an icon-only round button (no text), placed
  after the Light/Dark toggle at the far right of the top bar. Hovering it
  shows "Full screen" / "Exit full screen (Esc)".

## 0.6.0
- New "Full screen" button in the top bar of every AuthLab Devices admin page.
  It hides WordPress's own left menu and the black top toolbar so the page
  uses the whole screen. Click "Exit full screen" (or press Esc) to bring them
  back. The choice is remembered in the browser and applied before the page
  paints. On phones the button shows only its icon.

## 0.5.4
- The glossy primary button is now black (replaces the blue from 0.5.3):
  near-black ends, a lighter charcoal centre, glass highlight, soft glow and
  halo. In dark mode it uses a lighter charcoal with a light halo ring so it
  stays visible on the near-black page. Button colours are now theme tokens
  (--adi-btn-*), so they are easy to change in one place.

## 0.5.3
- The glossy primary button ("+ Add device", Save, Approve, Submit, ...) is
  now blue instead of violet: dark blue ends, lighter blue centre, same glass
  highlight, glow and halo. Everything else stays black/grey.

## 0.5.2
- Active/selected states are now black and grey instead of violet: the
  highlighted stat card, the active filter chip, the active Light/Dark
  toggle, ID links, focus rings and checkboxes. Dark mode uses a lighter grey
  so they stay visible on near-black.
- Text links get a subtle underline so they remain recognisable now that they
  are no longer coloured.
- The glossy violet "Add device" / primary button is unchanged.

## 0.5.1
- Dark mode is now near-black (neutral, no indigo tint); status badges and
  surfaces adjusted to match.
- Light mode is now cleaner and whiter: a flat near-white page instead of the
  lavender gradient, neutral greys, crisp card outlines.
- Drop shadows are about 30% lighter everywhere (cards, stat tiles, chips,
  search, theme toggle, button glow).

## 0.5.0
- New "floating" design: soft lavender page with a gentle gradient, white
  cards that float on soft shadows, large rounded corners, and a violet to
  magenta accent for active filters and the theme toggle.
- Buttons restyled as glossy violet pills (dark violet ends, lighter
  centre, glass highlight, soft glow underneath, faint halo ring).
- Light is now the default theme (Dark is an indigo version of the same
  look). Anyone who already picked a theme keeps their choice.
- Device list: the table sits in its own floating card; status badges are
  pastel pills; cards lift slightly on hover.
- Search box is a floating pill with a soft inner shadow.
- Form labels start with a capital letter on the Edit form.
- The public intake form uses the same palette (dark indigo panel).

## 0.4.0
- Light / Dark toggle at the top right of every AuthLab Devices admin page.
  The choice is remembered in the browser and applied before the page paints
  (no flash). Dark stays the default.
- Seamless design: the dark box on a grey page is gone. The whole wp-admin
  content area (page background, footer included) now takes the chosen theme.
- Responsive: the device list fills the screen on large monitors. On phones
  and narrow windows the table becomes a stack of cards, the search bar moves
  to the top at full width, and the toggle shrinks to icons.
- Device list: filters on the left, search on the right (pill-shaped, with a
  search icon). Fixes WordPress's own white search-box styling clashing with
  the dark theme.
- Page title is now "AuthLab Device Inventory" with the page name beneath it
  on the other pages; every admin page shares the same top bar.
- Settings page: "Save settings" button matches the plugin style and a
  "Settings saved" notice now appears after saving.
- Print Stickers: the controls and heading are now hidden when printing, so
  only the sticker sheet prints; page background is forced white for print.
- Form fields and dropdowns restyled for both themes (custom dropdown arrow,
  consistent heights).
- The public front-end shortcodes (intake form etc.) are unchanged: they stay
  a dark panel inside your site's own theme.

## 0.3.1
- UI: redesigned the device list — summary cards (Total / In use / In stock /
  Repair / Pending review, click to filter), live search (ID, device, owner,
  serial, department), status filter chips with counts, and a richer table
  (chip · memory · screen under the device name, department under the owner,
  serial column). Pending submissions are sorted to the top; whole rows are
  clickable. Added empty states for "no devices" and "no match".
- UI: removed the confusing "Device Detail" entry from the sidebar menu (the
  page still opens from the list; "All Devices" stays highlighted).
- UI: "Print stickers" shortcut button on the list page.
- Fix: links to a device's detail page no longer assume WordPress lives at
  the site root, so they work on sites installed in a subfolder.

## 0.3.0
- Device IDs are now assigned automatically and can never be changed:
  ALD-A001, ALD-A002 … ALD-A999, then ALD-B001 … ALD-B999, and so on up to
  ALD-Z999 (25,974 devices). Applies to both the intake form and "Add device".
- No more typing an ID by hand: the ID field is gone from the Add Device
  form, read-only on the Edit form, and ignored by the server if sent.
- IDs are never reused — deleting a device leaves a gap rather than freeing
  its number.
- Counter is atomic, so two simultaneous submissions can't get the same ID.
- The intake form no longer creates "PENDING-…" temporary codes. Approving
  a submission is now just an "Approve" button (the ID already exists).
- Upgrade: existing manually-set IDs are kept (the counter continues after
  the highest ALD-Axxx already in use, so ALD-A001 → next is ALD-A002), and
  any old PENDING-… devices are given real IDs, oldest first.

## 0.2.1
- Intake form: after submitting, the form is replaced by a confirmation
  ("Submitted. Thank you") with an "Add another device" button — the blank
  form no longer shows underneath the confirmation
- Intake form: added an annotated "About This Mac" reference image with a
  numbered legend (Device name, Screen size, Model year, Chip, Memory,
  Serial number). The serial number in the example image is masked.
- Intake form: network errors on submit now show an error message instead
  of failing silently

## 0.2.0
- Photo/screenshot upload added: on the intake form (verification photo) and on
  any history log note (handover/repair condition photos), stored via
  WordPress's own upload system
- Batch QR sticker printing added inside wp-admin (Device Inventory → Print
  Stickers) — select devices, set sticker size, print — no more need for the
  separate standalone sticker-generator HTML tool
- Device-record page slug is now configurable (Device Inventory → Settings)
  instead of hardcoded to `device-record`
- Automatic database schema upgrades on plugin update (no need to
  deactivate/reactivate)
- Applied a consistent Notion-dark-mode visual theme (120% base font size)
  across both the wp-admin pages and the front-end shortcodes

## 0.1.1
- Renamed the WP admin sidebar menu from "Device Inventory" to "AuthLab Devices"

## 0.1.0
- Initial build: device database tables, REST API, admin pages (list, add, detail),
  public intake form shortcode, device view shortcode, QR scanner shortcode,
  QR code generation, pending-review approval flow, history log
