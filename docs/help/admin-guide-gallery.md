# Gallery Admin — User Guide

This guide helps site administrators manage image galleries using TPW Core’s Gallery Admin.

## Access
- Route: `/gallery-admin/` (front‑end admin system page)
- Shortcode: `[tpw_gallery_admin]` — include on a page if not auto‑created
- Permission: Administrators (temporary capability `manage_options`)

## Create a Gallery
1. Click "Add Gallery".
2. Enter a Title and (optional) Description.
3. Choose a Category (or leave Uncategorised).
4. Click "Save Gallery".

## Add Images
- "Add Images" menu:
  - From Media Library: pick existing images; they are linked into the gallery.
  - Upload New Files: select images to upload; the gallery auto‑saves if needed.

## Edit Images
- Caption: Click the caption to edit, then Save or Cancel.
  - Save applies immediately; Cancel discards changes.
- Focal Point: Click the image (or "Focal" button) to open the focal editor.
  - Click or drag the green handle to set the focus; Save to apply.
  - The editor page thumbnail recenters to the saved focal point.

## Reorder Images
- Drag and drop thumbnails to change order.
- Order is saved automatically to the gallery.

## Remove vs Delete
- Remove: Unlinks the image from the gallery only (keeps it in Media Library).
- Delete: Permanently deletes the image from the Media Library (cannot be undone).

## Manage Categories
- Use "Manage Categories" to add or delete categories.
- The gallery form’s Category dropdown updates after closing the modal.

## Quick Edit vs Full Page
- Quick Edit: Opens a modal for lightweight changes.
- Edit (Page): Opens the full Gallery Editor for larger galleries and drag‑drop.

## Public Display
- Use `[tpw_gallery id="123"]` to show a gallery on public pages.
- Optional view modes:
  - Grid (default): `[tpw_gallery id="123" view="grid"]`
  - List: `[tpw_gallery id="123" view="list"]`
  - Story/Carousel (inline): `[tpw_gallery id="123" view="story"]` — one image at a time with Previous/Next, swipe, and arrow keys; no autoplay.
- Thumbnails crop to fit and honor focal points; captions are shown below (grid/list) or prominently with the image (story).

## Tips & Troubleshooting
- If changes don’t appear, refresh and ensure browser cache isn’t stale.
- The focal point is stored per image; set it for portraits to improve crops.
- You must Save the gallery to create it before uploading files.
- Only administrators can access `/gallery-admin/`.

## Reference
- Templates and assets: `modules/gallery/templates/`, `modules/gallery/assets/`
- Hooks and developer notes: see the developer‑focused [Gallery](gallery.md) help.
