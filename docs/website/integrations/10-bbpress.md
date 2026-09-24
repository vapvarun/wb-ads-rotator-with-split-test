# bbPress Integration

When bbPress is active, WB Ad Manager adds a single `bbpress` placement that covers seven forum, topic, and reply positions. Pick the positions per ad.

## Positions

| Position | Where it renders |
|----------|------------------|
| Before Forums | Top of the forums list |
| After Forums | Bottom of the forums list |
| Before Topics | Top of the topics list in a forum |
| After Topics | Bottom of the topics list in a forum |
| Before Single Topic | Above the topic title on a topic page |
| After Single Topic | Below the opening post, before replies |
| Between Replies | Every N replies inside a topic |

## Between-reply frequency

An ad using the "Between Replies" position has two extra options:

- **Show after** - the reply number to anchor on (default 5).
- **Repeat every** - if enabled, repeat the ad every N replies instead of showing once.

## bbPress widgets

Two dedicated widgets are available for bbPress sidebars:

- **WBAM: bbPress Forum Ad** - shows ads on bbPress forum pages.
- **WBAM: bbPress Topic Sidebar Ad** - shows ads on single topic pages only.

## Use it

1. Confirm bbPress is active.
2. Edit an ad, check the bbPress placement, and choose positions.
3. Publish.

## Next steps

- [BuddyPress Integration](00-buddypress.md)
- [Jetonomy Integration](20-jetonomy.md)
