# Apple Wallet Pass Images

This directory should contain the following PNG images for Apple Wallet passes:

## Required Images

### icon.png
- **Size**: 29x29 pixels
- **Purpose**: Icon shown in notifications and on lock screen
- **Format**: PNG with transparency
- **Note**: Apple will automatically create @2x and @3x versions if needed

### logo.png
- **Size**: 160x50 pixels (width x height)
- **Purpose**: Logo shown at the top of the pass
- **Format**: PNG with transparency
- **Note**: Should be readable at small sizes

## Optional Images

### background.png
- **Size**: 180x220 pixels
- **Purpose**: Background image for the pass
- **Format**: PNG with transparency
- **Note**: Optional - pass will use solid colors if not provided

### strip.png
- **Size**: 375x98 pixels
- **Purpose**: Strip image shown behind pass content
- **Format**: PNG with transparency
- **Note**: Optional - pass will use solid colors if not provided

## Creating Placeholder Images

Until you have final designs, you can create simple placeholder images:

### Using ImageMagick (if installed):
```bash
# Icon
convert -size 29x29 xc:#0EA5E9 -pointsize 20 -fill white -gravity center -annotate +0+0 "K" icon.png

# Logo
convert -size 160x50 xc:#0EA5E9 -pointsize 24 -fill white -gravity center -annotate +0+0 "Kawhe" logo.png

# Background (optional)
convert -size 180x220 xc:#1F2937 background.png

# Strip (optional)
convert -size 375x98 xc:#0EA5E9 strip.png
```

### Using Online Tools:
- https://placeholder.com - Create colored placeholder images
- https://www.canva.com - Design custom images
- https://www.figma.com - Design and export PNGs

### Using Design Software:
- Adobe Photoshop/Illustrator
- GIMP (free)
- Sketch
- Figma

## Current Status

**⚠️ Placeholder images are required before Apple Wallet passes will work.**

The service will check for these files and include them in the pass if they exist. If images are missing, the pass will still generate but may not display correctly.

## Production

For production, replace these placeholders with:
- Your actual brand logo
- Properly sized and optimized images
- Images that match your brand colors
