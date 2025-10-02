# TPW Control — Admin Guide

TPW Control centralizes front‑end admin tools for your society in one place. Put the shortcode `[tpw-control]` on a WordPress page (usually titled “TPW Control”). You’ll then access tools at URLs like `/tpw-control/?action=`.

- Dashboard: `/tpw-control/`
- Upload Pages: `/tpw-control/?action=upload-pages`
- Menu Manager: `/tpw-control/?action=menu-manager`

## Creating Upload Pages

1) Open Upload Pages
- Go to `/tpw-control/?action=upload-pages`.

2) Add a new Upload Page
- Click “Add New Page”.
- Fill Title (required) and Slug (required). Slug becomes part of links and must be unique.
- Optionally add a Description.
- Set Visibility (who can see this page):
  - Public: visible to everyone.
  - Logged-in: visible to logged-in users.
  - Flags: choose roles like Admin, Committee, Match Manager, Noticeboard Admin.
  - Statuses: restrict by member status (e.g., Active, Honorary).
- Save.

3) Upload files
- In the page’s Files Manager, select one or more files to upload.
- Optionally set a Year (e.g., 2025). Items show grouped by year.
- Optionally set a Label. If omitted, the file name is shown.
- Click Upload. Files appear in the list.

4) Manage files
- Reorder files: drag/sort controls adjust display order.
- Edit a file: change Label or Year, then save.
- Delete a file: remove it from the page.

## Adding Upload Pages to a menu

1) Open Menu Manager
- Go to `/tpw-control/?action=menu-manager`.

2) Pick or create a menu
- Select an existing menu or create a new menu.

3) Add a link to an Upload Page
- Under “Add Upload Page”, choose your page, set a label (optional), and click Add.

4) Adjust visibility per menu item (optional)
- Each item can store Visibility JSON and a Requires Login toggle.
- This allows items to show only to the right members.

5) Save changes
- Edits and deletes are immediate when you click their buttons; post‑submit you’ll be redirected back to the Menu Manager.

## Setting visibility — quick tips

- Public pages are visible to everyone (no login required).
- Logged-in requires users to be logged into the site.
- Admins can always see everything in TPW Control.
- Committee/Match Manager/Noticeboard Admin flags are respected when present on a member.
- For status limited content, pick allowed statuses (e.g., Active). Users with other statuses won’t see it.

## Troubleshooting

- Can’t find the TPW Control page? Ensure a Page exists with `[tpw-control]` in the content. Some TPW plugins auto‑create it on activation.
- Menu items not appearing for a user? Check the item’s visibility settings and the user’s member flags/status.
- Upload failing? Verify your file types and sizes are allowed in WordPress, and you’re logged in.
