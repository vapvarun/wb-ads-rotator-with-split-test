# Partnership Inquiry Form

Accept inbound link-partnership requests (paid link, link exchange, sponsored post) through a structured on-site form instead of email back-and-forth. Submissions land in **WB Ad Manager -> Links -> Partnerships**, where you accept or reject each one and automatic emails are sent.

## Add the form

Drop this shortcode on any page (an "Advertise" or "Work with us" page works well):

```
[wbam_partnership_inquiry]
```

## Default form fields

| Field | Required | Purpose |
|-------|----------|---------|
| Name | Yes | Contact name |
| Email | Yes | Reply-to for the decision |
| Website URL | Yes | The partner's site |
| Partnership type | Yes | Paid Link, Link Exchange, or Sponsored Post |
| Target page | Optional | The post/page they want the link in |
| Anchor text | Optional | The exact text they want linked |
| Budget | Optional | For paid placements |
| Message | Optional | Free-form pitch |

## Shortcode attributes

All attributes are optional.

```
[wbam_partnership_inquiry
    title="Partner with Us"
    description="Tell us about your partnership idea."
    show_budget="yes"
    show_target_page="yes"
    show_anchor="yes"
    show_message="yes"
    button_text="Send Inquiry"
    class="my-custom-form" ]
```

| Attribute | Values | Default |
|-----------|--------|---------|
| `title` | Any text | `Link Partnership Inquiry` |
| `description` | Any text | `Interested in a link partnership? Fill out the form below and we will get back to you.` |
| `show_budget` | `yes` / `no` | `yes` |
| `show_target_page` | `yes` / `no` | `no` |
| `show_anchor` | `yes` / `no` | `yes` |
| `show_message` | `yes` / `no` | `yes` |
| `button_text` | Any text | `Submit Inquiry` |
| `class` | CSS class | (empty) |

## Anti-abuse

The form is nonce-protected and sanitized server-side. The same email cannot submit against the same target page more than once in 24 hours - repeat submissions inside that window are silently ignored.

## Next steps

- [Link Management](../features/40-link-management.md)
- [Link Shortcodes](10-link-shortcodes.md)
