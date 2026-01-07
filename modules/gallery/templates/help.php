<?php
/**
 * Gallery Admin — Help Page Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="tpw-admin-ui tpw-gallery-help">
  <div class="tpw-admin-header">
    <h1><?php esc_html_e('Gallery Help', 'tpw-core'); ?></h1>
    <div class="tpw-row" style="gap:8px;align-items:center;">
      <a href="<?php echo esc_url( home_url( '/gallery-admin/' ) ); ?>" class="tpw-btn tpw-btn-secondary">&larr; <?php esc_html_e('Back to Galleries', 'tpw-core'); ?></a>
    </div>
  </div>
  <div class="tpw-card">
    <div class="tpw-card__body">
      <h2><?php esc_html_e('Access', 'tpw-core'); ?></h2>
      <ul>
        <li><?php esc_html_e('Route:', 'tpw-core'); ?> <code>/gallery-admin/</code></li>
        <li><?php esc_html_e('Shortcode:', 'tpw-core'); ?> <code>[tpw_gallery_admin]</code></li>
        <li><?php esc_html_e('Permission:', 'tpw-core'); ?> <?php esc_html_e('Administrators', 'tpw-core'); ?></li>
      </ul>

      <h2><?php esc_html_e('Create a Gallery', 'tpw-core'); ?></h2>
      <ol>
        <li><?php esc_html_e('Click “Add Gallery”.', 'tpw-core'); ?></li>
        <li><?php esc_html_e('Enter a Title and (optional) Description.', 'tpw-core'); ?></li>
        <li><?php esc_html_e('Choose a Category (or leave Uncategorised).', 'tpw-core'); ?></li>
        <li><?php esc_html_e('Click “Save Gallery”.', 'tpw-core'); ?></li>
      </ol>

      <h2><?php esc_html_e('Add Images', 'tpw-core'); ?></h2>
      <ul>
        <li><strong><?php esc_html_e('From Media Library', 'tpw-core'); ?></strong>: <?php esc_html_e('Pick existing images; they are linked into the gallery.', 'tpw-core'); ?></li>
        <li><strong><?php esc_html_e('Upload New Files', 'tpw-core'); ?></strong>: <?php esc_html_e('Select images to upload; the gallery auto‑saves if needed.', 'tpw-core'); ?></li>
      </ul>

      <h2><?php esc_html_e('Edit Images', 'tpw-core'); ?></h2>
      <ul>
        <li><strong><?php esc_html_e('Caption', 'tpw-core'); ?></strong>: <?php esc_html_e('Click the caption to edit, then Save or Cancel.', 'tpw-core'); ?></li>
        <li><strong><?php esc_html_e('Focal Point', 'tpw-core'); ?></strong>: <?php esc_html_e('Click the image (or “Focal” button) to open the focal editor.', 'tpw-core'); ?>
          <ul>
            <li><?php esc_html_e('Click or drag the green handle to set the focus; Save to apply.', 'tpw-core'); ?></li>
            <li><?php esc_html_e('Editor page thumbnails recenter to the saved focal point.', 'tpw-core'); ?></li>
          </ul>
        </li>
      </ul>

      <h2><?php esc_html_e('Reorder Images', 'tpw-core'); ?></h2>
      <p><?php esc_html_e('Drag and drop thumbnails to change order. Order is saved automatically.', 'tpw-core'); ?></p>

      <h2><?php esc_html_e('Remove vs Delete', 'tpw-core'); ?></h2>
      <ul>
        <li><strong><?php esc_html_e('Remove', 'tpw-core'); ?></strong>: <?php esc_html_e('Unlinks the image from the gallery only (keeps it in Media Library).', 'tpw-core'); ?></li>
        <li><strong><?php esc_html_e('Delete', 'tpw-core'); ?></strong>: <?php esc_html_e('Permanently deletes the image from the Media Library (cannot be undone).', 'tpw-core'); ?></li>
      </ul>

      <h2><?php esc_html_e('Manage Categories', 'tpw-core'); ?></h2>
      <p><?php esc_html_e('Use “Manage Categories” to add or delete categories. The gallery form’s Category dropdown updates after closing the modal.', 'tpw-core'); ?></p>

      <h2><?php esc_html_e('Quick Edit vs Full Page', 'tpw-core'); ?></h2>
      <ul>
        <li><strong><?php esc_html_e('Quick Edit', 'tpw-core'); ?></strong>: <?php esc_html_e('Opens a modal for lightweight changes.', 'tpw-core'); ?></li>
        <li><strong><?php esc_html_e('Edit (Page)', 'tpw-core'); ?></strong>: <?php esc_html_e('Opens the full Gallery Editor for larger galleries and drag‑drop.', 'tpw-core'); ?></li>
      </ul>

      <h2><?php esc_html_e('Public Display', 'tpw-core'); ?></h2>
      <p><?php esc_html_e('Use', 'tpw-core'); ?> <code>[tpw_gallery id="123"]</code> <?php esc_html_e('to show a gallery on public pages. Thumbnails crop to fit and honor focal points; captions are shown below.', 'tpw-core'); ?></p>

      <p>
        <?php esc_html_e('For very large galleries, you can limit how many thumbnails render at once (grid/list only):', 'tpw-core'); ?>
        <code>[tpw_gallery id="123" view="grid" paginate="1"]</code>
        <?php esc_html_e('or', 'tpw-core'); ?>
        <code>[tpw_gallery id="123" view="list" per_page="40"]</code>
      </p>

      <p>
        <?php esc_html_e('To hide the gallery heading (title/description) above the images:', 'tpw-core'); ?>
        <code>[tpw_gallery id="123" view="story" show_heading="0"]</code>
      </p>

      <div class="tpw-card note">
        <div class="tpw-card__body">
          <strong><?php esc_html_e('Tips & Troubleshooting', 'tpw-core'); ?></strong>
          <ul>
            <li><?php esc_html_e('If changes don’t appear, refresh and clear browser cache.', 'tpw-core'); ?></li>
            <li><?php esc_html_e('The focal point is stored per image; set it for portraits for better crops.', 'tpw-core'); ?></li>
            <li><?php esc_html_e('You must Save the gallery to create it before uploading files.', 'tpw-core'); ?></li>
            <li><?php esc_html_e('Only administrators can access /gallery-admin/.', 'tpw-core'); ?></li>
          </ul>
        </div>
      </div>

      <h2><?php esc_html_e('Reference', 'tpw-core'); ?></h2>
      <ul>
        <li><?php esc_html_e('Templates & assets:', 'tpw-core'); ?> <code>modules/gallery/templates/</code>, <code>modules/gallery/assets/</code></li>
        <li><?php esc_html_e('Developer hooks: see the developer help “Gallery”.', 'tpw-core'); ?></li>
      </ul>
    </div>
  </div>
</div>
