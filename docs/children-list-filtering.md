# Filtering and pagination in ChildrenList

In the settings for a Standard List page, choose a layout that supports filters,
such as Cards or Media List. Enable text search, tag selection, or both. For tag
selection, choose the tags visitors can use. Enable pagination and set the
number of entries per page if the layout supports paging.

## What visitors can find

Text search matches part of a page title, short description, or assigned tag
key. Matching ignores letter case. Selecting a tag narrows the results to pages
with that tag. With both filters active, entries must match both conditions.

The server filters the full list of visible entries before paginating. A search
from page 1 can therefore find an entry originally shown on page 3. The page
count reflects the filtered results and the current visitor's read permissions.
For a listing brick with an overall entry limit, the search covers the entries
within that limit.

## Links and browser history

Changing the search text or tag returns that list to page 1. The URL records
the selection so visitors can bookmark it or use the browser's Back and
Forward buttons.

| Selection | Example URL |
| --- | --- |
| Main list, page 2 | `/Children-List?sheet=2` |
| Main list, search and tag, page 1 | `/Children-List?list-q=Miami&list-tag=Technik` |
| Brick 445, tag selected | `/Children-List?list-brick-445-tag=Animation` |

The main list is the list rendered by the page type itself. Its first page
omits `sheet`. Older bookmarks such as `?list-site-21-page=2` still open the
requested page.

Listing bricks that supply their numeric brick ID use
`list-brick-<id>-q`, `list-brick-<id>-tag`, and `list-brick-<id>-page`.
Different bricks can be filtered independently. Two placements of the same
brick share the selection. AJAX identifies a direct placement by its UUID.
A direct placement without a stored UUID uses regular page navigation until
the page is saved and Bricks assigns the placement a UUID.

## Loading results

On the main list and supported direct brick placements, filtering and paging
update the results via AJAX. Without JavaScript, the search form and links
perform regular page navigation. Nested bricks without a directly resolvable
placement also use regular navigation.

On the main list, requests taking longer than 150 ms show skeletons over the
entries already on screen. Their dimensions stay the same until the new results
arrive. If a filter request fails, the previous entries reappear with an error
message. If an AJAX pagination request fails, the browser opens the page link.

## Custom templates

In an AJAX-enabled layout, mark the image or text areas that should appear as
skeletons with `qui-control-childrenlist__skeleton-part`. The shared filter
stylesheet applies the mask while the main results area is busy. Templates
can omit this class and keep their existing entries visible while loading.
A custom list supplied through `displayTemplate` uses the existing client-side
filter on the entries rendered by that template.

## Integrating a page type

Place the main list definition next to its page controller in the package.
For example, for `types/articles.php`, create `types/articles.children.php`.
It receives the loaded `$Site` and returns the configured control:

```php
<?php

use QUI\Controls\ChildrenList;

return new ChildrenList([
    'Site' => $Site,
    'Project' => $Site->getProject(),
    'isMainList' => true,
    'filter' => $Site->getAttribute('articles.filter'),
    'tags' => $Site->getAttribute('articles.tags')
]);
```

Load the definition from the page controller:

```php
$ChildrenList = require __DIR__ . '/articles.children.php';
```

Keep list settings, data sources and list callbacks in the definition. Keep
page rendering, template assignments and page-level structured data in the
controller. AJAX loads only the package definition for the saved page type
declared in the package's `site.xml`. Pages without a definition keep regular
links and the GET form. Brick placements resolve independently.
