# Abilities API

On WordPress 6.9 and later (where the Abilities API is available), WB Ad Manager registers ability categories and abilities so AI agents and other tools can work with ads programmatically. This is optional - if the Abilities API is not present, nothing is registered and the plugin runs normally.

## Categories

| Category | Label |
|----------|-------|
| `wbam-ads` | Ad Management |
| `wbam-analytics` | Ad Analytics |
| `wbam-links` | Link Management |

## Abilities

Abilities cover the same operations as the REST API - for example listing ads, getting an ad's details, and creating an ad - each with a defined input/output schema and a permission check. They run under the same `manage_options` gate as the admin REST routes.

## When it loads

Registration happens on dedicated Abilities API hooks only when `wp_register_ability()` exists. There is nothing to configure.

## Next steps

- [REST API](00-rest-api.md)
- [Hooks and Filters](10-hooks-and-filters.md)
