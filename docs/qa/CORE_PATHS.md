# WB Ad Manager (free) - Core Paths (the 60-70%)

> What nearly every owner uses, ranked. QA walks these first, every cycle, as the
> named role, on a clean install. Bug priority follows this list (owner-questions.md -> Triage).
> Seeded from: what activation/wizard creates, the Ads menu order, readme lead features,
> board hot-spots (Placements/Display 47 cards, Analytics 52), and the 2026-09-24 flow audit.

Last confirmed: PROVISIONAL 2026-09-24 (seeded by QA; needs one human confirmation)

| # | Flow (owner's words) | Role | Surface | Why it is core (evidence) | Journey | Free/Pro |
|---|---|---|---|---|---|---|
| 1 | I create an image ad, tick a placement, publish, and see it on my site | admin, then anon | Ads > Add New -> any post | Zero-config path; first CTA on the empty Ads list | audit/journeys/admin/01-create-publish-ad.md, customer/01 | Free |
| 2 | Header, footer, in-content and after-paragraph ads render on my theme, desktop and phone | anon | single post, home | Wizard sample ads use these; readme lead | customer/01 | Free |
| 3 | I limit an ad by page type, device, logged-in state, role and schedule | admin -> anon | Display Rules, Visitor Conditions, Schedule | Every ad editor shows these metaboxes | - | Free |
| 4 | Several ads in one slot rotate by priority, and impressions/clicks are counted | anon -> admin | slot on a post; ad Performance metabox | Plugin name promise ("rotator") | customer/02 | Free |
| 5 | I run Google AdSense (unit or Auto Ads) and it respects cookie consent | admin -> anon | Settings > AdSense, Privacy | Readme lead; consent plugins listed | - | Free |
| 6 | I cloak an affiliate link at /go/slug and see its clicks | admin -> anon | Links menu | Own top-level menu | - | Free |
| 7 | The setup wizard creates sample ads I can see on my site | admin | index.php?page=wbam-setup | First-run notice | - | Free |
| 8 | Ads show in BuddyPress activity/directories and bbPress forums | anon, member | BP/bbPress pages | Community-plugin audience | - | Free |

## Edge (walk after core)

| Flow | Role | Surface | Why it is edge |
|---|---|---|---|
| Sticky / popup placements with triggers | anon | any page | Opt-in advanced placements |
| Email-capture ad + CSV export | anon, admin | Email Captures | Niche ad type |
| Partnership inquiry form | anon, admin | [wbam_partnership_inquiry] | Only sites selling link deals |
| Geo targeting by country | anon | metabox + Settings > Geo | Needs a geo provider |
| Jetonomy placements | anon | Jetonomy pages | Only with Jetonomy installed |

## Zero-config check (C-2)

**Create an image ad with the Header placement and publish it - it must show on the front page with no settings touched.**
