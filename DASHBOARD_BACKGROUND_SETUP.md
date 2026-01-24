# Dashboard Background Image Setup

This guide explains how to set up and manage the customizable background image for the student dashboard.

## Setup Steps

### 1. Create Database Table

Run the SQL file to create the `system_settings` table:

```sql
-- Execute this file in your MySQL database
database files/create_system_settings_table.sql
```

This will create the `system_settings` table and insert the default setting for dashboard background.

### 2. Ensure Upload Directory Exists

The background images will be stored in:
```
uploads/backgrounds/
```

This directory should already be created. If not, the system will create it automatically when you upload the first image.

### 3. Access Admin Settings

As an admin user:

1. Log in to the admin panel
2. Navigate to **Settings** from the admin menu
3. You'll see the "Dashboard Background Image" section

### 4. Upload Background Image

To set a background image:

1. Click "Choose File" and select an image
2. **Recommended specifications:**
   - Format: JPG, JPEG, PNG, GIF, or WEBP
   - Dimensions: 1920x1080 pixels or higher
   - File size: Maximum 10MB
   - Quality: High resolution for best display
3. Click "Upload Background"
4. The image will be applied immediately to the student dashboard

### 5. Preview and Manage

- **Preview**: The current background image is shown in the settings page
- **Remove**: Click "Remove Background" to delete the current background and restore the default gray background
- **Replace**: Simply upload a new image to replace the existing one (old image will be automatically deleted)

## How It Works

### Database Storage

The background image path is stored in the `system_settings` table:
- **setting_key**: `dashboard_background`
- **setting_value**: Path to the image file (e.g., `uploads/backgrounds/dashboard_bg_1234567890.jpg`)
- **setting_type**: `image`

### File Storage

Images are stored in:
```
uploads/backgrounds/
```

File naming convention:
```
dashboard_bg_[timestamp].[extension]
```

### Display on Dashboard

The dashboard.php file:
1. Fetches the background image path from database
2. Applies it as a fixed background using CSS
3. Adds a semi-transparent overlay (95% opacity) to ensure content readability
4. Falls back to default gray background if no image is set

## Technical Details

### CSS Implementation

When a background is set, the following CSS is applied:

```css
body {
    background-image: url('path/to/image.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    background-repeat: no-repeat;
}

.content-overlay {
    background-color: rgba(243, 244, 246, 0.95);
    min-height: 100vh;
}
```

### Security Features

- **File type validation**: Only allows image formats (JPG, JPEG, PNG, GIF, WEBP)
- **File size limit**: Maximum 10MB
- **Unique filenames**: Prevents conflicts and overwrites
- **Automatic cleanup**: Old images are deleted when replaced or removed
- **Admin-only access**: Only admin users can modify the background

### Files Modified/Created

1. **Database**: `system_settings` table
2. **Admin Page**: `admin/settings.php` - Manage background image
3. **Dashboard**: `dashboard/dashboard.php` - Display background image
4. **Admin Header**: `admin/header.php` - Added Settings menu link
5. **Upload Directory**: `uploads/backgrounds/` - Store images

## Troubleshooting

### Background not showing?

1. Check if the SQL table was created successfully
2. Verify the image was uploaded to `uploads/backgrounds/`
3. Check file permissions on the uploads directory (should be writable)
4. Clear browser cache and refresh the page

### Image looks distorted?

- Use high-resolution images (1920x1080 or higher)
- Ensure proper aspect ratio (16:9 recommended)
- Try different image formats

### Cannot upload image?

- Check PHP file upload settings in php.ini:
  - `upload_max_filesize = 10M` (or higher)
  - `post_max_size = 10M` (or higher)
- Verify directory permissions for `uploads/backgrounds/`
- Check image file size (must be under 10MB)

## Tips for Best Results

1. **Use high-quality images**: Better resolution = better appearance
2. **Consider contrast**: Choose images that won't make text hard to read
3. **Test on different screens**: Check how it looks on various devices
4. **Professional images**: Use relevant educational or abstract backgrounds
5. **File size**: Compress images for faster loading while maintaining quality

## Future Enhancements

Potential features to add:
- Multiple background options for different user roles
- Background image gallery/library
- Scheduled background changes
- Custom overlay opacity control
- Support for gradients and patterns
