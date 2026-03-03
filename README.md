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
- **Configurable content order** – drag & drop to reorder and toggle content blocks (subtitle, image, player, description, shownotes) per feed
- **Multilingual** – German source strings with English (en_US) translation included
- **Auto-updates from GitHub** – new releases are detected automatically in the WordPress dashboard

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

### Via Git

```bash
cd wp-content/plugins/
git clone https://github.com/sannc/podigee-rss-importer.git
```

### Via Download

1. Download the [latest release](https://github.com/sannc/podigee-rss-importer/releases) as ZIP
2. In **WP Admin → Plugins → Add New → Upload Plugin**, upload the ZIP file

### Activate

1. Activate the plugin in **WP Admin → Plugins**
2. Navigate to **Podigee Importer** in the admin menu

## Usage

### Adding a feed

1. Go to **Podigee Importer → Feeds → Add feed** (German: *Feed hinzufügen*)
2. Enter a name and your Podigee RSS feed URL (e.g. `https://meinpodcast.podigee.io/feed/mp3`)
3. Configure post type, status, categories, tags, and cron schedule
4. Optionally configure the **content block order** – drag & drop to reorder blocks (subtitle, episode image, player, description, shownotes) and toggle each on or off
5. Save

### Importing episodes

1. Go to **Podigee Importer → Import**
2. Select a feed from the dropdown and click **Load episodes** (German: *Episoden laden*)
3. Choose individual episodes or use **Select new only** (German: *Nur neue auswählen*)
4. Click **Import selected** (German: *Ausgewählte importieren*)

### Automatic imports

Set a cron schedule per feed (hourly / twice-daily / daily / weekly). New episodes are imported automatically; existing episodes are only updated if **Update previously imported posts** (German: *Bereits importierte Posts aktualisieren*) is enabled.

## Player customisation

The audio player card uses CSS custom properties. Override them in your theme's CSS or via **Customizer → Additional CSS**:

```css
.podigee-player {
    --podigee-player-bg:         #1a1a2e;   /* card background */
    --podigee-player-color:      #eeeeee;   /* text & control colour */
    --podigee-player-accent:     #e94560;   /* progress bar, buttons, badge */
    --podigee-player-radius:     16px;      /* corner radius */
    --podigee-player-padding:    24px;      /* inner spacing */
    --podigee-player-shadow:     none;      /* box shadow (default: subtle drop shadow) */
    --podigee-player-tint:       transparent;/* background tint overlay */
    --podigee-player-thumb-size: 80px;      /* episode thumbnail size */
}
```

## File structure

```
podigee-rss-importer/
├── podigee-rss-importer.php      # Bootstrap, autoloader, auto-updater, frontend hooks
├── includes/
│   ├── class-feed-manager.php   # Feed CRUD (stored in wp_options), content order constants
│   ├── class-rss-parser.php     # SimplePie-based RSS parsing
│   ├── class-importer.php       # Import orchestration & deduplication
│   ├── class-post-creator.php   # Post creation, section builders, block builder, image sideload
│   └── class-cron-manager.php   # WP-Cron scheduling per feed
├── admin/
│   ├── class-admin.php          # Admin menus, form handlers, AJAX endpoints
│   ├── views/
│   │   ├── feeds-list.php       # Feed overview table
│   │   ├── feed-edit.php        # Add / edit feed form
│   │   └── import.php           # Episode list & import UI
│   └── assets/
│       ├── admin.js             # AJAX import, episode selection, sortable content order
│       └── admin.css            # Admin table, badge & sortable styles
├── uninstall.php                # Data cleanup on plugin deletion
├── LICENSE                      # GPL-2.0
├── public/assets/
│   ├── frontend.css             # Frontend styles (player card, featured image, typography)
│   └── plyr/
│       ├── plyr.min.js          # Plyr 3.8.4 (bundled)
│       └── plyr.css             # Plyr 3.8.4 styles (bundled)
├── vendor/
│   └── plugin-update-checker/          # GitHub auto-update library (PUC v5)
└── languages/
    ├── podigee-rss-importer.pot        # Translation template
    ├── podigee-rss-importer-en_US.po   # English translation (source)
    └── podigee-rss-importer-en_US.mo   # English translation (compiled)
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

The plugin uses German as its source language. An English translation (`en_US`) is included and loaded automatically when WordPress is set to English.

Translation files are in `languages/`:
- `podigee-rss-importer.pot` – template for new translations
- `podigee-rss-importer-en_US.po/.mo` – English translation

To add another language, create a `.po` file (e.g. `podigee-rss-importer-fr_FR.po`) using Poedit or a similar tool and compile it to `.mo`.

## License

GPL-2.0+
