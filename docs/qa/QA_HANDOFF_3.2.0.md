# QA Handoff - WB Ad Manager 3.2.0 (Free)

For the QA person picking up 3.2.0. Pro has its own page: `wb-ad-manager-pro/docs/qa/QA_HANDOFF_3.2.0.md`. Test both together; Pro needs Free active.

- **Branch:** `3.2.0` in both repos. Test site: `wp-ads.local`.
- **Log in:** `?autologin=1` for admin, `?autologin=qa_advertiser_a` for an advertiser. Do not type passwords.
- **Board:** Basecamp "WB Ad Manager" (project 44982066). Cards are the only channel: verdict goes on the card, not in chat.
- **Standing rules:** the owner decisions on card 10342761510 are the spec. Wherever the plugin does something automatically, the admin must still see a manual option. A missing piece is a reject, not a deferral.

## 1. What changed in Free since the 28 Sep handoff

| Change | Card | How to check |
|---|---|---|
| Cloaked link clicks store a visitor hash and the referrer | 10365882213 | Section 2, test A |
| Link-preview bots and HEAD requests are not counted as clicks | 10365882213 | Section 2, test A |
| New links start with `rel="sponsored"` ticked | 10365882066 | Section 2, test B |
| Readme says Rich Content is an HTML text box and archives are above or below the post list | 10369330953 | Section 2, test C |
| Widget with no ad chosen rotates the Widget placement | 10371276081, 10371281720 | Section 2, test D |

## 2. Tests

### A. Link clicks (card 10365882213)

1. Admin > Links: note a link's slug and its click count and its row count in `wp_wbam_link_clicks`.
2. From a terminal: `curl -s -o /dev/null -A "Mozilla/5.0 Chrome/120" -e "http://example.org/post-x" http://wp-ads.local/go/<slug>`.
3. **Expect:** HTTP 307 to the destination, click count +1, one new row with a 64-character `visitor_hash` and `referrer = http://example.org/post-x`.
4. Repeat with `-A "Googlebot/2.1"` and with `curl -I`.
5. **Expect:** both still redirect (307), click count and row count do **not** change.
6. Negative: on the previous commit the bot and HEAD requests were counted and `visitor_hash` was empty.

### B. Sponsored default (card 10365882066)

1. Admin > Links > Add New.
2. **Expect:** both `rel="nofollow"` and `rel="sponsored"` are ticked. Both stay visible and can be unticked and saved.
3. Open a link saved before 3.2.0.
4. **Expect:** its stored value is unchanged (no silent flip).

### C. Readme wording (card 10369330953)

1. Open `readme.txt`, Ad Types and Placements lists.
2. **Expect:** Rich Content is described as HTML and text content (the editor is a plain text box), and archive placements read "Before/After Archive Pages".

### D. Widget rotation (cards 10371276081, 10371281720)

1. Appearance > Widgets: add the WB Ad widget and leave the ad dropdown on "Rotate the ads assigned to the Widget placement".
2. Assign two active ads to the Widget placement.
3. **Expect:** the widget shows one of them and rotates between page loads. Choosing one ad in the dropdown pins it.
4. Not checked by the developer: the BuddyPress widgets.

## 3. Regression pass (once, after the cards above)

- Each admin menu loads with no PHP notice (`WP_DEBUG` on): Ads, Placements, Links, Analytics, Settings.
- Frontend: a page with a Header, a Content and a Widget placement shows ads; layout does not jump (CLS).
- Cache: with a page cache on, impressions still count once per view.
- 390px: Admin > Links and the new-link form have no horizontal scroll.

## 4. Static gates (run by the developer)

| Gate | Result (2026-11-02, branch `3.2.0`) |
|---|---|
| `bin/plugin-check-gate.sh` | 0 errors, 15 warnings on shipped files |
| `bin/check-hooks-documented.sh` | OK, 207 `wbam_` hook call sites documented |
| PHPUnit Free suite | 390 tests, green, 1 long-standing skip (`Test_First_Run_Wizard_Handoff`) |
| PHPUnit Pro suite (run from this repo, `tests/pro/`) | 1058 tests, 1 long-standing skip (`Test_Membership_Plans`) |
| New tests | `tests/free/test-link-click-recording.php`: fails on the previous `class-link-manager.php`, passes now |

Two Pro tests were stale and are fixed in the same change: the SDK pin test now expects the bundled 1.10.3, and the end-date cap tests use dates relative to today (they had fixed September dates and failed once those passed).

## 5. Known open, not part of this QA pass

- Card 10453478277: nine how-to docs pages are missing for features shown in the tutorial videos. Docs work, no code.
- The bundled Credits SDK is frozen for 3.2.0; the headless work is 3.3.0.

## 6. How to report

Post on the card: what you ran, what you saw, the viewport. Pass = move to Done with evidence (before and after). Fail = move to Bugs with steps. A visual or wording opinion is an input, not a verdict: the owner decides.
