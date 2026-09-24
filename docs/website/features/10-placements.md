# Placements

A placement is a location where an ad can render. Each ad carries a **Placements** metabox - check every location where it should appear. Assign the same placement to several ads and they rotate (see [Rotation and Split Testing](20-rotation-and-split-testing.md)).

## Built-in placements

These eleven placements are always available.

| Placement | Where it renders |
|-----------|------------------|
| Header | Top of the page (`wp_body_open`, plus common theme hooks) |
| Footer | Bottom of the page (`wp_footer`) |
| Before/After Content | Around single post/page content (`the_content`) |
| After Paragraph | After a chosen paragraph, with an optional repeat |
| Before Posts Archive | Before the first post on archive/blog pages (`loop_start`) |
| After Posts Archive | After the last post on archive/blog pages (`loop_end`) |
| Widget | Any widget area, via the WB Ad Manager widget |
| Sticky/Floating | Fixed bar or corner (`wp_footer`) |
| Popup/Modal | Overlay with trigger conditions (`wp_footer`) |
| Comments | Around the comment section |
| Shortcode | Manual placement with `[wbam_ad]` / `[wbam_ads]` |

The **Shortcode** placement is used only through shortcodes, so it does not appear as a checkbox in the placement picker.

## Choosing which placements are open

Under **Settings -> Placements** you control which placement slots may serve ads on your site (a site-wide allowlist). By default every placement is open. This is a site owner gate, not a Free/Pro gate - all eleven placements above are in the free plugin, including sticky and popup.

## Community placements

When a supported community platform is active, its placements register automatically:

- **BuddyPress** - activity stream plus four directory positions. See [BuddyPress](../integrations/00-buddypress.md).
- **bbPress** - one `bbpress` placement covering seven forum/topic/reply positions. See [bbPress](../integrations/10-bbpress.md).
- **Jetonomy** - seven sidebar and topic/reply positions. See [Jetonomy](../integrations/20-jetonomy.md).

## Next steps

- [Rotation and Split Testing](20-rotation-and-split-testing.md) - how a placement picks which ad to show.
- [Targeting and Scheduling](30-targeting-and-scheduling.md) - narrow where and to whom an ad runs.
