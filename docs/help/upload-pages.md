# Upload Pages – Admin Help

Use Upload Pages to share files with specific member groups securely. Direct access to uploaded files is blocked; files are served through a secure handler that enforces permissions.

## Steps

1) Create an Upload Page
- Go to TPW Control → Upload Pages
- Click "Create New Upload Page"
- Enter Title, optional Slug/Description, choose Layout, and set Visibility

2) Add Files
- Open the page you created and use "Add File" to upload one or more files
- Optionally set a Label and Year per file

3) Publish via Shortcode
- Add a WordPress page or post and insert the shortcode below (Shortcode block), or
- From the Upload Page edit screen, click "Create New Linked Page" to auto-create a WordPress Page containing the shortcode

### Shortcode

```
[tpw_upload_page slug="your-slug"]
```

- Replace `your-slug` with the slug of your Upload Page
- Page layout (Table/List/Cards) is controlled in the Upload Page settings

## Visibility
- Choose which roles/flags (Admin, Committee, Match Managers, Noticeboard Admins) and/or member statuses can view the page
- Admins always have access

## How it stays secure
- Files are stored in a protected uploads area; direct URLs are denied
- Front-end links are time-limited and permission-checked via a secure handler

## Tips
- Use clear Labels and Years to make files easy to scan
- The page description supports rich text and images (editor images are also secured)
- You can link an existing WordPress Page that already contains the shortcode
