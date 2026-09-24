# Rotation and Split Testing

When more than one ad targets the same placement, WB Ad Manager rotates them by weighted priority and lets you compare their performance side by side.

## How rotation works

For each placement, on each page load, the plugin:

1. Collects every published, enabled ad that has this placement checked.
2. Drops any ad that fails targeting or scheduling (see [Targeting and Scheduling](30-targeting-and-scheduling.md)).
3. Picks one winner at **weighted random**, where each ad's weight is its **Priority** (1-10, default 5). A priority-8 ad is shown roughly twice as often as a priority-4 ad.

If a picked ad cannot render, the next candidate is chosen until the pool is exhausted. By default an ad renders at most once per page across all its placements.

## Setting priority

Open an ad, and in the **Ad Status** metabox drag the **Priority** slider (1-10). The higher the number, the bigger the share of impressions when ads compete for a slot.

## Comparing ads (built-in A/B comparison)

When you edit an ad that has at least one placement, the **Ad Performance Comparison** metabox lists every other enabled ad sharing those placements, with impressions, clicks, and CTR side by side, and flags a current leader. Use it to spot which creative is winning and to disable weak performers.

To run a test:

1. Create two or more ads with the same placement checked.
2. Give them the same priority so they split traffic evenly.
3. Let them run, then open any one of them and read the **Ad Performance Comparison** metabox.
4. Disable the losers with the **Enabled** toggle in the Ad Status metabox.

## What the free plugin does and does not do

- Free: priority-weighted rotation, impression/click/CTR tracking, and the side-by-side comparison metabox.
- Pro (**WB Ad Manager Pro**): A/B tests that split traffic between an original ad and its variants with a significance readout, campaigns with budgets and pacing, and share-of-voice caps.

## Next steps

- [Targeting and Scheduling](30-targeting-and-scheduling.md)
- [Creating and Managing Ads](../usage/00-creating-and-managing-ads.md)
