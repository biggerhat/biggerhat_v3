# Aethervox (Channels)

"Across the Aethervox" — a content/creator hub. **Channels** (community creators:
podcasts, streams, blogs) publish **Transmissions** (posts/episodes), which can
be tagged with the models they discuss.

## Routes (`routes/web.php`)
- `channels` prefix → `ChannelController` — index ("Across the Aethervox"),
  `my-channels` (auth), channel show.
- `channels/{channel}/transmissions` prefix (auth) → transmission CRUD
  (`transmissions.*`).
- Admin: `routes/admin.php` `channels`, `transmissions`, `pod-links` prefixes.

## Models
- `Channel` — `users()` (`belongsToMany`, channel owners/members),
  `transmissions()` (`hasMany`), `image_url` accessor.
- `Transmission` — `belongsTo(Channel)`; `characters()` + `keywords()`
  (`MorphToMany` — tag the models a transmission is about); slug route key.
- `PodLink` — external podcast/platform links.

## Frontend
`pages/Channels/Index.vue` (titled "Across the Aethervox"),
`components/ResourcesPanel.vue` (the Aethervox panel), surfaced in
`components/AppSidebar.vue` and the home `pages/Index.vue`.

## Conventions / Gotchas
- "Aethervox" is the **branding** for the Channels feature — code/models/routes
  use `channel`/`transmission`, not `aethervox`. Grep `channel`/`transmission`.
- Transmission ↔ model tagging is polymorphic (`MorphToMany`), shared with other
  taggable content.

## Tests
`tests/Feature/` channel + transmission controller tests (CRUD, ownership,
publish gating).
