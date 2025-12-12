# Common Issues & Solutions

## What You'll Learn

- How to fix common problems
- Where to find error information
- When to contact support

---

## Quick Fixes

Before diving into specific issues, try these:

1. **Clear caches** - Browser cache, WordPress cache, CDN cache
2. **Flush permalinks** - Settings → Permalinks → Save Changes
3. **Check plugin conflicts** - Deactivate other plugins temporarily
4. **Update WordPress** - Ensure you're on the latest version

---

## Installation Issues

### Plugin won't activate

**Error:** "Plugin could not be activated because it triggered a fatal error"

**Solutions:**
1. Check PHP version (requires 7.4+)
   - Go to Tools → Site Health → Info → Server
2. Check WordPress version (requires 5.8+)
3. Increase PHP memory limit to 128MB+
4. Check error log for specific message

### Menu not appearing after activation

**Solutions:**
1. Clear browser cache and refresh
2. Log out and log back in
3. Check user role has administrator capabilities
4. Deactivate/reactivate the plugin

---

## Ad Display Issues

### Ads not showing

**Checklist:**
- [ ] Ad is published (not draft)
- [ ] Ad is assigned to the correct zone
- [ ] Zone slug in shortcode matches exactly
- [ ] Start date has passed (if set)
- [ ] End date hasn't passed (if set)
- [ ] Ad zone has been created

**Debug steps:**
1. Try displaying specific ad: `[wbam_ad id="123"]`
2. Check ad status in WB Ad Manager → Ads
3. Verify zone exists in WB Ad Manager → Ad Zones
4. View page source to check if container renders

### Shortcode shows as plain text

**Symptoms:** You see `[wbam_ad zone="xyz"]` instead of an ad

**Solutions:**
1. Verify plugin is activated
2. Check shortcode spelling (case-sensitive)
3. Ensure no extra spaces in shortcode
4. Try in a different page/post
5. Switch to default theme temporarily

### Wrong ad size

**Solutions:**
1. Check uploaded image dimensions
2. Use size parameter: `[wbam_ad zone="x" size="300x250"]`
3. Add CSS fix:
```css
.wbam-ad img {
    max-width: 100%;
    height: auto;
}
```
4. Verify theme isn't overriding image styles

### Same ad always showing

**Causes:**
- Only one ad in the zone
- Aggressive caching
- Cookie-based rotation

**Solutions:**
1. Add more ads to the zone
2. Clear all caches
3. Test in incognito/private browsing
4. Check rotation type in zone settings

---

## Click Tracking Issues

### Clicks not being tracked

**Solutions:**
1. Go to Settings and verify tracking is enabled
2. Check destination URL is valid (starts with http/https)
3. Test in incognito mode (ad blockers can interfere)
4. Check for JavaScript errors in browser console
5. Verify link isn't cached by CDN

### Analytics showing zero

**Solutions:**
1. Wait a few minutes (stats may be delayed)
2. Clear any caching
3. Check database tables exist
4. Verify tracking script is loading (view page source)

---

## Link Management Issues

### Links not displaying

**Solutions:**
1. Verify link ID is correct
2. Check link is published
3. Verify shortcode syntax: `[wbam_link id="123"]`
4. Check link category exists (if filtering)

### Partnership form not working

**Symptoms:** Form submits but nothing happens

**Solutions:**
1. Check browser console for JavaScript errors
2. Verify AJAX URL is accessible
3. Check email settings in WordPress
4. Look for form validation errors
5. Test with fewer fields

### Partnership emails not sending

**Solutions:**
1. Check WordPress email works (test with other plugins)
2. Verify email address in settings is correct
3. Check spam/junk folder
4. Use SMTP plugin (WP Mail SMTP recommended)
5. Check server isn't blocking mail

---

## Performance Issues

### Pages loading slowly

**Solutions:**
1. Enable built-in caching (if available)
2. Reduce number of ads per page
3. Optimize ad images before uploading
4. Use lazy loading for below-fold ads
5. Check for slow database queries

### High server resource usage

**Solutions:**
1. Reduce analytics data retention period
2. Optimize database tables
3. Enable object caching (Redis/Memcached)
4. Reduce ad rotation frequency

---

## Styling Issues

### Ads breaking layout

**Solutions:**
1. Add container width CSS:
```css
.wbam-ad {
    max-width: 100%;
    overflow: hidden;
}
```
2. Check for responsive issues on mobile
3. Verify ad zone size matches content area
4. Use browser inspector to find conflicts

### Ads not responsive

**Solution CSS:**
```css
.wbam-ad {
    width: 100%;
    max-width: 100%;
}

.wbam-ad img {
    max-width: 100%;
    height: auto;
}

@media (max-width: 768px) {
    .wbam-ad {
        text-align: center;
    }
}
```

---

## Database Issues

### "Table doesn't exist" error

**Solutions:**
1. Deactivate and reactivate plugin
2. Check database prefix matches wp-config.php
3. Manually run table creation (advanced)
4. Contact hosting for database access issues

### Stats not saving

**Solutions:**
1. Check database write permissions
2. Verify tables exist
3. Check available disk space
4. Look for database errors in error log

---

## Debugging

### Enable WordPress Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `/wp-content/debug.log`

### Check JavaScript Console

1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Look for red error messages
4. Note any errors related to wbam

### Check Network Tab

1. Open Developer Tools → Network tab
2. Reload the page
3. Look for failed requests (red)
4. Check AJAX calls are succeeding

---

## Conflicts with Other Plugins

### Common Conflict Sources

| Plugin Type | Potential Issue |
|-------------|----------------|
| Caching | Stale ads, tracking issues |
| Security | Blocked AJAX, false positives |
| Optimization | Broken JavaScript |
| Page builders | Shortcode rendering |
| Ad blockers | Hidden ads, no tracking |

### Testing for Conflicts

1. Activate only WB Starter Ads and a default theme
2. Test if issue persists
3. Reactivate plugins one by one
4. Identify the conflicting plugin
5. Contact support with findings

---

## Getting Help

### Before Contacting Support

Gather this information:
- WordPress version
- Plugin version
- PHP version
- Theme name
- List of active plugins
- Exact error message
- Steps to reproduce

### Checking Plugin Version

1. Go to Plugins → Installed Plugins
2. Find "WB Starter Ads"
3. Note the version number

### Support Resources

- **Documentation:** Read relevant docs first
- **WordPress Forum:** Community support
- **GitHub Issues:** Bug reports

---

## Upgrade to Pro

Many issues are solved in Pro version:
- Advanced debugging tools
- Priority support
- More configuration options
- Better error handling

[Learn About Pro →](link-to-pro)

---

*Still having issues? Make sure you've tried all the quick fixes at the top of this page.*
