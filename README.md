# Podigee RSS Importer

A WordPress plugin that imports Podigee podcast RSS feeds as WordPress posts. Multiple feeds are supported with per-feed configuration, automatic scheduling via WP-Cron, and a customisable audio player.

## Features

- **Multi-feed support** – manage any number of Podigee feeds independently
- **Gutenberg-native content** – episodes are imported as proper blocks (headings, paragraphs, lists, quotes)
- **Plyr.js audio player** – polished, accessible player bundled locally (no CDN dependency)
- **Customisable player card** – styled via CSS custom properties, easy to theme
- **Episode deduplication** – re-importing a feed never creates duplicate posts
- **WP-Cron scheduling** – automatic imports on hourly, twice-daily, daily, or weekly intervals
- **Featured image import** – episode artwork is sideloaded into the media library
- **Per-feed settings** – post type, post status, categories, tags, date handling, update mode

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Download or clone this repository into `wp-content/plugins/podigee-rss-importer/`
2. Activate the plugin in **WP Admin → Plugins**
3. Navigate to **Podigee Importer** in the admin menu

## Usage

### Adding a feed

1. Go to **Podigee Importer → Feeds → Feed hinzufügen**
2. Enter a name and your Podigee RSS feed URL (e.g. `https://meinpodcast.podigee.io/feed/mp3`)
3. Configure post type, status, categories, tags, and cron schedule
4. Save

### Importing episodes

1. Go to **Podigee Importer → Import**
2. Select a feed from the dropdown and click **Episoden laden**
3. Choose individual episodes or use **Alle neuen auswählen**
4. Click **Importieren**

### Automatic imports

Set a cron schedule per feed (hourly / twice-daily / daily / weekly). New episodes are imported automatically; existing episodes are only updated if **Bestehende Posts aktualisieren** is enabled.

## Player customisation

The audio player card uses CSS custom properties. Override them in your theme's CSS or via **Customizer → Additional CSS**:

```css
.podigee-player {
    --podigee-player-bg:         #1a1a2e;   /* card background */
    --podigee-player-color:      #eeeeee;   /* text & control colour */
    --podigee-player-accent:     #e94560;   /* progress bar, buttons, badge */
    --podigee-player-radius:     16px;      /* corner radius */
    --podigee-player-padding:    24px;      /* inner spacing */
    --podigee-player-thumb-size: 80px;      /* episode thumbnail size */
}
```

## File structure

```
podigee-rss-importer/
├── podigee-rss-importer.php      # Bootstrap, autoloader, frontend hooks
├── includes/
│   ├── class-feed-manager.php   # Feed CRUD (stored in wp_options)
│   ├── class-rss-parser.php     # SimplePie-based RSS parsing
│   ├── class-importer.php       # Import orchestration & deduplication
│   ├── class-post-creator.php   # Post creation, block builder, image sideload
│   └── class-cron-manager.php   # WP-Cron scheduling per feed
├── admin/
│   ├── class-admin.php          # Admin menus, form handlers, AJAX endpoints
│   ├── views/
│   │   ├── feeds-list.php       # Feed overview table
│   │   ├── feed-edit.php        # Add / edit feed form
│   │   └── import.php           # Episode list & import UI
│   └── assets/
│       ├── admin.js             # AJAX import, episode selection UI
│       └── admin.css            # Admin table & badge styles
├── public/assets/
│   ├── player.css               # Player card styles (CSS custom properties)
│   └── plyr/
│       ├── plyr.min.js          # Plyr 3.8.4 (bundled)
│       └── plyr.css             # Plyr 3.8.4 styles (bundled)
└── languages/
    └── podigee-rss-importer.pot # Translation template
```

## Post meta

Each imported post gets the following meta keys:

| Key | Content |
|---|---|
| `_podigee_episode_guid` | RSS GUID (used for deduplication) |
| `_podigee_feed_id` | ID of the source feed |
| `_podigee_audio_url` | Direct audio file URL |
| `_podigee_episode_number` | `<itunes:episode>` value |
| `_podigee_season_number` | `<itunes:season>` value |
| `_podigee_embed_url` | Podigee iframe embed URL (if available) |

## Translations

A `.pot` template is included in `languages/`. To add a translation, create a `.po` file (e.g. `podigee-rss-importer-de_DE.po`) using Poedit or a similar tool.

## License

GPL-2.0+
