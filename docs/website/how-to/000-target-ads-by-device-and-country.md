# Target ads by device and country

> **Included in Free** - no Pro license needed.

Show a mobile app banner only to phone visitors, or hide a promotion from countries you do not ship to. Both rules are set on the ad itself and checked before the ad is chosen.

## Target by device

1. Open the ad: **Ad Manager -> All Ads**, then the ad.
2. In the **Visitor Conditions** box, find **Devices**.
3. Tick **Desktop**, **Tablet** and/or **Mobile**. Leave all three unticked to show the ad on every device.
4. Update the ad.

Visit the page on a phone and on a desktop to confirm the ad appears only where you expect.

## Target by country

Country targeting needs a location source, which is off until you choose one.

1. Go to **Settings -> Location**.
2. Turn on **Geolocation** and pick a **Provider**:
   - **MaxMind Database**: a GeoLite2 Country file you upload. It stays on your server and nothing is sent anywhere.
   - **ipinfo.io**: needs a free API key.
3. Save. If the provider has a problem, the screen tells you.

Then, on the ad:

1. Open the **Geo Targeting** box and tick **Enable geo targeting for this ad**.
2. Choose a **Targeting Mode**: **Show only in selected countries** or **Show everywhere except selected countries**.
3. Pick the countries.
4. Decide whether to **Show ad to visitors with unknown location**. Location can be unknown behind a VPN or on a local address.
5. Update the ad.

If Geolocation is still off, the box warns you that the rules have no effect yet and links to the setting.

## Combine with other rules

Device and country rules work together with display rules, user status and the schedule: an ad shows only when every rule it has passes. See [Targeting and Scheduling](../features/030-targeting-and-scheduling.md).

## Next steps

- [Settings](../usage/010-settings.md)
- [Rotation and Split Testing](../features/020-rotation-and-split-testing.md)
