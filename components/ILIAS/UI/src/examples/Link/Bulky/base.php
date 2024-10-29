<?php

declare(strict_types=1);

namespace ILIAS\UI\examples\Link\Bulky;

/**
 * ---
 * description: >
 *   The Bulky Links in this example point to ilias.de
 *   Note the exact look of the Bulky Links is mostly defined by the
 *   surrounding container.
 *
 * expected output: >
 *   ILIAS shows two bulky links:
 *   1. An icon ("E") with the text "Link to ilias.de with Icon".
 *   2. A glyph ("Briefcase") with the text "Link to ilias.de with Glyph".
 *   Clicking the links will redirect you to ilias.de.
 * ---
 */
function base()
{
    global $DIC;
    $f = $DIC->ui()->factory();
    $renderer = $DIC->ui()->renderer();

    $target = new \ILIAS\Data\URI("https://ilias.de");

    $ico = $f->symbol()->icon()
             ->standard('someExample', 'Example')
             ->withAbbreviation('E')
             ->withSize('medium');
    $link = $f->link()->bulky($ico, 'Link to ilias.de with Icon', $target);

    $glyph = $f->symbol()->glyph()->briefcase();
    $link2 = $f->link()->bulky($glyph, 'Link to ilias.de with Glyph', $target);

    $link3 = $f->link()->bulky($glyph, '', $target);
    $link4 = $f->link()->bulky($ico, '', $target);


    return $renderer->render([
        $link,
        $f->divider()->horizontal(),
        $link2,
        $f->divider()->horizontal(),
        $link3,
        $f->divider()->horizontal(),
        $link4,
    ]);
}
