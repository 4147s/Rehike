<?php
foreach ($ytdata->contents->twoColumnBrowseResultsRenderer->tabs as $tab) {
    if (isset($tab->tabRenderer->selected) and $tab->tabRenderer->selected == true) {
        $playlistsTabContent = $tab->tabRenderer->content->sectionListRenderer->contents[0]->itemSectionRenderer->contents[0]->shelfRenderer->content->horizontalListRenderer->items ?? null;
    }
}

$yt->playlistList = $playlistsTabContent ?? null;
$yt->flow = $_GET["flow"] ?? "grid";