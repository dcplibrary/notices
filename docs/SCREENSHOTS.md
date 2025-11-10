# Screenshot Guide

This guide lists all screenshots needed for the documentation and where to place them.

## Required Screenshots

### Dashboard Documentation (DASHBOARD.md)

1. **Dashboard Overview** - `images/dashboard-overview.png`
   - Navigate to: `/notifications`
   - Show: Full dashboard with metrics, charts, and Shoutbomb stats
   - Browser window at ~1920x1080
   - Include navigation bar

2. **Notifications List** - `images/notifications-list.png`
   - Navigate to: `/notifications/notifications`
   - Show: Table with filters open
   - Apply some filters to show functionality
   - Show pagination at bottom

3. **Analytics Page** - `images/analytics-page.png`
   - Navigate to: `/notifications/analytics`
   - Show: Success rate trend and distribution charts
   - Full page view

4. **Shoutbomb Page** - `images/shoutbomb-page.png`
   - Navigate to: `/notifications/shoutbomb`
   - Show: Subscriber stats and growth chart
   - Full page view

5. **Mobile View** - `images/dashboard-mobile.png`
   - Open dashboard on mobile or resize browser to ~375px width
   - Show responsive layout

### API Documentation (API.md)

6. **API Response Example (Postman/Insomnia)** - `images/api-response.png`
   - Make request to: `GET /api/notices/logs`
   - Show: Request with headers and JSON response
   - Use Postman or Insomnia

7. **API Overview Endpoint** - `images/api-overview.png`
   - Request: `GET /api/notices/analytics/overview?days=30`
   - Show: Full JSON response with nested data

### Integration Guide (INTEGRATION.md)

8. **Config File** - `images/config-notifications.png`
   - Show: `config/notices.php` file in code editor
   - Highlight the dashboard and API sections
   - Use syntax highlighting

9. **Route List** - `images/route-list.png`
   - Terminal screenshot of: `php artisan route:list | grep notifications`
   - Show: All registered routes with middleware

### README.md

10. **Quick Start** - `images/readme-hero.png`
    - Dashboard overview or logo/banner image
    - Professional looking, welcoming

## How to Take Screenshots

### Browser Screenshots

1. **Use full browser window** - Not just viewport
2. **Consistent size** - 1920x1080 or similar
3. **Clean data** - Use demo data that looks realistic but not real patron data
4. **Highlight important areas** - Use red boxes/arrows if needed
5. **Consistent browser** - Use Chrome or Firefox

### Terminal Screenshots

1. **Use a clean theme** - Dark or light, but readable
2. **Appropriate width** - 120-140 characters wide
3. **Remove sensitive info** - No server names, passwords, etc.
4. **Use syntax highlighting** - Most terminals support this

### Code Editor Screenshots

1. **Popular theme** - VSCode Dark+ or similar
2. **Syntax highlighting on**
3. **Hide file tree** - Focus on the code
4. **Zoom appropriately** - Code should be readable

## Taking Screenshots

### Generate Demo Data First

```bash
# Seed demo data so screenshots show realistic content
php artisan notices:seed-demo --days=60
```

### Browser Developer Tools

For responsive screenshots:
1. Open DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Select device (iPhone, iPad, etc.)
4. Take screenshot

### Screenshot Tools

**macOS:**
- Cmd+Shift+4 (select area)
- Cmd+Shift+5 (screenshot tool)

**Windows:**
- Win+Shift+S (Snipping Tool)
- PrtScn (Print Screen)

**Linux:**
- gnome-screenshot
- Spectacle (KDE)
- Flameshot

**Browser Extensions:**
- Awesome Screenshot
- Nimbus Screenshot
- Full Page Screen Capture

## Image Specifications

### File Format
- **PNG** for UI screenshots (lossless)
- **JPG** for photos/complex images (smaller file size)
- **SVG** for diagrams/logos (scalable)

### File Size
- **Max width**: 1920px
- **Max file size**: 500KB per image
- **Optimize**: Use TinyPNG or ImageOptim

### Naming Convention
```
images/[section]-[description].png

Examples:
images/dashboard-overview.png
images/api-response-example.png
images/mobile-responsive.png
```

## Adding to Documentation

Once you have screenshots, add them to the markdown files:

```markdown
![Dashboard Overview](images/dashboard-overview.png)
```

Or with alt text and title:

```markdown
![Dashboard showing metrics and charts](images/dashboard-overview.png "Notifications Dashboard")
```

For GitHub, you can also use HTML for more control:

```html
<img src="images/dashboard-overview.png" alt="Dashboard Overview" width="800">
```

## Privacy & Security

**IMPORTANT:**
- ❌ Never include real patron data
- ❌ Never show real server names/IPs
- ❌ Never show authentication tokens
- ❌ Never show real passwords or API keys
- ✅ Use demo data from seed command
- ✅ Blur sensitive information if needed
- ✅ Use example.com for domains
- ✅ Use 127.0.0.1 or localhost for IPs

## Screenshot Checklist

Before adding screenshots, verify:

- [ ] No real patron information visible
- [ ] No sensitive credentials visible
- [ ] Image is clear and readable
- [ ] Image size is optimized (<500KB)
- [ ] Filename follows naming convention
- [ ] Screenshot shows relevant feature clearly
- [ ] Browser/UI looks professional
- [ ] Consistent styling with other screenshots

## Optional: Animated GIFs

For showing interactions:

**Tools:**
- LICEcap (Windows/macOS)
- Kap (macOS)
- peek (Linux)
- ScreenToGif (Windows)

**Use for:**
- Filtering notifications
- Date range selection
- Chart interactions
- Mobile menu navigation

**Specs:**
- Max 5 seconds
- Max 2MB file size
- 10-15 FPS
- 1280px width max

## Placeholder Images

While creating screenshots, you can use placeholder services:

```markdown
![Dashboard Overview](https://via.placeholder.com/800x600?text=Dashboard+Screenshot)
```

## After Adding Screenshots

1. **Test all images** - Verify they load correctly
2. **Check on GitHub** - Preview markdown rendering
3. **Update this guide** - Mark completed screenshots
4. **Commit images** - Use Git LFS if images are large

## Questions?

If you need help with a specific screenshot or have questions about what to capture, refer to the specific documentation file to see the context where the image will appear.
